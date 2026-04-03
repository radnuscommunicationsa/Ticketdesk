<?php
/**
 * Script to create missing password_reset_tokens table
 * Safe to run multiple times - uses CREATE TABLE IF NOT EXISTS
 */

require_once __DIR__ . '/includes/config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Setup</title>
    <style>
        body { font-family: sans-serif; padding: 2rem; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-top: 0; }
        .success { color: #10b981; font-weight: bold; padding: 1rem; background: #f0fdf4; border-radius: 4px; margin: 1rem 0; }
        .error { color: #ef4444; font-weight: bold; padding: 1rem; background: #fef2f2; border-radius: 4px; margin: 1rem 0; }
        .warning { color: #f59e0b; font-weight: bold; padding: 1rem; background: #fffbeb; border-radius: 4px; margin: 1rem 0; }
        .info { color: #3b82f6; padding: 0.5rem 0; }
        ul { line-height: 1.8; }
        a { color: #5552DD; text-decoration: none; }
        a:hover { text-decoration: underline; }
        pre { background: #f4f4f4; padding: 1rem; border-radius: 4px; overflow-x: auto; font-size: 0.9rem; }
    </style>
</head>
<body>
<div class='container'>";

try {
    echo "<h1>🔧 Password Reset Database Setup</h1>";
    echo "<p>Checking and creating required tables...</p>";

    // Check employees table first (required for foreign key)
    echo "<h3>1. Checking employees table...</h3>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'employees'");
    if ($stmt->rowCount() == 0) {
        throw new Exception("❌ employees table does not exist. Please run database.sql first to create the base tables.");
    }
    echo "<p class='success'>✅ employees table exists</p>";

    // Check employees data
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM employees WHERE status='active'");
    $count = $stmt->fetchColumn();
    echo "<p class='info'>👥 Active employees in database: <strong>$count</strong></p>";

    if ($count == 0) {
        echo "<p class='warning'>⚠️  No active employees found! Password reset requires at least one active employee.</p>";
        echo "<p>Add an employee first via admin interface or directly in phpMyAdmin.</p>";
    }

    // Create password_reset_tokens table
    echo "<h3>2. Creating/Verifying password_reset_tokens table...</h3>";

    $sql = "
        CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            emp_id INT NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            used TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (emp_id) REFERENCES employees(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE INDEX IF NOT EXISTS idx_password_reset_token ON password_reset_tokens(token);
        CREATE INDEX IF NOT EXISTS idx_password_reset_emp_id ON password_reset_tokens(emp_id);
        CREATE INDEX IF NOT EXISTS idx_password_reset_expires ON password_reset_tokens(expires_at);
    ";

    $pdo->exec($sql);
    echo "<p class='success'>✅ password_reset_tokens table ready</p>";

    // Verify table structure
    echo "<h3>3. Verifying table structure...</h3>";
    $stmt = $pdo->query("DESCRIBE password_reset_tokens");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $expected = ['id', 'emp_id', 'token', 'expires_at', 'used', 'created_at'];
    $missing = array_diff($expected, $columns);

    if (empty($missing)) {
        echo "<p class='success'>✅ All required columns present: " . implode(', ', $expected) . "</p>";
    } else {
        echo "<p class='error'>❌ Missing columns: " . implode(', ', $missing) . "</p>";
        throw new Exception("Table structure incomplete. Please check database.sql");
    }

    // Verify indexes
    echo "<h3>4. Verifying indexes...</h3>";
    $stmt = $pdo->query("SHOW INDEX FROM password_reset_tokens");
    $indexes = $stmt->fetchAll(PDO::FETCH_COLUMN, 'Key_name');
    $required = ['PRIMARY', 'idx_password_reset_token', 'idx_password_reset_emp_id', 'idx_password_reset_expires'];
    $missing_idx = array_diff($required, $indexes);

    if (empty($missing_idx)) {
        echo "<p class='success'>✅ All required indexes present</p>";
    } else {
        echo "<p class='warning'>⚠️  Missing indexes: " . implode(', ', $missing_idx) . "</p>";
        echo "<p>Performance may be affected. Consider running database.sql manually to add indexes.</p>";
    }

    // Final message
    echo "<h2>🎉 Setup Complete!</h2>";
    echo "<div class='success'>";
    echo "<p><strong>The password reset feature is ready to use.</strong></p>";
    echo "<p>Active employees: <strong>$count</strong></p>";
    echo "<p>Please ensure email is configured:</p>";
    echo "<ul>";
    echo "<li><strong>Localhost:</strong> Configure <code>C:\xampp\sendmail\sendmail.ini</code> with SMTP credentials</li>";
    echo "<li><strong>Railway:</strong> Set environment variables (see RAILWAY_EMAIL_SETUP.md)</li>";
    echo "</ul>";
    echo "</div>";

    echo "<h3>Next Steps</h3>";
    echo "<ol>";
    echo "<li><a href='test_email_config.php'>📧 Test Email Configuration</a> - Verify emails can be sent</li>";
    echo "<li><a href='forgot_password.php'>🔗 Test Forgot Password Form</a> - Try the full flow</li>";
    echo "<li><a href='diagnose_password_reset.php'>🔍 Run Diagnostic</a> - Check all components</li>";
    echo "</ol>";

} catch (Exception $e) {
    echo "<h2>❌ Setup Error</h2>";
    echo "<div class='error'>" . $e->getMessage() . "</div>";
    echo "<p>Please fix the issue and reload this page.</p>";
    error_log('Database setup failed: ' . $e->getMessage());
}

echo "</div></body></html>";
?>