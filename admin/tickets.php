<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();
$admin_notif_count = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE emp_id=" . (int)$_SESSION['user_id'] . " AND is_read=0")->fetchColumn();

// Filters
$status   = $_GET['status']   ?? '';
$priority = $_GET['priority'] ?? '';
$search   = $_GET['q']        ?? '';

$where = ['1=1'];
$params = [];

if ($status)   { $where[] = 't.status = ?';   $params[] = $status; }
if ($priority) { $where[] = 't.priority = ?'; $params[] = $priority; }
if ($search)   {
    $where[] = '(t.ticket_no LIKE ? OR t.subject LIKE ? OR e.name LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}

$sql = "SELECT t.*, e.name as emp_name, e.department
        FROM tickets t
        JOIN employees e ON t.emp_id = e.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY FIELD(t.priority,'critical','high','medium','low'), t.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

function avatarColor($n) {
    $c=['#1565c0','#6a1b9a','#00695c','#c62828','#e65100','#2e7d32','#37474f','#4527a0'];
    $h=0; foreach(str_split($n) as $ch) $h+=ord($ch); return $c[$h%count($c)];
}
function initials($n) {
    $p=explode(' ',$n); return strtoupper(substr($p[0],0,1).(isset($p[1])?substr($p[1],0,1):''));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>All Tickets — TicketDesk Admin</title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="topbar">
  <div class="logo"><div class="logo-icon">🖥</div>Ticket<span>Desk</span> <span style="font-size:0.7rem;color:var(--text-muted);margin-left:6px;font-weight:400">ADMIN</span></div>
  <div class="topbar-nav">
    <a href="dashboard.php">Dashboard</a>
    <a href="tickets.php" class="active">Tickets</a>
    <a href="assets.php">Assets</a>
    <a href="employees.php">Employees</a>
    <a href="reports.php">Reports</a>
  </div>
  <div class="topbar-right">
    <a href="<?= SITE_URL ?>/admin/admin_notifications.php" style="position:relative;text-decoration:none;font-size:1.2rem;padding:4px 8px" title="Notifications">🔔<?php if($admin_notif_count>0): ?><span style="position:absolute;top:0;right:0;background:#c62828;color:#fff;font-size:0.55rem;font-weight:700;padding:1px 4px;border-radius:10px"><?= $admin_notif_count ?></span><?php endif; ?></a>
    <a href="<?= SITE_URL ?>/logout.php" class="btn btn-ghost btn-sm">Logout</a>
  </div>
</div>
<div class="shell">
  <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>
  <main>
    <div class="page-header">
      <div class="breadcrumb">TICKETDESK / <span>TICKETS</span></div>
      <h1>All Tickets</h1>
      <p>Manage and update all IT support requests</p>
    </div>

    <form method="GET" style="display:contents">
      <div class="filters">
        <div class="search-input-wrap">
          <span class="search-icon">🔍</span>
          <input type="text" name="q" placeholder="Search tickets..." value="<?= sanitize($search) ?>"/>
        </div>
        <a href="tickets.php" class="filter-chip <?= !$status&&!$priority?'active':'' ?>">All</a>
        <a href="tickets.php?status=open" class="filter-chip <?= $status==='open'?'active':'' ?>">Open</a>
        <a href="tickets.php?status=in-progress" class="filter-chip <?= $status==='in-progress'?'active':'' ?>">In Progress</a>
        <a href="tickets.php?status=resolved" class="filter-chip <?= $status==='resolved'?'active':'' ?>">Resolved</a>
        <a href="tickets.php?status=closed" class="filter-chip <?= $status==='closed'?'active':'' ?>">Closed</a>
        <a href="tickets.php?priority=critical" class="filter-chip <?= $priority==='critical'?'active':'' ?>">🔴 Critical</a>
        <button type="submit" class="btn btn-ghost btn-sm">Search</button>
      </div>
    </form>

    <div class="card">
      <div class="card-header">
        <div class="card-title">Tickets (<?= count($tickets) ?>)</div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>ID</th><th>Subject</th><th>Employee</th><th>Dept</th><th>Priority</th><th>Status</th><th>Created</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($tickets as $t): ?>
            <tr>
              <td class="ticket-id"><?= $t['ticket_no'] ?></td>
              <td style="max-width:200px"><?= sanitize($t['subject']) ?></td>
              <td>
                <div class="flex gap-2">
                  <div class="emp-avatar" style="background:<?= avatarColor($t['emp_name']) ?>"><?= initials($t['emp_name']) ?></div>
                  <?= sanitize($t['emp_name']) ?>
                </div>
              </td>
              <td><span class="dept-badge"><?= sanitize($t['department']) ?></span></td>
              <td><?= getPriorityBadge($t['priority']) ?></td>
              <td><?= getStatusBadge($t['status']) ?></td>
              <td class="text-muted mono" style="font-size:0.73rem"><?= date('d M Y', strtotime($t['created_at'])) ?></td>
              <td style="display:flex;gap:6px">
                <a href="ticket_detail.php?id=<?= $t['id'] ?>" class="btn btn-ghost btn-xs">View</a>
                <a href="ticket_detail.php?id=<?= $t['id'] ?>" class="btn btn-primary btn-xs">Update</a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($tickets)): ?>
              <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:2rem">No tickets found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>
<script src="<?= SITE_URL ?>/assets/js/theme.js"></script>
</body>
</html>