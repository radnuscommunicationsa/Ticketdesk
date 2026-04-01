<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();
$admin_notif_count = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE emp_id=" . (int)$_SESSION['user_id'] . " AND is_read=0")->fetchColumn();

$success = $error = '';
$errors = [];

// ── Add Ticket ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_ticket') {
    verify_csrf();
    $emp_id   = (int)trim($_POST['emp_id']    ?? 0);
    $subject  = trim($_POST['subject']         ?? '');
    $priority = trim($_POST['priority']        ?? '');
    $desc     = trim($_POST['description']     ?? '');

    // ── Validation ──
    if (empty($emp_id) || $emp_id <= 0) {
        $errors['emp_id'] = 'Please select an employee.';
    } else {
        $checkEmp = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE id = ? AND status = 'active'");
        $checkEmp->execute([$emp_id]);
        if ($checkEmp->fetchColumn() == 0) {
            $errors['emp_id'] = 'Selected employee does not exist or is inactive.';
        }
    }

    if (empty($subject)) {
        $errors['subject'] = 'Ticket subject is required.';
    } elseif (strlen($subject) < 5) {
        $errors['subject'] = 'Subject must be at least 5 characters.';
    } elseif (strlen($subject) > 255) {
        $errors['subject'] = 'Subject must be under 255 characters.';
    }

    if (empty($priority)) {
        $errors['priority'] = 'Please select a priority level.';
    } elseif (!in_array($priority, ['low', 'medium', 'high', 'critical'])) {
        $errors['priority'] = 'Invalid priority selected.';
    }

    if (empty($desc)) {
        $errors['description'] = 'Ticket description is required.';
    } elseif (strlen($desc) < 10) {
        $errors['description'] = 'Description must be at least 10 characters.';
    } elseif (strlen($desc) > 5000) {
        $errors['description'] = 'Description is too long (max 5000 characters).';
    }

    // ── Insert only if no errors ──
    if (empty($errors)) {
        try {
            $ticket_no = generateTicketNo($pdo);
            $pdo->prepare("INSERT INTO tickets (ticket_no, emp_id, subject, priority, description, status) VALUES (?,?,?,?,?,'open')")
                ->execute([$ticket_no, $emp_id, $subject, $priority, $desc]);
            $new_ticket_id = $pdo->lastInsertId();
            logTicketAction($pdo, $new_ticket_id, 'created', $_SESSION['name'], 'Ticket created by admin.');
            $success = "Ticket <strong>$ticket_no</strong> created successfully.";
        } catch (PDOException $e) {
            $error = 'Failed to create ticket: ' . $e->getMessage();
        }
    } else {
        $error = 'Please fix the errors below.';
    }
}

// Filters
$status   = $_GET['status']   ?? '';
$priority = $_GET['priority'] ?? '';
$search   = $_GET['q']        ?? '';

$where = ['1=1'];
$params = [];

if ($status)   { $where[] = 't.status = ?';   $params[] = $status; }
if ($priority) { $where[] = 't.priority = ?'; $params[] = $priority; }
if ($search)   {
    $where[] = '(t.ticket_no LIKE ? OR t.subject LIKE ? OR e.name LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}

$sql = "SELECT t.*, e.name as emp_name, e.department
        FROM tickets t
        JOIN employees e ON t.emp_id = e.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY FIELD(t.priority,'critical','high','medium','low'), t.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

// Get active employees for the Add Ticket dropdown
$all_employees = $pdo->query("SELECT id, name, emp_id, department FROM employees WHERE status = 'active' ORDER BY name")->fetchAll();

function avatarColor($n) {
    $c=['#5552DD','#7B7AFF','#10B981','#F59E0B','#3B82F6','#EC4899','#8B5CF6','#14B8A6'];
    $h=0; foreach(str_split($n) as $ch) $h+=ord($ch); return $c[$h%count($c)];
}
function initials($n) {
    $p=explode(' ',$n); return strtoupper(substr($p[0],0,1).(isset($p[1])?substr($p[1],0,1):''));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>All Tickets — TicketDesk Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"/>
<style>
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:500;align-items:center;justify-content:center;backdrop-filter:blur(3px);}
.modal-overlay.open{display:flex;}
.modal-box{background:var(--bg-card);border:1px solid var(--border);border-radius:12px;width:100%;max-width:560px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);animation:slideUp 0.2s ease;}
@keyframes slideUp{from{transform:translateY(30px);opacity:0}to{transform:translateY(0);opacity:1}}
.modal-header{padding:1.1rem 1.5rem;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--bg-mid);z-index:1;}
.modal-header h3{font-size:1rem;font-weight:600;color:var(--text-main);}
.modal-close{background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--text-muted);padding:4px 8px;border-radius:4px;}
.modal-close:hover{background:var(--bg-hover);color:var(--text-main);}
.modal-body{padding:1.4rem;}
.form-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
.fg{display:flex;flex-direction:column;gap:5px;margin-bottom:0.9rem;}
.fg label{font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--text-muted);}
.field-error{color:#FCA5A5;font-size:0.72rem;margin-top:2px;display:block;}
.input-invalid{border-color:#EF4444 !important;}
.char-count{font-size:0.68rem;color:var(--text-muted);text-align:right;margin-top:2px;}
</style>
</head>
<body>
<div class="topbar">
  <div class="logo"><div class="logo-icon"><i class="fa-solid fa-computer"></i></div>Ticket<span>Desk</span> <span style="font-size:0.7rem;color:var(--text-muted);margin-left:6px;font-weight:400">ADMIN</span></div>
  <div class="topbar-nav">
    <a href="dashboard.php">Dashboard</a>
    <a href="tickets.php" class="active">Tickets</a>
    <a href="assets.php">Assets</a>
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
      <div class="breadcrumb">TICKETDESK / <span>TICKETS</span></div>
      <h1>All Tickets</h1>
      <p>Manage and update all IT support requests</p>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><i class="fa-solid fa-check"></i> <?= $success ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= sanitize($error) ?></div><?php endif; ?>

    <form method="GET" style="display:contents">
      <div class="filters">
        <div class="search-input-wrap">
          <span class="search-icon">🔍</span>
          <input type="text" name="q" placeholder="Search tickets..." value="<?= sanitize($search) ?>"/>
        </div>
        <a href="tickets.php" class="filter-chip <?= !$status&&!$priority?'active':'' ?>">All</a>
        <a href="tickets.php?status=open" class="filter-chip <?= $status==='open'?'active':'' ?>">Open</a>
        <a href="tickets.php?status=in-progress" class="filter-chip <?= $status==='in-progress'?'active':'' ?>">In Progress</a>
        <a href="tickets.php?status=resolved" class="filter-chip <?= $status==='resolved'?'active':'' ?>">Resolved</a>
        <a href="tickets.php?status=closed" class="filter-chip <?= $status==='closed'?'active':'' ?>">Closed</a>
        <a href="tickets.php?priority=critical" class="filter-chip <?= $priority==='critical'?'active':'' ?>"><i class="fa-solid fa-circle" style="color:#EF4444;font-size:0.7em;margin-right:4px"></i>Critical</a>
        <button type="submit" class="btn btn-ghost btn-sm">Search</button>
      </div>
    </form>

    <div style="display:flex;justify-content:flex-end;margin-bottom:1rem">
      <button class="btn btn-primary" onclick="document.getElementById('addTicketModal').classList.add('open')">➕ Add New Ticket</button>
    </div>

    <div class="card">
      <div class="card-header">
        <div class="card-title">Tickets (<?= count($tickets) ?>)</div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>ID</th><th>Subject</th><th>Employee</th><th>Dept</th><th>Priority</th><th>Status</th><th>Created</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($tickets as $t): ?>
            <tr>
              <td class="ticket-id"><?= $t['ticket_no'] ?></td>
              <td style="max-width:200px"><?= sanitize($t['subject']) ?></td>
              <td>
                <div class="flex gap-2">
                  <div class="emp-avatar" style="background:<?= avatarColor($t['emp_name']) ?>"><?= initials($t['emp_name']) ?></div>
                  <?= sanitize($t['emp_name']) ?>
                </div>
              </td>
              <td><span class="dept-badge"><?= sanitize($t['department']) ?></span></td>
              <td><?= getPriorityBadge($t['priority']) ?></td>
              <td><?= getStatusBadge($t['status']) ?></td>
              <td class="text-muted mono" style="font-size:0.73rem"><?= date('d M Y', strtotime($t['created_at'])) ?></td>
              <td style="display:flex;gap:6px">
                <a href="ticket_detail.php?id=<?= $t['id'] ?>" class="btn btn-ghost btn-xs">View</a>
                <a href="ticket_detail.php?id=<?= $t['id'] ?>" class="btn btn-primary btn-xs">Update</a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($tickets)): ?>
              <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:2rem">No tickets found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ADD TICKET MODAL -->
    <div class="modal-overlay" id="addTicketModal">
      <div class="modal-box">
        <div class="modal-header">
          <h3>➕ Add New Ticket</h3>
          <button class="modal-close" onclick="document.getElementById('addTicketModal').classList.remove('open')">✕</button>
        </div>
        <div class="modal-body">
          <form method="POST">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="add_ticket"/>

            <div class="fg">
              <label>Employee *</label>
              <select name="emp_id" class="<?= isset($errors['emp_id']) ? 'input-invalid' : '' ?>">
                <option value="">— Select Employee —</option>
                <?php foreach ($all_employees as $emp): ?>
                  <option value="<?= $emp['id'] ?>" <?= (($_POST['emp_id'] ?? '') == $emp['id']) ? 'selected' : '' ?>>
                    <?= sanitize($emp['name']) ?> (<?= sanitize($emp['emp_id']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if (isset($errors['emp_id'])): ?><span class="field-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= sanitize($errors['emp_id']) ?></span><?php endif; ?>
            </div>

            <div class="fg">
              <label>Subject *</label>
              <input type="text" name="subject" placeholder="Brief description of the issue"
                     value="<?= sanitize($_POST['subject'] ?? '') ?>"
                     maxlength="255"
                     class="<?= isset($errors['subject']) ? 'input-invalid' : '' ?>"/>
              <?php if (isset($errors['subject'])): ?><span class="field-error">⚠ <?= sanitize($errors['subject']) ?></span><?php endif; ?>
            </div>

            <div class="form-group">
              <label>Priority *</label>
              <select name="priority" class="<?= isset($errors['priority']) ? 'input-invalid' : '' ?>">
                <option value="">— Select —</option>
                <option value="low"      <?= (($_POST['priority'] ?? '') === 'low')      ? 'selected' : '' ?>>Low</option>
                <option value="medium"   <?= (($_POST['priority'] ?? '') === 'medium')   ? 'selected' : '' ?>>Medium</option>
                <option value="high"     <?= (($_POST['priority'] ?? '') === 'high')     ? 'selected' : '' ?>>High</option>
                <option value="critical" <?= (($_POST['priority'] ?? '') === 'critical') ? 'selected' : '' ?>>Critical</option>
              </select>
              <?php if (isset($errors['priority'])): ?><span class="field-error">⚠ <?= sanitize($errors['priority']) ?></span><?php endif; ?>
            </div>

            <div class="fg">
              <label>Description *</label>
              <textarea name="description" rows="4" placeholder="Describe the issue in detail (min. 10 characters)"
                        maxlength="5000"
                        id="ticket_desc"
                        class="<?= isset($errors['description']) ? 'input-invalid' : '' ?>"
                        style="resize:vertical"><?= sanitize($_POST['description'] ?? '') ?></textarea>
              <div class="char-count"><span id="desc_count">0</span> / 5000</div>
              <?php if (isset($errors['description'])): ?><span class="field-error">⚠ <?= sanitize($errors['description']) ?></span><?php endif; ?>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:0.5rem">
              <button type="button" class="btn btn-ghost" onclick="document.getElementById('addTicketModal').classList.remove('open')">Cancel</button>
              <button type="submit" class="btn btn-primary">🎫 Create Ticket</button>
            </div>
          </form>
        </div>
      </div>
    </div>

  </main>
</div>

<script>
// Auto-open modal if there were validation errors on add ticket
<?php if ($error && ($_POST['action'] ?? '') === 'add_ticket'): ?>
document.getElementById('addTicketModal').classList.add('open');
<?php endif; ?>

// Close modal on backdrop click
document.getElementById('addTicketModal').addEventListener('click', function(e){
    if (e.target === this) this.classList.remove('open');
});

// Character counter for description
var descArea = document.getElementById('ticket_desc');
var descCount = document.getElementById('desc_count');
if (descArea && descCount) {
    descCount.textContent = descArea.value.length;
    descArea.addEventListener('input', function() {
        descCount.textContent = this.value.length;
    });
}
</script>
<script src="<?= SITE_URL ?>/assets/js/theme.js"></script>
</body>
</html>