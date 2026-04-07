<?php
/**
 * Simple Railway Password Reset Diagnostic
 * Run this on Railway to quickly identify the issue
 */

require_once __DIR__ . '/includes/config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Railway Password Reset Debug</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1a1a2e; color: #e2e8f0; }
        .status { padding: 10px; margin: 10px 0; border-radius: 8px; }
        .ok { background: #065f46; color: #10b981; }
        .fail { background: #7f1d1d; color: #ef4444; }
        .warn { background: #92400e; color: #fbbf24; }
        code { background: #0f172a; padding: 2px 6px; border-radius: 4px; }
        pre { background: #0f172a; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
<h1>🔍 Railway Password Reset Diagnostics</h1>";

// 1. Check environment
echo "<h2>1. Environment</h2>";
$isRailway = getenv('MYSQLHOST') || getenv('RAILWAY_PUBLIC_DOMAIN');
echo "<div class='status " . ($isRailway ? 'ok' : 'warn') . "'>";
echo "Platform: " . ($isRailway ? '🚂 Railway' : '💻 Localhost') . "<br>";
echo "SITE_URL: " . SITE_URL . "</div>";

// 2. Check DB connection
echo "<h2>2. Database</h2>";
try {
    $test = $pdo->query("SELECT COUNT(*) FROM employees WHERE status='active'")->fetchColumn();
    echo "<div class='status ok'>✅ Connected to DB<br>Active employees: $test</div>";
} catch (Exception $e) {
    echo "<div class='status fail'>❌ DB Error: " . $e->getMessage() . "</div>";
}

// 3. Check password_reset_tokens table
echo "<h2>3. Password Reset Table</h2>";
try {
    $tables = $pdo->query("SHOW TABLES LIKE 'password_reset_tokens'")->fetchAll();
    if (count($tables) > 0) {
        echo "<div class='status ok'>✅ Table exists</div>";
        $cols = $pdo->query("DESCRIBE password_reset_tokens")->fetchAll(PDO::FETCH_COLUMN);
        $required = ['id','emp_id','token','expires_at','used','created_at'];
        $missing = array_diff($required, $cols);
        if (empty($missing)) {
            echo "<div class='status ok'>✅ All columns present: " . implode(', ', $required) . "</div>";
        } else {
            echo "<div class='status fail'>❌ Missing columns: " . implode(', ', $missing) . "</div>";
        }
    } else {
        echo "<div class='status fail'>❌ Table does NOT exist</div>";
        echo "<p>Run: <code>create_password_reset_table.php</code></p>";
    }
} catch (Exception $e) {
    echo "<div class='status fail'>❌ Error: " . $e->getMessage() . "</div>";
}

// 4. Check email configuration
echo "<h2>4. Email Configuration</h2>";
$smtp_vars = [
    'SMTP_HOST' => getenv('SMTP_HOST'),
    'SMTP_USER' => getenv('SMTP_USER'),
    'SMTP_PASS' => getenv('SMTP_PASS') ? '***SET***' : null,
    'SENDGRID_API_KEY' => getenv('SENDGRID_API_KEY') ? '***SET***' : null,
    'EMAIL_DEBUG' => getenv('EMAIL_DEBUG') ?: 'false'
];

$hasSMTP = $smtp_vars['SMTP_HOST'] && $smtp_vars['SMTP_USER'] && $smtp_vars['SMTP_PASS'];
$hasSendGrid = $smtp_vars['SENDGRID_API_KEY'];

if ($hasSMTP || $hasSendGrid) {
    echo "<div class='status ok'>✅ Email method configured</div>";
    echo "<ul>";
    if ($hasSMTP) {
        echo "<li>SMTP: {$smtp_vars['SMTP_HOST']} as {$smtp_vars['SMTP_USER']}</li>";
    }
    if ($hasSendGrid) {
        echo "<li>SendGrid API configured</li>";
    }
    echo "</ul>";
} else {
    echo "<div class='status fail'>❌ No email method configured!</div>";
    echo "<p>Set SMTP_* or SENDGRID_API_KEY in Railway Variables</p>";
}

// 5. Check functions exist
echo "<h2>5. Required Functions</h2>";
$functions = ['sendEmailSMTP', 'sendEmailSendGrid', 'generateResetToken', 'verifyResetToken', 'markTokenUsed'];
$missing_funcs = [];
foreach ($functions as $func) {
    if (!function_exists($func)) {
        $missing_funcs[] = $func;
    }
}
if (empty($missing_funcs)) {
    echo "<div class='status ok'>✅ All required functions exist</div>";
} else {
    echo "<div class='status fail'>❌ Missing functions: " . implode(', ', $missing_funcs) . "</div>";
}

// 6. Test email sending (dry run)
echo "<h2>6. Email Test</h2>";
echo "<form method='POST'>
    <input type='email' name='test_email' placeholder='your-email@example.com' required>
    <button type='submit' name='send_test'>Send Test Email</button>
</form>";

if ($_POST['send_test'] ?? false) {
    $test_email = filter_var($_POST['test_email'], FILTER_VALIDATE_EMAIL);
    if ($test_email) {
        echo "<div style='margin-top:20px;background:#0f172a;padding:15px;border-radius:8px;font-size:0.9rem;'>
              <strong>Testing email to: $test_email</strong><br><br>";

        error_log("🔔 [DEBUG] Starting email test to $test_email");

        // Test SMTP
        if ($hasSMTP) {
            echo "<pre>";
            $result = sendEmailSMTP($test_email, 'Test User', 'TicketDesk Test', '<p>Test</p>', 'Test', [
                'host' => getenv('SMTP_HOST'),
                'port' => getenv('SMTP_PORT') ?: 587,
                'username' => getenv('SMTP_USER'),
                'password' => getenv('SMTP_PASS'),
                'secure' => getenv('SMTP_SECURE') ?: 'tls',
                'from_name' => SITE_NAME,
                'debug' => true
            ]);
            echo "</pre>";
            echo $result ? "<div class='status ok'>✅ SMTP: Email sent!</div>" : "<div class='status fail'>❌ SMTP: Failed</div>";
        }
        // Test SendGrid
        elseif ($hasSendGrid) {
            echo "<pre>";
            $result = sendEmailSendGrid($test_email, 'Test User', 'TicketDesk Test', '<p>Test</p>', 'Test', [
                'api_key' => getenv('SENDGRID_API_KEY'),
                'from_email' => getenv('SMTP_USER') ?: 'noreply@' . $_SERVER['SERVER_NAME'],
                'from_name' => SITE_NAME
            ]);
            echo "</pre>";
            echo $result ? "<div class='status ok'>✅ SendGrid: Email sent!</div>" : "<div class='status fail'>❌ SendGrid: Failed</div>";
        }

        echo "</div>";
    } else {
        echo "<div class='status fail'>Invalid email address</div>";
    }
}

echo "<hr><p><a href='forgot_password.php'>→ Test forgot password form</a></p>";
echo "<p><a href='railway_email_test.php'>→ Full email config test</a></p>";
echo "</body></html>";
?>
