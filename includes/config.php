<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ticketdesk');
define('SITE_URL', 'http://localhost/ticketdesk');

if (session_status() === PHP_SESSION_NONE) session_start();

try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('<div style="font-family:sans-serif;padding:40px;background:#1a1a2e;color:#ef9a9a;text-align:center">
        <h2>&#9888;&#65039; Database Connection Failed</h2>
        <p>'.htmlspecialchars($e->getMessage()).'</p>
        <p style="color:#90a4ae;font-size:14px">Check your config.php database settings.</p>
    </div>');
}

function redirect($url) { header("Location: $url"); exit; }
function isLoggedIn() { return isset($_SESSION['user_id']); }
function isAdmin() { return isset($_SESSION['role']) && $_SESSION['role'] === 'admin'; }
function requireLogin() { if (!isLoggedIn()) redirect(SITE_URL.'/login.php'); }
function requireAdmin() { requireLogin(); if (!isAdmin()) redirect(SITE_URL.'/employee/dashboard.php'); }
function sanitize($val) { return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8'); }

function generateTicketNo($pdo) {
    $row = $pdo->query("SELECT COUNT(*) as cnt FROM tickets")->fetch();
    return 'TKT-' . (1000 + $row['cnt'] + 1);
}

function logTicketAction($pdo, $ticketId, $action, $doneBy, $note = '') {
    $pdo->prepare("INSERT INTO ticket_logs (ticket_id,action,done_by,note) VALUES (?,?,?,?)")
        ->execute([$ticketId, $action, $doneBy, $note]);
}

function getPriorityBadge($priority) {
    $map = [
        'critical' => ['label' => 'Critical', 'class' => 'critical'],
        'high'     => ['label' => 'High',     'class' => 'high'],
        'medium'   => ['label' => 'Medium',   'class' => 'medium'],
        'low'      => ['label' => 'Low',      'class' => 'low'],
    ];
    $p = $map[$priority] ?? ['label' => ucfirst($priority), 'class' => 'low'];
    return '<span class="priority '.$p['class'].'">'.$p['label'].'</span>';
}

function getStatusBadge($status) {
    $map = [
        'open'        => ['label' => 'Open',        'class' => 'open'],
        'in-progress' => ['label' => 'In Progress', 'class' => 'in-progress'],
        'resolved'    => ['label' => 'Resolved',    'class' => 'resolved'],
        'closed'      => ['label' => 'Closed',      'class' => 'closed'],
    ];
    $s = $map[$status] ?? ['label' => ucfirst($status), 'class' => 'open'];
    return '<span class="status '.$s['class'].'">'.$s['label'].'</span>';
}

function timeAgo($datetime) {
    $now  = new DateTime();
    $then = new DateTime($datetime);
    $diff = $now->diff($then);
    if ($diff->days > 7)  return $then->format('d M Y');
    if ($diff->days >= 1) return $diff->days . 'd ago';
    if ($diff->h >= 1)    return $diff->h . 'h ago';
    if ($diff->i >= 1)    return $diff->i . 'm ago';
    return 'Just now';
}