<?php
require_once 'includes/config.php';

// Enable full debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h2>Direct SMTP Connection Test</h2>";
echo "<pre>";

// Get SMTP settings
$host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
$port = getenv('SMTP_PORT') ?: 587;
$user = getenv('SMTP_USER') ?: 'radnuscommunicationsa@gmail.com';
$pass = getenv('SMTP_PASS');
$secure = getenv('SMTP_SECURE') ?: 'tls';

echo "Attempting to connect to:\n";
echo "Host: $host\n";
echo "Port: $port\n";
echo "User: $user\n";
echo "Pass: " . ($pass ? substr($pass, 0, 4) . '...' : 'NOT SET') . "\n";
echo "Secure: $secure\n\n";

if (!$pass) {
    die("ERROR: SMTP_PASS is not set!\n");
}

/* Connect */
echo "1. Opening socket to $host:$port...\n";
$socket = @fsockopen(($secure === 'ssl' && $port == 465 ? 'tls://' : '') . $host, $port, $errno, $errstr, 10);

if (!$socket) {
    die("❌ CONNECTION FAILED: $errstr ($errno)\n");
}

echo "✅ Connected!\n";
stream_set_timeout($socket, 10);

/* Read initial response */
$response = '';
while ($line = fgets($socket, 515)) {
    $response .= $line;
    echo "Server: " . trim($line) . "\n";
    if (substr($line, 3, 1) == ' ') break;
}

if (substr(trim($response), 0, 3) != '220') {
    die("❌ Unexpected initial response: " . trim($response) . "\n");
}

/* Send EHLO */
echo "\n2. Sending EHLO...\n";
fwrite($socket, "EHLO " . gethostname() . "\r\n");
echo "Client: EHLO " . gethostname() . "\n";

while ($line = fgets($socket, 515)) {
    echo "Server: " . trim($line) . "\n";
    if (substr($line, 3, 1) == ' ') break;
}

/* Start TLS if needed */
if ($secure === 'tls' && $port == 587) {
    echo "\n3. Starting TLS...\n";
    fwrite($socket, "STARTTLS\r\n");
    echo "Client: STARTTLS\n";
    $response = fgets($socket, 515);
    echo "Server: " . trim($response) . "\n";

    if (substr($response, 0, 3) != '220') {
        die("❌ STARTTLS failed: " . trim($response) . "\n");
    }

    if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        die("❌ Failed to enable TLS encryption\n");
    }
    echo "✅ TLS enabled\n";

    /* Send EHLO again */
    fwrite($socket, "EHLO " . gethostname() . "\r\n");
    echo "Client: EHLO (post-TLS)\n";
    while ($line = fgets($socket, 515)) {
        echo "Server: " . trim($line) . "\n";
        if (substr($line, 3, 1) == ' ') break;
    }
}

/* Authenticate */
echo "\n4. Authenticating...\n";
fwrite($socket, "AUTH LOGIN\r\n");
echo "Client: AUTH LOGIN\n";
$response = fgets($socket, 515);
echo "Server: " . trim($response) . "\n";

if (substr($response, 0, 3) != '334') {
    die("❌ AUTH not accepted: " . trim($response) . "\n");
}

/* Send username */
fwrite($socket, base64_encode($user) . "\r\n");
echo "Client: (username)\n";
$response = fgets($socket, 515);
echo "Server: " . trim($response) . "\n";

if (substr($response, 0, 3) != '334') {
    die("❌ Username not accepted: " . trim($response) . "\n");
}

/* Send password */
fwrite($socket, base64_encode($pass) . "\r\n");
echo "Client: (password)\n";
$response = fgets($socket, 515);
echo "Server: " . trim($response) . "\n";

if (substr($response, 0, 3) != '235') {
    die("❌ AUTHENTICATION FAILED: " . trim($response) . "\n");
}

echo "✅ Authenticated!\n";

/* End */
fwrite($socket, "QUIT\r\n");
fclose($socket);
echo "\n✅ SMTP connection successful! Email sending would work.\n";

echo "</pre>";
?>
