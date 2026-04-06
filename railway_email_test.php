<?php
/**
 * Railway Email Configuration Test
 * Visit this on your Railway app to test email setup
 */

require_once __DIR__ . '/includes/config.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Railway Email Test — TicketDesk</title>
<style>
    body { font-family: monospace; background: #1a1a2e; color: #e2e8f0; padding: 2rem; line-height: 1.6; }
    .container { max-width: 800px; margin: 0 auto; }
    h1 { color: #7B7AFF; margin-bottom: 2rem; }
    .section { background: #16213e; padding: 1.5rem; margin: 1rem 0; border-radius: 8px; border-left: 4px solid #5552DD; }
    .success { border-left-color: #10B981; }
    .error { border-left-color: #EF4444; }
    .warning { border-left-color: #F59E0B; }
    code { background: #0f172a; padding: 2px 6px; border-radius: 4px; color: #f8fafc; }
    pre { background: #0f172a; padding: 1rem; overflow-x: auto; border-radius: 4px; }
    .env-var { color: #FBBF24; font-weight: bold; }
    .value { color: #10B981; }
    .missing { color: #EF4444; }
    input, button { padding: 0.5rem; border-radius: 4px; border: 1px solid #333; background: #16213e; color: white; margin: 0.5rem 0; }
    input { width: 100%; max-width: 300px; }
    button { background: #5552DD; cursor: pointer; font-weight: bold; }
    button:hover { background: #7B7AFF; }
    .log { background: #0f172a; padding: 1rem; margin: 0.5rem 0; border-radius: 4px; font-size: 0.85rem; max-height: 400px; overflow-y: auto; }
    .log-entry { margin: 2px 0; padding: 2px 0; border-bottom: 1px solid #1e293b; }
    .log-success { color: #10B981; }
    .log-error { color: #EF4444; }
    .log-info { color: #60A5FA; }
</style>
</head>
<body>
<div class="container">
    <h1>🚀 Railway Email Configuration Test</h1>

    <div class="section <?= $isRailway ? 'warning' : '' ?>">
        <h2>🏗️ Environment Detection</h2>
        <?php
        $isRailway = getenv('MYSQLHOST') || getenv('RAILWAY_PUBLIC_DOMAIN');
        echo "<p>Running on: <strong>" . ($isRailway ? '🚂 Railway (Production)' : '💻 Localhost (Development)') . "</strong></p>";
        echo "<p>SITE_URL: <code>" . SITE_URL . "</code></p>";
        ?>
    </div>

    <div class="section">
        <h2>📧 Environment Variables Status</h2>
        <?php
        $vars = [
            'SMTP_HOST' => getenv('SMTP_HOST'),
            'SMTP_PORT' => getenv('SMTP_PORT') ?: '587 (default)',
            'SMTP_USER' => getenv('SMTP_USER'),
            'SMTP_PASS' => getenv('SMTP_PASS') ? '***Hidden***' : null,
            'SMTP_SECURE' => getenv('SMTP_SECURE') ?: 'tls (default)',
            'SENDGRID_API_KEY' => getenv('SENDGRID_API_KEY') ? '***Hidden***' : null,
            'EMAIL_DEBUG' => getenv('EMAIL_DEBUG') ?: 'false (default)',
        ];

        $allSet = true;
        foreach ($vars as $var => $value) {
            $status = $value ? '<span class="value">✓ SET</span>' : '<span class="missing">✗ NOT SET</span>';
            echo "<p><code class=\"env-var\">$var</code>: $status " . ($value ? "<small>($value)</small>" : "") . "</p>";
            if ($var !== 'SENDGRID_API_KEY' && $var !== 'SMTP_PASS' && !$value) {
                $allSet = false;
            }
        }

        if ($allSet) {
            echo "<p style='color: #10B981; font-weight: bold; margin-top: 1rem;'>✅ All required SMTP variables are configured!</p>";
        } else {
            echo "<p style='color: #EF4444; font-weight: bold; margin-top: 1rem;'>❌ SMTP is not fully configured. Emails will NOT send.</p>";
        }
        ?>
    </div>

    <div class="section">
        <h2>🧪 Send Test Email</h2>
        <p>Enter your email address to test if the email system is working:</p>
        <form method="POST">
            <input type="email" name="test_email" placeholder="your-email@example.com" required>
            <button type="submit" name="send_test" value="1">📧 Send Test Email</button>
        </form>

        <?php
        if ($_POST['send_test'] ?? false) {
            $testEmail = filter_var($_POST['test_email'], FILTER_VALIDATE_EMAIL);
            if (!$testEmail) {
                echo "<div class='error' style='margin-top:1rem'>❌ Invalid email address</div>";
            } else {
                echo "<div class='log'><h3>📨 Sending Test Email to: $testEmail</h3>";

                // Manually replicate the email sending logic
                $subject = "TicketDesk - Test Email";
                $message = "<html><body><h1>Test Email</h1><p>If you receive this, email is working on Railway!</p><p>Time: " . date('Y-m-d H:i:s') . "</p></body></html>";
                $plainMessage = "Test email from TicketDesk at " . date('Y-m-d H:i:s');

                $methodUsed = '';

                // Try SendGrid
                $sendgrid_api_key = getenv('SENDGRID_API_KEY');
                if ($sendgrid_api_key) {
                    echo "<div class='log-entry log-info'>🔍 Detected SENDGRID_API_KEY, trying SendGrid API...</div>";
                    if (function_exists('sendEmailSendGrid')) {
                        $result = sendEmailSendGrid($testEmail, 'Test User', $subject, $message, $plainMessage, [
                            'api_key' => $sendgrid_api_key,
                            'from_email' => getenv('SMTP_USER') ?: 'noreply@' . $_SERVER['SERVER_NAME'],
                            'from_name' => SITE_NAME
                        ]);
                        $methodUsed = $result ? 'SendGrid API ✓' : 'SendGrid API ✗';
                    } else {
                        echo "<div class='log-entry log-error'>❌ sendEmailSendGrid function not found</div>";
                    }
                }

                // Try SMTP
                if (empty($sendgrid_api_key)) {
                    $smtp_host = getenv('SMTP_HOST');
                    $smtp_user = getenv('SMTP_USER');
                    $smtp_pass = getenv('SMTP_PASS');

                    if ($smtp_host && $smtp_user && $smtp_pass) {
                        echo "<div class='log-entry log-info'>🔍 SMTP configured ($smtp_host), trying SMTP...</div>";
                        if (function_exists('sendEmailSMTP')) {
                            $result = sendEmailSMTP($testEmail, 'Test User', $subject, $message, $plainMessage, [
                                'host' => $smtp_host,
                                'port' => getenv('SMTP_PORT') ?: 587,
                                'username' => $smtp_user,
                                'password' => $smtp_pass,
                                'secure' => getenv('SMTP_SECURE') ?: 'tls',
                                'from_name' => SITE_NAME
                            ]);
                            $methodUsed = $result ? 'SMTP ✓' : 'SMTP ✗';
                        } else {
                            echo "<div class='log-entry log-error'>❌ sendEmailSMTP function not found</div>";
                        }
                    } else {
                        echo "<div class='log-entry log-error'>❌ SMTP not configured (SMTP_HOST, SMTP_USER, SMTP_PASS required)</div>";
                    }
                }

                // Show result
                if (strpos($methodUsed, '✓') !== false) {
                    echo "<div class='log-entry log-success'>✅ Email sent successfully via $methodUsed!</div>";
                    echo "<p style='color: #10B981; font-weight: bold;'>✅ Check your inbox! If you don't see it, check spam folder.</p>";
                } else {
                    echo "<div class='log-entry log-error'>❌ Email failed to send via $methodUsed</div>";
                    echo "<p style='color: #EF4444; font-weight: bold;'>❌ Email NOT sent. Check Railway logs for detailed errors.</p>";
                    echo "<h4>Next steps:</h4>";
                    echo "<ol>";
                    echo "<li>Go to Railway Dashboard → Your Project → Logs</li>";
                    echo "<li>Look for errors starting with ❌ or SMTP DEBUG</li>";
                    echo "<li>Verify your SMTP credentials are correct</li>";
                    echo "<li>Check SMTP provider allows connections from Railway</li>";
                    echo "</ol>";
                }

                echo "</div>";
            }
        }
        ?>
    </div>

    <div class="section">
        <h2>📋 Configuration Checklist</h2>
        <p>Make sure you've completed these steps:</p>
        <ol>
            <li><strong>Choose SMTP Provider:</strong> Gmail (App Password) or SendGrid (API Key as password)</li>
            <li><strong>Set Railway Variables:</strong>
                <pre>SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your-email@gmail.com
SMTP_PASS=your-app-password
SMTP_SECURE=tls</pre>
            </li>
            <li><strong>Restart/Redeploy:</strong> Railway auto-deploys on git push, or manually trigger deployment</li>
            <li><strong>Check Logs:</strong> Visit this page and send test email, then check Railway logs</li>
            <li><strong>Test Full Flow:</strong> Test forgot_password → reset_password flow end-to-end</li>
        </ol>
    </div>

    <div class="section warning">
        <h2>⚠️ Common Issues</h2>
        <ul>
            <li><strong>Gmail "Authentication failed":</strong> Use App Password, NOT your regular password. 2FA must be enabled.</li>
            <li><strong>SendGrid "Unauthorized":</strong> Make sure API key has "Mail Send" permission</li>
            <li><strong>Connection timeout:</strong> Some SMTP providers block datacenter IPs. Try different provider.</li>
            <li><strong>No logs from Railway:</strong> Make sure EMAIL_DEBUG is set to 'true' in Variables to see detailed logs</li>
        </ul>
    </div>

    <div class="section">
        <h2>🔍 Where to Find Logs</h2>
        <p>1. Go to <a href="https://railway.app/dashboard" target="_blank">Railway Dashboard</a></p>
        <p>2. Click your project → <strong>Logs</strong> tab</p>
        <p>3. Look for entries containing:</p>
        <ul>
            <li><code>✅ Email sent via SMTP</code></li>
            <li><code>❌ SMTP error:</code></li>
            <li><code>[SMTP DEBUG]</code></li>
        </ul>
    </div>
</div>
</body>
</html>