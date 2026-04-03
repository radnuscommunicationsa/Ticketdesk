<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();

// Get counts for sidebar badges with error handling
try {
    $open_count     = $pdo->query("SELECT COUNT(*) FROM tickets WHERE status='open'")->fetchColumn() ?: 0;
    $critical_count = $pdo->query("SELECT COUNT(*) FROM tickets WHERE priority='critical' AND status NOT IN ('resolved','closed')")->fetchColumn() ?: 0;
    $inprog_count   = $pdo->query("SELECT COUNT(*) FROM tickets WHERE status='in-progress'")->fetchColumn() ?: 0;
    $total_count    = $pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn() ?: 0;
    $emp_count      = $pdo->query("SELECT COUNT(*) FROM employees WHERE role='employee'")->fetchColumn() ?: 0;
    $asset_count    = $pdo->query("SELECT COUNT(*) FROM assets")->fetchColumn() ?: 0;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE emp_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id'] ?? 0]);
    $a_notif = (int)($stmt->fetchColumn() ?: 0);
} catch (PDOException $e) {
    error_log('Sidebar query error: ' . $e->getMessage());
    $open_count = $critical_count = $inprog_count = $total_count = $emp_count = $asset_count = $a_notif = 0;
}
$current_page   = basename($_SERVER['PHP_SELF'] ?? '');
?>
<!-- ✅ Font Awesome CDN -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

<div class="sidebar">
  <!-- Admin Profile Header -->
  <div class="sidebar-header">
    <div class="logo-large">
      <div class="logo-icon"><i class="fa-solid fa-shield-halved"></i></div>
      <div class="logo-text">
        <div class="logo-title">TicketDesk</div>
        <div class="logo-badge">Admin Portal</div>
      </div>
    </div>
    <div class="admin-quick">
      <div class="admin-avatar" style="background:<?= avatarColor($_SESSION['name'] ?? 'Admin') ?>">
        <?= initials($_SESSION['name'] ?? 'Admin') ?>
      </div>
      <div class="admin-info">
        <div class="admin-name"><?= sanitize($_SESSION['name'] ?? 'Administrator') ?></div>
        <div class="admin-role">Administrator</div>
      </div>
    </div>
  </div>

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
      <span class="side-icon"><i class="fa-solid fa-computer"></i></span> Assets
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
      <span class="side-icon"><i class="fa-solid fa-circle" style="color:#EF4444;font-size:6px"></i></span> Critical
      <?php if($critical_count > 0): ?>
        <span class="side-badge red"><?= $critical_count ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= SITE_URL ?>/admin/tickets.php?status=in-progress" class="side-item">
      <span class="side-icon"><i class="fa-solid fa-circle" style="color:#F59E0B;font-size:6px"></i></span> In Progress
      <span class="side-badge"><?= $inprog_count ?></span>
    </a>
    <a href="<?= SITE_URL ?>/admin/tickets.php?status=open" class="side-item">
      <span class="side-icon"><i class="fa-solid fa-circle" style="color:#3B82F6;font-size:6px"></i></span> Open
      <span class="side-badge"><?= $open_count ?></span>
    </a>
    <a href="<?= SITE_URL ?>/admin/tickets.php?status=resolved" class="side-item">
      <span class="side-icon"><i class="fa-solid fa-circle" style="color:#10B981;font-size:6px"></i></span> Resolved
    </a>
  </div>

  <div class="side-section">
    <div class="side-label">Account</div>
    <a href="<?= SITE_URL ?>/logout.php" class="side-item" style="color:var(--red)">
      <span class="side-icon"><i class="fa-solid fa-right-from-bracket"></i></span> Logout
    </a>
  </div>
</div>