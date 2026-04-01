<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
if (isAdmin()) redirect(SITE_URL . '/admin/dashboard.php');

$uid = $_SESSION['user_id'];

// Mark all as read when page opens
$pdo->prepare("UPDATE notifications SET is_read=1 WHERE emp_id=?")->execute([$uid]);

// Get all notifications
$notifs = $pdo->prepare("SELECT n.*, t.ticket_no FROM notifications n
    LEFT JOIN tickets t ON n.ticket_id=t.id
    WHERE n.emp_id=? ORDER BY n.created_at DESC LIMIT 50");
$notifs->execute([$uid]);
$notifs = $notifs->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Notifications — TicketDesk</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="topbar">
  <div class="logo"><div class="logo-icon"><i class="fa-solid fa-computer"></i></div>Ticket<span>Desk</span></div>
  <div class="topbar-nav">
    <a href="dashboard.php">My Tickets</a>
    <a href="raise_ticket.php">Raise Ticket</a>
    <a href="profile.php">Profile</a>
  </div>
  <div class="topbar-right">
    <a href="notifications.php" style="position:relative;text-decoration:none;font-size:1.2rem;padding:4px 8px"><i class="fa-solid fa-bell"></i></a>
    <div class="user">
      <div class="avatar"><?php $p=explode(' ',$_SESSION['name']); echo strtoupper(substr($p[0],0,1).(isset($p[1])?substr($p[1],0,1):'')); ?></div>
      <?= sanitize($_SESSION['name']) ?>
    </div>
    <a href="<?= SITE_URL ?>/logout.php" class="btn btn-ghost btn-sm">Logout</a>
  </div>
</div>

<div class="shell">
  <div class="sidebar">
    <div class="side-section">
      <div class="side-label">My Account</div>
      <a href="dashboard.php" class="side-item"><span class="side-icon"><i class="fa-solid fa-list-ul"></i></span> My Tickets</a>
      <a href="raise_ticket.php" class="side-item"><span class="side-icon"><i class="fa-solid fa-plus"></i></span> Raise Ticket</a>
      <a href="notifications.php" class="side-item active"><span class="side-icon"><i class="fa-solid fa-bell"></i></span> Notifications</a>
      <a href="profile.php" class="side-item"><span class="side-icon"><i class="fa-solid fa-user"></i></span> My Profile</a>
    </div>
    <div class="side-section">
      <div class="side-label">Account</div>
      <a href="<?= SITE_URL ?>/logout.php" class="side-item" style="color:var(--red)"><span class="side-icon"><i class="fa-solid fa-right-from-bracket"></i></span> Logout</a>
    </div>
  </div>

  <main>
    <div class="page-header">
      <div class="breadcrumb">TICKETDESK / <span>NOTIFICATIONS</span></div>
      <h1><i class="fa-solid fa-bell"></i> Notifications</h1>
      <p>Updates on your support tickets</p>
    </div>

    <div class="card">
      <div class="card-header">
        <div class="card-title">All Notifications (<?= count($notifs) ?>)</div>
      </div>
      <div class="card-body" style="padding:0">
        <?php if (empty($notifs)): ?>
          <div style="text-align:center;padding:3rem;color:var(--text-muted)">
            <div style="font-size:2.5rem;margin-bottom:1rem"><i class="fa-solid fa-bell"></i></div>
            <div>No notifications yet.</div>
            <div style="font-size:0.8rem;margin-top:4px">Ticket updates will appear here.</div>
          </div>
        <?php else: ?>
          <?php foreach($notifs as $n): ?>
          <div style="display:flex;gap:14px;padding:1rem 1.4rem;border-bottom:1px solid var(--border-mid);align-items:flex-start">
            <div style="font-size:1.3rem;margin-top:2px">
              <?php
              if(str_contains($n['message'],'resolved') || str_contains($n['message'],'Resolved')) echo '<i class="fa-solid fa-check" style="color:#10B981"></i>';
              elseif(str_contains($n['message'],'closed') || str_contains($n['message'],'Closed')) echo '<i class="fa-solid fa-lock" style="color:#64748B"></i>';
              elseif(str_contains($n['message'],'progress') || str_contains($n['message'],'Progress')) echo '<i class="fa-solid fa-screwdriver" style="color:#F59E0B"></i>';
              elseif(str_contains($n['message'],'assigned') || str_contains($n['message'],'Assigned')) echo '<i class="fa-solid fa-user-check" style="color:#3B82F6"></i>';
              else echo '<i class="fa-solid fa-bell" style="color:#5552DD"></i>';
              ?>
            </div>
            <div style="flex:1">
              <div style="font-size:0.85rem;color:var(--text-main);margin-bottom:3px"><?= sanitize($n['message']) ?></div>
              <div style="display:flex;gap:12px;align-items:center">
                <?php if($n['ticket_no']): ?>
                  <a href="view_ticket.php?id=<?= $n['ticket_id'] ?>" class="ticket-id" style="font-size:0.75rem"><?= $n['ticket_no'] ?></a>
                <?php endif; ?>
                <span style="font-size:0.72rem;color:var(--text-muted)"><?= date('d M Y, h:i A', strtotime($n['created_at'])) ?></span>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>
<script src="<?= SITE_URL ?>/assets/js/theme.js"></script>
</body>
</html>