<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect(SITE_URL . '/admin/assets.php');

$asset = $pdo->prepare("SELECT * FROM assets WHERE id=?");
$asset->execute([$id]); $asset = $asset->fetch();
if (!$asset) redirect(SITE_URL . '/admin/assets.php');

// Current assignment
$current = $pdo->prepare("SELECT aa.*, e.name as emp_name, e.emp_id as emp_code, e.department
    FROM asset_assignments aa JOIN employees e ON aa.emp_id=e.id
    WHERE aa.asset_id=? AND aa.returned_at IS NULL ORDER BY aa.assigned_at DESC LIMIT 1");
$current->execute([$id]); $current = $current->fetch();

// Assignment history
$history = $pdo->prepare("SELECT aa.*, e.name as emp_name, e.emp_id as emp_code, e.department,
    a2.name as assigned_by_name
    FROM asset_assignments aa
    JOIN employees e ON aa.emp_id=e.id
    JOIN employees a2 ON aa.assigned_by=a2.id
    WHERE aa.asset_id=? ORDER BY aa.assigned_at DESC");
$history->execute([$id]); $history = $history->fetchAll();

// Activity logs
$logs = $pdo->prepare("SELECT al.*, e.name as done_by_name FROM asset_logs al
    JOIN employees e ON al.done_by=e.id WHERE al.asset_id=? ORDER BY al.created_at DESC");
$logs->execute([$id]); $logs = $logs->fetchAll();

$success = $error = '';

// Update status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'status') {
    verify_csrf();
    $new_status = $_POST['status'] ?? '';
    $note       = trim($_POST['note'] ?? '');
    $valid = ['Available','Assigned','Under Repair','Damaged','Retired'];
    if (in_array($new_status, $valid)) {
        $pdo->prepare("UPDATE assets SET status=? WHERE id=?")->execute([$new_status, $id]);
        $pdo->prepare("INSERT INTO asset_logs (asset_id,action,done_by,note) VALUES (?,?,?,?)")
            ->execute([$id, "Status changed to '$new_status'", $_SESSION['user_id'], $note]);
        // Reload
        $asset_stmt = $pdo->prepare("SELECT * FROM assets WHERE id=?");
        $asset_stmt->execute([$id]); $asset = $asset_stmt->fetch();
        $logs_stmt = $pdo->prepare("SELECT al.*, e.name as done_by_name FROM asset_logs al JOIN employees e ON al.done_by=e.id WHERE al.asset_id=? ORDER BY al.created_at DESC");
        $logs_stmt->execute([$id]); $logs = $logs_stmt->fetchAll();
        $success = 'Asset status updated!';
    }
}

// Return asset
if (isset($_GET['return']) && $current) {
    $pdo->prepare("UPDATE asset_assignments SET returned_at=NOW() WHERE id=?")->execute([$current['id']]);
    $pdo->prepare("UPDATE assets SET status='Available' WHERE id=?")->execute([$id]);
    $pdo->prepare("INSERT INTO asset_logs (asset_id,action,done_by,note) VALUES (?,?,?,?)")
        ->execute([$id, "Asset returned by {$current['emp_name']}", $_SESSION['user_id'], '']);
    redirect(SITE_URL . '/admin/asset_detail.php?id=' . $id);
}

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
        default            => 'fa-gear'
    };
    return '<i class="fa-solid ' . $icon . '" style="font-size:1.2rem;vertical-align:middle"></i>';
}
$current_page = 'assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title><?= sanitize($asset['asset_code']) ?> — Asset Detail</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"/>
<style>
.status-pill { display:inline-block; font-size:0.75rem; font-weight:600; padding:4px 12px; border-radius:12px; }
.info-row { display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:0.8rem; }
.info-item { flex:1; min-width:140px; }
.info-label { font-size:0.67rem; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-muted); margin-bottom:4px; }
.info-value { font-size:0.85rem; color:var(--text-main); font-weight:500; }
</style>
</head>
<body>
<div class="topbar">
  <div class="logo"><div class="logo-icon">🖥</div>Ticket<span>Desk</span> <span style="font-size:0.7rem;color:var(--text-muted);margin-left:6px;font-weight:400">ADMIN</span></div>
  <div class="topbar-nav">
    <a href="dashboard.php">Dashboard</a>
    <a href="tickets.php">Tickets</a>
    <a href="assets.php" class="active">Assets</a>
    <a href="employees.php">Employees</a>
  </div>
  <div class="topbar-right">
    <a href="<?= SITE_URL ?>/logout.php" class="btn btn-ghost btn-sm">Logout</a>
  </div>
</div>

<div class="shell">
  <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>
  <main>
    <div class="page-header">
      <div class="breadcrumb">TICKETDESK / <a href="assets.php" style="color:var(--text-muted)">ASSETS</a> / <span><?= sanitize($asset['asset_code']) ?></span></div>
      <div class="flex gap-2" style="flex-wrap:wrap;align-items:flex-start">
        <div>
          <h1><?= catIcon($asset['category']) ?> <?= sanitize($asset['asset_code']) ?></h1>
          <p><?= sanitize($asset['name']) ?> — <?= sanitize($asset['brand']) ?> <?= sanitize($asset['model']) ?></p>
        </div>
        <div style="margin-left:auto">
          <span class="status-pill" style="<?= statusColor($asset['status']) ?>"><?= sanitize($asset['status']) ?></span>
        </div>
      </div>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><i class="fa-solid fa-check"></i> <?= $success ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= sanitize($error) ?></div><?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 320px;gap:1.5rem">

      <!-- LEFT -->
      <div>
        <!-- Asset Info -->
        <div class="card">
          <div class="card-header"><div class="card-title">📋 Asset Information</div></div>
          <div class="card-body">
            <div class="info-row">
              <div class="info-item"><div class="info-label">Asset Code</div><div class="info-value" style="font-family:'IBM Plex Mono',monospace;color:var(--red-accent)"><?= sanitize($asset['asset_code']) ?></div></div>
              <div class="info-item"><div class="info-label">Category</div><div class="info-value"><span class="dept-badge"><?= sanitize($asset['category']) ?></span></div></div>
              <div class="info-item"><div class="info-label">Status</div><div class="info-value"><span class="status-pill" style="<?= statusColor($asset['status']) ?>"><?= sanitize($asset['status']) ?></span></div></div>
            </div>
            <div class="info-row">
              <div class="info-item"><div class="info-label">Brand</div><div class="info-value"><?= sanitize($asset['brand'] ?: '—') ?></div></div>
              <div class="info-item"><div class="info-label">Model</div><div class="info-value"><?= sanitize($asset['model'] ?: '—') ?></div></div>
              <div class="info-item"><div class="info-label">Serial No</div><div class="info-value" style="font-family:'IBM Plex Mono',monospace;font-size:0.78rem"><?= sanitize($asset['serial_no'] ?: '—') ?></div></div>
            </div>
            <div class="info-row">
              <div class="info-item"><div class="info-label">Purchase Date</div><div class="info-value"><?= $asset['purchase_date'] ? date('d M Y', strtotime($asset['purchase_date'])) : '—' ?></div></div>
              <div class="info-item"><div class="info-label">Warranty Until</div>
                <div class="info-value">
                  <?php if ($asset['warranty_until']):
                    $days = (strtotime($asset['warranty_until']) - time()) / 86400;
                    $wc = $days < 0 ? 'color:var(--red)' : ($days < 90 ? 'color:var(--orange)' : 'color:var(--green)');
                  ?>
                  <span style="<?= $wc ?>"><?= date('d M Y', strtotime($asset['warranty_until'])) ?>
                    <?= $days < 0 ? ' (Expired)' : (' ('.round($days).'d left)') ?>
                  </span>
                  <?php else: ?>—<?php endif; ?>
                </div>
              </div>
              <div class="info-item"><div class="info-label">Location</div><div class="info-value"><?= sanitize($asset['location'] ?: '—') ?></div></div>
            </div>
            <?php if ($asset['notes']): ?>
            <div class="divider"></div>
            <div class="info-label" style="margin-bottom:6px">Notes</div>
            <div class="desc-box"><?= nl2br(sanitize($asset['notes'])) ?></div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Current Assignment -->
        <div class="card">
          <div class="card-header">
            <div class="card-title">👤 Current Assignment</div>
            <?php if ($asset['status'] === 'Available'): ?>
              <a href="asset_assign.php?id=<?= $id ?>" class="btn btn-primary btn-sm">Assign Now</a>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <?php if ($current): ?>
              <div class="info-row">
                <div class="info-item"><div class="info-label">Employee</div><div class="info-value"><?= sanitize($current['emp_name']) ?></div></div>
                <div class="info-item"><div class="info-label">Emp ID</div><div class="info-value" style="font-family:'IBM Plex Mono',monospace"><?= sanitize($current['emp_code']) ?></div></div>
                <div class="info-item"><div class="info-label">Department</div><div class="info-value"><span class="dept-badge"><?= sanitize($current['department']) ?></span></div></div>
              </div>
              <div class="info-item" style="margin-bottom:1rem"><div class="info-label">Assigned On</div><div class="info-value"><?= date('d M Y, H:i', strtotime($current['assigned_at'])) ?></div></div>
              <?php if ($current['notes']): ?>
              <div class="desc-box" style="margin-bottom:1rem"><?= nl2br(sanitize($current['notes'])) ?></div>
              <?php endif; ?>
              <a href="?id=<?= $id ?>&return=1" class="btn btn-danger btn-sm" onclick="return confirm('Mark this asset as returned?')">↩ Mark as Returned</a>
            <?php else: ?>
              <p style="color:var(--text-muted);font-size:0.85rem">This asset is not currently assigned to anyone.</p>
              <?php if ($asset['status'] === 'Available'): ?>
              <a href="asset_assign.php?id=<?= $id ?>" class="btn btn-primary btn-sm" style="margin-top:1rem">Assign to Employee →</a>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Assignment History -->
        <div class="card">
          <div class="card-header"><div class="card-title">📂 Assignment History</div></div>
          <div class="card-body">
            <?php if (empty($history)): ?>
              <p style="color:var(--text-muted);font-size:0.85rem">No assignment history yet.</p>
            <?php else: ?>
              <div class="table-wrap">
                <table>
                  <thead><tr><th>Employee</th><th>Dept</th><th>Assigned On</th><th>Returned On</th></tr></thead>
                  <tbody>
                    <?php foreach ($history as $h): ?>
                    <tr>
                      <td><?= sanitize($h['emp_name']) ?> <span style="font-family:'IBM Plex Mono',monospace;font-size:0.72rem;color:var(--text-muted)"><?= sanitize($h['emp_code']) ?></span></td>
                      <td><span class="dept-badge"><?= sanitize($h['department']) ?></span></td>
                      <td style="font-size:0.78rem"><?= date('d M Y', strtotime($h['assigned_at'])) ?></td>
                      <td style="font-size:0.78rem"><?= $h['returned_at'] ? date('d M Y', strtotime($h['returned_at'])) : '<span style="color:var(--orange)">Active</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- RIGHT -->
      <div>
        <!-- Update Status -->
        <div class="card">
          <div class="card-header"><div class="card-title">🔄 Update Status</div></div>
          <div class="card-body">
            <form method="POST">
              <?= csrf_input() ?>
              <input type="hidden" name="action" value="status"/>
              <div class="form-group" style="margin-bottom:1rem">
                <label>New Status</label>
                <select name="status">
                  <?php foreach (['Available','Assigned','Under Repair','Damaged','Retired'] as $s): ?>
                  <option value="<?= $s ?>" <?= $asset['status']===$s?'selected':'' ?>><?= $s ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group" style="margin-bottom:1rem">
                <label>Note (optional)</label>
                <textarea name="note" placeholder="Reason for status change..." style="min-height:70px"></textarea>
              </div>
              <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">Update Status</button>
            </form>
            <div class="divider"></div>
            <a href="assets.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-arrow-left"></i> Back to Assets</a>
            <a href="asset_assign.php?id=<?= $id ?>" class="btn btn-primary btn-sm" style="margin-left:6px">Assign <i class="fa-solid fa-arrow-right"></i></a>
          </div>
        </div>

        <!-- Activity Log -->
        <div class="card">
          <div class="card-header"><div class="card-title">📝 Activity Log</div></div>
          <div class="card-body">
            <?php if (empty($logs)): ?>
              <p style="color:var(--text-muted);font-size:0.85rem">No activity yet.</p>
            <?php else: ?>
              <div class="timeline">
                <?php foreach ($logs as $log): ?>
                <div class="tl-item">
                  <div class="tl-dot"></div>
                  <div>
                    <div class="tl-text"><strong><?= sanitize($log['done_by_name']) ?></strong> — <?= sanitize($log['action']) ?>
                      <?php if ($log['note']): ?><br><span style="color:var(--text-muted)"><?= sanitize($log['note']) ?></span><?php endif; ?>
                    </div>
                    <div class="tl-time"><?= date('d M Y, H:i', strtotime($log['created_at'])) ?></div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div>
  </main>
</div>
<script src="<?= SITE_URL ?>/assets/js/theme.js"></script>
</body>
</html>