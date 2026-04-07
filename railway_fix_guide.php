<?php
/**
 * COMPLETE RAILWAY PASSWORD RESET FIX GUIDE
 * Upload to Railway and run this to diagnose and fix issues
 */

require_once __DIR__ . '/includes/config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>🔧 Railway Password Reset - Complete Fix Guide</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; max-width: 1000px; margin: 0 auto; padding: 20px; background: #f5f5f5; }
        .box { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .check { padding: 12px; margin: 8px 0; border-radius: 6px; }
        .pass { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .fail { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        .warn { background: #fef3c7; color: #92400e; border-left: 4px solid #f59e0b; }
        .info { background: #dbeafe; color: #1e40af; border-left: 4px solid #3b82f6; }
        code { background: #f3f4f6; padding: 2px 6px; border-radius: 4px; font-size: 0.9em; }
        pre { background: #1f2937; color: #f9fafb; padding: 15px; border-radius: 6px; overflow-x: auto; }
        .btn { background: #6366f1; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 1rem; }
        .btn:hover { background: #4f46e5; }
        .btn-danger { background: #ef4444; }
        .btn-success { background: #10b981; }
        input, select { padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; width: 100%; max-width: 300px; margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; }
        .step { counter-increment: step; }
        h2::before { content: counter(step) ". "; color: #6366f1; font-weight: bold; }
        .log-output { background: #0f172a; color: #22c55e; padding: 15px; border-radius: 8px; font-family: 'Courier New', monospace; font-size: 0.9rem; max-height: 400px; overflow-y: auto; white-space: pre-wrap; }
        .log-entry { margin: 2px 0; }
        .log-success { color: #4ade80; }
        .log-error { color: #f87171; }
        .log-info { color: #60a5fa; }
        .log-warn { color: #fbbf24; }
    </style>
</head>
<body style='counter-reset: step;'>

<div class='box'>
    <h1 style='margin-top:0;color:#6366f1'>🔧 Railway Password Reset - Complete Fix Guide</h1>
    <p><strong>Follow these steps in order to fix password reset on Railway</strong></p>
</div>";

// 1. Environment Check
echo "<div class='box'><h2>1. Environment Check</h2>";
$isRailway = getenv('MYSQLHOST') || getenv('RAILWAY_PUBLIC_DOMAIN');
echo "<div class='check " . ($isRailway ? 'pass' : 'warn') . "'>";
echo "<strong>Platform:</strong> " . ($isRailway ? '🚂 Railway (Production)' : '💻 Localhost (Development)') . "</div>";
echo "<div class='check info'>SITE_URL: <code>" . SITE_URL . "</code></div>";

// Show all env vars
echo "<h3>📋 All Railway Variables</h3>";
$vars = [
    'SMTP_HOST' => getenv('SMTP_HOST'),
    'SMTP_PORT' => getenv('SMTP_PORT'),
    'SMTP_USER' => getenv('SMTP_USER'),
    'SMTP_PASS' => getenv('SMTP_PASS') ? '***SET (length: ' . strlen(getenv('SMTP_PASS')) . ')***' : 'NOT SET',
    'SMTP_SECURE' => getenv('SMTP_SECURE'),
    'SENDGRID_API_KEY' => getenv('SENDGRID_API_KEY') ? '***SET***' : 'NOT SET',
    'EMAIL_DEBUG' => getenv('EMAIL_DEBUG'),
    'MYSQLHOST' => getenv('MYSQLHOST'),
    'MYSQLDATABASE' => getenv('MYSQLDATABASE'),
    'RAILWAY_PUBLIC_DOMAIN' => getenv('RAILWAY_PUBLIC_DOMAIN')
];

echo "<table><tr><th>Variable</th><th>Value</th><th>Status</th></tr>";
foreach ($vars as $var => $value) {
    $set = $value && $value !== 'NOT SET' ? 'pass' : 'fail';
    echo "<tr><td><code>$var</code></td><td>$value</td><td><span class='check $set' style='display:inline;padding:4px 8px;margin:0'>" . ($set === 'pass' ? '✓ SET' : '✗ NOT SET') . "</span></td></tr>";
}
echo "</table></div>";

// 2. Database Check
echo "<div class='box'><h2>2. Database & Employees Check</h2>";
try {
    // Check DB connection
    $pdo->query("SELECT 1")->execute();
    echo "<div class='check pass'>✅ Database connection successful</div>";

    // Count active employees
    $active_emp = $pdo->query("SELECT COUNT(*) FROM employees WHERE status='active'")->fetchColumn();
    $total_emp = $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();

    echo "<div class='check " . ($active_emp > 0 ? 'pass' : 'fail') . "'>";
    echo "<strong>Active Employees:</strong> $active_emp / $total_emp total";
    if ($active_emp == 0) {
        echo "<br><em style='color:#991b1b'>⚠️ You need at least 1 ACTIVE employee for password reset to work!</em>";
    }
    echo "</div>";

    // Show employees table
    echo "<h3>👥 Employee Records</h3>";
    $emps = $pdo->query("SELECT id, emp_id, name, email, status, role FROM employees ORDER BY id")->fetchAll();
    if (count($emps) > 0) {
        echo "<table><tr><th>ID</th><th>Emp ID</th><th>Name</th><th>Email</th><th>Status</th><th>Role</th></tr>";
        foreach ($emps as $e) {
            $status_class = $e['status'] === 'active' ? 'pass' : 'fail';
            echo "<tr>";
            echo "<td>{$e['id']}</td>";
            echo "<td><code>{$e['emp_id']}</code></td>";
            echo "<td>{$e['name']}</td>";
            echo "<td>{$e['email'] ?: '<em style=color:#9ca3af>NULL</em>'}</td>";
            echo "<td><span class='check $status_class' style='display:inline;padding:2px 8px;margin:0;border-radius:10px;font-size:0.8rem'>{$e['status']}</span></td>";
            echo "<td>{$e['role']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='check fail'>❌ No employees in database. Add employees first!</div>";
    }

} catch (Exception $e) {
    echo "<div class='check fail'>❌ Database Error: " . $e->getMessage() . "</div>";
}

echo "</div>";

// 3. Check password_reset_tokens table
echo "<div class='box'><h2>3. Password Reset Table Check</h2>";
try {
    $tables = $pdo->query("SHOW TABLES LIKE 'password_reset_tokens'")->fetchAll();
    if (count($tables) == 0) {
        echo "<div class='check fail'>❌ <strong>password_reset_tokens table does NOT exist!</strong></div>";
        echo "<p>You must run the setup script to create this table.</p>";
        echo "<p><a href='create_password_reset_table.php' class='btn' target='_blank'>▶️ Run Setup Script</a></p>";
    } else {
        echo "<div class='check pass'>✅ password_reset_tokens table exists</div>";

        // Check table structure
        $cols = $pdo->query("DESCRIBE password_reset_tokens")->fetchAll(PDO::FETCH_COLUMN);
        $required = ['id', 'emp_id', 'token', 'expires_at', 'used', 'created_at'];
        $missing = array_diff($required, $cols);

        if (empty($missing)) {
            echo "<div class='check pass'>✅ All required columns: " . implode(', ', $required) . "</div>";
        } else {
            echo "<div class='check fail'>❌ Missing columns: " . implode(', ', $missing) . "</div>";
        }

        // Show recent tokens (last 5)
        $tokens = $pdo->query("SELECT * FROM password_reset_tokens ORDER BY created_at DESC LIMIT 5")->fetchAll();
        if (count($tokens) > 0) {
            echo "<h4>Recent Reset Tokens (for debug)</h4><table><tr><th>ID</th><th>Emp ID</th><th>Token (first 10 chars)</th><th>Expires</th><th>Used</th><th>Created</th></tr>";
            foreach ($tokens as $t) {
                $token_short = substr($t['token'], 0, 10) . '...';
                $used = $t['used'] ? '✅ Used' : '⏳ Pending';
                echo "<tr>";
                echo "<td>{$t['id']}</td>";
                echo "<td>{$t['emp_id']}</td>";
                echo "<td><code>$token_short</code></td>";
                echo "<td>{$t['expires_at']}</td>";
                echo "<td>$used</td>";
                echo "<td>{$t['created_at']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<div class='check info'>ℹ️ No password reset tokens have been created yet (normal if no resets attempted)</div>";
        }
    }
} catch (Exception $e) {
    echo "<div class='check fail'>❌ Error: " . $e->getMessage() . "</div>";
}

echo "</div>";

// 4. Email Configuration Check
echo "<div class='box'><h2>4. Email Configuration Status</h2>";

$hasSMTP = getenv('SMTP_HOST') && getenv('SMTP_USER') && getenv('SMTP_PASS');
$hasSendGrid = getenv('SENDGRID_API_KEY');

if ($hasSMTP || $hasSendGrid) {
    echo "<div class='check pass'>✅ Email method configured</div>";
    if ($hasSMTP) {
        echo "<div class='check info'><strong>SMTP Settings:</strong><br>";
        echo "Host: " . getenv('SMTP_HOST') . "<br>";
        echo "Port: " . getenv('SMTP_PORT') . "<br>";
        echo "User: " . getenv('SMTP_USER') . "<br>";
        echo "Secure: " . getenv('SMTP_SECURE') . "</div>";
    }
    if ($hasSendGrid) {
        echo "<div class='check info'><strong>SendGrid:</strong> API Key configured</div>";
    }
} else {
    echo "<div class='check fail'>❌ No email method configured!</div>";
    echo "<p><strong>Fix:</strong> Set SMTP_* or SENDGRID_API_KEY in Railway Variables</p>";
}

// Validate Gmail App Password format
if ($hasSMTP && strpos(getenv('SMTP_USER'), '@gmail.com') !== false) {
    $smtp_pass = getenv('SMTP_PASS');
    if ($smtp_pass) {
        $len = strlen($smtp_pass);
        if ($len === 16) {
            echo "<div class='check pass'>✅ SMTP_PASS length is 16 characters (valid Gmail App Password format)</div>";
        } else {
            echo "<div class='check fail'>❌ SMTP_PASS length is $len characters (should be exactly 16 for Gmail App Password)</div>";
            echo "<p><strong>Fix:</strong> Generate a new Gmail App Password (16 characters)</p>";
        }
    }
}

echo "</div>";

// 5. Test Email Sending
echo "<div class='box'><h2>5. Send Test Email</h2>";
echo "<p>Enter your email address to test if the email system is working:</p>";
echo "<form method='POST'>
    <input type='email' name='test_email' placeholder='your-email@example.com' value='" . (getenv('SMTP_USER') ?: '') . "' required>
    <button type='submit' name='send_test' class='btn'>📧 Send Test Email</button>
</form>";

if ($_POST['send_test'] ?? false) {
    $test_email = filter_var($_POST['test_email'], FILTER_VALIDATE_EMAIL);
    if (!$test_email) {
        echo "<div class='check fail'>❌ Invalid email address</div>";
    } else {
        echo "<h3>📨 Sending test email to: $test_email</h3>";
        echo "<div class='log-output' id='log'>";

        // Replicate email sending with full debug
        $subject = "TicketDesk Railway Test - " . date('Y-m-d H:i:s');
        $htmlMessage = "<h1>✅ Test Email Successful!</h1><p>This confirms email is working on Railway.</p><p><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</p><p><strong>URL:</strong> " . SITE_URL . "</p>";
        $plainMessage = "Test email from TicketDesk Railway at " . date('Y-m-d H:i:s');

        error_log("🔔 [TEST] Starting email test to $test_email");

        // Try SMTP
        if ($hasSMTP) {
            echo "<div class='log-entry log-info'>🔍 Using SMTP: " . getenv('SMTP_HOST') . ":" . (getenv('SMTP_PORT') ?: 587) . "</div>";
            $result = sendEmailSMTP($test_email, 'TicketDesk Test', $subject, $htmlMessage, $plainMessage, [
                'host' => getenv('SMTP_HOST'),
                'port' => getenv('SMTP_PORT') ?: 587,
                'username' => getenv('SMTP_USER'),
                'password' => getenv('SMTP_PASS'),
                'secure' => getenv('SMTP_SECURE') ?: 'tls',
                'from_name' => SITE_NAME,
                'debug' => true
            ]);

            if ($result) {
                echo "<div class='log-entry log-success'>✅ SUCCESS! Email sent via SMTP</div>";
                echo "<div style='margin-top:10px;padding:10px;background:#10b981;color:white;border-radius:6px;font-weight:bold;'>🎉 EMAIL SENT SUCCESSFULLY! Check your inbox now.</div>";
            } else {
                echo "<div class='log-entry log-error'>❌ FAILED! SMTP returned false</div>";
                echo "<div style='margin-top:10px;padding:10px;background:#ef4444;color:white;border-radius:6px;'>❌ Email failed. Check Railway logs for SMTP errors.</div>";
            }
        }
        // Try SendGrid
        elseif ($hasSendGrid) {
            echo "<div class='log-entry log-info'>🔍 Using SendGrid API</div>";
            $result = sendEmailSendGrid($test_email, 'TicketDesk Test', $subject, $htmlMessage, $plainMessage, [
                'api_key' => getenv('SENDGRID_API_KEY'),
                'from_email' => getenv('SMTP_USER') ?: 'noreply@' . $_SERVER['SERVER_NAME'],
                'from_name' => SITE_NAME
            ]);

            if ($result) {
                echo "<div class='log-entry log-success'>✅ SUCCESS! Email sent via SendGrid</div>";
                echo "<div style='margin-top:10px;padding:10px;background:#10b981;color:white;border-radius:6px;font-weight:bold;'>🎉 EMAIL SENT SUCCESSFULLY! Check your inbox now.</div>";
            } else {
                echo "<div class='log-entry log-error'>❌ FAILED! SendGrid returned false</div>";
            }
        } else {
            echo "<div class='log-entry log-error'>❌ No email method configured!</div>";
        }

        echo "</div>";
    }
}

echo "</div>";

// 6. Action Steps
echo "<div class='box' style='background:#fff7ed;border:2px solid #f59e0b;'><h2 style='color:#92400e'>📋 Action Steps - Fix Password Reset</h2>";

echo "<h3>✅ Step 1: Verify/ Fix Gmail App Password</h3>";
echo "<ol>";
echo "<li><strong>Go to:</strong> <a href='https://myaccount.google.com/security' target='_blank'>Google Account Security</a></li>";
echo "<li><strong>Enable 2-Step Verification</strong> (if not already enabled)</li>";
echo "<li><strong>Go to:</strong> 'App passwords' section</li>";
echo "<li><strong>Generate new app password:</strong><br>";
echo "    - App: <strong>Mail</strong><br>";
echo "    - Device: <strong>Other (Custom) → \"TicketDesk\"</strong></li>";
echo "<li><strong>Copy the 16-character code</strong> (e.g., <code>abcdEFGHijklMNOP</code>)</li>";
echo "<li><strong>Update Railway Variable:</strong> <code>SMTP_PASS=your-16-digit-code</code></li>";
echo "<li><strong>Redeploy Railway</strong> (Dashboard → Deployments → New Deployment)</li>";
echo "</ol>";

echo "<h3>✅ Step 2: Ensure Email Matches</h3>";
echo "<p><strong>SMTP_USER</strong> must be the same Gmail address you generated the app password for.<br>";
echo "Current: <code>" . getenv('SMTP_USER') . "</code></p>";

echo "<h3>✅ Step 3: Create Password Reset Table</h3>";
echo "<p>If not already done, run: <a href='create_password_reset_table.php' class='btn btn-success' target='_blank'>▶️ Create Table</a></p>";

echo "<h3>✅ Step 4: Test Full Flow</h3>";
echo "<ol>";
echo "<li><a href='forgot_password.php' target='_blank'>Open Forgot Password</a></li>";
echo "<li>Enter email of an <strong>ACTIVE</strong> employee</li>";
echo "<li>Check <strong>Railway Logs</strong> for \"✅ Email sent\"</li>";
echo "<li>Check email inbox (and spam folder)</li>";
echo "<li>Click reset link and set new password</li>";
echo "</ol>";

echo "<h3>⚠️ If Still Not Working - Alternative: Use SendGrid (Free)</h3>";
echo "<p>If Gmail keeps failing, use SendGrid instead (more reliable on Railway):</p>";
echo "<ol>";
echo "<li>Sign up at <a href='https://sendgrid.com' target='_blank'>sendgrid.com</a> (free tier: 100 emails/day)</li>";
echo "<li>Create API Key with 'Mail Send' permission</li>";
echo "<li>Set Railway Variables:<br>";
echo "    <code>SENDGRID_API_KEY=SG.your-key-here</code><br>";
echo "    <em>(Remove SMTP_* variables or keep both - SendGrid takes priority)</em></li>";
echo "<li>Redeploy Railway</li>";
echo "</ol>";

echo "<h3>📊 Check Railway Logs</h3>";
echo "<p>1. Railway Dashboard → Your Project → <strong>Logs</strong><br>";
echo "2. Try forgot password<br>";
echo "3. Search logs for: <code>SMTP DEBUG</code>, <code>✅ Email sent</code>, <code>❌ SMTP error</code></p>";

echo "</div>";

// 7. Current Status Summary
echo "<div class='box'><h2>📊 Current Status Summary</h2>";
echo "<table>";
echo "<tr><td><strong>Platform</strong></td><td>" . ($isRailway ? '🚂 Railway' : '💻 Localhost') . "</td></tr>";
echo "<tr><td><strong>Database</strong></td><td>" . ($active_emp > 0 ? "✅ Connected ($active_emp active employees)" : "❌ No active employees") . "</td></tr>";
echo "<tr><td><strong>Password Reset Table</strong></td><td>" . (isset($tables) && count($tables) > 0 ? "✅ Exists" : "❌ Missing") . "</td></tr>";
echo "<tr><td><strong>Email Method</strong></td><td>" . ($hasSMTP ? "SMTP (Gmail)" : ($hasSendGrid ? "SendGrid" : "❌ Not configured")) . "</td></td>";
echo "<tr><td><strong>Gmail App Password</strong></td><td>" . ($hasSMTP && strlen(getenv('SMTP_PASS')) === 16 ? "✅ Format OK (16 chars)" : "❌ Check SMTP_PASS") . "</td></tr>";
echo "<tr><td><strong>EMAIL_DEBUG</strong></td><td>" . (getenv('EMAIL_DEBUG') === 'true' ? "✅ Enabled" : "⚠️ Disabled (set to true)") . "</td></tr>";
echo "</table>";

// Overall status
$all_good = $active_emp > 0 && isset($tables) && count($tables) > 0 && ($hasSMTP || $hasSendGrid) && strlen(getenv('SMTP_PASS')) === 16;
echo "<div style='margin-top:20px;padding:15px;background:" . ($all_good ? '#d1fae5;color:#065f46' : '#fee2e2;color:#991b1b') . ";border-radius:8px;font-weight:bold;font-size:1.1rem;'>";
echo $all_good ? "✅ All systems ready! Test password reset now." : "❌ Fix the issues above, then test.";
echo "</div>";

echo "</div>";

echo "<div class='box info'>
    <h3>📞 Need Help?</h3>
    <p>If email still doesn't send after fixing the app password:</p>
    <ol>
        <li><strong>Check Railway Logs</strong> - This is the most important step!</li>
        <li>Look for specific error messages (authentication, connection timeout, etc.)</li>
        <li>Try port <strong>465</strong> with <strong>ssl</strong> instead of 587/tls</li>
        <li>Consider using SendGrid (more reliable on Railway)</li>
    </ol>
    <p><strong>Share your Railway logs</strong> if you need more help!</p>
</div>";

echo "</body></html>";
?>
