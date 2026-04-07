<?php
// Diagnostic script to check asset assignment issue
require_once __DIR__ . '/includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    die("Not logged in");
}

echo "<h2>Asset Assignment Diagnostics</h2>";
echo "<p>Current User ID (session user_id): <strong>" . $_SESSION['user_id'] . "</strong></p>";
echo "<p>Current Employee Code (session emp_id): <strong>" . $_SESSION['emp_id'] . "</strong></p>";
echo "<p>Current User Name: <strong>" . $_SESSION['name'] . "</strong></p>";

// Check asset_assignments table structure
try {
    echo "<h3>1. Table Structure: asset_assignments</h3>";
    $stmt = $pdo->query("DESCRIBE asset_assignments");
    $columns = $stmt->fetchAll();
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Check assets table relevant columns
    echo "<h3>2. Table Structure: assets (relevant columns)</h3>";
    $stmt = $pdo->query("DESCRIBE assets");
    $columns = $stmt->fetchAll();
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th></tr>";
    foreach ($columns as $col) {
        echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td></tr>";
    }
    echo "</table>";

    // Check employees table relevant columns
    echo "<h3>3. Table Structure: employees (relevant columns)</h3>";
    $stmt = $pdo->query("DESCRIBE employees");
    $columns = $stmt->fetchAll();
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th></tr>";
    foreach ($columns as $col) {
        echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td></tr>";
    }
    echo "</table>";

    // Show sample assignments
    echo "<h3>4. Recent Asset Assignments (all)</h3>";
    $stmt = $pdo->query("
        SELECT aa.*, a.asset_code, a.name as asset_name, e.emp_id as employee_code, e.name as employee_name
        FROM asset_assignments aa
        LEFT JOIN assets a ON aa.asset_id = a.id
        LEFT JOIN employees e ON aa.emp_id = e.id
        ORDER BY aa.assigned_at DESC
        LIMIT 10
    ");
    $assignments = $stmt->fetchAll();
    if (empty($assignments)) {
        echo "<p>No assignments found.</p>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Asset</th><th>emp_id stored</th><th>Employee Name</th><th>Assigned At</th><th>Returned At</th></tr>";
        foreach ($assignments as $a) {
            echo "<tr>";
            echo "<td>{$a['id']}</td>";
            echo "<td>{$a['asset_code']} - {$a['asset_name']}</td>";
            echo "<td><strong>{$a['emp_id']}</strong> (type: " . gettype($a['emp_id']) . ")</td>";
            echo "<td>{$a['employee_name']} (code: {$a['employee_code']})</td>";
            echo "<td>{$a['assigned_at']}</td>";
            echo "<td>{$a['returned_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // Show assignments for current employee using different methods
    echo "<h3>5. Current Employee's Assignments (using \$_SESSION['user_id'] = {$_SESSION['user_id']})</h3>";
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as cnt FROM asset_assignments
        WHERE emp_id = ? AND returned_at IS NULL
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $count = $stmt->fetch();
    echo "<p>Count with user_id ({$_SESSION['user_id']}): <strong>{$count['cnt']}</strong></p>";

    echo "<h3>6. Current Employee's Assignments (using \$_SESSION['emp_id'] = '{$_SESSION['emp_id']}')</h3>";
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as cnt FROM asset_assignments
        WHERE emp_id = ? AND returned_at IS NULL
    ");
    $stmt->execute([$_SESSION['emp_id']]);
    $count = $stmt->fetch();
    echo "<p>Count with emp_id ('{$_SESSION['emp_id']}'): <strong>{$count['cnt']}</strong></p>";

    // Show direct join query that employee pages use
    echo "<h3>7. Test employee query (same as assets.php)</h3>";
    $stmt = $pdo->prepare("
        SELECT a.*, aa.assigned_at, aa.notes as assignment_notes, aa.returned_at
        FROM assets a
        JOIN asset_assignments aa ON a.id = aa.asset_id
        WHERE aa.emp_id = ? AND aa.returned_at IS NULL
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $results = $stmt->fetchAll();
    echo "<p>Query returned <strong>" . count($results) . "</strong> results using user_id</p>";

    // Try with emp_id string
    $stmt->execute([$_SESSION['emp_id']]);
    $results2 = $stmt->fetchAll();
    echo "<p>Query returned <strong>" . count($results2) . "</strong> results using emp_id string</p>";

    // Check if there's mismatch
    echo "<h3>8. Investigation: Compare employee IDs</h3>";
    $stmt = $pdo->prepare("SELECT id, emp_id FROM employees WHERE id = ? OR emp_id = ? OR id = ? OR emp_id = ?");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['emp_id'], $_SESSION['emp_id']]);
    $empRows = $stmt->fetchAll();
    echo "<p>Employees matching session IDs:</p><ul>";
    foreach ($empRows as $e) {
        echo "<li>ID: {$e['id']} (type: " . gettype($e['id']) . "), emp_id: {$e['emp_id']}</li>";
    }
    echo "</ul>";

} catch (PDOException $e) {
    echo "<p style='color:red'>Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
