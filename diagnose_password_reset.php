<?php
/**
 * Diagnostic Script for Password Reset Feature
 * Run this to identify and fix issues
 */

header('Content-Type: text/plain');
echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║     PASSWORD RESET FEATURE - DIAGNOSTIC REPORT                ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

require_once __DIR__ . '/includes/config.php';

$issues = [];
$warnings = [];
$passed = [];

echo "1. DATABASE CONNECTION TEST\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
try {
    $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);
    echo "✅ Database connection: OK\n\n";
    $passed[] = "Database connection";
} catch (PDOException $e) {
    echo "❌ Database connection: FAILED\n";
    echo "   Error: " . $e->getMessage() . "\n\n";
    $issues[] = "Database connection failed - check credentials in config.php";
    exit(1);
}

echo "2. CHECK REQUIRED TABLES\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// Check employees table
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'employees'");
    if ($stmt->rowCount() > 0) {
        echo "✅ employees table: EXISTS\n";
        $passed[] = "employees table exists";

        // Check structure
        $stmt = $pdo->query("DESCRIBE employees");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $required = ['id', 'emp_id', 'email', 'name', 'password', 'status', 'role', 'department'];
        $missing = array_diff($required, $columns);

        if (empty($missing)) {
            echo "   ✅ Structure: All required columns present\n";
        } else {
            echo "   ❌ Missing columns: " . implode(', ', $missing) . "\n";
            $issues[] = "employees table missing columns: " . implode(', ', $missing);
        }

        // Check if has data
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM employees");
        $count = $stmt->fetchColumn();
        echo "   📊 Total employees: $count\n";

        if ($count == 0) {
            $warnings[] = "No employees in database - add at least one employee first";
            echo "   ⚠️  WARNING: No employees found!\n";
        }

        // Check active employees
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM employees WHERE status = 'active'");
        $active = $stmt->fetchColumn();
        echo "   👥 Active employees: $active\n";

        if ($active == 0) {
            $issues[] = "No active employees - need at least 1 active employee to test";
            echo "   ❌ ERROR: No active employees!\n";
        }

    } else {
        echo "❌ employees table: NOT FOUND\n";
        $issues[] = "employees table does not exist - run database.sql";
    }
} catch (PDOException $e) {
    echo "❌ employees table check: FAILED - " . $e->getMessage() . "\n";
    $issues[] = "Error checking employees table: " . $e->getMessage();
}
echo "\n";

// Check password_reset_tokens table
echo "3. CHECK PASSWORD RESET TABLES\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'password_reset_tokens'");
    if ($stmt->rowCount() > 0) {
        echo "✅ password_reset_tokens table: EXISTS\n";
        $passed[] = "password_reset_tokens table exists";

        // Check structure
        $stmt = $pdo->query("DESCRIBE password_reset_tokens");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $required = ['id', 'emp_id', 'token', 'expires_at', 'used', 'created_at'];
        $missing = array_diff($required, $columns);

        if (empty($missing)) {
            echo "   ✅ Structure: All required columns present\n";
        } else {
            echo "   ❌ Missing columns: " . implode(', ', $missing) . "\n";
            $issues[] = "password_reset_tokens missing columns: " . implode(', ', $missing);
        }

        // Check indexes
        $stmt = $pdo->query("SHOW INDEX FROM password_reset_tokens");
        $indexes = $stmt->fetchAll(PDO::FETCH_COLUMN, 'Key_name');
        $required_indexes = ['PRIMARY', 'idx_password_reset_token', 'idx_password_reset_emp_id', 'idx_password_reset_expires'];
        $missing_indexes = array_diff($required_indexes, $indexes);

        if (empty($missing_indexes)) {
            echo "   ✅ Indexes: All indexes present\n";
        } else {
            echo "   ⚠️  Missing indexes: " . implode(', ', $missing_indexes) . "\n";
            $warnings[] = "Some indexes missing - consider adding for performance";
        }

    } else {
        echo "❌ password_reset_tokens table: NOT FOUND\n";
        $issues[] = "password_reset_tokens table does not exist - run database.sql";
    }
} catch (PDOException $e) {
    echo "❌ password_reset_tokens check: FAILED - " . $e->getMessage() . "\n";
    $issues[] = "Error checking password_reset_tokens: " . $e->getMessage();
}
echo "\n";

echo "4. CHECK FUNCTIONS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$functions = ['generateResetToken', 'verifyResetToken', 'markTokenUsed', 'sendPasswordResetEmail'];
foreach ($functions as $func) {
    if (function_exists($func)) {
        echo "✅ Function $func: EXISTS\n";
        $passed[] = "Function $func exists";
    } else {
        echo "❌ Function $func: NOT FOUND\n";
        $issues[] = "Function $func missing - check includes/config.php";
    }
}
echo "\n";

echo "5. CHECK CONSTANTS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
if (defined('SITE_URL')) {
    echo "✅ SITE_URL: " . SITE_URL . "\n";
    $passed[] = "SITE_URL defined";
} else {
    echo "❌ SITE_URL: NOT DEFINED\n";
    $issues[] = "SITE_URL constant missing from config.php";
}

if (defined('SITE_NAME')) {
    echo "✅ SITE_NAME: " . SITE_NAME . "\n";
    $passed[] = "SITE_NAME defined";
} else {
    echo "⚠️  SITE_NAME: NOT DEFINED (optional)\n";
    $warnings[] = "SITE_NAME constant not defined - will use default in emails";
}
echo "\n";

echo "6. TEST TOKEN GENERATION\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
try {
    // Get first active employee
    $stmt = $pdo->query("SELECT id FROM employees WHERE status = 'active' LIMIT 1");
    $emp = $stmt->fetch();

    if ($emp) {
        $token = generateResetToken($pdo, $emp['id']);
        if ($token && strlen($token) === 64) {
            echo "✅ Token generation: SUCCESS\n";
            echo "   Token: " . substr($token, 0, 20) . "...\n";
            $passed[] = "Token generation works";

            // Verify it was inserted
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM password_reset_tokens WHERE token = ?");
            $stmt->execute([$token]);
            if ($stmt->fetchColumn() > 0) {
                echo "✅ Token storage: SUCCESS\n";
            } else {
                echo "❌ Token storage: FAILED - token not in database\n";
                $issues[] = "Token not stored in database";
            }
        } else {
            echo "❌ Token generation: FAILED - invalid token format\n";
            $issues[] = "Token generation returned invalid value";
        }
    } else {
        echo "❌ Cannot test - no active employee found\n";
        $issues[] = "Need at least 1 active employee to test token generation";
    }
} catch (Exception $e) {
    echo "❌ Token generation: FAILED - " . $e->getMessage() . "\n";
    $issues[] = "Token generation error: " . $e->getMessage();
}
echo "\n";

echo "7. CHECK FILE PERMISSIONS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$files = [
    'forgot_password.php' => 'read',
    'reset_password.php' => 'read',
    'test_password_reset.php' => 'read',
    'includes/config.php' => 'read',
];

foreach ($files as $file => $permission) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        if (is_readable($path)) {
            echo "✅ $file: readable\n";
        } else {
            echo "❌ $file: NOT readable\n";
            $issues[] = "File $file is not readable - check permissions";
        }
    } else {
        echo "❌ $file: NOT FOUND\n";
        $issues[] = "File $file missing";
    }
}
echo "\n";

echo "8. CHECK PHP VERSION & EXTENSIONS\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "PHP Version: " . PHP_VERSION . "\n";

$required_ext = ['pdo', 'pdo_mysql', 'mbstring'];
foreach ($required_ext as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ Extension $ext: loaded\n";
    } else {
        echo "❌ Extension $ext: NOT loaded\n";
        $issues[] = "PHP extension $ext required - enable in php.ini";
    }
}
echo "\n";

echo "═══════════════════════════════════════════════════════════════\n";
echo "SUMMARY\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

echo "✅ PASSED CHECKS (" . count($passed) . "):\n";
foreach ($passed as $p) {
    echo "   ✓ $p\n";
}
echo "\n";

if ($warnings) {
    echo "⚠️  WARNINGS (" . count($warnings) . "):\n";
    foreach ($warnings as $w) {
        echo "   ! $w\n";
    }
    echo "\n";
}

if ($issues) {
    echo "❌ FAILED CHECKS (" . count($issues) . "):\n";
    foreach ($issues as $i) {
        echo "   ✗ $i\n";
    }
    echo "\n";
    echo "╔═══════════════════════════════════════════════════════════════╗\n";
    echo "║  FIX THE ABOVE ISSUES BEFORE TESTING PASSWORD RESET         ║\n";
    echo "╚═══════════════════════════════════════════════════════════════╝\n";
} else {
    echo "🎉 ALL CHECKS PASSED! Password reset feature should work.\n\n";
    echo "Next steps:\n";
    echo "1. Visit: " . SITE_URL . "/test_password_reset.php\n";
    echo "2. Click 'Generate New Token'\n";
    echo "3. Check error log for simulated email\n";
    echo "4. Test the reset flow\n";
}
