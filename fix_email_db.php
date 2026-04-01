<?php
// IMMEDIATE FIX: Make email column nullable
require_once __DIR__ . '/includes/config.php';

header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Fix</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: auto; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; }
        button { background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin: 5px; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <h1>🚨 TicketDesk - Database Fix Tool</h1>
    <div class="info">
        <strong>Issue:</strong> employees.email column is NOT NULL, but your code treats email as optional.<br>
        <strong>Fix:</strong> Make email column nullable so blank emails are allowed.
    </div>

<?php
if (isset($_POST['confirm'])) {
    echo "<h2>Attempting Fix...</h2>";
    try {
        $pdo->exec("ALTER TABLE employees MODIFY email VARCHAR(150) NULL UNIQUE");
        echo "<div class='success'>
            <h3>✅ SUCCESS!</h3>
            <p>The employees.email column is now <strong>NULLABLE</strong>.</p>
            <p>You can now add employees with blank emails.</p>
            <p><a href='admin/employees.php'>← Go back to Employees page</a></p>
        </div>";
    } catch (Exception $e) {
        echo "<div class='error'>
            <h3>❌ FAILED</h3>
            <p>Error: " . htmlspecialchars($e->getMessage()) . "</p>
            <p>Try running this SQL manually in phpMyAdmin:</p>
            <pre>ALTER TABLE employees MODIFY email VARCHAR(150) NULL UNIQUE;</pre>
        </div>";
    }
    exit;
}

// Show current structure
try {
    $stmt = $pdo->query("DESCRIBE employees");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h2>Current employees Table Structure:</h2><ul>";
    foreach ($columns as $col) {
        $highlight = ($col['Field'] == 'email') ? 'style="background:#fff3cd;padding:5px;border-radius:3px;"' : '';
        echo "<li $highlight><strong>{$col['Field']}</strong>: {$col['Type']} | Null: {$col['Null']} | Key: {$col['Key']}</li>";
    }
    echo "</ul>";

    $emailCol = null;
    foreach ($columns as $col) {
        if ($col['Field'] == 'email') {
            $emailCol = $col;
            break;
        }
    }

    if ($emailCol && $emailCol['Null'] === 'NO') {
        echo "<div class='error'>
            <h3>🔴 PROBLEM DETECTED</h3>
            <p>The <strong>email</strong> column is <strong>NOT NULL</strong>. This prevents blank emails.</p>
            <p>Your code allows blank emails (optional field), but the database rejects them.</p>
        </div>";
        ?>
        <form method="POST" onsubmit="return confirm('This will modify the database. Continue?')">
            <button type="submit" name="confirm" value="1">✅ APPLY FIX NOW</button>
        </form>
        <div class="info">
            <h3>What this fix does:</h3>
            <pre>ALTER TABLE employees MODIFY email VARCHAR(150) NULL UNIQUE;</pre>
            <ul>
                <li><strong>NULL</strong> - Allows blank/empty emails</li>
                <li><strong>UNIQUE</strong> - Still prevents duplicate emails (but only for non-blank values)</li>
            </ul>
        </div>
        <?php
    } else {
        echo "<div class='success'>
            <h3>✅ DATABASE IS CORRECT</h3>
            <p>The email column is already nullable. The error might be something else.</p>
            <p><a href='admin/employees.php'>← Test adding an employee</a></p>
        </div>";
    }

} catch (Exception $e) {
    echo "<div class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>
</body>
</html>
