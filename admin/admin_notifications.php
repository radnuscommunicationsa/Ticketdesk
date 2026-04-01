<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();
$admin_notif_count = 0; // already on this page

$uid = $_SESSION['user_id'];

// Mark all as read
$pdo->prepare("UPDATE notifications SET is_read=1 WHERE emp_id=?")->execute([$uid]);

// Get all notifications for this admin
$notifs = $pdo->prepare("SELECT n.*, t.ticket_no, t.priority FROM notifications n
    LEFT JOIN tickets t ON n.ticket_id = t.id
    WHERE n.emp_id = ? ORDER BY n.created_at DESC LIMIT 100");
$notifs->execute([$uid]);
$notifs = $notifs->fetchAll();

$current_page = 'admin_notifications.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Notifications — TicketDesk Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="topbar">
  <div class="logo"><div class="logo-icon">🖥</div>Ticket<span>Desk</span> <span style="font-size:0.7rem;color:var(--text-muted);margin-left:6px;font-weight:400">ADMIN</span></div>
  <div class="topbar-nav">
    <a href="dashboard.php">Dashboard</a>
    <a href="tickets.php">Tickets</a>
    <a href="assets.php">Assets</a>
    <a href="employees.php">Employees</a>
    <a href="reports.php">Reports</a>
  </div>
  <div class="topbar-right">
    <a href="admin_notifications.php" style="text-decoration:none;font-size:1.2rem;padding:4px 8px">🔔</a>
    <a href="<?= SITE_URL ?>/logout.php" class="btn btn-ghost btn-sm">Logout</a>
  </div>
</div>

<div class="shell">
  <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>
  <main>
    <div class="page-header">
      <div class="breadcrumb">TICKETDESK / <span>NOTIFICATIONS</span></div>
      <h1>🔔 Admin Notifications</h1>
      <p>New tickets and system alerts</p>
    </div>

    <div class="card">
      <div class="card-header">
        <div class="card-title">All Notifications (<?= count($notifs) ?>)</div>
        <?php if (!empty($notifs)): ?>
        <form method="POST" action="">
          <input type="hidden" name="clear_all" value="1"/>
        </form>
        <?php endif; ?>
      </div>
      <div class="card-body" style="padding:0">
        <?php if (empty($notifs)): ?>
          <div style="text-align:center;padding:3rem;color:var(--text-muted)">
            <div style="font-size:2.5rem;margin-bottom:1rem">🔔</div>
            <div>No notifications yet.</div>
            <div style="font-size:0.8rem;margin-top:4px">New ticket alerts will appear here.</div>
          </div>
        <?php else: ?>
          <?php foreach($notifs as $n):
            $pri = $n['priority'] ?? '';
            $icon = '🎫';
            $color = 'var(--text-main)';
            if ($pri === 'critical') { $icon = '🔴'; $color = '#EF4444'; }
            elseif ($pri === 'high') { $icon = '🟠'; $color = 'var(--orange)'; }
            elseif ($pri === 'medium') { $icon = '🟡'; }
            elseif ($pri === 'low') { $icon = '🟢'; }
          ?>
          <div style="display:flex;gap:14px;padding:1rem 1.4rem;border-bottom:1px solid var(--border-mid);align-items:flex-start;transition:background 0.15s" onmouseover="this.style.background='var(--bg-hover)'" onmouseout="this.style.background=''">
            <div style="font-size:1.4rem;margin-top:2px"><?= $icon ?></div>
            <div style="flex:1">
              <div style="font-size:0.85rem;color:<?= $color ?>;margin-bottom:4px;line-height:1.5"><?= sanitize($n['message']) ?></div>
              <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
                <?php if ($n['ticket_no'] && $n['ticket_id']): ?>
                  <a href="ticket_detail.php?id=<?= $n['ticket_id'] ?>" class="btn btn-primary btn-xs">View Ticket <?= $n['ticket_no'] ?> <i class="fa-solid fa-arrow-right"></i></a>
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