<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();
$admin_notif_count = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE emp_id=" . (int)$_SESSION['user_id'] . " AND is_read=0")->fetchColumn();

// Stats
$total    = $pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn();
$critical = $pdo->query("SELECT COUNT(*) FROM tickets WHERE priority='critical' AND status NOT IN ('resolved','closed')")->fetchColumn();
$inprog   = $pdo->query("SELECT COUNT(*) FROM tickets WHERE status='in-progress'")->fetchColumn();
$resolved = $pdo->query("SELECT COUNT(*) FROM tickets WHERE status IN ('resolved','closed')")->fetchColumn();

// Recent tickets
$tickets = $pdo->query("
    SELECT t.*, e.name as emp_name, e.department
    FROM tickets t
    JOIN employees e ON t.emp_id = e.id
    ORDER BY t.created_at DESC
    LIMIT 10
")->fetchAll();

function avatarColor($name) {
    $colors = ['#5552DD','#7B7AFF','#10B981','#F59E0B','#3B82F6','#EC4899','#8B5CF6','#14B8A6'];
    $h = 0; foreach (str_split($name) as $c) $h += ord($c);
    return $colors[$h % count($colors)];
}
function initials($name) {
    $parts = explode(' ', $name);
    return strtoupper(substr($parts[0],0,1) . (isset($parts[1]) ? substr($parts[1],0,1) : ''));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Admin Dashboard — TicketDesk</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="topbar">
  <div class="logo"><div class="logo-icon"><i class="fa-solid fa-computer"></i></div>Ticket<span>Desk</span> <span style="font-size:0.7rem;color:var(--text-muted);margin-left:6px;font-weight:400">ADMIN</span></div>
  <div class="topbar-nav">
    <a href="dashboard.php" class="active">Dashboard</a>
    <a href="tickets.php">Tickets</a>
    <a href="assets.php">Assets</a>
    <a href="employees.php">Employees</a>
    <a href="reports.php">Reports</a>
  </div>
  <div class="topbar-right">
    <div class="user">
      <div class="avatar"><?= initials($_SESSION['name']) ?></div>
      <?= sanitize($_SESSION['name']) ?>
    </div>
    <a href="<?= SITE_URL ?>/logout.php" class="btn btn-ghost btn-sm">Logout</a>
  </div>
</div>

<div class="shell">
  <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>
  <main>
    <div class="page-header">
      <div class="breadcrumb">TICKETDESK / <span>DASHBOARD</span></div>
      <h1>IT Support Dashboard</h1>
      <p>Welcome back, <?= sanitize($_SESSION['name']) ?> — here's your system overview</p>
    </div>

    <div class="stats">
      <div class="stat-card c-blue">
        <div class="stat-label">Total Tickets</div>
        <div class="stat-value"><?= $total ?></div>
        <div class="stat-sub">All time</div>
      </div>
      <div class="stat-card c-red">
        <div class="stat-label">Critical Open</div>
        <div class="stat-value"><?= $critical ?></div>
        <div class="stat-sub">Needs immediate action</div>
      </div>
      <div class="stat-card c-gold">
        <div class="stat-label">In Progress</div>
        <div class="stat-value"><?= $inprog ?></div>
        <div class="stat-sub">Being worked on</div>
      </div>
      <div class="stat-card c-green">
        <div class="stat-label">Resolved</div>
        <div class="stat-value"><?= $resolved ?></div>
        <div class="stat-sub">Successfully closed</div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <div class="card-title">Recent Tickets</div>
        <a href="tickets.php" class="btn btn-ghost btn-sm">View All →</a>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Ticket ID</th><th>Subject</th><th>Employee</th><th>Department</th>
              <th>Priority</th><th>Status</th><th>Created</th><th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($tickets as $t): ?>
            <tr>
              <td class="ticket-id"><?= $t['ticket_no'] ?></td>
              <td style="max-width:220px"><?= sanitize($t['subject']) ?></td>
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
              <td><a href="ticket_detail.php?id=<?= $t['id'] ?>" class="btn btn-ghost btn-xs">View</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($tickets)): ?>
              <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:2rem">No tickets yet.</td></tr>
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