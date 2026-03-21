<?php
$host = getenv('MYSQLHOST');
$user = getenv('MYSQLUSER');
$pass = getenv('MYSQLPASSWORD');
$name = getenv('MYSQLDATABASE');
$port = getenv('MYSQLPORT') ?: '3306';

define('DB_HOST', $host ?: 'mysql.railway.internal');
define('DB_USER', $user ?: 'root');
define('DB_PASS', $pass ?: 'MirzYGprFgACSPcpHmIFRpsxLxGGFYNw');
define('DB_NAME', $name ?: 'railway');
define('DB_PORT', $port);

define('SITE_URL', getenv('RAILWAY_PUBLIC_DOMAIN')
    ? 'https://' . getenv('RAILWAY_PUBLIC_DOMAIN')
    : 'http://localhost');

if (session_status() === PHP_SESSION_NONE) session_start();

try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => true,
    ]);
} catch (PDOException $e) {
    die('<div style="font-family:sans-serif;padding:40px;background:#1a1a2e;color:#ef9a9a;text-align:center">
        <h2>Database Connection Failed ❌</h2>
        <p>' . htmlspecialchars($e->getMessage()) . '</p>
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
function getPriorityBadge($priority) { $map = ['critical'=>['Critical','critical'],'high'=>['High','high'],'medium'=>['Medium','medium'],'low'=>['Low','low']]; $p = $map[$priority] ?? [ucfirst($priority),'low']; return '<span class="priority '.$p[1].'">'.$p[0].'</span>'; }
function getStatusBadge($status) { $map = ['open'=>['Open','open'],'in-progress'=>['In Progress','in-progress'],'resolved'=>['Resolved','resolved'],'closed'=>['Closed','closed']]; $s = $map[$status] ?? [ucfirst($status),'open']; return '<span class="status '.$s[1].'">'.$s[0].'</span>'; }
function timeAgo($datetime) { $now = new DateTime(); $then = new DateTime($datetime); $diff = $now->diff($then); if ($diff->days > 7) return $then->format('d M Y'); if ($diff->days >= 1) return $diff->days . 'd ago'; if ($diff->h >= 1) return $diff->h . 'h ago'; if ($diff->i >= 1) return $diff->i . 'm ago'; return 'Just now'; }
?>