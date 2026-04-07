<?php
/**
 * Asset Assignment Visibility Fix
 *
 * This script will:
 * 1. Check current database schema
 * 2. Convert asset_assignments.emp_id from VARCHAR to INT if needed
 * 3. Add foreign key constraint
 * 4. Verify data consistency
 */

require_once __DIR__ . '/includes/config.php';

echo "<!DOCTYPE html><html><head><title>Asset Fix Tool</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#1a1a2e;color:#e0e0e0} .success{color:#10B981} .error{color:#EF4444} .warning{color:#F59E0B} table{border-collapse:collapse;margin:10px 0} th,td{border:1px solid #333;padding:8px} th{background:#2a2a4e}</style></head><body>";
echo "<h1>🔧 Asset Assignment Fix Tool</h1>";

// Check if user is admin
if (!isAdmin()) {
    echo "<p class='error'>❌ Access denied. Admin privileges required.</p>";
    exit;
}

try {
    // 1. Show current schema
    echo "<h2>1. Current Database Schema</h2>";

    // asset_assignments
    echo "<h3>asset_assignments table:</h3>";
    $stmt = $pdo->query("DESCRIBE asset_assignments");
    $cols = $stmt->fetchAll();
    echo "<table><tr><th>Field</th><th>Type</th><th>Key</th></tr>";
    foreach ($cols as $col) {
        $key = $col['Key'] ?: '';
        echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>$key</td></tr>";
    }
    echo "</table>";

    // employees
    echo "<h3>employees table (reference):</h3>";
    $stmt = $pdo->query("DESCRIBE employees");
    $cols = $stmt->fetchAll();
    echo "<table><tr><th>Field</th><th>Type</th></tr>";
    foreach ($cols as $col) {
        echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td></tr>";
    }
    echo "</table>";

    // 2. Check foreign keys
    echo "<h2>2. Foreign Key Constraints</h2>";
    $stmt = $pdo->query("
        SELECT
            tc.CONSTRAINT_NAME,
            kcu.COLUMN_NAME,
            kcu.REFERENCED_TABLE_NAME,
            kcu.REFERENCED_COLUMN_NAME
        FROM information_schema.TABLE_CONSTRAINTS tc
        JOIN information_schema.KEY_COLUMN_USAGE kcu
          ON tc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
        WHERE tc.TABLE_NAME = 'asset_assignments'
          AND tc.CONSTRAINT_TYPE = 'FOREIGN KEY'
    ");
    $fks = $stmt->fetchAll();

    if (count($fks) == 0) {
        echo "<p class='warning'>⚠️ No foreign key constraints on asset_assignments</p>";
    } else {
        echo "<table><tr><th>Constraint</th><th>Column</th><th>References</th></tr>";
        foreach ($fks as $fk) {
            $ref = "{$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}";
            echo "<tr><td>{$fk['CONSTRAINT_NAME']}</td><td>{$fk['COLUMN_NAME']}</td><td>$ref</td></tr>";
        }
        echo "</table>";
    }

    // 3. Check current data
    echo "<h2>3. Sample Data Analysis</h2>";
    $stmt = $pdo->query("SELECT id, asset_id, emp_id FROM asset_assignments LIMIT 5");
    $assignments = $stmt->fetchAll();

    if (count($assignments) == 0) {
        echo "<p>No assignments in database.</p>";
    } else {
        echo "<table><tr><th>ID</th><th>asset_id</th><th>emp_id (stored)</th><th>emp_id type</th><th>Valid employee?</th></tr>";
        foreach ($assignments as $a) {
            $type = gettype($a['emp_id']);
            // Check if this emp_id exists in employees table
            $check = $pdo->prepare("SELECT id FROM employees WHERE id = ?");
            $check->execute([$a['emp_id']]);
            $exists = $check->fetch() ? '✅' : '❌';
            echo "<tr><td>{$a['id']}</td><td>{$a['asset_id']}</td><td><strong>{$a['emp_id']}</strong></td><td>$type</td><td>$exists</td></tr>";
        }
        echo "</table>";
    }

    // 4. Count assignments per employee ID method
    echo "<h2>4. Current Employee Session</h2>";
    if (!isset($_SESSION['user_id'])) {
        echo "<p class='error'>❌ Not logged in!</p>";
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $emp_id = $_SESSION['emp_id'] ?? '';

    echo "<p>Logged in as: {$_SESSION['name']}</p>";
    echo "<p>user_id (INT from employees.id): <strong>$user_id</strong></p>";
    echo "<p>emp_id (VARCHAR from employees.emp_id): <strong>$emp_id</strong></p>";

    // Test queries
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM asset_assignments WHERE emp_id = ? AND returned_at IS NULL");
    $stmt->execute([$user_id]);
    $count_user = $stmt->fetchColumn();

    $stmt->execute([$emp_id]);
    $count_emp = $stmt->fetchColumn();

    echo "<p>Assignments with emp_id = user_id ($user_id): <strong>$count_user</strong></p>";
    echo "<p>Assignments with emp_id = emp_id ('$emp_id'): <strong>$count_emp</strong></p>";

    // 5. Diagnosis & Fix
    echo "<h2>5. Diagnosis & Fix</h2>";

    $needsFix = false;
    $issues = [];

    // Check if emp_id column is INT
    $emp_id_is_int = false;
    foreach ($cols as $col) {
        if ($col['Field'] == 'emp_id') {
            if (stripos($col['Type'], 'int') !== false) {
                $emp_id_is_int = true;
            }
        }
    }

    if (!$emp_id_is_int) {
        $issues[] = "asset_assignments.emp_id is NOT INT (should be INT to match employees.id)";
        $needsFix = true;
    }

    // Check if FK exists on emp_id
    $has_fk_on_emp = false;
    foreach ($fks as $fk) {
        if ($fk['COLUMN_NAME'] == 'emp_id') {
            $has_fk_on_emp = true;
        }
    }

    if (!$has_fk_on_emp) {
        $issues[] = "Missing foreign key constraint on asset_assignments.emp_id";
    }

    if (count($issues) > 0) {
        echo "<div class='error'><h3>❌ Problems Found:</h3><ul>";
        foreach ($issues as $issue) {
            echo "<li>$issue</li>";
        }
        echo "</ul></div>";

        echo "<h3>Applying Fix...</h3>";
        echo "<form method='POST'>
            <button type='submit' name='apply_fix' style='background:#EF4444;color:white;padding:12px 24px;border:none;border-radius:6px;cursor:pointer;font-size:16px;font-weight:bold;'>
                🛠️ Fix Schema (Convert to INT + Add Foreign Key)
            </button>
            <p style='color:#F59E0B;font-size:0.9rem;margin-top:10px;'>
                ⚠️ Warning: This will modify your database structure. Make a backup first!
            </p>
        </form>";

        if (isset($_POST['apply_fix'])) {
            echo "<h3>🔨 Applying Fix...</h3>";

            try {
                $pdo->beginTransaction();

                // Step 1: Create backup
                echo "<p>1. Creating backup table...</p>";
                $pdo->exec("DROP TABLE IF EXISTS asset_assignments_backup");
                $pdo->exec("CREATE TABLE asset_assignments_backup AS SELECT * FROM asset_assignments");
                echo "<p class='success'>✅ Backup created (asset_assignments_backup)</p>";

                // Step 2: Check for non-numeric data
                echo "<p>2. Checking for non-numeric emp_id values...</p>";
                $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM asset_assignments WHERE emp_id NOT REGEXP '^[0-9]+$'");
                $bad = $stmt->fetchColumn();
                if ($bad > 0) {
                    echo "<p class='warning'>⚠️ Found $bad rows with non-numeric emp_id. These need to be cleaned first.</p>";
                    $stmt = $pdo->query("SELECT id, emp_id FROM asset_assignments WHERE emp_id NOT REGEXP '^[0-9]+$' LIMIT 5");
                    $rows = $stmt->fetchAll();
                    echo "<p>Examples (first 5):</p><ul>";
                    foreach ($rows as $r) {
                        echo "<li>ID {$r['id']}: emp_id = '{$r['emp_id']}'</li>";
                    }
                    echo "</ul>";
                    echo "<p class='error'>❌ Cannot proceed with conversion. Clean these records first.</p>";
                    $pdo->rollBack();
                    exit;
                }

                // Step 3: Convert emp_id to INT
                echo "<p>3. Converting emp_id column to INT...</p>";
                try {
                    $pdo->exec("ALTER TABLE asset_assignments MODIFY COLUMN emp_id INT NOT NULL");
                    echo "<p class='success'>✅ emp_id converted to INT</p>";
                } catch (Exception $e) {
                    throw new Exception("Failed to convert emp_id: " . $e->getMessage());
                }

                // Step 4: Add foreign key
                echo "<p>4. Adding foreign key constraint...</p>";
                try {
                    // First, ensure data matches employees.id
                    $pdo->exec("
                        DELETE aa FROM asset_assignments aa
                        LEFT JOIN employees e ON aa.emp_id = e.id
                        WHERE e.id IS NULL
                    ");
                    $deleted = $pdo->rowCount();
                    if ($deleted > 0) {
                        echo "<p class='warning'>⚠️ Removed $deleted orphaned assignment(s) (no matching employee)</p>";
                    }

                    $pdo->exec("
                        ALTER TABLE asset_assignments
                        ADD CONSTRAINT fk_asset_assignments_employee
                        FOREIGN KEY (emp_id) REFERENCES employees(id) ON DELETE CASCADE
                    ");
                    echo "<p class='success'>✅ Foreign key added (emp_id → employees.id)</p>";
                } catch (Exception $e) {
                    echo "<p class='warning'>⚠️ Could not add FK (may already exist or data mismatch): " . htmlspecialchars($e->getMessage()) . "</p>";
                }

                $pdo->commit();

                echo "<h2 class='success'>✅ Fix Applied Successfully!</h2>";
                echo "<div class='success' style='background:#1a332e;padding:15px;border-radius:8px;border:1px solid #10B981;'>";
                echo "<p><strong>Changes made:</strong></p>";
                echo "<ul>";
                echo "<li>✅ asset_assignments.emp_id converted to INT</li>";
                echo "<li>✅ Foreign key constraint added (if possible)</li>";
                echo "<li>✅ Orphaned records removed</li>";
                echo "</ul>";
                echo "<p>Now test: <a href='employee/assets.php' style='color:#10B981'>employee/assets.php</a></p>";
                echo "</div>";

            } catch (Exception $e) {
                $pdo->rollBack();
                echo "<p class='error'>❌ Fix failed: " . htmlspecialchars($e->getMessage()) . "</p>";
                echo "<p>Backup saved in table: asset_assignments_backup</p>";
            }
        }

    } else {
        echo "<div class='success'><h3>✅ Schema Looks Correct</h3>";
        echo "<p>asset_assignments.emp_id is INT and has proper foreign key.</p>";

        echo "<h4>Test Results:</h4>";
        if ($count_user > 0) {
            echo "<p class='success'>✅ User has $count_user assigned asset(s). The employee page should display them.</p>";
        } else if ($count_emp > 0) {
            echo "<div class='warning'>";
            echo "<p>⚠️ Assets found using emp_id string but NOT using user_id integer.</p>";
            echo "<p><strong>Solution:</strong> Change employee pages to use \$_SESSION['emp_id'] instead of \$_SESSION['user_id'].</p>";
            echo "</div>";
        } else {
            echo "<p class='warning'>⚠️ No assignments found for this employee. Check if an assignment was actually created.</p>";
        }
    }

} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr><p><a href='javascript:history.back()'>← Back</a></p>";
echo "</body></html>";
?>
