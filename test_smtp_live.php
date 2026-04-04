<?php
/**
 * Live SMTP Test with Error Capture
 * Shows exactly what error occurs during email sending
 */

require_once __DIR__ . '/includes/config.php';

// Enable verbose debugging
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Capture error logs in memory
$capturedLogs = [];
$originalErrorLog = function($msg) use (&$capturedLogs) {
    $capturedLogs[] = date('Y-m-d H:i:s') . ' | ' . $msg;
};

// Temporarily replace error_log function using runkit or by overriding in same scope
// Since we can't truly override, we'll add our own logging wrapper

header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Live SMTP Test with Error Capture</title>
    <style>
        body { font-family: 'Courier New', monospace; background: #1a1a2e; color: #fff; padding: 20px; }
        .log { background: #000; padding: 15px; border-radius: 6px; margin: 10px 0; white-space: pre-wrap; word-wrap: break-word; }
        .success { color: #10b981; }
        .error { color: #ef4444; }
        .info { color: #3b82f6; }
        h1 { color: #fff; }
        .btn { background: #5552DD; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; }
        .btn:hover { background: #7B7AFF; }
        textarea { width: 100%; height: 300px; background: #000; color: #fff; padding: 10px; border: 1px solid #333; border-radius: 6px; font-family: 'Courier New', monospace; margin: 10px 0; }
        .section { margin: 20px 0; border: 1px solid #333; padding: 15px; border-radius: 6px; }
    </style>
</head>
<body>
<h1>🔬 Live SMTP Test - Error Capture</h1>

<div class="btn" onclick="runTest()">🚀 Run Complete Test</div>

<div id="output"></div>

<script>
function runTest() {
    const output = document.getElementById('output');
    output.innerHTML = '<div class="log info">Running test... please wait.</div>';

    fetch('?run=1&debug=1')
        .then(res => res.text())
        .then(html => {
            const match = html.match(/<div id='test-results'>(.*?)<\/div>\s*<\/body>/s);
            if (match) {
                output.innerHTML = match[1];
            } else {
                output.innerHTML = html;
            }
        })
        .catch(err => {
            output.innerHTML = '<div class="log error">❌ Fetch error: ' + err + '</div>';
        });
}
</script>

<?php
if (isset($_GET['run'])) {
    echo "<div id='test-results'>";

    echo "<div class='section'>";
    echo "<h2 style='color:#fff;margin-top:0;'>Environment Info</h2>";
    echo "Running on: " . (getenv('RAILWAY_ENVIRONMENT') ? '🚂 Railway' : '💻 Localhost') . "\n";
    echo "PHP Version: " . PHP_VERSION . "\n";
    echo "SITE_URL: " . SITE_URL . "\n";
    echo "</div>\n";

    echo "<div class='section'>";
    echo "<h2 style='color:#fff;margin-top:0;'>SMTP Configuration</h2>";
    $smtp_host = getenv('SMTP_HOST');
    $smtp_port = getenv('SMTP_PORT') ?: 587;
    $smtp_user = getenv('SMTP_USER');
    $smtp_pass = getenv('SMTP_PASS');
    $smtp_secure = getenv('SMTP_SECURE') ?: 'tls';

    echo "Host: " . ($smtp_host ?: 'NOT SET') . "\n";
    echo "Port: " . $smtp_port . "\n";
    echo "Username: " . ($smtp_user ? 'SET (hidden)' : 'NOT SET') . "\n";
    echo "Password: " . ($smtp_pass ? 'SET (hidden)' : 'NOT SET') . "\n";
    echo "Security: " . ($smtp_secure ?: 'NOT SET') . "\n";

    $smtpConfigured = ($smtp_host && $smtp_user && $smtp_pass);
    echo $smtpConfigured ? "✅ SMTP Configured\n" : "❌ SMTP NOT Configured\n";
    echo "</div>\n";

    // Test database first
    echo "<div class='section'>";
    echo "<h2 style='color:#fff;margin-top:0;'>1. Database Check</h2>";
    try {
        $pdo->query("SELECT 1");
        echo "✅ Database connection OK\n";

        // Check if test user exists
        $testEmail = 'radnuscommunicationsa@gmail.com';
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE email = ? OR emp_id = ?");
        $stmt->execute([$testEmail, $testEmail]);
        $user = $stmt->fetch();

        if ($user) {
            echo "✅ Test user found: {$user['name']} (ID: {$user['id']}, Status: {$user['status']})\n";
        } else {
            echo "⚠️ Test user '{$testEmail}' not found. Using any employee for token test.\n";
            // Get first active employee
            $stmt = $pdo->prepare("SELECT * FROM employees WHERE status='active' LIMIT 1");
            $stmt->execute();
            $user = $stmt->fetch();
            if ($user) {
                echo "✅ Using test employee: {$user['name']} (ID: {$user['id']})\n";
                $testEmail = $user['email'];
            } else {
                echo "❌ No active employees found in database!\n";
                exit;
            }
        }
    } catch (PDOException $e) {
        echo "❌ Database error: " . $e->getMessage() . "\n";
        exit;
    }
    echo "</div>\n";

    // Test token generation
    echo "<div class='section'>";
    echo "<h2 style='color:#fff;margin-top:0;'>2. Token Generation Test</h2>";
    try {
        $token = generateResetToken($pdo, $user['id']);
        echo "✅ Token generated: " . substr($token, 0, 20) . "...\n";

        // Verify token works
        $resetData = verifyResetToken($pdo, $token);
        if ($resetData) {
            echo "✅ Token verified successfully\n";
        } else {
            echo "❌ Token verification failed\n";
        }

        // Cleanup
        $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE token = ?");
        $stmt->execute([$token]);
        echo "✅ Test token cleaned up\n";
    } catch (Exception $e) {
        echo "❌ Token test failed: " . $e->getMessage() . "\n";
    }
    echo "</div>\n";

    // Test email sending with debug output
    echo "<div class='section'>";
    echo "<h2 style='color:#fff;margin-top:0;'>3. Email Sending Test (Live)</h2>";

    // Custom error handler to capture warnings
    $errors = [];
    set_error_handler(function($errno, $errstr) use (&$errors) {
        $errors[] = "[$errno] $errstr";
        return true; // Suppress default error handler
    });

    // Capture error_log calls by temporarily overriding
    // We'll also log to our own variable
    $originalLog = function($msg) {
        // Keep original behavior
        error_log($msg);
    };

    echo "Attempting to send test email to: {$user['email']} ({$user['name']})\n";
    echo "Method: " . ($smtpConfigured ? 'SMTP' : 'PHP mail()') . "\n\n";

    $testToken = bin2hex(random_bytes(32));
    $start = microtime(true);
    $result = sendPasswordResetEmail($user['email'], $user['name'], $testToken);
    $duration = round(microtime(true) - $start, 3);

    restore_error_handler();

    if ($result) {
        echo "<span class='success'>✅ Email function returned SUCCESS</span>\n";
        echo "Time taken: {$duration}s\n";
    } else {
        echo "<span class='error'>❌ Email function returned FAILURE</span>\n";
        echo "Time taken: {$duration}s\n";
    }

    if (!empty($errors)) {
        echo "\n<span class='error'>⚠️ PHP Errors/Warnings:</span>\n";
        foreach ($errors as $error) {
            echo "  - $error\n";
        }
    }

    echo "</div>\n";

    // Show captured error logs
    echo "<div class='section'>";
    echo "<h2 style='color:#fff;margin-top:0;'>Captured Error Logs</h2>";

    // Try to get recent error log entries
    $logEntries = [];
    if (function_exists('error_get_last')) {
        // Check recent errors
        $last = error_get_last();
        if ($last) {
            echo "Last error: " . print_r($last, true) . "\n";
        }
    }

    if (empty($capturedLogs) && empty($errors)) {
        echo "No errors captured. Enable EMAIL_DEBUG for more verbosity.\n";
    }

    echo "</div>\n";

    // Suggestions
    echo "<div class='section'>";
    echo "<h2 style='color:#fff;margin-top:0;'>Troubleshooting</h2>";

    if (!$result) {
        echo "<span class='error'>❌ Email failed to send.</span>\n\n";

        echo "Most likely causes:\n\n";
        echo "1. <strong>SMTP Connection Blocked</strong> (Railway free tier)\n";
        echo "   - Railway may block outbound SMTP on ports 25/587/465\n";
        echo "   - Check Railway docs or support\n";
        echo "   - Solution: Use email API (SendGrid, Mailgun) or upgrade\n\n";

        echo "2. <strong>Authentication Failed</strong>\n";
        echo "   - Wrong Gmail App Password\n";
        echo "   - 2FA not enabled on Google Account\n";
        echo "   - Google blocked sign-in as suspicious\n";
        echo "   - Check your Gmail for security alerts\n\n";

        echo "3. <strong>PHP mail() not configured</strong>\n";
        echo "   - On Railway, mail() likely won't work\n";
        echo "   - Must use SMTP with proper configuration\n\n";

        echo "4. <strong>Database issue</strong>\n";
        echo "   - password_reset_tokens table missing\n";
        echo "   - Verify with: SHOW TABLES LIKE 'password_reset_tokens'\n";
    } else {
        echo "<span class='success'>✅ Email sent successfully!</span>\n";
        echo "Check your inbox at: {$user['email']}\n";
        echo "Don't forget to check spam folder.\n";
    }

    echo "</div>\n";

    echo "</div>"; // Close test-results
}

?>
</body>
</html>
