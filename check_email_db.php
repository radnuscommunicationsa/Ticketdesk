<?php
// Script to check and fix employees table email column
require_once __DIR__ . '/includes/config.php';

echo "<h2>Database Check for employees.email</h2>";

try {
    // Check current structure
    $stmt = $pdo->query("DESCRIBE employees");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h3>Current Table Structure:</h3><ul>";
    foreach ($columns as $col) {
        echo "<li><strong>{$col['Field']}</strong>: {$col['Type']} - {$col['Null']} - {$col['Key']}</li>";
        if ($col['Field'] == 'email') {
            $emailCol = $col;
        }
    }
    echo "</ul>";

    if (isset($emailCol)) {
        echo "<h3>Email Column Analysis:</h3>";
        echo "<p>Current: {$emailCol['Type']}, Nullable: {$emailCol['Null']}</p>";

        if ($emailCol['Null'] === 'NO') {
            echo "<p style='color:red;font-weight:bold'>⚠️ ISSUE FOUND: Email is NOT NULL but your code treats it as optional!</p>";
            echo "<p>To fix, run this SQL command:</p>";
            echo "<pre>ALTER TABLE employees MODIFY email VARCHAR(150) NULL UNIQUE;</pre>";
            echo "<p><strong>WHY?</strong>: Your code allows empty emails (sets to NULL), but the database rejects NULL values. This causes the confusing 'email already exists' error.</p>";
        } else {
            echo "<p style='color:green'>✅ Email column is nullable. This matches your optional email design.</p>";
        }
    }

    // Test duplicate email check
    echo "<h3>Test Duplicate Email Check:</h3>";
    $testEmail = 'test@example.com';
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE email = ? AND email IS NOT NULL");
    $stmt->execute([$testEmail]);
    $count = $stmt->fetchColumn();
    echo "<p>Email '$testEmail' exists in database: $count times</p>";

    // Check if there are any NULL emails
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE email IS NULL");
    $stmt->execute([]);
    $nullCount = $stmt->fetchColumn();
    echo "<p>Employees with no email (NULL): $nullCount</p>";

} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}

echo "<hr><p><a href='admin/employees.php'>Back to Employees Page</a></p>";
?>