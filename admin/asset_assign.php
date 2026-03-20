<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect(SITE_URL . '/admin/assets.php');

$asset = $pdo->prepare("SELECT * FROM assets WHERE id=?");
$asset->execute([$id]); $asset = $asset->fetch();
if (!$asset) redirect(SITE_URL . '/admin/assets.php');

$employees = $pdo->query("SELECT id,emp_id,name,department FROM employees WHERE role='employee' AND status='active' ORDER BY name")->fetchAll();

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emp_id = (int)($_POST['emp_id'] ?? 0);
    $note   = trim($_POST['note'] ?? '');

    if (!$emp_id) {
        $error = 'Please select an employee.';
    } else {
        // Close any existing open assignment
        $pdo->prepare("UPDATE asset_assignments SET returned_at=NOW() WHERE asset_id=? AND returned_at IS NULL")->execute([$id]);
        // New assignment
        $pdo->prepare("INSERT INTO asset_assignments (asset_id,emp_id,assigned_by,notes) VALUES (?,?,?,?)")
            ->execute([$id, $emp_id, $_SESSION['user_id'], $note]);
        // Update asset status
        $pdo->prepare("UPDATE assets SET status='Assigned' WHERE id=?")->execute([$id]);
        // Get emp name for log
        $emp = $pdo->prepare("SELECT name FROM employees WHERE id=?"); $emp->execute([$emp_id]); $emp = $emp->fetch();
        // Log
        $pdo->prepare("INSERT INTO asset_logs (asset_id,action,done_by,note) VALUES (?,?,?,?)")
            ->execute([$id, "Asset assigned to {$emp['name']}", $_SESSION['user_id'], $note]);
        redirect(SITE_URL . '/admin/asset_detail.php?id=' . $id);
    }
}

function catIcon($c) {
    return match($c) {
        'Laptop','Desktop'=>'💻','Monitor'=>'🖥️','Printer'=>'🖨️',
        'Phone'=>'📱','Server'=>'🖧','Network Device'=>'🌐',default=>'🔧'
    };
}
$current_page = 'assets.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Assign Asset — TicketDesk</title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"/>
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
      <div class="breadcrumb">TICKETDESK / <a href="assets.php" style="color:var(--text-muted)">ASSETS</a> / <a href="asset_detail.php?id=<?= $id ?>" style="color:var(--text-muted)"><?= sanitize($asset['asset_code']) ?></a> / <span>ASSIGN</span></div>
      <h1>Assign Asset to Employee</h1>
      <p><?= catIcon($asset['category']) ?> <?= sanitize($asset['name']) ?> — <?= sanitize($asset['brand']) ?> <?= sanitize($asset['model']) ?></p>
    </div>

    <?php if ($error): ?><div class="alert alert-error">⚠️ <?= sanitize($error) ?></div><?php endif; ?>

    <div style="max-width:560px">
      <div class="card">
        <!-- Asset summary -->
        <div class="card-header"><div class="card-title">Asset: <?= sanitize($asset['asset_code']) ?></div></div>
        <div class="card-body">
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:1.5rem">
            <div><div style="font-size:0.67rem;text-transform:uppercase;color:var(--text-muted);margin-bottom:4px">Category</div><span class="dept-badge"><?= sanitize($asset['category']) ?></span></div>
            <div><div style="font-size:0.67rem;text-transform:uppercase;color:var(--text-muted);margin-bottom:4px">Brand</div><div style="font-size:0.85rem"><?= sanitize($asset['brand']) ?></div></div>
            <div><div style="font-size:0.67rem;text-transform:uppercase;color:var(--text-muted);margin-bottom:4px">Location</div><div style="font-size:0.85rem"><?= sanitize($asset['location'] ?: '—') ?></div></div>
          </div>

          <form method="POST">
            <div class="form-group" style="margin-bottom:1.2rem">
              <label>Select Employee *</label>
              <select name="emp_id" required>
                <option value="">— Choose Employee —</option>
                <?php foreach ($employees as $e): ?>
                <option value="<?= $e['id'] ?>"><?= sanitize($e['name']) ?> (<?= sanitize($e['emp_id']) ?>) — <?= sanitize($e['department']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group" style="margin-bottom:1.5rem">
              <label>Assignment Notes (optional)</label>
              <textarea name="note" placeholder="e.g. Assigned for remote work, temporary assignment..." style="min-height:80px"></textarea>
            </div>
            <div style="display:flex;gap:10px">
              <a href="asset_detail.php?id=<?= $id ?>" class="btn btn-ghost">← Cancel</a>
              <button type="submit" class="btn btn-primary">✅ Confirm Assignment</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </main>
</div>
<script src="<?= SITE_URL ?>/assets/js/theme.js"></script>
</body>
</html>
