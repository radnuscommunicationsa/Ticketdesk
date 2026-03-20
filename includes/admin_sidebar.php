<?php
// Get counts for sidebar badges
$open_count     = $pdo->query("SELECT COUNT(*) FROM tickets WHERE status='open'")->fetchColumn();
$critical_count = $pdo->query("SELECT COUNT(*) FROM tickets WHERE priority='critical' AND status NOT IN ('resolved','closed')")->fetchColumn();
$inprog_count   = $pdo->query("SELECT COUNT(*) FROM tickets WHERE status='in-progress'")->fetchColumn();
$total_count    = $pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn();
$emp_count      = $pdo->query("SELECT COUNT(*) FROM employees WHERE role='employee'")->fetchColumn();
$current_page   = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
  <div class="side-section">
    <div class="side-label">Overview</div>
    <a href="<?= SITE_URL ?>/admin/dashboard.php" class="side-item <?= $current_page==='dashboard.php'?'active':'' ?>">
      <span class="side-icon">📊</span> Dashboard
    </a>
    <a href="<?= SITE_URL ?>/admin/tickets.php" class="side-item <?= $current_page==='tickets.php'?'active':'' ?>">
      <span class="side-icon">🎫</span> All Tickets
      <span class="side-badge"><?= $total_count ?></span>
    </a>
    <a href="<?= SITE_URL ?>/admin/employees.php" class="side-item <?= $current_page==='employees.php'?'active':'' ?>">
      <span class="side-icon">👥</span> Employees
      <span class="side-badge"><?= $emp_count ?></span>
    </a>
    <a href="<?= SITE_URL ?>/admin/assets.php" class="side-item <?= in_array($current_page,['assets.php','asset_detail.php','asset_assign.php'])?'active':'' ?>">
      <span class="side-icon">🖥️</span> Assets
      <?php $asset_count = $pdo->query("SELECT COUNT(*) FROM assets")->fetchColumn(); ?>
      <span class="side-badge"><?= $asset_count ?></span>
    </a>
  </div>
  <div class="side-section">
    <div class="side-label">Reports</div>
    <a href="<?= SITE_URL ?>/admin/reports.php" class="side-item <?= $current_page==='reports.php'?'active':'' ?>">
      <span class="side-icon">📊</span> Monthly Report
    </a>
    <a href="<?= SITE_URL ?>/admin/admin_notifications.php" class="side-item <?= $current_page==='admin_notifications.php'?'active':'' ?>">
      <span class="side-icon">🔔</span> Notifications
      <?php
        $a_notif = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE emp_id=" . (int)$_SESSION['user_id'] . " AND is_read=0")->fetchColumn();
        if($a_notif > 0): ?>
        <span class="side-badge"><?= $a_notif ?></span>
      <?php endif; ?>
    </a>
  </div>
  <div class="side-section">
    <div class="side-label">Queue</div>
    <a href="<?= SITE_URL ?>/admin/tickets.php?priority=critical" class="side-item">
      <span class="side-icon">🔴</span> Critical
      <?php if($critical_count > 0): ?>
        <span class="side-badge red"><?= $critical_count ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= SITE_URL ?>/admin/tickets.php?status=in-progress" class="side-item">
      <span class="side-icon">🟠</span> In Progress
      <span class="side-badge"><?= $inprog_count ?></span>
    </a>
    <a href="<?= SITE_URL ?>/admin/tickets.php?status=open" class="side-item">
      <span class="side-icon">🔵</span> Open
      <span class="side-badge"><?= $open_count ?></span>
    </a>
    <a href="<?= SITE_URL ?>/admin/tickets.php?status=resolved" class="side-item">
      <span class="side-icon">🟢</span> Resolved
    </a>
  </div>
  <div class="side-section">
    <div class="side-label">Account</div>
    <a href="<?= SITE_URL ?>/logout.php" class="side-item" style="color:var(--red)">
      <span class="side-icon">🚪</span> Logout
    </a>
  </div>
</div>