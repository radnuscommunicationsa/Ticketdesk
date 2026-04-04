<?php
/**
 * Email & Password Reset Diagnostic Script
 * Checks all components needed for password reset to work
 */

require_once __DIR__ . '/includes/config.php';

echo "<!DOCTYPE html><html><head><title>Diagnostics</title>";
echo "<style>
    body { font-family: monospace; padding: 20px; background: #1a1a2e; color: #fff; }
    .check { margin: 10px 0; padding: 10px; border-radius: 4px; }
    .success { background: #064e3b; border-left: 4px solid #10b981; }
    .error { background: #7f1d1d; border-left: 4px solid #ef4444; }
    .info { background: #1e3a8a; border-left: 4px solid #3b82f6; }
    h2 { color: #fff; margin-top: 20px; }
    pre { background: #000; padding: 10px; border-radius: 4px; overflow-x: auto; }
</style>";
echo "</head><body><h1>🔍 Email & Password Reset Diagnostics</h1>";

// Test 1: Database Connection
echo "<h2>1. Database Connection</h2>";
try {
    $pdo->query("SELECT 1");
    echo "<div class='check success'>✅ Database connection OK</div>";
} catch (PDOException $e) {
    echo "<div class='check error'>❌ Database connection FAILED: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// Test 2: Check employees table
echo "<h2>2. Employees Table</h2>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM employees");
    $count = $stmt->fetch();
    echo "<div class='check success'>✅ Employees table exists: {$count['cnt']} employees</div>";

    // Check if test email exists
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE email = ? OR emp_id = ?");
    $stmt->execute(['radnuscommunicationsa@gmail.com', 'radnuscommunicationsa@gmail.com']);
    $user = $stmt->fetch();
    if ($user) {
        echo "<div class='check info'>ℹ️ Test user found: ID={$user['id']}, Name={$user['name']}, Status={$user['status']}</div>";
    } else {
        echo "<div class='check error'>❌ Test user NOT FOUND. Create an employee with email 'radnuscommunicationsa@gmail.com' first.</div>";
    }
} catch (PDOException $e) {
    echo "<div class='check error'>❌ Employees table error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// Test 3: Check password_reset_tokens table
echo "<h2>3. Password Reset Tokens Table</h2>";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'password_reset_tokens'");
    $table = $stmt->fetch();
    if ($table) {
        echo "<div class='check success'>✅ Table password_reset_tokens exists</div>";

        // Check structure
        $stmt = $pdo->query("DESCRIBE password_reset_tokens");
        $columns = $stmt->fetchAll();
        echo "<div class='check info'>ℹ️ Columns: ";
        $colNames = array_column($columns, 'Field');
        echo implode(', ', $colNames) . "</div>";

        // Check required columns
        $required = ['id', 'emp_id', 'token', 'expires_at', 'used', 'created_at'];
        $missing = array_diff($required, $colNames);
        if (empty($missing)) {
            echo "<div class='check success'>✅ All required columns present</div>";
        } else {
            echo "<div class='check error'>❌ Missing columns: " . implode(', ', $missing) . "</div>";
        }

        // Check indexes
        $stmt = $pdo->query("SHOW INDEX FROM password_reset_tokens");
        $indexes = $stmt->fetchAll();
        $indexNames = array_unique(array_column($indexes, 'Key_name'));
        echo "<div class='check info'>ℹ️ Indexes: " . implode(', ', $indexNames) . "</div>";
    } else {
        echo "<div class='check error'>❌ Table password_reset_tokens DOES NOT EXIST</div>";
        echo "<div class='check info'>💡 Run migrate_password_reset.php to create it</div>";
    }
} catch (PDOException $e) {
    echo "<div class='check error'>❌ Error checking table: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// Test 4: SMTP Configuration
echo "<h2>4. SMTP Configuration</h2>";
$smtp_host = getenv('SMTP_HOST');
$smtp_user = getenv('SMTP_USER');
$smtp_pass = getenv('SMTP_PASS');
$smtp_port = getenv('SMTP_PORT');
$smtp_secure = getenv('SMTP_SECURE');

echo "<div class='check info'>ℹ️ Environment Variables:</div>";
echo "<pre>";
echo "SMTP_HOST  = " . ($smtp_host ?: 'NOT SET') . "\n";
echo "SMTP_PORT  = " . ($smtp_port ?: 'NOT SET') . "\n";
echo "SMTP_USER  = " . ($smtp_user ? 'SET (hidden)' : 'NOT SET') . "\n";
echo "SMTP_PASS  = " . ($smtp_pass ? 'SET (hidden)' : 'NOT SET') . "\n";
echo "SMTP_SECURE= " . ($smtp_secure ?: 'NOT SET') . "\n";
echo "</pre>";

if ($smtp_host && $smtp_user && $smtp_pass) {
    echo "<div class='check success'>✅ All SMTP environment variables are set</div>";
} else {
    echo "<div class='check error'>❌ Missing SMTP environment variables</div>";
}

// Test 5: Test token generation (without sending email)
echo "<h2>5. Token Generation Test</h2>";
if ($user) {
    try {
        $token = generateResetToken($pdo, $user['id']);
        echo "<div class='check success'>✅ Token generated successfully</div>";
        echo "<div class='check info'>ℹ️ Token: " . substr($token, 0, 20) . "...</div>";

        // Verify token
        $resetData = verifyResetToken($pdo, $token);
        if ($resetData) {
            echo "<div class='check success'>✅ Token verified successfully</div>";
        } else {
            echo "<div class='check error'>❌ Token verification failed</div>";
        }

        // Clean up - delete test token
        $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE token = ?");
        $stmt->execute([$token]);
        echo "<div class='check info'>ℹ️ Test token cleaned up</div>";

    } catch (PDOException $e) {
        echo "<div class='check error'>❌ Token generation failed: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
} else {
    echo "<div class='check error'>❌ Cannot test - user not found</div>";
}

// Test 6: Test SMTP Connection (if configured)
echo "<h2>6. SMTP Connection Test</h2>";
if ($smtp_host && $smtp_user && $smtp_pass) {
    echo "<div class='check info'>ℹ️ Testing connection to $smtp_host:$smtp_port...</div>";

    $socket = @fsockopen(($smtp_secure === 'ssl' && $smtp_port == 465 ? 'tls://' : '') . $smtp_host, $smtp_port, $errno, $errstr, 10);

    if ($socket) {
        echo "<div class='check success'>✅ Connected to SMTP server</div>";

        // Read greeting
        $response = fgets($socket, 515);
        echo "<div class='check info'>ℹ️ Server: " . trim($response) . "</div>";

        fclose($socket);
        echo "<div class='check info'>💡 Connection test passed. Email sending should work (check logs for detailed SMTP conversation)</div>";
    } else {
        echo "<div class='check error'>❌ Cannot connect to SMTP server: $errstr ($errno)</div>";
        echo "<div class='check error'>💡 Check firewall, port blocking, or wrong host/port</div>";
    }
} else {
    echo "<div class='check info'>ℹ️ SMTP not configured - will use PHP mail() (likely to fail on Railway)</div>";
}

// Test 7: Check logs
echo "<h2>7. Recent Error Log</h2>";
$logFile = ini_get('error_log') ?: '/var/log/php_errors.log';
if (file_exists($logFile)) {
    $logs = tail($logFile, 20);
    echo "<pre>" . htmlspecialchars($logs) . "</pre>";
} else {
    echo "<div class='check info'>ℹ️ No error log file found at: $logFile</div>";
}

echo "<h2>Summary</h2>";
echo "<div class='check info'>ℹ️ After fixing all red errors above, test the full password reset flow.</div>";

function tail($file, $lines = 20) {
    if (!file_exists($file)) return "Log file not found: $file";
    $fp = fopen($file, 'r');
    $buffer = '';
    $chunk = 4096;
    fseek($fp, -1, SEEK_END);
    $pos = ftell($fp);
    $lineCount = 0;
    while ($pos > 0 && $lineCount < $lines) {
        $read = ($pos > $chunk) ? $chunk : $pos;
        fseek($fp, $pos - $read);
        $chunkData = fread($fp, $read);
        $buffer = $chunkData . $buffer;
        $lineCount = substr_count($buffer, "\n");
        fseek($fp, pos - $read);
        $pos -= $read;
    }
    fclose($fp);
    $allLines = explode("\n", $buffer);
    return implode("\n", array_slice($allLines, -$lines));
}

?>
</body></html>
