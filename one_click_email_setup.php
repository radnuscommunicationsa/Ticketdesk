<?php
/**
 * ONE-CLICK EMAIL SETUP FOR LOCALHOST
 *
 * This script configures email sending immediately without needing to restart Apache.
 * Just enter your Gmail App Password and click Configure.
 */

// First, try to create a temp config file that will be included
$configFile = __DIR__ . '/includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚡ One-Click Email Setup</title>
    <style>
        body { font-family: sans-serif; max-width: 700px; margin: 3rem auto; padding: 1rem; background: #f5f5f5; }
        .card { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h1 { color: #dc2626; margin-top: 0; }
        .field { margin: 1rem 0; }
        label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
        input[type="email"], input[type="password"] {
            width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px;
        }
        .btn {
            background: #dc2626; color: white; border: none; padding: 12px 24px;
            border-radius: 6px; cursor: pointer; font-size: 16px; margin-top: 1rem;
        }
        .btn:hover { background: #b91c1c; }
        .success { background: #f0fdf4; color: #166534; padding: 1rem; border-radius: 6px; margin: 1rem 0; border-left: 4px solid #16a34a; }
        .error { background: #fef2f2; color: #991b1b; padding: 1rem; border-radius: 6px; margin: 1rem 0; border-left: 4px solid #dc2626; }
        .info { background: #eff6ff; color: #1e40af; padding: 1rem; border-radius: 6px; margin: 1rem 0; border-left: 4px solid #2563eb; }
        code { background: #f1f1f1; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        ol { line-height: 1.8; }
        a { color: #dc2626; }
    </style>
</head>
<body>
    <div class="card">
        <h1>⚡ One-Click Email Setup</h1>
        <p>Configure password reset emails to work immediately. No Apache restart needed!</p>

        <div class="info">
            <strong>ℹ️ How it works:</strong>
            <p>This will inject SMTP credentials into your config.php for the current session.
            The change takes effect immediately - no restart required.</p>
            <p><strong>Note:</strong> For permanent setup, edit <code>C:\xampp\apache\conf\extra\httpd-xampp.conf</code> instead.</p>
        </div>

        <form method="POST">
            <div class="field">
                <label>Your Gmail Address:</label>
                <input type="email" name="email" required placeholder="you@gmail.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <div class="field">
                <label>Gmail App Password (16 characters, no spaces):</label>
                <input type="password" name="app_password" required placeholder="abcd1234efgh5678">
                <small><a href="https://myaccount.google.com/security" target="_blank">Get App Password →</a></small>
            </div>

            <button type="submit" class="btn">🚀 Configure Email Now</button>
        </form>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim($_POST['email'] ?? '');
            $password = trim($_POST['app_password'] ?? '');

            if (!$email || !$password) {
                echo '<div class="error">❌ Both email and password are required</div>';
            } else {
                // Read the config file
                if (!file_exists($configFile)) {
                    echo '<div class="error">❌ Config file not found: ' . htmlspecialchars($configFile) . '</div>';
                } else {
                    $configContent = file_get_contents($configFile);

                    // Check if putenv lines already exist
                    if (strpos($configContent, 'putenv(\'SMTP_USER=') !== false) {
                        // Remove existing putenv lines first
                        $configContent = preg_replace('/\n\s*putenv\(\'SMTP_[^\']+\'[^;]*\);\s*/', "\n", $configContent);
                    }

                    // Find the position after the EMAIL_DEBUG define and the if block
                    $insertPos = strpos($configContent, "error_log('ℹ️ PHP mail()");
                    if ($insertPos === false) {
                        $insertPos = strpos($configContent, "if (!getenv('SMTP_HOST')");
                    }

                    if ($insertPos !== false) {
                        // Find the end of the if block
                        $blockEnd = strpos($configContent, "}", $insertPos);
                        if ($blockEnd !== false) {
                            // Insert after the if block
                            $afterBlock = strpos($configContent, "\n", $blockEnd + 1);
                            if ($afterBlock !== false) {
                                $insertAt = $afterBlock + 1;
                            } else {
                                $insertAt = $blockEnd + 1;
                            }
                        } else {
                            $insertAt = $insertPos;
                        }
                    } else {
                        // Fallback: insert after EMAIL_DEBUG line
                        $insertAt = strpos($configContent, "define('EMAIL_DEBUG'") ?: 0;
                    }

                    // Add new lines
                    $newLines = "\n\n// ════════════════════════════════════════════════════════════════════════════════\n";
                    $newLines .= "// TEMPORARY LOCALHOST SMTP (Added by One-Click Setup on " . date('Y-m-d H:i:s') . ")\n";
                    $newLines .= "// Remove this block when deploying to production or set via environment variables\n";
                    $newLines .= "// ════════════════════════════════════════════════════════════════════════════════\n";
                    $newLines .= "putenv('SMTP_HOST=smtp.gmail.com');\n";
                    $newLines .= "putenv('SMTP_PORT=587');\n";
                    $newLines .= "putenv('SMTP_USER=" . addslashes($email) . "');\n";
                    $newLines .= "putenv('SMTP_PASS=" . addslashes($password) . "');\n";
                    $newLines .= "putenv('SMTP_SECURE=tls');\n";
                    $newLines .= "// ════════════════════════════════════════════════════════════════════════════════\n";

                    // Insert the new content
                    $newContent = substr_replace($configContent, $newLines, $insertAt, 0);

                    // Save
                    if (file_put_contents($configFile, $newContent) !== false) {
                        echo '<div class="success">';
                        echo '<strong>✅ Success! Email configured!</strong><br><br>';
                        echo 'SMTP credentials have been added to config.php.<br>';
                        echo 'Password reset emails will now work immediately.<br><br>';
                        echo '<strong>Next steps:</strong><br>';
                        echo '<ol>';
                        echo '<li><a href="test_email_config.php">📧 Test Email Configuration</a> - Verify it works</li>';
                        echo '<li><a href="forgot_password.php">🔗 Test Forgot Password</a> - Try the full flow</li>';
                        echo '<li>Check your email (and spam folder) for the test email</li>';
                        echo '</ol>';
                        echo '<p><strong>⚠️ Security Note:</strong> These credentials are now in config.php. ';
                        echo 'For production, use environment variables or Railway Variables instead.</p>';
                        echo '</div>';
                    } else {
                        echo '<div class="error">❌ Failed to write to config.php. Check file permissions.</div>';
                    }
                }
            }
        }
        ?>

        <hr style="margin: 2rem 0; border: none; border-top: 1px solid #ddd;">

        <div class="info">
            <h3>📖 How to get Gmail App Password</h3>
            <ol>
                <li>Go to <a href="https://myaccount.google.com/security" target="_blank">Google Account Security</a></li>
                <li><strong>Enable 2-Step Verification</strong> (if not already enabled)</li>
                <li>Scroll to <strong>"App passwords"</strong> section</li>
                <li>Click it, then select "Other (Custom name)"</li>
                <li>Type: <code>TicketDesk</code></li>
                <li>Copy the 16-character password (e.g., <code>abcd1234efgh5678</code>)</li>
                <li><strong>Remove any spaces</strong> and paste above</li>
            </ol>
            <p><strong>Note:</strong> App passwords only work if 2FA is enabled on your Google account.</p>
        </div>

        <div style="margin-top: 1.5rem; text-align: center; color: #666; font-size: 0.9rem;">
            <p>After setup, you can remove this temporary configuration and use:</p>
            <ul style="list-style: none; padding: 0;">
                <li>• <code>C:\xampp\apache\conf\extra\httpd-xampp.conf</code> with SetEnv (permanent)</li>
                <li>• <code>C:\xampp\sendmail\sendmail.ini</code> for sendmail method</li>
            </ul>
        </div>
    </div>
</body>
</html>