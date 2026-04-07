<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
if (isAdmin()) redirect(SITE_URL . '/admin/dashboard.php');

$uid = $_SESSION['user_id'];
$status = $_GET['status'] ?? '';

// Unread notification count
$notif_count = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE emp_id=? AND is_read=0");
$notif_count->execute([$uid]);
$notif_count = (int)$notif_count->fetchColumn();

// Get assigned assets count
$assets_count = $pdo->prepare("SELECT COUNT(*) FROM asset_assignments aa JOIN assets a ON aa.asset_id = a.id WHERE aa.emp_id = ? AND aa.returned_at IS NULL");
$assets_count->execute([$uid]);
$assets_count = (int)$assets_count->fetchColumn();

// Stats (always show overall counts regardless of filter)
$my_total    = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE emp_id=?"); $my_total->execute([$uid]); $my_total = $my_total->fetchColumn();
$my_open     = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE emp_id=? AND status='open'"); $my_open->execute([$uid]); $my_open = $my_open->fetchColumn();
$my_inprog   = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE emp_id=? AND status='in-progress'"); $my_inprog->execute([$uid]); $my_inprog = $my_inprog->fetchColumn();
$my_resolved = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE emp_id=? AND status IN ('resolved','closed')"); $my_resolved->execute([$uid]); $my_resolved = $my_resolved->fetchColumn();

// Build query with optional status filter
$sql = "SELECT * FROM tickets WHERE emp_id=?";
$params = [$uid];

if ($status === 'open') {
    $sql .= " AND status='open'";
} elseif ($status === 'in-progress') {
    $sql .= " AND status='in-progress'";
} elseif ($status === 'resolved') {
    $sql .= " AND status IN ('resolved','closed')";
}

$sql .= " ORDER BY created_at DESC LIMIT 10";

$tickets = $pdo->prepare($sql);
$tickets->execute($params);
$tickets = $tickets->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>My Dashboard — TicketDesk</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="topbar">
  <div class="logo"><div class="logo-icon"><i class="fa-solid fa-computer"></i></div>Ticket<span>Desk</span></div>
  <div class="topbar-nav">
    <a href="dashboard.php" class="active">My Tickets</a>
    <a href="raise_ticket.php">Raise Ticket</a>
    <a href="profile.php">Profile</a>
  </div>
  <div class="topbar-right">
    <a href="notifications.php" style="position:relative;text-decoration:none;font-size:1.2rem;padding:4px 8px" title="Notifications">
      <i class="fa-solid fa-bell"></i><?php if($notif_count>0): ?><span style="position:absolute;top:0;right:0;background:#EF4444;color:#fff;font-size:0.55rem;font-weight:700;padding:1px 4px;border-radius:10px"><?= $notif_count ?></span><?php endif; ?>
    </a>
    <div class="user">
      <div class="avatar"><?php $p=explode(' ',$_SESSION['name']); echo strtoupper(substr($p[0],0,1).(isset($p[1])?substr($p[1],0,1):'')); ?></div>
      <?= sanitize($_SESSION['name']) ?> <span class="text-muted">(<?= sanitize($_SESSION['emp_id']) ?>)</span>
    </div>
    <a href="<?= SITE_URL ?>/logout.php" class="btn btn-ghost btn-sm">Logout</a>
  </div>
</div>

<div class="shell">
  <div class="sidebar">
    <div class="side-section">
      <div class="side-label">My Account</div>
      <a href="dashboard.php" class="side-item active"><span class="side-icon"><i class="fa-solid fa-list-ul"></i></span> My Tickets</a>
      <a href="raise_ticket.php" class="side-item"><span class="side-icon"><i class="fa-solid fa-plus"></i></span> Raise Ticket</a>
      <a href="assets.php" class="side-item"><span class="side-icon"><i class="fa-solid fa-box"></i></span> My Assets <?php if($assets_count>0): ?><span class="side-badge"><?= $assets_count ?></span><?php endif; ?></a>
      <a href="notifications.php" class="side-item"><span class="side-icon"><i class="fa-solid fa-bell"></i></span> Notifications <?php if($notif_count>0): ?><span class="side-badge"><?= $notif_count ?></span><?php endif; ?></a>
      <a href="profile.php" class="side-item"><span class="side-icon"><i class="fa-solid fa-user"></i></span> My Profile</a>
    </div>
    <div class="side-section">
      <div class="side-label">Filter</div>
      <a href="dashboard.php?status=open" class="side-item <?= $status === 'open' ? 'active' : '' ?>"><span class="side-icon"><i class="fa-solid fa-circle" style="color:#3B82F6"></i></span> Open <span class="side-badge"><?= $my_open ?></span></a>
      <a href="dashboard.php?status=in-progress" class="side-item <?= $status === 'in-progress' ? 'active' : '' ?>"><span class="side-icon"><i class="fa-solid fa-circle" style="color:#F59E0B"></i></span> In Progress <span class="side-badge"><?= $my_inprog ?></span></a>
      <a href="dashboard.php?status=resolved" class="side-item <?= $status === 'resolved' ? 'active' : '' ?>"><span class="side-icon"><i class="fa-solid fa-circle" style="color:#10B981"></i></span> Resolved</a>
    </div>
    <div class="side-section">
      <div class="side-label">Account</div>
      <a href="<?= SITE_URL ?>/logout.php" class="side-item" style="color:var(--red)"><span class="side-icon"><i class="fa-solid fa-right-from-bracket"></i></span> Logout</a>
    </div>
  </div>

  <main>
    <div class="page-header">
      <div class="breadcrumb">TICKETDESK / <span>MY TICKETS</span></div>
      <h1>Welcome, <?= sanitize(explode(' ', $_SESSION['name'])[0]) ?>!</h1>
      <p>Your IT support tickets — track status and updates</p>
    </div>

    <div class="stats">
      <div class="stat-card c-blue"><div class="stat-label">Total Raised</div><div class="stat-value"><?= $my_total ?></div><div class="stat-sub">All time</div></div>
      <div class="stat-card c-gold"><div class="stat-label">Open</div><div class="stat-value"><?= $my_open ?></div><div class="stat-sub">Awaiting IT team</div></div>
      <div class="stat-card c-red"><div class="stat-label">In Progress</div><div class="stat-value"><?= $my_inprog ?></div><div class="stat-sub">Being worked on</div></div>
      <div class="stat-card c-green"><div class="stat-label">Resolved</div><div class="stat-value"><?= $my_resolved ?></div><div class="stat-sub">Completed</div></div>
    </div>

    <div class="card">
      <div class="card-header">
        <div class="card-title">My Tickets</div>
        <a href="raise_ticket.php" class="btn btn-primary btn-sm">+ Raise New Ticket</a>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Ticket ID</th><th>Subject</th><th>Category</th><th>Priority</th><th>Status</th><th>Created</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach ($tickets as $t): ?>
            <tr>
              <td class="ticket-id"><?= $t['ticket_no'] ?></td>
              <td><?= sanitize($t['subject']) ?></td>
              <td style="color:var(--text-muted);font-size:0.78rem"><?= sanitize($t['category']) ?></td>
              <td><?= getPriorityBadge($t['priority']) ?></td>
              <td><?= getStatusBadge($t['status']) ?></td>
              <td class="text-muted mono" style="font-size:0.73rem"><?= date('d M Y', strtotime($t['created_at'])) ?></td>
              <td><a href="view_ticket.php?id=<?= $t['id'] ?>" class="btn btn-ghost btn-xs">View</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($tickets)): ?>
              <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:2.5rem">
                You haven't raised any tickets yet. <a href="raise_ticket.php">Raise your first ticket →</a>
              </td></tr>
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