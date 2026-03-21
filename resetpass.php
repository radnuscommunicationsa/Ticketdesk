<?php
require_once __DIR__ . '/includes/config.php';

// All employees password reset to 'password123'
$newpass = password_hash('password123', PASSWORD_BCRYPT);

$pdo->exec("UPDATE employees SET password = '$newpass'");

echo "Done! All passwords reset to: password123";
?>