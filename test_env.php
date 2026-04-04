<?php
// Test if Apache environment variables are working
echo "<h2>Environment Variable Test</h2>";
echo "<pre>";
echo "SMTP_HOST: " . getenv('SMTP_HOST') . "\n";
echo "SMTP_PORT: " . getenv('SMTP_PORT') . "\n";
echo "SMTP_USER: " . getenv('SMTP_USER') . "\n";
echo "SMTP_PASS: " . (getenv('SMTP_PASS') ? 'SET (hidden)' : 'NOT SET') . "\n";
echo "SMTP_SECURE: " . getenv('SMTP_SECURE') . "\n";
echo "</pre>";

// Test database connection
require_once 'includes/config.php';
echo "<h2>Database Connection</h2>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM employees WHERE status='active'");
    $row = $stmt->fetch();
    echo "✅ Connected. Active employees: " . $row['cnt'];
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage();
}

// Show recent tokens
echo "<h2>Recent Password Reset Tokens</h2>";
try {
    $stmt = $pdo->query("
        SELECT prt.token, prt.created_at, prt.expires_at, prt.used,
               e.name, e.email
        FROM password_reset_tokens prt
        JOIN employees e ON prt.emp_id = e.id
        ORDER BY prt.created_at DESC
        LIMIT 3
    ");
    $tokens = $stmt->fetchAll();
    echo "<pre>";
    print_r($tokens);
    echo "</pre>";
} catch (Exception $e) {
    echo "No tokens or error: " . $e->getMessage();
}
?>
