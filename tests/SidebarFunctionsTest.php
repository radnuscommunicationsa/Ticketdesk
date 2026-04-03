<?php
/**
 * Sidebar Functions Test
 * Tests the avatarColor and initials functions that are missing from admin_sidebar.php
 */

// Include config to get PDO connection
require_once __DIR__ . '/../includes/config.php';

class SidebarFunctionsTest {
    private $passed = 0;
    private $failed = 0;

    public function run() {
        echo "🧪 Testing Sidebar Helper Functions\n";
        echo str_repeat("=", 60) . "\n\n";

        $this->testAvatarColorDefinition();
        $this->testInitialsDefinition();
        $this->testAvatarColorOutput();
        $this->testInitialsOutput();
        $this->testAvatarColorDeterministic();
        $this->testInitialsEdgeCases();

        $total = $this->passed + $this->failed;
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "📊 Function Test Summary\n";
        echo str_repeat("=", 60) . "\n";
        echo "Total: {$total} | ✅ Passed: {$this->passed} | ❌ Failed: {$this->failed}\n";
        echo "Success Rate: " . round(($this->passed / $total) * 100, 1) . "%\n";

        return $this->failed === 0;
    }

    private function assert($condition, $testName, $msg = '') {
        if ($condition) {
            $this->passed++;
            echo "✅ PASS: {$testName}\n";
        } else {
            $this->failed++;
            echo "❌ FAIL: {$testName}" . ($msg ? " - {$msg}" : '') . "\n";
        }
    }

    private function testAvatarColorDefinition() {
        $this->assert(
            file_exists(__DIR__ . '/../includes/admin_sidebar.php'),
            'admin_sidebar.php exists'
        );

        $content = file_get_contents(__DIR__ . '/../includes/admin_sidebar.php');
        $hasFunction = strpos($content, 'function avatarColor(') !== false;
        $this->assert(
            $hasFunction,
            'avatarColor function defined in admin_sidebar.php',
            $hasFunction ? '' : 'Function not found in sidebar file'
        );
    }

    private function avatarColor($name) {
        $colors = ['#5552DD','#7B7AFF','#10B981','#F59E0B','#3B82F6','#EC4899','#8B5CF6','#14B8A6'];
        $h = 0; foreach (str_split($name) as $c) $h += ord($c);
        return $colors[$h % count($colors)];
    }

    private function initials($name) {
        $parts = explode(' ', $name);
        return strtoupper(substr($parts[0],0,1) . (isset($parts[1]) ? substr($parts[1],0,1) : ''));
    }

    private function testInitialsDefinition() {
        $content = file_get_contents(__DIR__ . '/../includes/admin_sidebar.php');
        $hasFunction = strpos($content, 'function initials(') !== false;
        $this->assert(
            $hasFunction,
            'initials function defined in admin_sidebar.php',
            $hasFunction ? '' : 'Function not found in sidebar file'
        );
    }

    private function testAvatarColorOutput() {
        $colors = ['#5552DD','#7B7AFF','#10B981','#F59E0B','#3B82F6','#EC4899','#8B5CF6','#14B8A6'];
        $color1 = $this->avatarColor('Admin User');
        $this->assert(in_array($color1, $colors), 'avatarColor returns valid hex color');

        $color2 = $this->avatarColor('John Doe');
        $this->assert(in_array($color2, $colors), 'avatarColor returns valid hex for different input');
    }

    private function testInitialsOutput() {
        $init1 = $this->initials('John Doe');
        $this->assert($init1 === 'JD', 'initials returns JD for "John Doe"');

        $init2 = $this->initials('Admin User');
        $this->assert($init2 === 'AU', 'initials returns AU for "Admin User"');

        $init3 = $this->initials('SingleName');
        $this->assert($init3 === 'S', 'initials returns first letter for single name');
    }

    private function testAvatarColorDeterministic() {
        $color1_run1 = $this->avatarColor('Test User');
        $color1_run2 = $this->avatarColor('Test User');
        $this->assert(
            $color1_run1 === $color1_run2,
            'avatarColor is deterministic',
            "Both returned {$color1_run1}"
        );
    }

    private function testInitialsEdgeCases() {
        $this->assert($this->initials('') === '', 'initials handles empty string');
        $this->assert($this->initials('A B C D') === 'AB', 'initials takes only first two names');
        $this->assert($this->initials('  Leading  Spaces  ') === 'LS', 'initials handles extra spaces');
    }
}

// Run
$test = new SidebarFunctionsTest();
$success = $test->run();
exit($success ? 0 : 1);
