<?php
// Set timezone to prevent token expiry issues
date_default_timezone_set('Asia/Kolkata');

// ============================================================
// ✅ AUTO DETECT: Works on BOTH Localhost & Railway
// ============================================================

// ── Strongest possible Railway detection ──
$railway_host   = getenv('MYSQLHOST');
$railway_domain = getenv('RAILWAY_PUBLIC_DOMAIN');
$railway_env    = getenv('RAILWAY_ENVIRONMENT');
$railway_user   = getenv('MYSQLUSER');

$is_railway = (
    !empty($railway_host) ||
    !empty($railway_domain) ||
    !empty($railway_env) ||
    !empty($railway_user)
);

if ($is_railway) {
    // ── 🚂 RAILWAY (Production) ──
    define('DB_HOST', !empty($railway_host) ? $railway_host : 'mysql.railway.internal');
    define('DB_USER', getenv('MYSQLUSER')     ?: 'root');
    define('DB_PASS', getenv('MYSQLPASSWORD') ?: '');
    define('DB_NAME', getenv('MYSQLDATABASE') ?: 'railway');
    define('DB_PORT', getenv('MYSQLPORT')     ?: '3306');
    define('SITE_URL', !empty($railway_domain)
        ? 'https://' . $railway_domain
        : 'https://ticketdesk-production.up.railway.app');
    define('SITE_NAME', 'TicketDesk');
} else {
    // ── 💻 LOCALHOST (Development) ──
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');             // ← blank for XAMPP, or enter your password
    define('DB_NAME', 'ticketdesk');   // ✅ matches your local phpMyAdmin DB name
    define('DB_PORT', '3306');
    define('SITE_URL', 'http://localhost/ticketdesk');
    define('SITE_NAME', 'TicketDesk');
}

// ── ⚙️ EMAIL CONFIGURATION ──
// Railway: Use environment variables for SMTP
// Localhost: Will use PHP mail() function (configure sendmail in xampp/sendmail/)
// OR can use SMTP directly by setting SMTP_* environment variables
define('EMAIL_DEBUG', getenv('EMAIL_DEBUG') ?: true); // Set to true for detailed logs

// ════════════════════════════════════════════════════════════════════════════════
// EMAIL CONFIGURATION
// ════════════════════════════════════════════════════════════════════════════════
// Railway: Set SMTP_* environment variables (see RAILWAY_EMAIL_SETUP.md)
// Localhost: Configure XAMPP sendmail or set SMTP_* variables
// Default EMAIL_DEBUG to false for production safety
define('EMAIL_DEBUG', getenv('EMAIL_DEBUG') === 'true'); // Only true if explicitly set

// ════════════════════════════════════════════════════════════════════════════════
// ⚡ EMAIL CONFIGURATION - GET GMAIL APP PASSWORD FIRST!
// ════════════════════════════════════════════════════════════════════════════════
// 1. Get 2FA enabled: https://myaccount.google.com/security
// 2. Click "App passwords"
// 3. Generate for "TicketDesk" (16-digit code)
// 4. Paste below REPLACING the placeholder
// ════════════════════════════════════════════════════════════════════════════════


// ════════════════════════════════════════════════════════════════════════════════
if (session_status() === PHP_SESSION_NONE) session_start();

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function verify_csrf() {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        die('Invalid CSRF token. Please refresh the page and try again.');
    }
}

function csrf_input() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
}

try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    // Log the error for debugging but show generic message to user
    error_log('Database Connection Error: ' . $e->getMessage() . ' - Host: ' . DB_HOST . ' - DB: ' . DB_NAME);
    $env_info = $is_railway ? 'Production' : 'Development';
    die('<div style="font-family:sans-serif;padding:40px;background:#1a1a2e;color:#ef9a9a;text-align:center">
        <h2>Database Connection Failed ❌</h2>
        <p>We are experiencing technical difficulties. Our team has been notified.</p>
        <p style="font-size:0.85rem;margin-top:8px;color:#aaa">Environment: ' . $env_info . '</p>
    </div>');
}

function redirect($url) { header("Location: $url"); exit; }
function isLoggedIn() { return isset($_SESSION['user_id']); }
function isAdmin() { return isset($_SESSION['role']) && $_SESSION['role'] === 'admin'; }
function requireLogin() { if (!isLoggedIn()) redirect(SITE_URL . '/login.php'); }
function requireAdmin() { requireLogin(); if (!isAdmin()) redirect(SITE_URL . '/employee/dashboard.php'); }
function sanitize($val) {
    return htmlspecialchars(trim($val ?? ''), ENT_QUOTES, 'UTF-8');
}
function generateTicketNo($pdo) { $row = $pdo->query("SELECT COUNT(*) as cnt FROM tickets")->fetch(); return 'TKT-' . (1000 + $row['cnt'] + 1); }
function logTicketAction($pdo, $ticketId, $action, $doneBy, $note = '') { $pdo->prepare("INSERT INTO ticket_logs (ticket_id,action,done_by,note) VALUES (?,?,?,?)")->execute([$ticketId, $action, $doneBy, $note]); }
function getPriorityBadge($priority) {
    $map = [
        'critical'=>['Critical','critical','#EF4444'],
        'high'=>['High','high','#F59E0B'],
        'medium'=>['Medium','medium','#FBBF24'],
        'low'=>['Low','low','#10B981']
    ];
    $p = $map[$priority] ?? [ucfirst($priority),'low','#10B981'];
    return '<span class="priority '.$p[1].'"><i class="fa-solid fa-circle" style="font-size:0.5em;color:'.$p[2].'"></i> '.$p[0].'</span>';
}
function getStatusBadge($status) { $map = ['open'=>['Open','open'],'in-progress'=>['In Progress','in-progress'],'resolved'=>['Resolved','resolved'],'closed'=>['Closed','closed']]; $s = $map[$status] ?? [ucfirst($status),'open']; return '<span class="status '.$s[1].'">'.$s[0].'</span>'; }
function timeAgo($datetime) { $now = new DateTime(); $then = new DateTime($datetime); $diff = $now->diff($then); if ($diff->days > 7) return $then->format('d M Y'); if ($diff->days >= 1) return $diff->days . 'd ago'; if ($diff->h >= 1) return $diff->h . 'h ago'; if ($diff->i >= 1) return $diff->i . 'm ago'; return 'Just now'; }

/* ═════════════════════════════════════════════════════════════════════════════
   PASSWORD RESET FUNCTIONS
══════════════════════════════════════════════════════════════════════════════ */

function generateResetToken($pdo, $emp_id) {
    // Generate secure random token
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour')); // 1 hour expiry

    // Store in database
    $stmt = $pdo->prepare("INSERT INTO password_reset_tokens (emp_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$emp_id, $token, $expires]);

    return $token;
}

function verifyResetToken($pdo, $token) {
    $stmt = $pdo->prepare("
        SELECT prt.*, e.id AS employee_id, e.email, e.name, e.emp_id
        FROM password_reset_tokens prt
        JOIN employees e ON prt.emp_id = e.id
        WHERE prt.token = ?
          AND prt.used = 0
          AND prt.expires_at > NOW()
    ");
    $stmt->execute([$token]);
    return $stmt->fetch();
}

function markTokenUsed($pdo, $token) {
    $stmt = $pdo->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?");
    return $stmt->execute([$token]);
}

function sendPasswordResetEmail($to, $name, $token) {
    $resetLink = SITE_URL . "/reset_password.php?token=" . $token;
    $subject = "TicketDesk - Password Reset Request";

    // HTML email template
    $message = "
    <html>
    <head><title>Password Reset</title></head>
    <body style='font-family: Inter, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: linear-gradient(135deg, #5552DD 0%, #7B7AFF 100%); color: white; padding: 20px; border-radius: 10px 10px 0 0; text-align: center;'>
                <h1 style='margin: 0; font-size: 24px;'>TicketDesk</h1>
                <p style='margin: 5px 0 0 0; opacity: 0.9;'>Password Reset</p>
            </div>

            <div style='background: #f8fafc; padding: 30px; border-radius: 0 0 10px 10px; border: 1px solid #e2e8f0; border-top: none;'>
                <p>Hello <strong>" . htmlspecialchars($name) . "</strong>,</p>
                <p>We received a request to reset your password for your TicketDesk account.</p>
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='" . $resetLink . "' style='background: #5552DD; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: 600; display: inline-block;'>
                        Reset Password
                    </a>
                </p>
                <p style='font-size: 0.85rem; color: #64748b;'>
                    <strong>Or copy this link:</strong><br>
                    <code style='background: #e2e8f0; padding: 10px; display: block; word-break: break-all; border-radius: 4px; margin-top: 5px;'>" . $resetLink . "</code>
                </p>
                <p style='font-size: 0.85rem; color: #64748b; margin-top: 20px;'>
                    This link will expire in <strong>1 hour</strong> for security reasons.<br>
                    If you did not request this reset, please ignore this email. Your account remains secure.
                </p>
            </div>

            <div style='text-align: center; margin-top: 30px; font-size: 0.8rem; color: #94a3b8;'>
                <p>© " . date('Y') . " TicketDesk. All rights reserved.</p>
                <p>IT Support Portal</p>
            </div>
        </div>
    </body>
    </html>");

    // Plain text version
    $plainMessage = "TicketDesk - Password Reset\n\n";
    $plainMessage .= "Hello " . $name . ",\n\n";
    $plainMessage .= "We received a request to reset your password for your TicketDesk account.\n\n";
    $plainMessage .= "Reset your password by clicking the link below or copying it into your browser:\n";
    $plainMessage .= $resetLink . "\n\n";
    $plainMessage .= "This link will expire in 1 hour for security reasons.\n";
    $plainMessage .= "If you did not request this reset, please ignore this email. Your account remains secure.\n\n";
    $plainMessage .= "© " . date('Y') . " TicketDesk. All rights reserved.\n";
    $plainMessage .= "IT Support Portal\n";

    // Check for SendGrid API key (preferred - works on Railway)
    $sendgrid_api_key = getenv('SENDGRID_API_KEY');
    $sendgrid_from_email = getenv('SENDGRID_FROM_EMAIL') ?: (getenv('SMTP_USER') ?: SITE_NAME . ' <noreply@' . $_SERVER['SERVER_NAME'] . '>');
    $sendgrid_from_name = getenv('SENDGRID_FROM_NAME') ?: SITE_NAME;

    if ($sendgrid_api_key) {
        // Use SendGrid API
        return sendEmailSendGrid($to, $name, $subject, $message, $plainMessage, [
            'api_key' => $sendgrid_api_key,
            'from_email' => $sendgrid_from_email,
            'from_name' => $sendgrid_from_name
        ], $token);
    }

    // Check for SMTP configuration via environment variables
    $smtp_host = getenv('SMTP_HOST');
    $smtp_port = getenv('SMTP_PORT') ?: 587;
    $smtp_user = getenv('SMTP_USER');
    $smtp_pass = getenv('SMTP_PASS');
    $smtp_secure = getenv('SMTP_SECURE') ?: 'tls';

    if ($smtp_host && $smtp_user && $smtp_pass) {
        // Use SMTP
        return sendEmailSMTP($to, $name, $subject, $message, $plainMessage, [
            'host' => $smtp_host,
            'port' => $smtp_port,
            'username' => $smtp_user,
            'password' => $smtp_pass,
            'secure' => $smtp_secure,
            'from_name' => SITE_NAME
        ], $token);
    } else {
        // No SMTP configured - fail explicitly
        error_log('❌ Email failed: No SMTP configuration found. Set SMTP_HOST, SMTP_USER, SMTP_PASS in Railway Variables.');
        error_log("   Attempted to send to: $to ($name)");
        error_log("   Tip: See RAILWAY_EMAIL_SETUP.md for configuration instructions.");
        return false;
    }
}

function sendEmailSMTP($to, $name, $subject, $htmlMessage, $plainMessage, $smtp, $token = null) {
    $host = $smtp['host'];
    $port = $smtp['port'];
    $username = $smtp['username'];
    $password = $smtp['password'];
    $secure = $smtp['secure'] ?? 'tls';
    $fromName = $smtp['from_name'] ?? SITE_NAME;

    $debug = EMAIL_DEBUG || ($smtp['debug'] ?? false);
    $log = function($msg) use ($debug) {
        if ($debug) error_log("[SMTP DEBUG] $msg");
    };

    try {
        // Connect to SMTP server
        $log("Connecting to $host:$port");
        $socket = @fsockopen(($secure === 'ssl' && $port == 465 ? 'tls://' : '') . $host, $port, $errno, $errstr, 10);

        if (!$socket) {
            throw new Exception("Could not connect to SMTP server: $errstr ($errno)");
        }

        $log("Connected");
        stream_set_timeout($socket, 10);

        // Read initial response (may be multi-line)
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            $log("Server: " . trim($line));
            if (substr($line, 3, 1) == ' ') break; // Last line
        }

        if (substr(trim($response), 0, 3) != '220') {
            throw new Exception("SMTP error: " . trim($response));
        }

        // Send EHLO
        $ehlo = "EHLO " . gethostname() . "\r\n";
        fwrite($socket, $ehlo);
        $log("Client: EHLO " . gethostname());

        // Read EHLO response (can be multi-line)
        while ($line = fgets($socket, 515)) {
            $log("Server: " . trim($line));
            if (substr($line, 3, 1) == ' ') break; // Last line
        }

        // Start TLS if needed (for port 587)
        if ($secure === 'tls' && $port == 587) {
            fwrite($socket, "STARTTLS\r\n");
            $log("Client: STARTTLS");
            $response = fgets($socket, 515);
            $log("Server: " . trim($response));

            if (substr($response, 0, 3) != '220') {
                throw new Exception("STARTTLS failed: " . trim($response));
            }

            // Enable crypto encryption
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception("Failed to enable TLS encryption");
            }
            $log("TLS enabled");

            // Send EHLO again after TLS
            fwrite($socket, $ehlo);
            $log("Client: EHLO (post-TLS)");
            while ($line = fgets($socket, 515)) {
                $log("Server: " . trim($line));
                if (substr($line, 3, 1) == ' ') break;
            }
        }

        // Authenticate
        fwrite($socket, "AUTH LOGIN\r\n");
        $log("Client: AUTH LOGIN");
        $response = fgets($socket, 515);
        $log("Server: " . trim($response));

        if (substr($response, 0, 3) != '334') {
            throw new Exception("AUTH not accepted: " . trim($response));
        }

        // Send username (base64 encoded)
        fwrite($socket, base64_encode($username) . "\r\n");
        $log("Client: (username)");
        $response = fgets($socket, 515);
        $log("Server: " . trim($response));

        if (substr($response, 0, 3) != '334') {
            throw new Exception("Username not accepted: " . trim($response));
        }

        // Send password (base64 encoded)
        fwrite($socket, base64_encode($password) . "\r\n");
        $log("Client: (password)");
        $response = fgets($socket, 515);
        $log("Server: " . trim($response));

        if (substr($response, 0, 3) != '235') {
            throw new Exception("Authentication failed: " . trim($response));
        }
        $log("Authenticated");

        // MAIL FROM
        $from = $username;
        fwrite($socket, "MAIL FROM:<$from>\r\n");
        $log("Client: MAIL FROM:<$from>");
        $response = fgets($socket, 515);
        $log("Server: " . trim($response));

        if (substr($response, 0, 3) != '250') {
            throw new Exception("MAIL FROM failed: " . trim($response));
        }

        // RCPT TO
        fwrite($socket, "RCPT TO:<$to>\r\n");
        $log("Client: RCPT TO:<$to>");
        $response = fgets($socket, 515);
        $log("Server: " . trim($response));

        if (substr($response, 0, 3) != '250') {
            throw new Exception("RCPT TO failed: " . trim($response));
        }

        // DATA
        fwrite($socket, "DATA\r\n");
        $log("Client: DATA");
        $response = fgets($socket, 515);
        $log("Server: " . trim($response));

        if (substr($response, 0, 3) != '354') {
            throw new Exception("DATA not accepted: " . trim($response));
        }

        // Build email headers and body
        $boundary = md5(uniqid(time()));
        $headers = "From: $fromName <$from>\r\n";
        $headers .= "Reply-To: $from\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"" . $boundary . "\"\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        $headers .= "Message-ID: <" . uniqid('', true) . "@" . $_SERVER['SERVER_NAME'] . ">\r\n";
        $headers .= "X-Mailer: TicketDesk SMTP/" . PHP_VERSION . "\r\n";

        // Body
        $body = "--" . $boundary . "\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8; format=flowed\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $plainMessage . "\r\n\r\n";

        $body .= "--" . $boundary . "\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $htmlMessage . "\r\n\r\n";
        $body .= "--" . $boundary . "--\r\n";

        $email = $headers . "\r\n" . $body;

        // Send email data
        fwrite($socket, $email);
        $log("Client: (email content sent)");

        // End data
        fwrite($socket, ".\r\n");
        $log("Client: .");
        $response = fgets($socket, 515);
        $log("Server: " . trim($response));

        if (substr($response, 0, 3) != '250') {
            throw new Exception("DATA failed: " . trim($response));
        }

        // QUIT
        fwrite($socket, "QUIT\r\n");
        $log("Client: QUIT");
        $response = fgets($socket, 515);
        $log("Server: " . trim($response));

        fclose($socket);

        error_log("✅ Email sent via SMTP to: $to ($name)");
        if ($token) {
            error_log("   Reset link: " . SITE_URL . "/reset_password.php?token=" . $token);
        }
        return true;

    } catch (Exception $e) {
        error_log("❌ SMTP error: " . $e->getMessage());
        if (isset($socket) && is_resource($socket)) {
            fclose($socket);
        }
        return false;
    }
}

function sendEmailSendGrid($to, $name, $subject, $htmlMessage, $plainMessage, $sg, $token = null) {
    $apiKey = $sg['api_key'];
    $fromEmail = $sg['from_email'];
    $fromName = $sg['from_name'];

    error_log("📧 SendGrid: Attempting to send email to $to ($name)");

    $data = [
        'personalizations' => [
            [
                'to' => [
                    ['email' => $to, 'name' => $name]
                ],
                'subject' => $subject
            ]
        ],
        'from' => [
            'email' => $fromEmail,
            'name' => $fromName
        ],
        'content' => [
            [
                'type' => 'text/plain',
                'value' => $plainMessage
            ],
            [
                'type' => 'text/html',
                'value' => $htmlMessage
            ]
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.sendgrid.com/v3/mail/send');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("❌ SendGrid cURL error: " . htmlspecialchars($error));
        return false;
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        error_log("✅ Email sent via SendGrid to: $to ($name)");
        if ($token) {
            error_log("   Reset link: " . SITE_URL . "/reset_password.php?token=" . $token);
        }
        return true;
    } else {
        error_log("❌ SendGrid API error: HTTP $httpCode");
        error_log("   Response: " . substr($response, 0, 500));
        return false;
    }
}

function sendEmailMail($to, $name, $subject, $htmlMessage, $plainMessage, $token = null) {
    $boundary = md5(uniqid(time()));

    $headers = "From: " . SITE_NAME . " <noreply@" . $_SERVER['SERVER_NAME'] . ">\r\n";
    $headers .= "Reply-To: noreply@" . $_SERVER['SERVER_NAME'] . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"" . $boundary . "\"\r\n";
    $headers .= "X-Mailer: PHP/" . PHP_VERSION . "\r\n";

    // Plain text part
    $body = "--" . $boundary . "\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= $plainMessage . "\r\n\r\n";

    // HTML part
    $body .= "--" . $boundary . "\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= $htmlMessage . "\r\n\r\n";
    $body .= "--" . $boundary . "--";

    // Try to send
    $sent = mail($to, $subject, $body, $headers);

    if ($sent) {
        error_log("✅ Email sent via mail() to: $to ($name)");
        if ($token) {
            error_log("   Reset link: " . SITE_URL . "/reset_password.php?token=" . $token);
        }
        return true;
    } else {
        error_log("❌ mail() failed to send to: $to");
        error_log("   Subject: $subject");
        error_log("   To: $to");
        error_log("   Headers: " . substr($headers, 0, 200) . "...");
        $lastError = error_get_last();
        error_log("   PHP error: " . ($lastError['message'] ?? 'Unknown error'));
        return false;
    }
}

/* ═════════════════════════════════════════════════════════════════════════════
   HELPER FUNCTIONS - Centralized for reuse across the application
══════════════════════════════════════════════════════════════════════════════ */

function avatarColor($name, $palette = 'admin') {
    // Support multibyte strings for international names
    $name = $name ?? '';
    if (function_exists('mb_str_split')) {
        $chars = mb_str_split($name);
    } else {
        // Fallback for environments without mbstring
        $chars = preg_split('//u', $name, -1, PREG_SPLIT_NO_EMPTY);
    }

    $h = 0;
    foreach ($chars as $c) {
        $h += function_exists('mb_ord') ? mb_ord($c, 'UTF-8') : ord($c);
    }

    // Different palettes for different contexts
    $palettes = [
        'admin' => ['#5552DD', '#7B7AFF', '#10B981', '#F59E0B', '#3B82F6', '#EC4899', '#8B5CF6', '#14B8A6'],
        'employee' => ['#EF4444', '#F59E0B', '#10B981', '#3B82F6', '#EC4899', '#8B5CF6', '#14B8A6', '#5552DD', '#1565c0'],
    ];

    $colors = $palettes[$palette] ?? $palettes['admin'];
    return $colors[$h % count($colors)];
}

function initials($name) {
    $parts = preg_split('/\s+/', trim($name ?? ''));
    $first = $parts[0] ?? '';
    $second = $parts[1] ?? '';
    return strtoupper(substr($first, 0, 1) . substr($second, 0, 1));
}
?>