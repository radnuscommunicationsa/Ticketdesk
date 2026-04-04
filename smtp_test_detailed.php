<?php
/**
 * Detailed SMTP Test - Shows full SMTP conversation
 * This will help us see exactly why Gmail is rejecting the credentials
 */

echo "<!DOCTYPE html>
<html>
<head><title>Detailed SMTP Test</title>
<style>
    body { font-family: 'Consolas', 'Monaco', monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px; }
    .container { max-width: 1000px; margin: 0 auto; }
    .box { background: #252526; border: 1px solid #3e3e42; border-radius: 4px; padding: 15px; margin-bottom: 15px; }
    h1, h2, h3 { color: #569cd6; margin-top: 0; }
    .success { color: #4ec9b0; }
    .error { color: #f44747; }
    .warning { color: #cca700; }
    .info { color: #9cdcfe; }
    pre { background: #1e1e1e; border: 1px solid #3e3e42; padding: 10px; border-radius: 4px; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; }
    .client { color: #c586c0; }
    .server { color: #d7ba7d; }
    .log-entry { margin: 2px 0; font-size: 0.9rem; }
    input, button, select { padding: 8px; margin: 5px; }
    input[type='email'], input[type='password'] { width: 250px; background: #3c3c3c; border: 1px solid #555; color: #d4d4d4; }
    button { background: #0e639c; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 3px; }
    button:hover { background: #1177bb; }
</style>
</head>
<body>
<div class='container'>";

require_once __DIR__ . '/includes/config.php';

// Get credentials
$smtp_host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
$smtp_port = getenv('SMTP_PORT') ?: 587;
$smtp_user = getenv('SMTP_USER');
$smtp_pass = getenv('SMTP_PASS');
$smtp_secure = getenv('SMTP_SECURE') ?: 'tls';

echo "<h1>📡 Detailed SMTP Connection Test</h1>";

echo "<div class='box'>";
echo "<h2>Configuration</h2>";
echo "<pre>";
echo "SMTP Host:  " . htmlspecialchars($smtp_host) . "\n";
echo "SMTP Port:  " . htmlspecialchars($smtp_port) . "\n";
echo "SMTP User:  " . htmlspecialchars($smtp_user) . "\n";
echo "SMTP Pass:  " . ($smtp_pass ? '***SET***' : 'NOT SET') . "\n";
echo "SMTP Secure: " . htmlspecialchars($smtp_secure) . "\n";
echo "</pre>";
echo "</div>";

if (!$smtp_user || !$smtp_pass) {
    echo "<div class='box'><h2 class='error'>❌ Credentials Missing</h2>";
    echo "<p>SMTP_USER or SMTP_PASS is not set.</p>";
    echo "<p>Check your config.php - make sure putenv() lines are present and have values.</p>";
    echo "</div>";
    exit;
}

echo "<div class='box'>";
echo "<h2>Connection Attempt</h2>";
echo "<form method='POST'>";
echo "<label>Email (username): <input type='email' name='email' value='" . htmlspecialchars($smtp_user) . "'></label><br>";
echo "<label>Password (app password): <input type='password' name='password' value='" . htmlspecialchars($smtp_pass) . "'></label><br>";
echo "<label>Host: <input type='text' name='host' value='" . htmlspecialchars($smtp_host) . "'></label><br>";
echo "<label>Port: <input type='number' name='port' value='" . htmlspecialchars($smtp_port) . "'></label><br>";
echo "<button type='submit'>🔁 Test Connection</button>";
echo "</form>";

// Allow override via POST
if ($_POST) {
    $smtp_user = $_POST['email'] ?: $smtp_user;
    $smtp_pass = $_POST['password'] ?: $smtp_pass;
    $smtp_host = $_POST['host'] ?: $smtp_host;
    $smtp_port = $_POST['port'] ?: $smtp_port;
}

echo "<h3>SMTP Conversation Log</h3>";
echo "<pre style='background: #0e0e0e; border-left: 3px solid #0e639c; padding-left: 10px;'>";

// Start connection
$host = $smtp_host;
$port = $smtp_port;
$username = $smtp_user;
$password = $smtp_pass;
$secure = $smtp_secure;

function log_msg($type, $msg) {
    $color = $type == 'C' ? '#c586c0' : ($type == 'S' ? '#d7ba7d' : '#569cd6');
    echo "<span class='log-entry' style='color: $color;'>" . htmlspecialchars($msg) . "</span>\n";
}

log_msg('I', "Connecting to $host:$port...");

$socket = @fsockopen($host, $port, $errno, $errstr, 10);

if (!$socket) {
    log_msg('E', "❌ Connection failed: $errstr ($errno)");
    echo "</pre></div>";
    echo "<div class='box'><h2 class='error'>Connection Failed</h2>";
    echo "<ul>";
    echo "<li>Check internet connection</li>";
    echo "<li>Check firewall isn't blocking port $port</li>";
    echo "<li>Verify SMTP_HOST is correct: $host</li>";
    echo "<li>For Gmail, use smtp.gmail.com and port 587</li>";
    echo "</ul>";
    echo "</div>";
    exit;
}

log_msg('S', "✅ Connected successfully");

// Set timeout
stream_set_timeout($socket, 15);

// Read initial response
$response = fgets($socket, 515);
log_msg('S', "Server: " . trim($response));

if (substr($response, 0, 3) != '220') {
    log_msg('E', "❌ Unexpected initial response");
}

// EHLO
$ehlo = "EHLO " . gethostname() . "\r\n";
fwrite($socket, $ehlo);
log_msg('C', "C: EHLO " . gethostname());

// Read multi-line EHLO response
while ($line = fgets($socket, 515)) {
    log_msg('S', "S: " . trim($line));
    if (substr($line, 3, 1) == ' ') break; // Last line has space after code
}

// TLS if needed
if ($secure === 'tls' && $port == 587) {
    log_msg('I', "→ Starting TLS...");
    fwrite($socket, "STARTTLS\r\n");
    log_msg('C', "C: STARTTLS");
    $response = fgets($socket, 515);
    log_msg('S', "S: " . trim($response));

    if (substr($response, 0, 3) == '220') {
        if (stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            log_msg('S', "✅ TLS encryption enabled");

            // EHLO again after TLS
            fwrite($socket, $ehlo);
            log_msg('C', "C: EHLO (after TLS)");
            while ($line = fgets($socket, 515)) {
                log_msg('S', "S: " . trim($line));
                if (substr($line, 3, 1) == ' ') break;
            }
        } else {
            log_msg('E', "❌ Failed to enable TLS");
        }
    } else {
        log_msg('E', "❌ STARTTLS rejected: " . trim($response));
    }
}

// AUTH LOGIN
log_msg('I', "→ Authenticating...");
fwrite($socket, "AUTH LOGIN\r\n");
log_msg('C', "C: AUTH LOGIN");
$response = fgets($socket, 515);
log_msg('S', "S: " . trim($response));

if (substr($response, 0, 3) != '334') {
    log_msg('E', "❌ AUTH not accepted. Server might not support AUTH LOGIN or SSL required.");
} else {
    // Send username
    fwrite($socket, base64_encode($username) . "\r\n");
    log_msg('C', "C: [username base64]");
    $response = fgets($socket, 515);
    log_msg('S', "S: " . trim($response));

    $usernameStatus = substr($response, 0, 3);

    if ($usernameStatus == '334') {
        // Send password
        fwrite($socket, base64_encode($password) . "\r\n");
        log_msg('C', "C: [password base64]");
        $response = fgets($socket, 515);
        log_msg('S', "S: " . trim($response));

        $authStatus = substr($response, 0, 3);

        if ($authStatus == '235') {
            log_msg('S', "✅ Authentication successful!");
        } else {
            log_msg('E', "❌ Authentication FAILED with code $authStatus");
            log_msg('E', "Error: " . trim($response));

            // Common Gmail error codes
            if (strpos($response, '535') !== false) {
                log_msg('E', "\n🔍 DIAGNOSIS: Gmail rejected credentials");
                log_msg('E', "Possible causes:");
                log_msg('E', "  1. Not using App Password (using regular password)");
                log_msg('E', "  2. 2FA not enabled on account");
                log_msg('E', "  3. App password was generated for different account/email");
                log_msg('E', "  4. Google blocked sign-in (check email for security alert)");
                log_msg('E', "  5. App password expired/revoked (generate new one)");
                log_msg('E', "  6. Trying to use this from suspicious location/IP");
            }
        }
    } else if ($usernameStatus == '535') {
        log_msg('E', "❌ Username rejected immediately");
        log_msg('E', "Check: Is your email address correct? Do you have 2FA enabled?");
    } else {
        log_msg('E', "❌ Username challenge failed: $usernameStatus");
    }

    // Continue with mail flow if authenticated
    if ($authStatus == '235') {
        log_msg('I', "→ Sending email...");

        // MAIL FROM
        fwrite($socket, "MAIL FROM:<$username>\r\n");
        log_msg('C', "C: MAIL FROM:<$username>");
        $response = fgets($socket, 515);
        log_msg('S', "S: " . trim($response));

        if (substr($response, 0, 3) == '250') {
            // RCPT TO
            $testTo = 'test@example.com';
            fwrite($socket, "RCPT TO:<$testTo>\r\n");
            log_msg('C', "C: RCPT TO:<$testTo>");
            $response = fgets($socket, 515);
            log_msg('S', "S: " . trim($response));

            if (substr($response, 0, 3) == '250') {
                log_msg('S', "✅ Recipient accepted (would deliver email)");

                // Cancel before actually sending
                fwrite($socket, "RSET\r\n");
                log_msg('C', "C: RSET (reset - not actually sending)");
                $response = fgets($socket, 515);
                log_msg('S', "S: " . trim($response));
            }
        }

        // QUIT
        fwrite($socket, "QUIT\r\n");
        log_msg('C', "C: QUIT");
        $response = fgets($socket, 515);
        log_msg('S', "S: " . trim($response));
    }
}

fclose($socket);

echo "</pre>";

echo "<div class='box'>";
echo "<h2>🔍 Analysis</h2>";

// Check if we logged success
$lastLines = array_slice(explode("\n", ob_get_clean()), -10);
$hadSuccess = false;
$hadAuthFailure = false;

foreach ($lastLines as $line) {
    if (strpos($line, 'Authentication successful') !== false) $hadSuccess = true;
    if (strpos($line, 'Authentication FAILED') !== false) $hadAuthFailure = true;
}

if ($hadAuthFailure) {
    echo "<p class='error'><strong>❌ AUTHENTICATION FAILED</strong></p>";
    echo "<h3>Next Steps:</h3>";
    echo "<ol>";
    echo "<li><strong>Enable 2FA</strong> on your Google account</li>";
    echo "<li>Go to <a href='https://myaccount.google.com/security' target='_blank'>Google Security</a></li>";
    echo "<li>Wait 60 seconds after enabling 2FA</li>";
    echo "<li>Generate a <strong>NEW</strong> App Password (old one may be invalid)</li>";
    echo "<li>Copy the 16-digit password exactly as shown</li>";
    echo "<li>Paste into config.php (line 71)</li>";
    echo "<li>Check Gmail for security alert - you may need to 'Allow' the sign-in</li>";
    echo "</ol>";
} elseif ($hadSuccess) {
    echo "<p class='success'><strong>✅ AUTHENTICATION SUCCESSFUL</strong></p>";
    echo "<p>Your SMTP credentials work! The problem might be elsewhere.</p>";
    echo "<ul>";
    echo "<li>Check the test_email_config.php test page</li>";
    echo "<li>Verify the email sending logic is being called</li>";
    echo "<li>Check error logs for other issues</li>";
    echo "</ul>";
} else {
    echo "<p class='warning'><strong>⚠️ Connection did not complete</strong></p>";
    echo "<p>Review the SMTP conversation above. Look for error messages.</p>";
}

echo "</div>";

echo "</div></body></html>";