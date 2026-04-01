<?php
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
} else {
    // ── 💻 LOCALHOST (Development) ──
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');             // ← blank for XAMPP, or enter your password
    define('DB_NAME', 'ticketdesk');   // ✅ matches your local phpMyAdmin DB name
    define('DB_PORT', '3306');
    define('SITE_URL', 'http://localhost/ticketdesk');
}

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
function sanitize($val) { return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8'); }
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
?>