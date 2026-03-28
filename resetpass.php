<?php
require_once __DIR__ . '/includes/config.php';

$newPassword = password_hash('password', PASSWORD_DEFAULT);

$stmt = $pdo->prepare("UPDATE employees SET password = ?, status = 'active', role = 'admin' WHERE id = 1");
$stmt->execute([$newPassword]);

echo "Done! Rows updated: " . $stmt->rowCount();
echo "<br>New hash: " . $newPassword;
?>