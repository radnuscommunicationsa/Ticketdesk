<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
if (isAdmin()) redirect(SITE_URL . '/admin/dashboard.php');

$uid = $_SESSION['user_id'];
$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect(SITE_URL . '/employee/assets.php');

// Get asset with assignment verification - must be assigned to this employee
$asset = $pdo->prepare("
    SELECT a.*, aa.assigned_at, aa.notes as assignment_notes, aa.returned_at
    FROM assets a
    JOIN asset_assignments aa ON a.id = aa.asset_id
    WHERE a.id = ? AND aa.emp_id = ? AND aa.returned_at IS NULL
");
$asset->execute([$id, $uid]);
$asset = $asset->fetch();

if (!$asset) {
    // Asset not found or not assigned to this employee
    $_SESSION['error'] = 'Asset not found or you do not have permission to view it.';
    redirect(SITE_URL . '/employee/assets.php');
}

// Unread notification count
$notif_count = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE emp_id=? AND is_read=0");
$notif_count->execute([$uid]);
$notif_count = (int)$notif_count->fetchColumn();

function statusColor($s) {
    return match($s) {
        'Available'    => 'color:#10B981;background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.2)',
        'Assigned'     => 'color:#3B82F6;background:rgba(59,130,246,0.08);border:1px solid rgba(59,130,246,0.2)',
        'Under Repair' => 'color:#F59E0B;background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2)',
        'Damaged'      => 'color:#EF4444;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2)',
        'Retired'      => 'color:#64748B;background:rgba(100,116,139,0.08);border:1px solid rgba(100,116,139,0.2)',
        default        => ''
    };
}
function catIcon($c) {
    $icon = match($c) {
        'Laptop','Desktop' => 'fa-laptop',
        'Monitor'          => 'fa-display',
        'Printer'          => 'fa-print',
        'Phone'            => 'fa-mobile-screen',
        'Server'           => 'fa-server',
        'Network Device'   => 'fa-network-wired',
        default            => 'fa-box'
    };
    return '<i class="fa-solid ' . $icon . '" style="font-size:2rem;color:var(--primary)"></i>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Asset <?= htmlspecialchars($asset['asset_code']) ?> — TicketDesk</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"/>
<style>
.asset-header {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 2px solid var(--border);
}
.asset-icon-lg {
    width: 80px;
    height: 80px;
    background: var(--primary-glow);
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
}
.asset-title-area h1 {
    margin: 0 0 0.5rem 0;
    font-size: 1.8rem;
}
.asset-code-display {
    font-family: monospace;
    background: var(--bg-input);
    padding: 6px 16px;
    border-radius: 8px;
    font-size: 1rem;
    color: var(--text-muted);
    display: inline-block;
}
.status-pill {
    display: inline-block;
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    margin-left: 1rem;
}
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin: 1.5rem 0;
}
.info-card {
    background: var(--bg-mid);
    padding: 1.2rem;
    border-radius: var(--radius-md);
    border: 1px solid var(--border);
}
.info-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    color: var(--text-muted);
    margin-bottom: 8px;
    letter-spacing: 0.05em;
}
.info-value {
    font-size: 1rem;
    font-weight: 500;
}
.assignment-info {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.08) 0%, rgba(59, 130, 246, 0.15) 100%);
    border: 1px solid rgba(59, 130, 246, 0.2);
    border-radius: var(--radius-md);
    padding: 1.2rem;
    margin: 1.5rem 0;
}
.back-link {
    display: inline-flex;
       align-items: center;
    gap: 8px;
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
    margin-bottom: 1.5rem;
}
.back-link:hover {
    text-decoration: underline;
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
      <div class="breadcrumb">TICKETDESK / <a href="assets.php" style="color:var(--text-muted)">MY ASSETS</a> / <span><?= htmlspecialchars($asset['asset_code']) ?></span></div>
      <a href="<?= SITE_URL ?>/employee/assets.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Assets</a>
      <h1><i class="fa-solid fa-box"></i> Asset Details</h1>
      <p>Information about your assigned asset</p>
    </div>

    <div class="asset-header">
      <div class="asset-icon-lg">
        <?= catIcon($asset['category']) ?>
      </div>
      <div class="asset-title-area">
        <h1><?= htmlspecialchars($asset['name']) ?></h1>
        <div>
          <span class="asset-code-display"><?= htmlspecialchars($asset['asset_code']) ?></span>
          <span class="status-pill" style="<?= statusColor($asset['status']) ?>"><?= htmlspecialchars($asset['status']) ?></span>
        </div>
      </div>
    </div>

    <div class="info-grid">
      <div class="info-card">
        <div class="info-label">Category</div>
        <div class="info-value"><?= htmlspecialchars($asset['category']) ?></div>
      </div>
      <div class="info-card">
        <div class="info-label">Brand</div>
        <div class="info-value"><?= htmlspecialchars($asset['brand'] ?: '—') ?></div>
      </div>
      <div class="info-card">
        <div class="info-label">Model</div>
        <div class="info-value"><?= htmlspecialchars($asset['model'] ?: '—') ?></div>
      </div>
      <div class="info-card">
        <div class="info-label">Serial Number</div>
        <div class="info-value" style="font-family:monospace"><?= htmlspecialchars($asset['serial_no'] ?: '—') ?></div>
      </div>
      <div class="info-card">
        <div class="info-label">Location</div>
        <div class="info-value"><?= htmlspecialchars($asset['location'] ?: '—') ?></div>
      </div>
      <div class="info-card">
        <div class="info-label">Purchase Date</div>
        <div class="info-value"><?= $asset['purchase_date'] ? date('d M Y', strtotime($asset['purchase_date'])) : '—' ?></div>
      </div>
      <div class="info-card">
        <div class="info-label">Warranty Until</div>
        <div class="info-value"><?= $asset['warranty_until'] ? date('d M Y', strtotime($asset['warranty_until'])) : '—' ?></div>
      </div>
      <div class="info-card">
        <div class="info-label">Assigned On</div>
        <div class="info-value"><?= date('d M Y, H:i', strtotime($asset['assigned_at'])) ?></div>
      </div>
    </div>

    <?php if ($asset['assignment_notes']): ?>
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fa-solid fa-sticky-note"></i> Assignment Notes</div>
      </div>
      <div class="card-body">
        <p style="margin:0;line-height:1.6"><?= nl2br(htmlspecialchars($asset['assignment_notes'])) ?></p>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($asset['notes']): ?>
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fa-solid fa-circle-info"></i> Asset Notes</div>
      </div>
      <div class="card-body">
        <p style="margin:0;line-height:1.6"><?= nl2br(htmlspecialchars($asset['notes'])) ?></p>
      </div>
    </div>
    <?php endif; ?>

    <div style="margin-top:1.5rem">
      <a href="<?= SITE_URL ?>/employee/assets.php" class="btn btn-ghost">
        <i class="fa-solid fa-arrow-left"></i> Back to All Assets
      </a>
    </div>
  </main>
</div>
<script src="<?= SITE_URL ?>/assets/js/theme.js"></script>
</body>
</html>