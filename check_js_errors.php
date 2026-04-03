<?php
// Check for JavaScript errors
$js_file = 'assets/js/theme.js';
$content = file_get_contents($js_file);
echo "<h2>JavaScript File Check</h2>";
echo "<p>File size: " . strlen($content) . " bytes</p>";

// Check for syntax errors using PHP's tokenizer
$tokens = @token_get_all($content);
$errors = [];
$line = 1;
foreach ($tokens as $token) {
    if (is_array($token) && $token[0] === T_ERROR) {
        $errors[] = "Line $line: " . $token[1];
    }
    if (is_array($token) && $token[0] === T_STRING) {
        // Check for common typos
        if ($token[1] === 'mobileSidebar' && strpos($content, 'mobileSidebar') !== false) {
            // Good
        }
    }
}
if (empty($errors)) {
    echo "<p style='color:green'>✅ No PHP tokenizer errors found</p>";
} else {
    echo "<p style='color:red'>❌ Errors found:</p><pre>";
    print_r($errors);
    echo "</pre>";
}

// Check for critical functions
$checks = [
    'classList' => strpos($content, 'classList') !== false,
    'addEventListener' => strpos($content, 'addEventListener') !== false,
    'mobile-sidebar' => strpos($content, 'mobile-sidebar') !== false,
    'hamburger' => strpos($content, 'hamburger') !== false,
    'openMenu' => strpos($content, 'function openMenu') !== false,
    'closeMenu' => strpos($content, 'function closeMenu') !== false,
];

echo "<h3>Critical Code Checks:</h3><ul>";
foreach ($checks as $key => $found) {
    echo "<li>" . ($found ? "✅" : "❌") . " $key</li>";
}
echo "</ul>";
?>
