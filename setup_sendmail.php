<?php
/**
 * Quick Setup Helper for XAMPP Sendmail
 * This helps you configure sendmail.ini for Gmail (or other SMTP)
 */

header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XAMPP Sendmail Setup Helper</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; max-width: 800px; margin: 2rem auto; padding: 0 1rem; line-height: 1.6; }
        h1 { color: #dc2626; }
        .step { background: #fef2f2; padding: 1.5rem; margin: 1rem 0; border-radius: 8px; border-left: 4px solid #dc2626; }
        .success { background: #f0fdf4; border-left-color: #16a34a; color: #166534; }
        code { background: #f1f1f1; padding: 0.2rem 0.4rem; border-radius: 3px; font-family: 'Courier New', monospace; }
        pre { background: #1e1e1e; color: #d4d4d4; padding: 1rem; border-radius: 8px; overflow-x: auto; }
        .btn { display: inline-block; background: #dc2626; color: white; padding: 0.5rem 1rem; text-decoration: none; border-radius: 6px; margin: 0.5rem 0; }
        .btn:hover { background: #b91c1c; }
        .warning { background: #fffbeb; border: 1px solid #fbbf24; padding: 1rem; border-radius: 8px; }
        ul { line-height: 1.8; }
    </style>
</head>
<body>
    <h1>📧 XAMPP Sendmail Setup Helper</h1>
    <p>This helper will configure XAMPP's sendmail to work with Gmail (or any SMTP provider) for sending password reset emails.</p>

    <a href="#configure" class="btn">🚀 Start Setup</a>

    <div class="warning">
        <strong>⚠️ Important:</strong> After setup, you must:
        <ol>
            <li>Restart Apache through XAMPP Control Panel</li>
            <li><strong>Use a Gmail App Password</strong> (not your regular password)</li>
            <li>Enable 2-Factor Authentication on your Google Account first</li>
        </ol>
    </div>

    <div id="configure" class="step">
        <h2>Step 1: Create Gmail App Password</h2>
        <p>If using Gmail, you MUST create an App Password (Google no longer allows "less secure apps"):</p>
        <ol>
            <li>Go to: <a href="https://myaccount.google.com/security" target="_blank">Google Account Security</a></li>
            <li>Enable <strong>2-Factor Authentication</strong> (if not already enabled)</li>
            <li>Click on <strong>App passwords</strong></li>
            <li>Select "Other (Custom name)" → type "TicketDesk"</li>
            <li>Copy the 16-character password (e.g., <code>abcd efgh ijkl mnop</code>)</li>
            <li><strong>Remove spaces</strong> when entering in the form below</li>
        </ol>
    </div>

    <div class="step">
        <h2>Step 2: Configure sendmail.ini</h2>
        <p>The file <code>C:\xampp\sendmail\sendmail.ini</code> has been pre-configured with Gmail settings. You just need to add your credentials.</p>

        <form method="POST" style="margin-top: 1rem;">
            <fieldset style="border: 1px solid #ddd; padding: 1rem; border-radius: 8px;">
                <legend><strong>Your Email Credentials</strong></legend>

                <p>
                    <label>Your Email (Gmail):<br>
                        <input type="email" name="email" required placeholder="you@gmail.com" style="width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px;">
                    </label>
                </p>

                <p>
                    <label>Gmail App Password (16 chars, no spaces):<br>
                        <input type="text" name="password" required placeholder="abcd1234efgh5678" style="width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px;">
                    </label>
                </p>

                <p>
                    <label>
                        <input type="checkbox" name="other_smtp"> I want to use a different SMTP provider (SendGrid, Mailgun, etc.)
                    </label>
                </p>

                <div id="smtp-other" style="display: none; margin-top: 1rem; padding: 1rem; background: #f9f9f9; border-radius: 4px;">
                    <p>
                        <label>SMTP Host:<br>
                            <input type="text" name="smtp_host" placeholder="smtp.sendgrid.net" style="width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px;">
                        </label>
                    </p>
                    <p>
                        <label>SMTP Port:<br>
                            <input type="number" name="smtp_port" value="587" style="width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px;">
                        </label>
                    </p>
                    <p>
                        <label>SMTP Username (usually email):<br>
                            <input type="text" name="smtp_user" placeholder="apikey (for SendGrid)" style="width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px;">
                        </label>
                    </p>
                    <p>
                        <label>SMTP Password (or API key):<br>
                            <input type="password" name="smtp_pass" style="width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px;">
                        </label>
                    </p>
                </div>

                <button type="submit" class="btn" style="border: none; cursor: pointer; padding: 10px 20px; font-size: 1rem;">💾 Save Configuration</button>
            </fieldset>
        </form>

        <script>
            document.querySelector('input[name="other_smtp"]').addEventListener('change', function() {
                document.getElementById('smtp-other').style.display = this.checked ? 'block' : 'none';
            });
        </script>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            $useOther = isset($_POST['other_smtp']);
            $smtpHost = $_POST['smtp_host'] ?? 'smtp.gmail.com';
            $smtpPort = $_POST['smtp_port'] ?? '587';
            $smtpUser = $_POST['smtp_user'] ?? $email;
            $smtpPass = $_POST['smtp_pass'] ?? $password;

            if (!$useOther) {
                $smtpHost = 'smtp.gmail.com';
                $smtpPort = '587';
                $smtpUser = $email;
                $smtpPass = $password;
            }

            $sendmail Ini = implode("\n", [
                '; configuration for sendmail',
                '',
                '[sendmail]',
                '',
                '; SMTP server',
                "smtp_server=$smtpHost",
                '',
                '; SMTP port',
                "smtp_port=$smtpPort",
                '',
                '; Use TLS',
                'smtp_ssl=tls',
                '',
                '; Authentication',
                "auth_username=$smtpUser",
                "auth_password=$smtpPass",
                '',
                '; Force sender (MAIL FROM)',
                "force_sender=$smtpUser",
                '',
                '; Logging',
                'error_logfile=error.log',
                'debug_logfile=debug.log',
                '',
                '; Hostname (optional)',
                'hostname=localhost',
            ]);

            $file = 'C:/xampp/sendmail/sendmail.ini';
            $result = file_put_contents($file, $sendmailIni);

            if ($result !== false) {
                echo "<div class='success' style='margin-top: 1rem;'><h3>✅ Configuration Saved!</h3>";
                echo "<p>sendmail.ini has been updated with your settings.</p>";
                echo "<h4>Next Steps:</h4>";
                echo "<ol>";
                echo "<li><strong>Restart Apache</strong> via XAMPP Control Panel</li>";
                echo "<li><a href='test_email_config.php'>Test Email Configuration</a></li>";
                echo "<li>Check that password reset emails work</li>";
                echo "</ol>";
                echo "<p><strong>Note:</strong> Your credentials have been saved to <code>C:\\xampp\\sendmail\\sendmail.ini</code>. Keep this file secure!</p>";
                echo "</div>";
            } else {
                echo "<div class='error' style='margin-top: 1rem; color: red;'><h3>❌ Failed to save</h3>";
                echo "<p>Could not write to C:\\xampp\\sendmail\\sendmail.ini</p>";
                echo "<p>Make sure the file exists and is writable. Try:</p>";
                echo "<pre>1. Open C:\\xampp\\sendmail\\sendmail.ini in Notepad (as Administrator)\n2. Update these values manually:\n   smtp_server=smtp.gmail.com\n   smtp_port=587\n   auth_username=your-email@gmail.com\n   auth_password=your-app-password\n   force_sender=your-email@gmail.com\n3. Save and restart Apache</pre>";
                echo "</div>";
            }
        }
        ?>
    </div>

    <div class="step success">
        <h2>Step 3: Restart Apache</h2>
        <p>After configuring sendmail.ini:</p>
        <ol>
            <li>Open <strong>XAMPP Control Panel</strong></li>
            <li>Stop <strong>Apache</strong></li>
            <li>Start <strong>Apache</strong> again</li>
            <li>Wait for it to show "Running"</li>
        </ol>
    </div>

    <div class="step">
        <h2>Step 4: Test Email</h2>
        <p>Once Apache is restarted, visit:</p>
        <p><a href="test_email_config.php" class="btn">📧 Test Email Configuration</a></p>
        <p>Enter your email and click "Send Test Email". Check your inbox (and spam folder).</p>
    </div>

    <div class="step">
        <h2>Alternative: Use SMTP Directly (No sendmail)</h2>
        <p>Instead of configuring sendmail, you can also use SMTP directly by setting environment variables:</p>
        <p>Add to <code>C:\xampp\apache\conf\extra\httpd-xampp.conf</code> or use a <code>.env</code> file:</p>
        <pre>SetEnv SMTP_HOST smtp.gmail.com
SetEnv SMTP_PORT 587
SetEnv SMTP_USER your-email@gmail.com
SetEnv SMTP_PASS your-app-password
SetEnv SMTP_SECURE tls</pre>
        <p>Then restart Apache. This makes the system use the <code>sendEmailSMTP()</code> function directly, bypassing sendmail.</p>
    </div>

    <hr style="margin: 2rem 0;">

    <div class="warning">
        <h3>🔧 Troubleshooting</h3>
        <ul>
            <li><strong>"Connection refused"</strong> → Check SMTP settings, firewall may block port 587</li>
            <li><strong>"Authentication failed"</strong> → Verify Gmail App Password (not regular password)</li>
            <li><strong>"Could not open socket"</strong> → sendmail.exe not found; check XAMPP installation</li>
            <li><strong>No email received</strong> → Check spam folder; view <code>C:\xampp\sendmail\debug.log</code></li>
        </ul>
        <p>Check logs:</p>
        <ul>
            <li><code>C:\xampp\php\logs\php_error.log</code> - PHP errors</li>
            <li><code>C:\xampp\sendmail\debug.log</code> - SMTP conversation</li>
            <li><code>C:\xampp\sendmail\error.log</code> - Sendmail errors</li>
        </ul>
    </div>

    <p style="margin-top: 2rem; text-align: center; color: #666;">
        <a href="COMPLETE_FIX_SUMMARY.md">View Full Documentation</a> |
        <a href="forgot_password.php">Test Forgot Password</a> |
        <a href="diagnose_password_reset.php">Run Diagnostics</a>
    </p>
</body>
</html>
