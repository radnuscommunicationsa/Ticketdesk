<?php
/**
 * Simple Email Test - Shows ALL errors on screen
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

require_once __DIR__ . '/includes/config.php';

echo "<h1>🧪 Email System Test</h1>";

// Show environment
echo "<h2>Environment Variables</h2>";
echo "<pre>";
echo "SMTP_HOST: " . (getenv('SMTP_HOST') ?: 'NOT SET') . "\n";
echo "SMTP_PORT: " . (getenv('SMTP_PORT') ?: 'NOT SET') . "\n";
echo "SMTP_USER: " . (getenv('SMTP_USER') ?: 'NOT SET') . "\n";
echo "SMTP_PASS: " . (getenv('SMTP_PASS') ? '***SET***' : 'NOT SET') . "\n";
echo "SMTP_SECURE: " . (getenv('SMTP_SECURE') ?: 'NOT SET') . "\n";
echo "SENDGRID_API_KEY: " . (getenv('SENDGRID_API_KEY') ? '***SET***' : 'NOT SET') . "\n";
echo "EMAIL_DEBUG: " . (getenv('EMAIL_DEBUG') ?: 'false (default)') . "\n";
echo "SITE_URL: " . SITE_URL . "\n";
echo "</pre>";

// Test database connection
echo "<h2>Database Test</h2>";
try {
    $test = $pdo->query("SELECT COUNT(*) as total FROM employees");
    $count = $test->fetch();
    echo "<p style='color:green;'>✅ Database connected. Employees: " . $count['total'] . "</p>";

    // Check if your test employee exists
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE email = ? OR emp_id = ?");
    $stmt->execute(['radnuscommunicationsa@gmail.com', 'radnuscommunicationsa@gmail.com']);
    $emp = $stmt->fetch();

    if ($emp) {
        echo "<p style='color:green;'>✅ Employee found: ID={$emp['id']}, Name={$emp['name']}, Status={$emp['status']}</p>";
    } else {
        echo "<p style='color:red;'>❌ Employee NOT FOUND with email/ID: radnuscommunicationsa@gmail.com</p>";
        echo "<p>Make sure you have an employee account in the database with this email.</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color:red;'>❌ Database error: " . $e->getMessage() . "</p>";
}

// Test email sending
if (isset($_POST['test_email'])) {
    $testEmail = filter_var($_POST['test_email'], FILTER_VALIDATE_EMAIL);
    if ($testEmail) {
        echo "<h2>📧 Sending Test Email</h2>";
        echo "<pre>";

        // Generate a dummy token
        $dummyToken = bin2hex(random_bytes(16));

        echo "Attempting to send email to: $testEmail\n";
        echo "Using function: sendPasswordResetEmail()\n\n";

        // Call the actual function
        $result = sendPasswordResetEmail($testEmail, 'Test User', $dummyToken);

        echo "Result: " . ($result ? 'TRUE (success)' : 'FALSE (failed)') . "\n";

        // Check error log
        $lastError = error_get_last();
        if ($lastError && strpos($lastError['message'], 'Email') !== false) {
            echo "Last error: " . $lastError['message'] . "\n";
        }

        echo "\n";
        echo "Check your inbox (and spam folder) for the email.\n";
        echo "If you don't receive it, check Railway logs for detailed errors.\n";

        echo "</pre>";
    } else {
        echo "<p style='color:red;'>Invalid email</p>";
    }
}
?>

<h2>📤 Send Test Email</h2>
<form method="POST">
    <input type="email" name="test_email" value="radnuscommunicationsa@gmail.com" placeholder="Email address" style="padding:8px;width:300px;">
    <button type="submit" style="padding:8px 16px;background:#5552DD;color:white;border:none;border-radius:4px;cursor:pointer;">Send Test</button>
</form>

<h2>🔍 Next Steps</h2>
<ol>
    <li>Click "Send Test" above</li>
    <li>Check if email arrives in your inbox</li>
    <li>If <strong style='color:red;'>FAILED</strong>, check Railway logs</li>
    <li>If successful, test the actual forgot password page</li>
</ol>
