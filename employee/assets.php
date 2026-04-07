<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
if (isAdmin()) redirect(SITE_URL . '/admin/dashboard.php');

$uid = $_SESSION['user_id'];

// Unread notification count
$notif_count = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE emp_id=? AND is_read=0");
$notif_count->execute([$uid]);
$notif_count = (int)$notif_count->fetchColumn();

// Get assigned assets for this employee
$assets = $pdo->prepare("
    SELECT a.*, aa.assigned_at, aa.notes as assignment_notes, aa.returned_at
    FROM assets a
    JOIN asset_assignments aa ON a.id = aa.asset_id
    WHERE aa.emp_id = ? AND aa.returned_at IS NULL
    ORDER BY aa.assigned_at DESC
");
$assets->execute([$uid]);
$assets = $assets->fetchAll();

// Stats
$total_assigned = count($assets);
$available = 0; // Not applicable for employee view
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>My Assets — TicketDesk</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"/>
<style>
.asset-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.2rem;
    margin-bottom: 1rem;
    transition: all 0.2s;
}
.asset-card:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow-md);
}
.asset-header {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 1rem;
}
.asset-icon {
    font-size: 2rem;
    color: var(--primary);
    background: var(--primary-glow);
    padding: 10px;
    border-radius: var(--radius-md);
}
.asset-title {
    flex: 1;
}
.asset-title h3 {
    margin: 0 0 4px 0;
    font-size: 1.1rem;
    color: var(--text-main);
}
.asset-code {
    font-family: monospace;
    background: var(--bg-input);
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    color: var(--text-muted);
}
.asset-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
}
.asset-detail-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.asset-detail-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    color: var(--text-muted);
    letter-spacing: 0.05em;
}
.asset-detail-value {
    font-size: 0.9rem;
    color: var(--text-main);
}
.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}
.status-assigned {
    background: rgba(59, 130, 246, 0.1);
    color: #3B82F6;
    border: 1px solid rgba(59, 130, 246, 0.2);
}
</style>
</head>
<body>
<div class="topbar">
  <div class="logo"><div class="logo-icon">🖥</div>Ticket<span>Desk</span></div>
  <div class="topbar-nav">
    <a href="dashboard.php">My Tickets</a>
    <a href="raise_ticket.php">Raise Ticket</a>
    <a href="assets.php" class="active">My Assets</a>
    <a href="profile.php">Profile</a>
  </div>
  <div class="topbar-right">
    <a href="notifications.php" style="position:relative;text-decoration:none;font-size:1.2rem;padding:4px 8px" title="Notifications">
      🔔<?php if($notif_count>0): ?><span style="position:absolute;top:0;right:0;background:#EF4444;color:#fff;font-size:0.55rem;font-weight:700;padding:1px 4px;border-radius:10px"><?= $notif_count ?></span><?php endif; ?>
    </a>
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
      <a href="assets.php" class="side-item active"><span class="side-icon"><i class="fa-solid fa-box"></i></span> My Assets</a>
      <a href="notifications.php" class="side-item"><span class="side-icon"><i class="fa-solid fa-bell"></i></span> Notifications <?php if($notif_count>0): ?><span class="side-badge"><?= $notif_count ?></span><?php endif; ?></a>
      <a href="profile.php" class="side-item"><span class="side-icon"><i class="fa-solid fa-user"></i></span> My Profile</a>
    </div>
    <div class="side-section">
      <div class="side-label">Account</div>
      <a href="<?= SITE_URL ?>/logout.php" class="side-item" style="color:var(--red)"><span class="side-icon"><i class="fa-solid fa-right-from-bracket"></i></span> Logout</a>
    </div>
  </div>

  <main>
    <div class="page-header">
      <div class="breadcrumb">TICKETDESK / <span>MY ASSETS</span></div>
      <h1><i class="fa-solid fa-box"></i> My Assets</h1>
      <p>Assets currently assigned to you</p>
    </div>

    <?php if (empty($assets)): ?>
      <div class="card" style="text-align:center;padding:3rem">
        <div style="font-size:4rem;color:var(--text-muted);margin-bottom:1rem">📦</div>
        <h3 style="color:var(--text-muted)">No Assets Assigned</h3>
        <p style="color:var(--text-muted);margin-top:0.5rem">
          You don't have any assets assigned to you yet.
        </p>
      </div>
    <?php else: ?>
      <div class="card">
        <div class="card-header">
          <div class="card-title">Assigned Assets (<?= count($assets) ?>)</div>
        </div>
        <div class="card-body" style="padding:0">
          <?php foreach ($assets as $index => $a): ?>
            <div class="asset-card" style="margin:0;border-radius:0;border:none;border-bottom:1px solid var(--border);padding:1.5rem">
              <?php if ($index === 0): ?>
                <style>.asset-card { border-radius: 12px 12px 0 0 !important; }</style>
              <?php endif; ?>
              <?php if ($index === count($assets) - 1): ?>
                <style>.asset-card { border-bottom: none !important; }</style>
              <?php endif; ?>

              <div class="asset-header">
                <div class="asset-icon">
                  <i class="fa-solid
                    <?= $a['category'] == 'Laptop' ? 'fa-laptop' : '' ?>
                    <?= $a['category'] == 'Desktop' ? 'fa-desktop' : '' ?>
                    <?= $a['category'] == 'Monitor' ? 'fa-display' : '' ?>
                    <?= $a['category'] == 'Printer' ? 'fa-print' : '' ?>
                    <?= $a['category'] == 'Phone' ? 'fa-mobile-screen' : '' ?>
                    <?= $a['category'] == 'Server' ? 'fa-server' : '' ?>
                    <?= $a['category'] == 'Network Device' ? 'fa-network-wired' : '' ?>
                    <?= !in_array($a['category'], ['Laptop','Desktop','Monitor','Printer','Phone','Server','Network Device']) ? 'fa-box' : '' ?>
                  "></i>
                </div>
                <div class="asset-title">
                  <h3><?= htmlspecialchars($a['name']) ?></h3>
                  <div>
                    <span class="asset-code"><?= htmlspecialchars($a['asset_code']) ?></span>
                    <span class="status-badge status-assigned">Assigned</span>
                  </div>
                </div>
              </div>

              <div class="asset-details">
                <div class="asset-detail-item">
                  <span class="asset-detail-label">Category</span>
                  <span class="asset-detail-value"><?= htmlspecialchars($a['category']) ?></span>
                </div>
                <div class="asset-detail-item">
                  <span class="asset-detail-label">Brand/Model</span>
                  <span class="asset-detail-value"><?= htmlspecialchars($a['brand'] . ' ' . $a['model']) ?></span>
                </div>
                <div class="asset-detail-item">
                  <span class="asset-detail-label">Serial Number</span>
                  <span class="asset-detail-value" style="font-family:monospace"><?= htmlspecialchars($a['serial_no'] ?: '—') ?></span>
                </div>
                <div class="asset-detail-item">
                  <span class="asset-detail-label">Location</span>
                  <span class="asset-detail-value"><?= htmlspecialchars($a['location'] ?: '—') ?></span>
                </div>
                <div class="asset-detail-item">
                  <span class="asset-detail-label">Assigned Date</span>
                  <span class="asset-detail-value"><?= date('d M Y', strtotime($a['assigned_at'])) ?></span>
                </div>
                <?php if ($a['assignment_notes']): ?>
                <div class="asset-detail-item" style="grid-column: 1 / -1;">
                  <span class="asset-detail-label">Assignment Notes</span>
                  <span class="asset-detail-value"><?= nl2br(htmlspecialchars($a['assignment_notes'])) ?></span>
                </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </main>
</div>
<script src="<?= SITE_URL ?>/assets/js/theme.js"></script>
</body>
</html>