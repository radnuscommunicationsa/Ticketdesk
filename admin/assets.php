<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();
$admin_notif_count = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE emp_id=" . (int)$_SESSION['user_id'] . " AND is_read=0")->fetchColumn();

$success = $error = '';

// ── Delete Asset ──
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM asset_assignments WHERE asset_id=?")->execute([$del_id]);
        $pdo->prepare("DELETE FROM asset_logs WHERE asset_id=?")->execute([$del_id]);
        $pdo->prepare("DELETE FROM assets WHERE id=?")->execute([$del_id]);
        $pdo->commit();
        $success = 'Asset deleted successfully.';
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = 'Delete failed: ' . $e->getMessage();
    }
}

// ── Add Asset ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    verify_csrf();
    $asset_code   = trim($_POST['asset_code']   ?? '');
    $name         = trim($_POST['name']         ?? '');
    $category     = trim($_POST['category']     ?? '');
    $brand        = trim($_POST['brand']        ?? '');
    $model        = trim($_POST['model']        ?? '');
    $serial_no    = trim($_POST['serial_no']    ?? '');
    $purchase_date= trim($_POST['purchase_date']?? '');
    $warranty     = trim($_POST['warranty_until']?? '');
    $status       = trim($_POST['status']       ?? 'Available');
    $location     = trim($_POST['location']     ?? '');
    $notes        = trim($_POST['notes']        ?? '');

    // Auto generate asset code if empty
    if (empty($asset_code)) {
        $last = $pdo->query("SELECT asset_code FROM assets ORDER BY id DESC LIMIT 1")->fetchColumn();
        $num = 1001;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $num = (int)$m[1] + 1;
        }
        $asset_code = 'AST-' . $num;
    }

    if (!$name || !$category) {
        $error = 'Asset Name and Category are required.';
    } else {
        try {
            $pdo->prepare("INSERT INTO assets (asset_code,name,category,brand,model,serial_no,purchase_date,warranty_until,status,location,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$asset_code,$name,$category,$brand,$model,$serial_no,
                    $purchase_date ?: null, $warranty ?: null, $status,$location,$notes]);
            $success = "Asset $asset_code added successfully.";
        } catch (PDOException $e) {
            $error = 'Asset code already exists.';
        }
    }
}

// ── Edit Asset ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    verify_csrf();
    $edit_id      = (int)($_POST['edit_id']      ?? 0);
    $asset_code   = trim($_POST['asset_code']    ?? '');
    $name         = trim($_POST['name']          ?? '');
    $category     = trim($_POST['category']      ?? '');
    $brand        = trim($_POST['brand']         ?? '');
    $model        = trim($_POST['model']         ?? '');
    $serial_no    = trim($_POST['serial_no']     ?? '');
    $purchase_date= trim($_POST['purchase_date'] ?? '');
    $warranty     = trim($_POST['warranty_until']?? '');
    $status       = trim($_POST['status']        ?? 'Available');
    $location     = trim($_POST['location']      ?? '');
    $notes        = trim($_POST['notes']         ?? '');

    if (!$name || !$category) {
        $error = 'Asset Name and Category are required.';
    } else {
        try {
            $pdo->prepare("UPDATE assets SET asset_code=?,name=?,category=?,brand=?,model=?,serial_no=?,purchase_date=?,warranty_until=?,status=?,location=?,notes=? WHERE id=?")
                ->execute([$asset_code,$name,$category,$brand,$model,$serial_no,
                    $purchase_date ?: null, $warranty ?: null, $status,$location,$notes,$edit_id]);
            $success = "Asset updated successfully.";
        } catch (PDOException $e) {
            $error = 'Asset code already exists for another asset.';
        }
    }
}

// ── Get all assets ──
$filter  = $_GET['status'] ?? '';
$search  = trim($_GET['q'] ?? '');
$where   = [];
$params  = [];

if ($filter && $filter !== 'All') {
    $where[]  = "a.status = ?";
    $params[] = $filter;
}
if ($search) {
    $where[]  = "(a.asset_code LIKE ? OR a.name LIKE ? OR a.brand LIKE ? OR a.model LIKE ?)";
    $params   = array_merge($params, ["%$search%","%$search%","%$search%","%$search%"]);
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$assets = $pdo->prepare("
    SELECT a.*, e.name as assigned_to
    FROM assets a
    LEFT JOIN asset_assignments aa ON a.id = aa.asset_id AND aa.returned_at IS NULL
    LEFT JOIN employees e ON aa.emp_id = e.id
    $where_sql
    ORDER BY a.asset_code
");
$assets->execute($params);
$assets = $assets->fetchAll();

// Stats
$stats = $pdo->query("SELECT
    COUNT(*) as total,
    SUM(status='Available') as available,
    SUM(status='Assigned') as assigned,
    SUM(status='Under Repair') as repair,
    SUM(status='Damaged') as damaged
    FROM assets")->fetch();

$categories = ['Laptop','Desktop','Monitor','Printer','Phone','Server','Network Device','Other'];
$statuses   = ['Available','Assigned','Under Repair','Damaged','Retired'];

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Assets — TicketDesk Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"/>
<style>
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:500;align-items:center;justify-content:center;backdrop-filter:blur(3px);}
.modal-overlay.open{display:flex;}
.modal-box{background:var(--bg-card);border:1px solid var(--border);border-radius:12px;width:100%;max-width:600px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);animation:slideUp 0.2s ease;}
@keyframes slideUp{from{transform:translateY(30px);opacity:0}to{transform:translateY(0);opacity:1}}
.modal-header{padding:1.1rem 1.5rem;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--bg-mid);z-index:1;}
.modal-header h3{font-size:1rem;font-weight:600;color:var(--text-main);}
.modal-close{background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--text-muted);padding:4px 8px;border-radius:4px;}
.modal-close:hover{background:var(--bg-hover);color:var(--text-main);}
.modal-body{padding:1.4rem;}
.form-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
.fg{display:flex;flex-direction:column;gap:5px;margin-bottom:0.9rem;}
.fg label{font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--text-muted);}
.status-pill{display:inline-block;font-size:0.73rem;font-weight:600;padding:3px 10px;border-radius:10px;}
</style>
</head>
<body>
<div class="topbar">
  <div class="logo"><div class="logo-icon"><i class="fa-solid fa-computer"></i></div>Ticket<span>Desk</span> <span style="font-size:0.7rem;color:var(--text-muted);margin-left:6px;font-weight:400">ADMIN</span></div>
  <div class="topbar-nav">
    <a href="dashboard.php">Dashboard</a>
    <a href="tickets.php">Tickets</a>
    <a href="assets.php" class="active">Assets</a>
    <a href="employees.php">Employees</a>
    <a href="reports.php">Reports</a>
  </div>
  <div class="topbar-right">
    <a href="<?= SITE_URL ?>/admin/admin_notifications.php" style="position:relative;text-decoration:none;font-size:1.2rem;padding:4px 8px" title="Notifications"><i class="fa-solid fa-bell"></i><?php if($admin_notif_count>0): ?><span style="position:absolute;top:0;right:0;background:#EF4444;color:#fff;font-size:0.55rem;font-weight:700;padding:1px 4px;border-radius:10px"><?= $admin_notif_count ?></span><?php endif; ?></a>
    <a href="<?= SITE_URL ?>/logout.php" class="btn btn-ghost btn-sm">Logout</a>
  </div>
</div>

<div class="shell">
  <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>
  <main>
    <div class="page-header">
      <div class="breadcrumb">TICKETDESK / <span>ASSETS</span></div>
      <h1>🖥 Asset Management</h1>
      <p>Track and manage all company assets</p>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><i class="fa-solid fa-check"></i> <?= $success ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= sanitize($error) ?></div><?php endif; ?>

    <!-- Stats -->
    <div class="stats" style="grid-template-columns:repeat(5,1fr)">
      <div class="stat-card c-blue"><div class="stat-label">Total</div><div class="stat-value"><?= $stats['total'] ?></div></div>
      <div class="stat-card c-green"><div class="stat-label">Available</div><div class="stat-value"><?= $stats['available'] ?></div></div>
      <div class="stat-card c-gold"><div class="stat-label">Assigned</div><div class="stat-value"><?= $stats['assigned'] ?></div></div>
      <div class="stat-card c-red"><div class="stat-label">Repair</div><div class="stat-value"><?= $stats['repair'] ?></div></div>
      <div class="stat-card c-red"><div class="stat-label">Damaged</div><div class="stat-value"><?= $stats['damaged'] ?></div></div>
    </div>

    <!-- Filters + Add Button -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:1rem">
      <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <div class="search-input-wrap">
          <span class="search-icon">🔍</span>
          <input type="text" name="q" placeholder="Search assets..." value="<?= sanitize($search) ?>"/>
        </div>
        <?php foreach (['All','Available','Assigned','Under Repair','Damaged','Retired'] as $s): ?>
          <a href="?status=<?= $s ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="filter-chip <?= ($filter === $s || (!$filter && $s === 'All')) ? 'active' : '' ?>"><?= $s ?></a>
        <?php endforeach; ?>
        <button type="submit" class="btn btn-ghost btn-sm">Search</button>
      </form>
      <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('open')">➕ Add Asset</button>
    </div>

    <!-- Assets Table -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">All Assets (<?= count($assets) ?>)</div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Asset</th><th>Code</th><th>Category</th><th>Brand/Model</th><th>Status</th><th>Assigned To</th><th>Location</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($assets as $a): ?>
            <tr>
              <td>
                <div style="font-size:1.2rem;display:inline-block;margin-right:6px"><?= catIcon($a['category']) ?></div>
                <span style="font-weight:500"><?= sanitize($a['name']) ?></span>
              </td>
              <td class="ticket-id"><?= sanitize($a['asset_code']) ?></td>
              <td><span class="dept-badge"><?= sanitize($a['category']) ?></span></td>
              <td style="font-size:0.78rem;color:var(--text-muted)"><?= sanitize($a['brand'] . ' ' . $a['model']) ?></td>
              <td><span class="status-pill" style="<?= statusColor($a['status']) ?>"><?= sanitize($a['status']) ?></span></td>
              <td style="font-size:0.8rem"><?= $a['assigned_to'] ? sanitize($a['assigned_to']) : '<span style="color:var(--text-muted)">—</span>' ?></td>
              <td style="font-size:0.78rem;color:var(--text-muted)"><?= sanitize($a['location'] ?: '—') ?></td>
              <td style="display:flex;gap:5px;flex-wrap:wrap">
                <a href="asset_detail.php?id=<?= $a['id'] ?>" class="btn btn-ghost btn-xs">View</a>
                <button class="btn btn-primary btn-xs" onclick="openEditAsset(<?= htmlspecialchars(json_encode($a)) ?>)"><i class="fa-solid fa-pencil"></i> Edit</button>
                <a href="?delete=<?= $a['id'] ?>" class="btn btn-danger btn-xs" onclick="return confirm('Delete this asset?')">Delete</a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($assets)): ?>
              <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:2rem">No assets found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ══ ADD ASSET MODAL ══ -->
    <div class="modal-overlay" id="addModal">
      <div class="modal-box">
        <div class="modal-header">
          <h3><i class="fa-solid fa-plus"></i> Add New Asset</h3>
          <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('open')">✕</button>
        </div>
        <div class="modal-body">
          <form method="POST">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="add"/>
            <div class="form-grid-2">
              <div class="fg">
                <label>Asset Code <span style="font-weight:400;text-transform:none;font-size:0.65rem">(optional — auto generated)</span></label>
                <input type="text" name="asset_code" placeholder="AST-1001 or leave blank"/>
              </div>
              <div class="fg">
                <label>Asset Name *</label>
                <input type="text" name="name" placeholder="Dell Laptop XPS 15" required/>
              </div>
            </div>
            <div class="form-grid-2">
              <div class="fg">
                <label>Category *</label>
                <select name="category" required>
                  <option value="">— Select —</option>
                  <?php foreach($categories as $c): ?><option><?= $c ?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="fg">
                <label>Status</label>
                <select name="status">
                  <?php foreach($statuses as $s): ?><option><?= $s ?></option><?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="form-grid-2">
              <div class="fg"><label>Brand</label><input type="text" name="brand" placeholder="Dell"/></div>
              <div class="fg"><label>Model</label><input type="text" name="model" placeholder="XPS 15 9530"/></div>
            </div>
            <div class="form-grid-2">
              <div class="fg"><label>Serial No</label><input type="text" name="serial_no" placeholder="SN-XXXX"/></div>
              <div class="fg"><label>Location</label><input type="text" name="location" placeholder="Floor 1"/></div>
            </div>
            <div class="form-grid-2">
              <div class="fg"><label>Purchase Date</label><input type="date" name="purchase_date"/></div>
              <div class="fg"><label>Warranty Until</label><input type="date" name="warranty_until"/></div>
            </div>
            <div class="fg"><label>Notes</label><textarea name="notes" placeholder="Optional notes..." style="min-height:60px"></textarea></div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:0.5rem">
              <button type="button" class="btn btn-ghost" onclick="document.getElementById('addModal').classList.remove('open')">Cancel</button>
              <button type="submit" class="btn btn-primary">➕ Add Asset</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- ══ EDIT ASSET MODAL ══ -->
    <div class="modal-overlay" id="editModal">
      <div class="modal-box">
        <div class="modal-header">
          <h3><i class="fa-solid fa-pencil"></i> Edit Asset</h3>
          <button class="modal-close" onclick="document.getElementById('editModal').classList.remove('open')">✕</button>
        </div>
        <div class="modal-body">
          <form method="POST">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="edit"/>
            <input type="hidden" name="edit_id" id="edit_id"/>
            <div class="form-grid-2">
              <div class="fg"><label>Asset Code</label><input type="text" name="asset_code" id="edit_asset_code"/></div>
              <div class="fg"><label>Asset Name *</label><input type="text" name="name" id="edit_name" required/></div>
            </div>
            <div class="form-grid-2">
              <div class="fg">
                <label>Category *</label>
                <select name="category" id="edit_category" required>
                  <?php foreach($categories as $c): ?><option value="<?= $c ?>"><?= $c ?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="fg">
                <label>Status</label>
                <select name="status" id="edit_status">
                  <?php foreach($statuses as $s): ?><option value="<?= $s ?>"><?= $s ?></option><?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="form-grid-2">
              <div class="fg"><label>Brand</label><input type="text" name="brand" id="edit_brand"/></div>
              <div class="fg"><label>Model</label><input type="text" name="model" id="edit_model"/></div>
            </div>
            <div class="form-grid-2">
              <div class="fg"><label>Serial No</label><input type="text" name="serial_no" id="edit_serial"/></div>
              <div class="fg"><label>Location</label><input type="text" name="location" id="edit_location"/></div>
            </div>
            <div class="form-grid-2">
              <div class="fg"><label>Purchase Date</label><input type="date" name="purchase_date" id="edit_purchase"/></div>
              <div class="fg"><label>Warranty Until</label><input type="date" name="warranty_until" id="edit_warranty"/></div>
            </div>
            <div class="fg"><label>Notes</label><textarea name="notes" id="edit_notes" style="min-height:60px"></textarea></div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:0.5rem">
              <button type="button" class="btn btn-ghost" onclick="document.getElementById('editModal').classList.remove('open')">Cancel</button>
              <button type="submit" class="btn btn-primary">💾 Save Changes</button>
            </div>
          </form>
        </div>
      </div>
    </div>

  </main>
</div>

<script>
function openEditAsset(a) {
    document.getElementById('edit_id').value          = a.id;
    document.getElementById('edit_asset_code').value  = a.asset_code;
    document.getElementById('edit_name').value        = a.name;
    document.getElementById('edit_category').value    = a.category;
    document.getElementById('edit_status').value      = a.status;
    document.getElementById('edit_brand').value       = a.brand    || '';
    document.getElementById('edit_model').value       = a.model    || '';
    document.getElementById('edit_serial').value      = a.serial_no|| '';
    document.getElementById('edit_location').value    = a.location || '';
    document.getElementById('edit_purchase').value    = a.purchase_date   || '';
    document.getElementById('edit_warranty').value    = a.warranty_until  || '';
    document.getElementById('edit_notes').value       = a.notes    || '';
    document.getElementById('editModal').classList.add('open');
}
['addModal','editModal'].forEach(function(id){
    document.getElementById(id).addEventListener('click', function(e){
        if(e.target === this) this.classList.remove('open');
    });
});
<?php if ($error): ?>
document.getElementById('addModal').classList.add('open');
<?php endif; ?>
</script>
<script src="<?= SITE_URL ?>/assets/js/theme.js"></script>
</body>
</html>