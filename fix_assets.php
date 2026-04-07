<?php
require_once __DIR__ . '/includes/config.php';

echo "<h1>Asset Assignment Fix Tool</h1>";

try {
    // 1. Check if asset_assignments exists
    $tables = $pdo->query("SHOW TABLES LIKE 'asset_assignments'")->fetchAll();
    if (count($tables) == 0) {
        echo "<p style='color:red'>❌ asset_assignments table does NOT exist!</p>";
        echo "<p>You need to create the asset tables first.</p>";
        exit;
    }

    echo "<p>✅ asset_assignments table exists</p>";

    // 2. Check column types
    $stmt = $pdo->query("DESCRIBE asset_assignments");
    $cols = $stmt->fetchAll();
    $emp_id_type = '';
    $asset_id_type = '';
    foreach ($cols as $col) {
        if ($col['Field'] == 'emp_id') $emp_id_type = $col['Type'];
        if ($col['Field'] == 'asset_id') $asset_id_type = $col['Type'];
    }

    echo "<p>asset_assignments.emp_id type: <strong>$emp_id_type</strong></p>";
    echo "<p>asset_assignments.asset_id type: <strong>$asset_id_type</strong></p>";

    // 3. Check employees table
    $stmt = $pdo->query("DESCRIBE employees");
    $cols = $stmt->fetchAll();
    $employees_id_type = '';
    $employees_emp_id_type = '';
    foreach ($cols as $col) {
        if ($col['Field'] == 'id') $employees_id_type = $col['Type'];
        if ($col['Field'] == 'emp_id') $employees_emp_id_type = $col['Type'];
    }

    echo "<p>employees.id type: <strong>$employees_id_type</strong></p>";
    echo "<p>employees.emp_id type: <strong>$employees_emp_id_type</strong></p>";

    // 4. Check for foreign keys
    $stmt = $pdo->query("
        SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'asset_assignments' AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $fks = $stmt->fetchAll();

    echo "<h3>Foreign Keys on asset_assignments:</h3>";
    if (count($fks) == 0) {
        echo "<p style='color:orange'>⚠️ No foreign keys found</p>";
    } else {
        foreach ($fks as $fk) {
            echo "<p>✅ {$fk['COLUMN_NAME']} → {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}</p>";
        }
    }

    // 5. Show sample data
    echo "<h3>Sample Assignments (first 5):</h3>";
    $stmt = $pdo->query("
        SELECT aa.id, aa.asset_id, aa.emp_id, a.asset_code, e.emp_id as emp_code, e.name
        FROM asset_assignments aa
        LEFT JOIN assets a ON aa.asset_id = a.id
        LEFT JOIN employees e ON aa.emp_id = e.id
        LIMIT 5
    ");
    $rows = $stmt->fetchAll();

    if (count($rows) == 0) {
        echo "<p>No assignments found.</p>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Assignment ID</th><th>Asset ID (type)</th><th>emp_id stored</th><th>Matches employee.id?</th><th>Employee</th></tr>";
        foreach ($rows as $row) {
            $matches = '';
            // Check if this emp_id matches an employee
            $check = $pdo->prepare("SELECT id FROM employees WHERE id = ? OR emp_id = ?");
            $check->execute([$row['emp_id'], $row['emp_id']]);
            $match = $check->fetch();
            $matches = $match ? '✅ Yes' : '❌ No';
            echo "<tr>
                <td>{$row['id']}</td>
                <td>{$row['asset_id']} (type: " . gettype($row['asset_id']) . ")</td>
                <td><strong>{$row['emp_id']}</strong> (type: " . gettype($row['emp_id']) . ")</td>
                <td>$matches</td>
                <td>{$row['name']} (code: {$row['emp_code']})</td>
            </tr>";
        }
        echo "</table>";
    }

    // 6. Suggest fix
    echo "<h2>Analysis & Fix</h2>";

    $needs_fix = false;

    if (strpos($emp_id_type, 'int') === false) {
        echo "<p style='color:red'>❌ PROBLEM: asset_assignments.emp_id is $emp_id_type, but should be INT to match employees.id ($employees_id_type)</p>";
        $needs_fix = true;
    }

    if (count($fks) == 0) {
        echo "<p style='color:orange'>⚠️ PROBLEM: No foreign key constraint on emp_id</p>";
        $needs_fix = true;
    }

    if (!$needs_fix) {
        echo "<p style='color:green'>✅ Schema looks correct. The issue might be data-related.</p>";

        // Show what user_id values we're querying
        echo "<h3>Current Session:</h3>";
        echo "<p>user_id = {$_SESSION['user_id']} (type: " . gettype($_SESSION['user_id']) . ")</p>";
        echo "<p>emp_id = {$_SESSION['emp_id']} (type: " . gettype($_SESSION['emp_id']) . ")</p>";

        // Test counts
        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM asset_assignments WHERE emp_id = ? AND returned_at IS NULL");
        $stmt->execute([$_SESSION['user_id']]);
        $r1 = $stmt->fetch();
        $stmt->execute([$_SESSION['emp_id']]);
        $r2 = $stmt->fetch();

        echo "<p>Count with user_id ({$_SESSION['user_id']}): <strong>{$r1['cnt']}</strong></p>";
        echo "<p>Count with emp_id ('{$_SESSION['emp_id']}'): <strong>{$r2['cnt']}</strong></p>";

        if ($r1['cnt'] == 0 && $r2['cnt'] == 0) {
            echo "<p style='color:red'>⚠️ No assignments found for current user. Check if any assignment was actually made for this employee.</p>";
        } elseif ($r1['cnt'] > 0) {
            echo "<p style='color:green'>✅ user_id query works! The employee page should show assets.</p>";
        } elseif ($r2['cnt'] > 0) {
            echo "<p style='color:orange'>⚠️ Only emp_id string matches. Code should use \$_SESSION['emp_id'] instead of user_id.</p>";
        }
    } else {
        echo "<h3>Apply Fix Automatically?</h3>";
        echo "<p><strong>Warning:</strong> This will modify your database structure. Make a backup first!</p>";
        echo '<form method="POST">
            <button type="submit" name="fix" style="background:#EF4444;color:white;padding:10px 20px;border:none;border-radius:6px;cursor:pointer;">
                Fix Schema: Convert emp_id to INT and add FK
            </button>
            ' . csrf_input() . '
        </form>';

        if (isset($_POST['fix'])) {
            verify_csrf();

            echo "<h3>Applying Fix...</h3>";

            // Backup data first
            echo "<p>1. Backing up current assignments...</p>";
            $pdo->exec("CREATE TEMPORARY TABLE asset_assignments_backup AS SELECT * FROM asset_assignments");

            // Try to convert emp_id to INT
            try {
                echo "<p>2. Converting emp_id to INT (this may fail if non-numeric data exists)...</p>";
                $pdo->exec("ALTER TABLE asset_assignments MODIFY COLUMN emp_id INT NOT NULL");
                echo "<p style='color:green'>✅ emp_id converted to INT</p>";
            } catch (Exception $e) {
                echo "<p style='color:red'>❌ Failed to convert: " . htmlspecialchars($e->getMessage()) . "</p>";
                echo "<p>Try cleaning non-numeric values first:</p>";
                echo "<pre>UPDATE asset_assignments SET emp_id = CAST(emp_id AS UNSIGNED) WHERE emp_id REGEXP '^[0-9]+$';</pre>";
            }

            // Add foreign key
            try {
                echo "<p>3. Adding foreign key constraint...</p>";
                $pdo->exec("
                    ALTER TABLE asset_assignments
                    ADD CONSTRAINT fk_asset_assignments_employee
                    FOREIGN KEY (emp_id) REFERENCES employees(id) ON DELETE CASCADE
                ");
                echo "<p style='color:green'>✅ Foreign key added</p>";
            } catch (Exception $e) {
                echo "<p style='color:orange'>⚠️ Could not add FK (may fail if data doesn't match): " . htmlspecialchars($e->getMessage()) . "</p>";
            }

            echo "<p style='color:green;font-weight:bold'>✅ Fix complete! Test the employee assets page now.</p>";
        }
    }

} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
