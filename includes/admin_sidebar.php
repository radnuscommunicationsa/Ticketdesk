<?php
// Get counts for sidebar badges
$open_count     = $pdo->query("SELECT COUNT(*) FROM tickets WHERE status='open'")->fetchColumn();
$critical_count = $pdo->query("SELECT COUNT(*) FROM tickets WHERE priority='critical' AND status NOT IN ('resolved','closed')")->fetchColumn();
$inprog_count   = $pdo->query("SELECT COUNT(*) FROM tickets WHERE status='in-progress'")->fetchColumn();
$total_count    = $pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn();
$emp_count      = $pdo->query("SELECT COUNT(*) FROM employees WHERE role='employee'")->fetchColumn();
$asset_count    = $pdo->query("SELECT COUNT(*) FROM assets")->fetchColumn();
$a_notif        = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE emp_id=" . (int)$_SESSION['user_id'] . " AND is_read=0")->fetchColumn();
$current_page   = basename($_SERVER['PHP_SELF']);
?>
<!-- ✅ Font Awesome CDN -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

<div class="sidebar">
  <div class="side-section">
    <div class="side-label">Overview</div>
    <a href="<?= SITE_URL ?>/admin/dashboard.php" class="side-item <?= $current_page==='dashboard.php'?'active':'' ?>">
      <span class="side-icon"><i class="fa-solid fa-gauge-high"></i></span> Dashboard
    </a>
    <a href="<?= SITE_URL ?>/admin/tickets.php" class="side-item <?= $current_page==='tickets.php'?'active':'' ?>">
      <span class="side-icon"><i class="fa-solid fa-ticket"></i></span> All Tickets
      <span class="side-badge"><?= $total_count ?></span>
    </a>
    <a href="<?= SITE_URL ?>/admin/employees.php" class="side-item <?= $current_page==='employees.php'?'active':'' ?>">
      <span class="side-icon"><i class="fa-solid fa-users"></i></span> Employees
      <span class="side-badge"><?= $emp_count ?></span>
    </a>
    <a href="<?= SITE_URL ?>/admin/assets.php" class="side-item <?= in_array($current_page,['assets.php','asset_detail.php','asset_assign.php'])?'active':'' ?>">
      <span class="side-icon"><i class="fa-solid fa-desktop"></i></span> Assets
      <span class="side-badge"><?= $asset_count ?></span>
    </a>
  </div>

  <div class="side-section">
    <div class="side-label">Reports</div>
    <a href="<?= SITE_URL ?>/admin/reports.php" class="side-item <?= $current_page==='reports.php'?'active':'' ?>">
      <span class="side-icon"><i class="fa-solid fa-chart-bar"></i></span> Monthly Report
    </a>
    <a href="<?= SITE_URL ?>/admin/admin_notifications.php" class="side-item <?= $current_page==='admin_notifications.php'?'active':'' ?>">
      <span class="side-icon"><i class="fa-solid fa-bell"></i></span> Notifications
      <?php if($a_notif > 0): ?>
        <span class="side-badge"><?= $a_notif ?></span>
      <?php endif; ?>
    </a>
  </div>

  <div class="side-section">
    <div class="side-label">Queue</div>
    <a href="<?= SITE_URL ?>/admin/tickets.php?priority=critical" class="side-item">
      <span class="side-icon"><i class="fa-solid fa-circle" style="color:#c62828"></i></span> Critical
      <?php if($critical_count > 0): ?>
        <span class="side-badge red"><?= $critical_count ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= SITE_URL ?>/admin/tickets.php?status=in-progress" class="side-item">
      <span class="side-icon"><i class="fa-solid fa-circle" style="color:#f57c00"></i></span> In Progress
      <span class="side-badge"><?= $inprog_count ?></span>
    </a>
    <a href="<?= SITE_URL ?>/admin/tickets.php?status=open" class="side-item">
      <span class="side-icon"><i class="fa-solid fa-circle" style="color:#1976d2"></i></span> Open
      <span class="side-badge"><?= $open_count ?></span>
    </a>
    <a href="<?= SITE_URL ?>/admin/tickets.php?status=resolved" class="side-item">
      <span class="side-icon"><i class="fa-solid fa-circle" style="color:#388e3c"></i></span> Resolved
    </a>
  </div>

  <div class="side-section">
    <div class="side-label">Account</div>
    <a href="<?= SITE_URL ?>/logout.php" class="side-item" style="color:var(--red)">
      <span class="side-icon"><i class="fa-solid fa-right-from-bracket"></i></span> Logout
    </a>
  </div>
</div>