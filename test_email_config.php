<?php
/**
 * Email Configuration Test Script
 * Tests both mail() and SMTP configurations
 */

require_once __DIR__ . '/includes/config.php';

header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Configuration Test</title>
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--bg-body); color: var(--text-main); padding: 2rem; line-height: 1.6; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 1.5rem; margin-bottom: 1rem; }
        h1 { color: var(--primary); margin-bottom: 0.5rem; }
        h2 { color: var(--text-main); border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; margin-top: 0; }
        .success { color: #10b981; font-weight: bold; }
        .error { color: #ef4444; font-weight: bold; }
        .warning { color: #f59e0b; font-weight: bold; }
        .info { color: #3b82f6; }
        pre { background: var(--bg-input); padding: 1rem; border-radius: var(--radius-sm); overflow-x: auto; font-size: 0.85rem; }
        code { font-family: 'SF Mono', 'Consolas', monospace; }
        .btn { display: inline-block; background: var(--primary); color: white; padding: 0.5rem 1rem; border-radius: var(--radius-sm); text-decoration: none; margin: 0.25rem; }
        .btn:hover { background: var(--primary-hover); }
        .test-form { background: var(--bg-input); padding: 1rem; border-radius: var(--radius-sm); margin-top: 1rem; }
        .test-form input, .test-form select { padding: 0.5rem; border: 1px solid var(--border); border-radius: var(--radius-sm); width: 100%; margin-bottom: 0.5rem; }
        .test-form label { display: block; margin-bottom: 0.25rem; font-weight: 500; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { text-align: left; padding: 0.75rem; border-bottom: 1px solid var(--border); }
        th { background: var(--bg-input); font-weight: 600; }
        .log-entry { padding: 0.25rem 0; border-bottom: 1px dashed var(--border-mid); }
    </style>
</head>
<body>
<div class="container">
    <h1>📧 Email Configuration Test</h1>
    <p class="info">This script tests both SMTP and PHP mail() configuration for password reset emails.</p>

    <div class="card">
        <h2>🔍 Environment Detection</h2>
        <?php
        $isRailway = getenv('RAILWAY_ENVIRONMENT') || getenv('MYSQLHOST');
        echo $isRailway ?
            '<p class="success">✅ Running on <strong>Railway</strong> (Production environment detected)</p>' :
            '<p class="warning">⚠️ Running on <strong>Localhost</strong> (Development environment)</p>';
        ?>
        <p>SITE_URL: <code><?= SITE_URL ?></code></p>
    </div>

    <div class="card">
        <h2>⚙️ SMTP Configuration</h2>
        <?php
        $smtp_host = getenv('SMTP_HOST');
        $smtp_port = getenv('SMTP_PORT');
        $smtp_user = getenv('SMTP_USER');
        $smtp_pass = getenv('SMTP_PASS') ? '***hidden***' : null;
        $smtp_secure = getenv('SMTP_SECURE');

        if ($smtp_host && $smtp_user && $smtp_pass) {
            echo '<p class="success">✅ SMTP is <strong>CONFIGURED</strong></p>';
            echo '<table>';
            echo '<tr><th>Setting</th><th>Value</th></tr>';
            echo '<tr><td>Host</td><td><code>' . htmlspecialchars($smtp_host) . '</code></td></tr>';
            echo '<tr><td>Port</td><td><code>' . htmlspecialchars($smtp_port ?: '587 (default)') . '</code></td></tr>';
            echo '<tr><td>Username</td><td><code>' . htmlspecialchars($smtp_user) . '</code></td></tr>';
            echo '<tr><td>Password</td><td><code>' . htmlspecialchars($smtp_pass) . '</code></td></tr>';
            echo '<tr><td>Security</td><td><code>' . htmlspecialchars($smtp_secure ?: 'tls (default)') . '</code></td></tr>';
            echo '</table>';
            echo '<p class="warning">⚠️ SMTP will be used for sending emails (overrides PHP mail())</p>';
        } else {
            echo '<p class="warning">⚠️ SMTP is <strong>NOT CONFIGURED</strong></p>';
            echo '<p>Falling back to PHP <code>mail()</code> function. To use SMTP (recommended for Railway), set these environment variables:</p>';
            echo '<pre>
SMTP_HOST=your-smtp-server.com
SMTP_PORT=587
SMTP_USER=your-email@example.com
SMTP_PASS=your-password-or-api-key
SMTP_SECURE=tls
            </pre>';
        }
        ?>
    </div>

    <div class="card">
        <h2>📨 Send Test Email</h2>
        <form method="POST" class="test-form">
            <label>Recipient Email:</label>
            <input type="email" name="test_email" required placeholder="your-email@example.com"
                   value="<?= htmlspecialchars($_POST['test_email'] ?? '') ?>">

            <label>Recipient Name:</label>
            <input type="text" name="test_name" placeholder="John Doe"
                   value="<?= htmlspecialchars($_POST['test_name'] ?? '') ?>">

            <button type="submit" class="btn" style="background: #10b981; border: none; cursor: pointer;">
                📤 Send Test Email
            </button>
        </form>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $testEmail = $_POST['test_email'] ?? '';
            $testName = $_POST['test_name'] ?? 'Test User';

            echo '<h3>Test Result:</h3>';

            if (empty($testEmail) || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                echo '<p class="error">❌ Invalid email address</p>';
            } else {
                // Generate test token (doesn't get stored, just for email)
                $testToken = bin2hex(random_bytes(32));

                echo '<div class="test-results">';
                echo '<p>Sending test email to: <strong>' . htmlspecialchars($testEmail) . '</strong></p>';

                // Call the actual email function
                $result = sendPasswordResetEmail($testEmail, $testName, $testToken);

                if ($result) {
                    echo '<p class="success">✅ Email function returned <strong>SUCCESS</strong></p>';
                    echo '<p class="info">ℹ️ Check your inbox or spam folder. Also check error logs for delivery details.</p>';
                } else {
                    echo '<p class="error">❌ Email function returned <strong>FAILURE</strong></p>';
                    echo '<p>Check the error logs below for details.</p>';
                }

                echo '</div>';
            }

            // Show recent logs
            echo '<h3>Recent Email Logs:</h3>';
            echo '<pre style="max-height: 400px; overflow-y: auto;">';
            showRecentLogs(20);
            echo '</pre>';
        }
        ?>

        <div style="margin-top: 1rem;">
            <a href="diagnose_password_reset.php" class="btn">🔧 Run Full Diagnostic</a>
            <a href="forgot_password.php" class="btn">🔗 Test Forgot Password Form</a>
        </div>
    </div>

    <div class="card">
        <h2>📋 Setup Checklist</h2>
        <?php
        $checks = [];

        // Database check
        try {
            $pdo->query("SELECT 1 FROM password_reset_tokens LIMIT 1");
            $checks[] = ['✅ Database connected', true];
        } catch (Exception $e) {
            $checks[] = ['❌ Database error: ' . $e->getMessage(), false];
        }

        // Check if we have at least one employee
        try {
            $count = $pdo->query("SELECT COUNT(*) FROM employees WHERE status='active'")->fetchColumn();
            if ($count > 0) {
                $checks[] = ["✅ Found $count active employee(s)", true];
            } else {
                $checks[] = ['⚠️ No active employees in database', false];
            }
        } catch (Exception $e) {
            $checks[] = ['❌ Cannot check employees: ' . $e->getMessage(), false];
        }

        // SMTP config check
        if ($smtp_host && $smtp_user) {
            $checks[] = ['✅ SMTP configured', true];
        } else {
            $checks[] = ['⚠️ SMTP not configured (will use PHP mail())', true];
        }

        // PHP mail function check
        if (function_exists('mail')) {
            $checks[] = ['✅ PHP mail() function available', true];
        } else {
            $checks[] = ['❌ PHP mail() function disabled', false];
        }

        foreach ($checks as $check) {
            echo '<div class="log-entry">' . $check[0] . '</div>';
        }
        ?>
    </div>

    <div class="card">
        <h2>📖 Next Steps</h2>
        <ol>
            <li>If using SMTP on Railway, set the environment variables in Railway dashboard</li>
            <li>If using localhost, configure <code>C:\xampp\sendmail\sendmail.ini</code> with your SMTP credentials</li>
            <li>Run this test again and click "Send Test Email"</li>
            <li>Check your inbox/spam folder</li>
            <li>Review the error log below if email doesn't arrive</li>
            <li>Visit <a href="forgot_password.php">forgot_password.php</a> to test the actual form</li>
        </ol>
    </div>
</div>

<script>
// Dark mode toggle
if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    document.documentElement.classList.add('dark');
}
</script>

<?php
function showRecentLogs($lines = 50) {
    // Read the last N lines of PHP error log
    $logFile = ini_get('error_log') ?: 'C:\xampp\php\logs\php_error.log';

    if (!file_exists($logFile)) {
        echo "Error log not found at: $logFile\n";
        return;
    }

    $file = file($logFile);
    if (!$file) {
        echo "Could not read error log\n";
        return;
    }

    $totalLines = count($file);
    $startLine = max(0, $totalLines - $lines);

    $output = '';
    for ($i = $startLine; $i < $totalLines; $i++) {
        $line = rtrim($file[$i]);
        // Only show lines with email, SMTP, or password reset
        if (stripos($line, 'email') !== false || stripos($line, 'smtp') !== false ||
            stripos($line, 'password reset') !== false || stripos($line, '✅') !== false ||
            stripos($line, '❌') !== false) {
            $output .= $line . "\n";
        }
    }

    echo $output ?: "No recent email-related logs found";
}
?>
</body>
</html>
