<?php
/**
 * Debug: Check if SMTP credentials are properly set
 */

echo "<!DOCTYPE html>
<html>
<head><title>SMTP Config Debug</title>
<style>
    body { font-family: monospace; padding: 20px; background: #f5f5f5; }
    .box { background: white; padding: 20px; border-radius: 8px; margin-bottom: 1rem; }
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
    pre { background: #f0f0f0; padding: 10px; border-radius: 4px; overflow-x: auto; }
</style>
</head>
<body>
<div class='box'>
<h2>🔍 SMTP Configuration Diagnostic</h2>";

require_once __DIR__ . '/includes/config.php';

echo "<h3>Environment Variables (getenv)</h3>";
echo "<pre>";
echo "SMTP_HOST: " . (getenv('SMTP_HOST') ?: 'NOT SET') . "\n";
echo "SMTP_PORT: " . (getenv('SMTP_PORT') ?: 'NOT SET') . "\n";
echo "SMTP_USER: " . (getenv('SMTP_USER') ?: 'NOT SET') . "\n";
echo "SMTP_PASS: " . (getenv('SMTP_PASS') ? '***HIDDEN***' : 'NOT SET') . "\n";
echo "SMTP_SECURE: " . (getenv('SMTP_SECURE') ?: 'NOT SET') . "\n";
echo "</pre>";

// Check if putenv lines exist in config.php
echo "<h3>Config.php SMTP Settings</h3>";
$configContent = file_get_contents(__DIR__ . '/includes/config.php');
$hasPutenv = (strpos($configContent, "putenv('SMTP_USER=") !== false);
$hasPutenvPass = (strpos($configContent, "putenv('SMTP_PASS=") !== false);

echo $hasPutenv ?
    "<p class='success'>✅ putenv() SMTP configuration found in config.php</p>" :
    "<p class='error'>❌ putenv() SMTP configuration NOT found in config.php</p>";

if ($hasPutenv) {
    echo "<h4>Configuration lines found:</h4><pre>";
    $lines = explode("\n", $configContent);
    foreach ($lines as $i => $line) {
        if (strpos($line, 'putenv(\'SMTP_') !== false) {
            echo "$i: $line\n";
        }
    }
    echo "</pre>";
}

// Test connection
echo "<h3>SMTP Connection Test</h3>";
$smtp_host = getenv('SMTP_HOST');
$smtp_port = getenv('SMTP_PORT');
$smtp_user = getenv('SMTP_USER');
$smtp_pass = getenv('SMTP_PASS');

if ($smtp_host && $smtp_user && $smtp_pass) {
    echo "<p>Attempting to connect to $smtp_host:$smtp_port...</p>";

    // Simple connection test
    $socket = @fsockopen($smtp_host, $smtp_port, $errno, $errstr, 5);

    if ($socket) {
        echo "<p class='success'>✅ Connected to $smtp_host:$smtp_port</p>";

        // Read server response
        $response = fgets($socket, 515);
        echo "<pre>Server: " . htmlspecialchars(trim($response)) . "</pre>";

        // Test EHLO
        fwrite($socket, "EHLO localhost\r\n");
        stream_set_timeout($socket, 2);
        $ehloResponse = '';
        while ($line = fgets($socket, 515)) {
            $ehloResponse .= $line;
            if (substr($line, 3, 1) == ' ') break;
        }
        echo "<pre>EHLO Response: " . htmlspecialchars($ehloResponse) . "</pre>";

        fclose($socket);
    } else {
        echo "<p class='error'>❌ Could not connect: $errstr ($errno)</p>";
        echo "<p>Possible causes:</p>";
        echo "<ul>";
        echo "<li>Firewall blocking port $smtp_port</li>";
        echo "<li>Wrong SMTP_HOST or SMTP_PORT</li>";
        echo "<li>No internet connection</li>";
        echo "</ul>";
    }
} else {
    echo "<p class='warning'>⚠️ SMTP credentials incomplete. Check config.php.</p>";
}

echo "
</div>
</body>
</html>";