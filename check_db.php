<?php
require_once __DIR__ . '/includes/config.php';
?>
<!DOCTYPE html>
<html>
<head><title>DB Check</title></head>
<body style="font-family:monospace; padding:20px;">
<h2>Database Structure Check</h2>
<?php
try {
    // Check tables
    echo "<h3>1. Check if asset_assignments table exists</h3>";
    $result = $pdo->query("SHOW TABLES LIKE 'asset_assignments'");
    $tables = $result->fetchAll();
    echo count($tables) > 0 ? "✅ EXISTS" : "❌ MISSING";

    if (count($tables) > 0) {
        echo "<h3>2. Structure of asset_assignments</h3>";
        $stmt = $pdo->query('DESCRIBE asset_assignments');
        echo "<table border=1 cellpadding=5><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($col = $stmt->fetch()) {
            echo "<tr>
                <td>{$col['Field']}</td>
                <td>{$col['Type']}</td>
                <td>{$col['Null']}</td>
                <td>{$col['Key']}</td>
                <td>{$col['Default']}</td>
            </tr>";
        }
        echo "</table>";
    }

    echo "<h3>3. Compare with employees table</h3>";
    $stmt = $pdo->query('DESCRIBE employees');
    echo "<strong>employees.id</strong> type: ";
    while ($col = $stmt->fetch()) {
        if ($col['Field'] == 'id') echo $col['Type'];
    }
    echo "<br><strong>employees.emp_id</strong> type: ";
    $stmt->execute();
    while ($col = $stmt->fetch()) {
        if ($col['Field'] == 'emp_id') echo $col['Type'];
    }

    echo "<h3>4. Sample assignments</h3>";
    $stmt = $pdo->query("SELECT * FROM asset_assignments LIMIT 3");
    $rows = $stmt->fetchAll();
    if (count($rows) == 0) {
        echo "No assignments in database.";
    } else {
        echo "<pre>" . print_r($rows, true) . "</pre>";
    }

    echo "<h3>5. Your session</h3>";
    echo "user_id: " . $_SESSION['user_id'] . " (type: " . gettype($_SESSION['user_id']) . ")<br>";
    echo "emp_id: " . $_SESSION['emp_id'] . " (type: " . gettype($_SESSION['emp_id']) . ")";

    echo "<h3>6. Test query with user_id</h3>";
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM asset_assignments WHERE emp_id = ? AND returned_at IS NULL");
    $stmt->execute([$_SESSION['user_id']]);
    $r = $stmt->fetch();
    echo "Count using user_id: {$r['cnt']}";

    $stmt->execute([$_SESSION['emp_id']]);
    $r = $stmt->fetch();
    echo "<br>Count using emp_id: {$r['cnt']}";

} catch (Exception $e) {
    echo "<p style='color:red'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
</body></html>
