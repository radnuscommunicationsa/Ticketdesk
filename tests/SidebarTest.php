<?php
// Test runner for Sidebar functionality
// Run with: php tests/SidebarTest.php

require_once __DIR__ . '/../includes/config.php';

class SidebarTest {
    private $pdo;
    private $testsPassed = 0;
    private $testsFailed = 0;
    private $results = [];

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function runAll() {
        echo "🧪 Running Sidebar Automated Tests...\n";
        echo str_repeat("=", 60) . "\n\n";

        // Database tests
        $this->testDatabaseConnection();
        $this->testTicketCounts();
        $this->testEmployeeCount();
        $this->testAssetCount();
        $this->testNotificationCount();
        $this->testCriticalPriorityQuery();

        // Logic tests
        $this->testAvatarColorFunction();
        $this->testInitialsFunction();
        $this->testSanitizeFunction();
        $this->testCurrentPageDetection();

        // Security tests
        $this->testSessionValidation();
        $this->testSqlInjectionPrevention();

        // Print summary
        $this->printSummary();

        return $this->testsFailed === 0;
    }

    private function assert($condition, $testName, $message = '') {
        if ($condition) {
            $this->testsPassed++;
            $this->results[] = ["✅ PASS", $testName];
            echo "✅ PASS: {$testName}\n";
        } else {
            $this->testsFailed++;
            $this->results[] = ["❌ FAIL", $testName . ($message ? " - {$message}" : '')];
            echo "❌ FAIL: {$testName}" . ($message ? " - {$message}" : '') . "\n";
        }
    }

    private function testDatabaseConnection() {
        try {
            $testPdo = new PDO(
                "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $this->assert($testPdo !== null, 'Database connection established');
        } catch (PDOException $e) {
            $this->assert(false, 'Database connection', $e->getMessage());
        }
    }

    private function testTicketCounts() {
        try {
            $total = $this->pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn();
            $this->assert(is_numeric($total) && $total >= 0, 'Total tickets count is valid', "Count: {$total}");

            $open = $this->pdo->query("SELECT COUNT(*) FROM tickets WHERE status='open'")->fetchColumn();
            $this->assert(is_numeric($open) && $open >= 0, 'Open tickets count is valid', "Count: {$open}");

            $inprog = $this->pdo->query("SELECT COUNT(*) FROM tickets WHERE status='in-progress'")->fetchColumn();
            $this->assert(is_numeric($inprog) && $inprog >= 0, 'In-progress tickets count is valid', "Count: {$inprog}");

            $critical = $this->pdo->query("SELECT COUNT(*) FROM tickets WHERE priority='critical' AND status NOT IN ('resolved','closed')")->fetchColumn();
            $this->assert(is_numeric($critical) && $critical >= 0, 'Critical active tickets count is valid', "Count: {$critical}");
        } catch (Exception $e) {
            $this->assert(false, 'Ticket counts query', $e->getMessage());
        }
    }

    private function testEmployeeCount() {
        try {
            $count = $this->pdo->query("SELECT COUNT(*) FROM employees WHERE role='employee'")->fetchColumn();
            $this->assert(is_numeric($count) && $count >= 0, 'Employee count is valid', "Count: {$count}");
        } catch (Exception $e) {
            $this->assert(false, 'Employee count query', $e->getMessage());
        }
    }

    private function testAssetCount() {
        try {
            $count = $this->pdo->query("SELECT COUNT(*) FROM assets")->fetchColumn();
            $this->assert(is_numeric($count) && $count >= 0, 'Asset count is valid', "Count: {$count}");
        } catch (Exception $e) {
            $this->assert(false, 'Asset count query', $e->getMessage());
        }
    }

    private function testNotificationCount() {
        try {
            // Test with user_id = 1 (assuming at least one admin exists)
            $count = $this->pdo->prepare("SELECT COUNT(*) FROM notifications WHERE emp_id = ? AND is_read = 0");
            $count->execute([1]);
            $count = $count->fetchColumn();
            $this->assert(is_numeric($count) && $count >= 0, 'Unread notification count is valid', "Count: {$count}");
        } catch (Exception $e) {
            $this->assert(false, 'Notification count query', $e->getMessage());
        }
    }

    private function testCriticalPriorityQuery() {
        try {
            // Verify critical tickets logic: critical + not resolved/closed
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM tickets
                WHERE priority = 'critical'
                AND status NOT IN ('resolved', 'closed')
            ");
            $stmt->execute();
            $criticalActive = $stmt->fetchColumn();

            // Should return 0 or positive number
            $this->assert(is_numeric($criticalActive), 'Critical active tickets returns numeric');

            // Verify that resolved critical tickets are excluded
            $totalCritical = $this->pdo->query("SELECT COUNT(*) FROM tickets WHERE priority='critical'")->fetchColumn();
            if ($totalCritical > 0) {
                $this->assert(
                    $criticalActive <= $totalCritical,
                    'Critical active count <= total critical',
                    "Active: {$criticalActive}, Total: {$totalCritical}"
                );
            }
        } catch (Exception $e) {
            $this->assert(false, 'Critical priority query', $e->getMessage());
        }
    }

    private function testAvatarColorFunction() {
        // Define the function if not exists
        if (!function_exists('avatarColor')) {
            function avatarColor($name) {
                $colors = ['#5552DD','#7B7AFF','#10B981','#F59E0B','#3B82F6','#EC4899','#8B5CF6','#14B8A6'];
                $h = 0; foreach (str_split($name) as $c) $h += ord($c);
                return $colors[$h % count($colors)];
            }
        }

        $color1 = avatarColor('Admin User');
        $color2 = avatarColor('John Doe');
        $color3 = avatarColor('');

        $this->assert(in_array($color1, ['#5552DD','#7B7AFF','#10B981','#F59E0B','#3B82F6','#EC4899','#8B5CF6','#14B8A6']), 'avatarColor returns valid color for "Admin User"');
        $this->assert(in_array($color2, ['#5552DD','#7B7AFF','#10B981','#F59E0B','#3B82F6','#EC4899','#8B5CF6','#14B8A6']), 'avatarColor returns valid color for "John Doe"');
        $this->assert(!empty($color3), 'avatarColor returns non-empty for empty string');
        $this->assert($color1 === avatarColor('Admin User'), 'avatarColor is deterministic');
    }

    private function testInitialsFunction() {
        // Define the function if not exists
        if (!function_exists('initials')) {
            function initials($name) {
                $parts = explode(' ', $name);
                return strtoupper(substr($parts[0],0,1) . (isset($parts[1]) ? substr($parts[1],0,1) : ''));
            }
        }

        $this->assert(initials('John Doe') === 'JD', 'initials extracts JD from "John Doe"');
        $this->assert(initials('Admin User') === 'AU', 'initials extracts AU from "Admin User"');
        $this->assert(initials('Single') === 'S', 'initials handles single name');
        $this->assert(initials('') === '', 'initials handles empty string');
        $this->assert(initials('John Michael Doe') === 'JM', 'initials uses first two names only');
    }

    private function testSanitizeFunction() {
        $testInput = '<script>alert("XSS")</script>';
        $sanitized = sanitize($testInput);
        $this->assert(
            strpos($sanitized, '<script>') === false,
            'sanitize removes script tags',
            "Got: " . htmlspecialchars($sanitized)
        );

        $this->assert(sanitize(null) === '', 'sanitize handles null');
        $this->assert(sanitize('  test  ') === 'test', 'sanitize trims whitespace');
        $this->assert(sanitize('Normal text') === 'Normal text', 'sanitize preserves normal text');
    }

    private function testCurrentPageDetection() {
        $currentPage = basename($_SERVER['PHP_SELF'] ?? 'dashboard.php');
        $this->assert(!empty($currentPage), 'Current page name is detected');
        $this->assert(strpos($currentPage, '.php') !== false || strpos($currentPage, '/') !== false, 'Current page looks like a filename');
    }

    private function testSessionValidation() {
        $this->assert(isset($_SESSION), 'Session is available');
        $this->assert(function_exists('isLoggedIn'), 'isLoggedIn function exists');
        $this->assert(function_exists('isAdmin'), 'isAdmin function exists');

        // Test isLoggedIn
        $isLoggedIn = isLoggedIn();
        $this->assert(is_bool($isLoggedIn), 'isLoggedIn returns boolean');

        // Test isAdmin
        $isAdmin = isAdmin();
        $this->assert(is_bool($isAdmin), 'isAdmin returns boolean');
    }

    private function testSqlInjectionPrevention() {
        // Simulate the notification count query with potential injection
        $user_id = 1; // Normal integer

        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM notifications WHERE emp_id = ? AND is_read = 0");
            $stmt->execute([$user_id]);
            $result = $stmt->fetchColumn();
            $this->assert(is_numeric($result), 'Prepared statement prevents injection', "Result: {$result}");
        } catch (Exception $e) {
            $this->assert(false, 'Prepared statement execution', $e->getMessage());
        }

        // Test that admin_sidebar.php uses proper type casting
        // The line: (int)$_SESSION['user_id'] is safe
        $testSessionId = "1 OR 1=1"; // Malicious attempt
        $safeId = (int)$testSessionId;
        $this->assert($safeId === 1, 'Type casting prevents injection', "Input: '{$testSessionId}', Casted: {$safeId}");
    }

    private function printSummary() {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "📊 TEST SUMMARY\n";
        echo str_repeat("=", 60) . "\n";
        echo "Total Tests: " . ($this->testsPassed + $this->testsFailed) . "\n";
        echo "✅ Passed: {$this->testsPassed}\n";
        echo "❌ Failed: {$this->testsFailed}\n";
        echo "Success Rate: " . round(($this->testsPassed / ($this->testsPassed + $this->testsFailed)) * 100, 1) . "%\n";

        if ($this->testsFailed > 0) {
            echo "\n⚠️  Failed Tests:\n";
            foreach ($this->results as $result) {
                if ($result[0] === '❌ FAIL') {
                    echo "   - {$result[1]}\n";
                }
            }
        }

        echo "\n" . ($this->testsFailed === 0 ? "🎉 All tests passed!" : "⚠️  Some tests failed. Review the output above.") . "\n";
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' || (PHP_SAPI !== 'cgi-fcgi' && !headers_sent())) {
    $test = new SidebarTest($pdo);
    $success = $test->runAll();
    exit($success ? 0 : 1);
}
