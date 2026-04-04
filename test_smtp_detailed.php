<?php
/**
 * Detailed SMTP Test - Shows Real-Time Debug Output
 * This will show exactly why email sending is failing
 */

require_once __DIR__ . '/includes/config.php';

// Force debug mode
define('EMAIL_DEBUG', true);

header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Detailed SMTP Test</title>
    <style>
        body { font-family: 'Courier New', monospace; background: #1a1a2e; color: #fff; padding: 20px; }
        .log { background: #000; padding: 15px; border-radius: 6px; margin: 10px 0; white-space: pre-wrap; word-wrap: break-word; }
        .success { color: #10b981; }
        .error { color: #ef4444; }
        .info { color: #3b82f6; }
        h1 { color: #fff; }
        .btn { background: #5552DD; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; }
        .btn:hover { background: #7B7AFF; }
        textarea { width: 100%; height: 200px; background: #000; color: #0f0; padding: 10px; border: 1px solid #333; border-radius: 6px; font-family: 'Courier New', monospace; margin: 10px 0; }
    </style>
</head>
<body>
<h1>🔍 Detailed SMTP Test</h1>

<div class="btn" onclick="runTest()">🚀 Run Test</div>

<div id="output"></div>

<script>
function runTest() {
    const output = document.getElementById('output');
    output.innerHTML = '<div class="log info">Running test... Check server response.</div>';

    fetch('?run=1')
        .then(res => res.text())
        .then(html => {
            // Extract just the log section
            const match = html.match(/<div class='log'>(.*?)<\/div>\s*<\/body>/s);
            if (match) {
                output.innerHTML = match[1];
            } else {
                output.innerHTML = html;
            }
        })
        .catch(err => {
            output.innerHTML = '<div class="log error">❌ Fetch error: ' + err + '</div>';
        });
}
</script>

<?php
if (isset($_GET['run'])) {

    // Capture all error_log output
    $logs = [];
    $originalErrorLog = function($msg) use (&$logs) {
        $logs[] = date('H:i:s') . ' | ' . $msg;
    };

    // Temporarily override error_log
    set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$logs) {
        $logs[] = date('H:i:s') . ' | PHP ERROR: ' . $errstr;
        return true;
    });

    // Override error_log function (works in same request)
    runSmtpTest($logs);

    restore_error_handler();

    echo "<div class='log'>";
    echo "╔══════════════════════════════════════════════════════════════╗\n";
    echo "║           DETAILED SMTP CONNECTION TEST                     ║\n";
    echo "╚══════════════════════════════════════════════════════════════╝\n\n";

    echo "Environment: " . (getenv('RAILWAY_ENVIRONMENT') ? 'Railway' : 'Localhost') . "\n";
    echo "Time: " . date('Y-m-d H:i:s') . "\n";
    echo "SITE_URL: " . SITE_URL . "\n\n";

    echo "SMTP Settings:\n";
    echo "  Host: " . (getenv('SMTP_HOST') ?: 'NOT SET') . "\n";
    echo "  Port: " . (getenv('SMTP_PORT') ?: 'NOT SET (default 587)') . "\n";
    echo "  User: " . (getenv('SMTP_USER') ? 'SET (hidden)' : 'NOT SET') . "\n";
    echo "  Pass: " . (getenv('SMTP_PASS') ? 'SET (hidden)' : 'NOT SET') . "\n";
    echo "  Secure: " . (getenv('SMTP_SECURE') ?: 'NOT SET (default tls)') . "\n\n";

    if (empty(getenv('SMTP_HOST')) || empty(getenv('SMTP_USER')) || empty(getenv('SMTP_PASS'))) {
        echo "<span class='error'>❌ SMTP NOT CONFIGURED - Will fall back to PHP mail()</span>\n";
        echo "This will likely fail on Railway.\n";
    }

    echo "\n" . str_repeat('─', 70) . "\n";
    echo "CONNECTION LOG:\n";
    echo str_repeat('─', 70) . "\n\n";

    foreach ($logs as $log) {
        if (strpos($log, '✅') !== false || strpos($log, '❌') !== false) {
            echo $log . "\n";
        } else {
            echo "<span class='info'>$log</span>\n";
        }
    }

    echo "\n" . str_repeat('─', 70) . "\n";

    // Check if there's any success indicator
    $success = false;
    foreach ($logs as $log) {
        if (strpos($log, '✅ Email sent via SMTP') !== false) {
            $success = true;
            break;
        }
    }

    if ($success) {
        echo "<span class='success'>✅ SUCCESS: Email was sent via SMTP!</span>\n";
    } else {
        echo "<span class='error'>❌ FAILURE: Email was NOT sent.</span>\n";
        echo "\nPossible causes:\n";
        echo "  1. Wrong SMTP credentials (check Gmail App Password)\n";
        echo "  2. 2FA not enabled on Google Account\n";
        echo "  3. Railway blocks outbound SMTP connections\n";
        echo "  4. Gmail is blocking the sign-in as suspicious\n";
        echo "  5. Wrong port/host configuration\n";
        echo "\nCheck your Gmail account for security alerts.\n";
    }

    echo str_repeat('─', 70) . "\n";
    echo "</div>";
}

function runSmtpTest(&$logs) {
    $logs[] = "Starting SMTP test...";

    $smtp_host = getenv('SMTP_HOST');
    $smtp_port = getenv('SMTP_PORT') ?: 587;
    $smtp_user = getenv('SMTP_USER');
    $smtp_pass = getenv('SMTP_PASS');
    $smtp_secure = getenv('SMTP_SECURE') ?: 'tls';

    if (!$smtp_host || !$smtp_user || !$smtp_pass) {
        $logs[] = "❌ SMTP environment variables not set. Cannot test SMTP directly.";
        $logs[] = "Will fall back to PHP mail() function.";
        return;
    }

    $logs[] = "Attempting connection to $smtp_host:$smtp_port";

    // Connect
    $socket = @fsockopen(($smtp_secure === 'ssl' && $smtp_port == 465 ? 'tls://' : '') . $smtp_host, $smtp_port, $errno, $errstr, 10);

    if (!$socket) {
        $logs[] = "❌ Connection failed: $errstr ($errno)";
        $logs[] = "Possible causes:";
        $logs[] = "  - Firewall blocking outbound SMTP";
        $logs[] = "  - Railway free tier blocks SMTP (need upgrade)";
        $logs[] = "  - Wrong host/port";
        $logs[] = "  - DNS resolution failed";
        return;
    }

    $logs[] = "✅ Connected successfully";
    stream_set_timeout($socket, 10);

    // Read initial response
    $response = '';
    while ($line = fgets($socket, 515)) {
        $response .= $line;
        $logs[] = "S: " . trim($line);
        if (substr($line, 3, 1) == ' ') break;
    }

    if (substr(trim($response), 0, 3) != '220') {
        $logs[] = "❌ Unexpected initial response: " . trim($response);
        fclose($socket);
        return;
    }

    // EHLO
    $ehlo = "EHLO " . gethostname() . "\r\n";
    fwrite($socket, $ehlo);
    $logs[] = "C: EHLO " . gethostname();

    while ($line = fgets($socket, 515)) {
        $logs[] = "S: " . trim($line);
        if (substr($line, 3, 1) == ' ') break;
    }

    // STARTTLS if needed
    if ($smtp_secure === 'tls' && $smtp_port == 587) {
        fwrite($socket, "STARTTLS\r\n");
        $logs[] = "C: STARTTLS";
        $response = fgets($socket, 515);
        $logs[] = "S: " . trim($response);

        if (substr($response, 0, 3) != '220') {
            $logs[] = "❌ STARTTLS failed";
            fclose($socket);
            return;
        }

        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            $logs[] = "❌ Failed to enable TLS encryption";
            fclose($socket);
            return;
        }

        $logs[] = "✅ TLS encryption enabled";

        // Send EHLO again
        fwrite($socket, $ehlo);
        $logs[] = "C: EHLO (post-TLS)";
        while ($line = fgets($socket, 515)) {
            $logs[] = "S: " . trim($line);
            if (substr($line, 3, 1) == ' ') break;
        }
    }

    // AUTH LOGIN
    fwrite($socket, "AUTH LOGIN\r\n");
    $logs[] = "C: AUTH LOGIN";
    $response = fgets($socket, 515);
    $logs[] = "S: " . trim($response);

    if (substr($response, 0, 3) != '334') {
        $logs[] = "❌ AUTH not accepted";
        fclose($socket);
        return;
    }

    // Username
    fwrite($socket, base64_encode($smtp_user) . "\r\n");
    $logs[] = "C: (username - base64 encoded)";
    $response = fgets($socket, 515);
    $logs[] = "S: " . trim($response);

    if (substr($response, 0, 3) != '334') {
        $logs[] = "❌ Username rejected";
        fclose($socket);
        return;
    }

    // Password
    fwrite($socket, base64_encode($smtp_pass) . "\r\n");
    $logs[] = "C: (password - base64 encoded)";
    $response = fgets($socket, 515);
    $logs[] = "S: " . trim($response);

    if (substr($response, 0, 3) != '235') {
        $logs[] = "❌ Authentication FAILED";
        $logs[] = "   This means either:";
        $logs[] = "   - Wrong password (not an App Password)";
        $logs[] = "   - 2FA not enabled (App Passwords won't work)";
        $logs[] = "   - Google blocked the sign-in as suspicious";
        $logs[] = "   - Account doesn't exist or is locked";
        fclose($socket);
        return;
    }

    $logs[] = "✅ Authentication successful";

    // MAIL FROM
    fwrite($socket, "MAIL FROM:<$smtp_user>\r\n");
    $logs[] = "C: MAIL FROM:<$smtp_user>";
    $response = fgets($socket, 515);
    $logs[] = "S: " . trim($response);

    if (substr($response, 0, 3) != '250') {
        $logs[] = "❌ MAIL FROM failed";
        fclose($socket);
        return;
    }

    // RCPT TO
    $testEmail = 'test@example.com'; // We won't actually send, just test auth
    fwrite($socket, "RCPT TO:<$testEmail>\r\n");
    $logs[] = "C: RCPT TO:<$testEmail>";
    $response = fgets($socket, 515);
    $logs[] = "S: " . trim($response);

    if (substr($response, 0, 3) != '250') {
        $logs[] = "❌ RCPT TO failed (recipient rejected)";
    } else {
        $logs[] = "✅ Recipient accepted";
    }

    // ROLLBACK - don't actually send
    fwrite($socket, "RSET\r\n");
    $logs[] = "C: RSET (rolling back test)";
    $response = fgets($socket, 515);

    // QUIT
    fwrite($socket, "QUIT\r\n");
    $logs[] = "C: QUIT";
    $response = fgets($socket, 515);
    $logs[] = "S: " . trim($response);

    fclose($socket);
    $logs[] = "\n✅ SMTP connection test PASSED - Authentication works!";
    $logs[] = "Email sending should work if there are no other issues.";
}
?>
</body>
</html>
