<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();
$admin_notif_count = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE emp_id=" . (int)$_SESSION['user_id'] . " AND is_read=0")->fetchColumn();

$success = $error = '';

// ── Delete Employee ──
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    $tickets = $pdo->prepare("SELECT id FROM tickets WHERE emp_id = ?");
    $tickets->execute([$del_id]);
    $ticket_ids = $tickets->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($ticket_ids)) {
        $in = implode(',', $ticket_ids);
        $pdo->exec("DELETE FROM ticket_logs WHERE ticket_id IN ($in)");
    }
    $pdo->prepare("DELETE FROM tickets WHERE emp_id = ?")->execute([$del_id]);
    $pdo->prepare("DELETE FROM employees WHERE id = ? AND role = 'employee'")->execute([$del_id]);
    $success = 'Employee deleted successfully.';
}

// ── Add Employee ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $fields = ['name','emp_id','email','department','phone','role'];
    $data = [];
    foreach ($fields as $f) $data[$f] = trim($_POST[$f] ?? '');
    $data['password'] = trim($_POST['password'] ?? '');
    if (!$data['name'] || !$data['emp_id'] || !$data['email'] || !$data['password']) {
        $error = 'Name, Employee ID, Email and Password are required.';
    } else {
        try {
            $hashed = password_hash($data['password'], PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO employees (emp_id,name,email,password,department,phone,role) VALUES (?,?,?,?,?,?,?)")
                ->execute([$data['emp_id'],$data['name'],$data['email'],$hashed,$data['department'],$data['phone'],$data['role']]);
            $success = "Employee {$data['name']} added successfully.";
        } catch (PDOException $e) {
            $error = 'Employee ID or Email already exists.';
        }
    }
}

// ── Edit Employee ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    $edit_id   = (int)($_POST['edit_id'] ?? 0);
    $name      = trim($_POST['name']       ?? '');
    $emp_id    = trim($_POST['emp_id']     ?? '');
    $email     = trim($_POST['email']      ?? '');
    $dept      = trim($_POST['department'] ?? '');
    $phone     = trim($_POST['phone']      ?? '');
    $role      = trim($_POST['role']       ?? 'employee');
    $status    = trim($_POST['status']     ?? 'active');
    $new_pass  = trim($_POST['new_password'] ?? '');

    if (!$name || !$emp_id || !$email) {
        $error = 'Name, Employee ID and Email are required.';
    } else {
        try {
            if ($new_pass) {
                $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE employees SET name=?,emp_id=?,email=?,password=?,department=?,phone=?,role=?,status=? WHERE id=?")
                    ->execute([$name,$emp_id,$email,$hashed,$dept,$phone,$role,$status,$edit_id]);
            } else {
                $pdo->prepare("UPDATE employees SET name=?,emp_id=?,email=?,department=?,phone=?,role=?,status=? WHERE id=?")
                    ->execute([$name,$emp_id,$email,$dept,$phone,$role,$status,$edit_id]);
            }
            $success = "Employee <strong>$name</strong> updated successfully.";
        } catch (PDOException $e) {
            $error = 'Employee ID or Email already exists for another employee.';
        }
    }
}

// ── Get employees ──
$employees = $pdo->query("
    SELECT e.*, COUNT(t.id) as ticket_count,
           SUM(CASE WHEN t.status IN ('open','in-progress') THEN 1 ELSE 0 END) as open_tickets
    FROM employees e
    LEFT JOIN tickets t ON e.id = t.emp_id
    WHERE e.role = 'employee'
    GROUP BY e.id ORDER BY e.name
")->fetchAll();

// ── Get admins ──
$admins = $pdo->query("SELECT * FROM employees WHERE role='admin' ORDER BY name")->fetchAll();

function avatarColor($n){$c=['#1565c0','#6a1b9a','#00695c','#c62828','#e65100','#2e7d32','#37474f','#4527a0'];$h=0;foreach(str_split($n)as $ch)$h+=ord($ch);return $c[$h%count($c)];}
function initials($n){$p=explode(' ',$n);return strtoupper(substr($p[0],0,1).(isset($p[1])?substr($p[1],0,1):''));}

$dept_list = ['Loan','Accounts','Faculty','Web Development','Mobile Development','Digital Marketing','Sales','Design','Admission','HR','Telecalling','IT Software Support','Stock','Distribution'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Employees — TicketDesk Admin</title>
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
</style>
</head>
<body>
<div class="topbar">
  <div class="logo"><div class="logo-icon">🖥</div>Ticket<span>Desk</span> <span style="font-size:0.7rem;color:var(--text-muted);margin-left:6px;font-weight:400">ADMIN</span></div>
  <div class="topbar-nav">
    <a href="dashboard.php">Dashboard</a>
    <a href="tickets.php">Tickets</a>
    <a href="assets.php">Assets</a>
    <a href="employees.php" class="active">Employees</a>
  </div>
  <div class="topbar-right">
    <a href="<?= SITE_URL ?>/admin/admin_notifications.php" style="position:relative;text-decoration:none;font-size:1.2rem;padding:4px 8px" title="Notifications">🔔<?php if($admin_notif_count>0): ?><span style="position:absolute;top:0;right:0;background:#c62828;color:#fff;font-size:0.55rem;font-weight:700;padding:1px 4px;border-radius:10px"><?= $admin_notif_count ?></span><?php endif; ?></a>
    <a href="<?= SITE_URL ?>/logout.php" class="btn btn-ghost btn-sm">Logout</a>
  </div>
</div>

<div class="shell">
  <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>
  <main>
    <div class="page-header">
      <div class="breadcrumb">TICKETDESK / <span>EMPLOYEES</span></div>
      <h1>Employee Management</h1>
      <p>Manage your team members and their access</p>
    </div>

    <?php if ($success): ?><div class="alert alert-success">✅ <?= $success ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error">⚠️ <?= sanitize($error) ?></div><?php endif; ?>

    <!-- Add Button -->
    <div style="display:flex;justify-content:flex-end;margin-bottom:1rem">
      <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('open')">➕ Add New Employee</button>
    </div>

    <!-- Employee Table -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">All Employees (<?= count($employees) ?>)</div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Employee</th><th>ID</th><th>Department</th><th>Email</th><th>Tickets</th><th>Open</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($employees as $e): ?>
            <tr>
              <td>
                <div class="flex gap-2">
                  <div class="emp-avatar" style="background:<?= avatarColor($e['name']) ?>"><?= initials($e['name']) ?></div>
                  <div>
                    <div style="font-weight:500"><?= sanitize($e['name']) ?></div>
                    <div class="text-muted"><?= $e['status'] === 'active' ? '<span class="online-dot">● Active</span>' : '<span class="offline-dot">● Inactive</span>' ?></div>
                  </div>
                </div>
              </td>
              <td class="ticket-id"><?= sanitize($e['emp_id']) ?></td>
              <td><span class="dept-badge"><?= sanitize($e['department']) ?></span></td>
              <td style="font-size:0.78rem;color:var(--text-muted)"><?= sanitize($e['email']) ?></td>
              <td><span class="side-badge"><?= $e['ticket_count'] ?></span></td>
              <td><?= $e['open_tickets'] > 0 ? '<span class="side-badge red">'.$e['open_tickets'].'</span>' : '-' ?></td>
              <td style="display:flex;gap:5px;flex-wrap:wrap">
                <a href="<?= SITE_URL ?>/admin/tickets.php?q=<?= urlencode($e['name']) ?>" class="btn btn-ghost btn-xs">Tickets</a>
                <button class="btn btn-primary btn-xs" onclick="openEditEmp(<?= htmlspecialchars(json_encode($e)) ?>)">✏️ Edit</button>
                <a href="?delete=<?= $e['id'] ?>" class="btn btn-danger btn-xs" onclick="return confirm('Delete this employee AND all their tickets?')">Delete</a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($employees)): ?>
              <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:2rem">No employees yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Admin Table -->
    <div class="card" style="margin-top:1.5rem">
      <div class="card-header">
        <div class="card-title">🛡️ Admins (<?= count($admins) ?>)</div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Admin</th><th>ID</th><th>Email</th><th>Phone</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($admins as $a): ?>
            <tr>
              <td>
                <div class="flex gap-2">
                  <div class="emp-avatar" style="background:#c62828"><?= initials($a['name']) ?></div>
                  <div>
                    <div style="font-weight:500"><?= sanitize($a['name']) ?></div>
                    <div class="text-muted"><span style="color:var(--red-primary);font-size:0.7rem">● Admin</span></div>
                  </div>
                </div>
              </td>
              <td class="ticket-id"><?= sanitize($a['emp_id']) ?></td>
              <td style="font-size:0.78rem;color:var(--text-muted)"><?= sanitize($a['email']) ?></td>
              <td style="font-size:0.78rem;color:var(--text-muted)"><?= sanitize($a['phone'] ?: '—') ?></td>
              <td>
                <button class="btn btn-primary btn-xs" onclick="openEditAdmin(<?= htmlspecialchars(json_encode($a)) ?>)">✏️ Edit</button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ══ ADD EMPLOYEE MODAL ══ -->
    <div class="modal-overlay" id="addModal">
      <div class="modal-box">
        <div class="modal-header">
          <h3>➕ Add New Employee</h3>
          <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('open')">✕</button>
        </div>
        <div class="modal-body">
          <form method="POST">
            <input type="hidden" name="action" value="add"/>
            <div class="form-grid-2">
              <div class="fg"><label>Full Name *</label><input type="text" name="name" placeholder="John Smith" required/></div>
              <div class="fg"><label>Employee ID *</label><input type="text" name="emp_id" placeholder="EMP-0120" required/></div>
            </div>
            <div class="fg"><label>Email *</label><input type="email" name="email" placeholder="john@company.com" required/></div>
            <div class="fg"><label>Password *</label><input type="password" name="password" placeholder="Set initial password" required/></div>
            <div class="form-grid-2">
              <div class="fg">
                <label>Department</label>
                <select name="department">
                  <option value="">— Select —</option>
                  <?php foreach($dept_list as $d): ?><option><?= $d ?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="fg">
                <label>Role</label>
                <select name="role">
                  <option value="employee">Employee</option>
                  <option value="admin">Admin</option>
                </select>
              </div>
            </div>
            <div class="fg"><label>Phone</label><input type="tel" name="phone" placeholder="Optional"/></div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:0.5rem">
              <button type="button" class="btn btn-ghost" onclick="document.getElementById('addModal').classList.remove('open')">Cancel</button>
              <button type="submit" class="btn btn-primary">➕ Add Employee</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- ══ EDIT EMPLOYEE MODAL ══ -->
    <div class="modal-overlay" id="editModal">
      <div class="modal-box">
        <div class="modal-header">
          <h3>✏️ Edit Employee</h3>
          <button class="modal-close" onclick="document.getElementById('editModal').classList.remove('open')">✕</button>
        </div>
        <div class="modal-body">
          <form method="POST">
            <input type="hidden" name="action" value="edit"/>
            <input type="hidden" name="edit_id" id="edit_id"/>
            <div class="form-grid-2">
              <div class="fg"><label>Full Name *</label><input type="text" name="name" id="edit_name" required/></div>
              <div class="fg"><label>Employee ID *</label><input type="text" name="emp_id" id="edit_emp_id" required/></div>
            </div>
            <div class="fg"><label>Email *</label><input type="email" name="email" id="edit_email" required/></div>
            <div class="form-grid-2">
              <div class="fg">
                <label>Department</label>
                <select name="department" id="edit_dept">
                  <option value="">— Select —</option>
                  <?php foreach($dept_list as $d): ?><option value="<?= $d ?>"><?= $d ?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="fg">
                <label>Role</label>
                <select name="role" id="edit_role">
                  <option value="employee">Employee</option>
                  <option value="admin">Admin</option>
                </select>
              </div>
            </div>
            <div class="form-grid-2">
              <div class="fg"><label>Phone</label><input type="tel" name="phone" id="edit_phone"/></div>
              <div class="fg">
                <label>Status</label>
                <select name="status" id="edit_status">
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
              </div>
            </div>
            <div class="fg">
              <label>New Password <span style="color:var(--text-muted);font-weight:400;text-transform:none">(leave blank = no change)</span></label>
              <input type="password" name="new_password" placeholder="Enter new password to change..."/>
            </div>
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

<!-- ══ EDIT ADMIN MODAL ══ -->
<div class="modal-overlay" id="editAdminModal">
  <div class="modal-box">
    <div class="modal-header">
      <h3>🛡️ Edit Admin</h3>
      <button class="modal-close" onclick="document.getElementById('editAdminModal').classList.remove('open')">✕</button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="action" value="edit"/>
        <input type="hidden" name="edit_id" id="ea_id"/>
        <input type="hidden" name="role" value="admin"/>
        <div class="form-grid-2">
          <div class="fg"><label>Full Name *</label><input type="text" name="name" id="ea_name" required/></div>
          <div class="fg"><label>Admin ID *</label><input type="text" name="emp_id" id="ea_emp_id" required/></div>
        </div>
        <div class="fg"><label>Email *</label><input type="email" name="email" id="ea_email" required/></div>
        <div class="form-grid-2">
          <div class="fg"><label>Phone</label><input type="tel" name="phone" id="ea_phone"/></div>
          <div class="fg">
            <label>Status</label>
            <select name="status" id="ea_status">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
        <div class="fg">
          <label>Department</label>
          <select name="department" id="ea_dept">
            <option value="">— Select —</option>
            <?php foreach($dept_list as $d): ?><option value="<?= $d ?>"><?= $d ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="fg">
          <label>New Password <span style="color:var(--text-muted);font-weight:400;text-transform:none">(leave blank = no change)</span></label>
          <input type="password" name="new_password" placeholder="Enter new password to change..."/>
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:0.5rem">
          <button type="button" class="btn btn-ghost" onclick="document.getElementById('editAdminModal').classList.remove('open')">Cancel</button>
          <button type="submit" class="btn btn-primary">💾 Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openEditEmp(e) {
    document.getElementById('edit_id').value      = e.id;
    document.getElementById('edit_name').value    = e.name;
    document.getElementById('edit_emp_id').value  = e.emp_id;
    document.getElementById('edit_email').value   = e.email;
    document.getElementById('edit_phone').value   = e.phone || '';
    document.getElementById('edit_dept').value    = e.department || '';
    document.getElementById('edit_role').value    = e.role || 'employee';
    document.getElementById('edit_status').value  = e.status || 'active';
    document.getElementById('editModal').classList.add('open');
}
// Close on overlay click
['addModal','editModal','editAdminModal'].forEach(function(id){
    document.getElementById(id).addEventListener('click', function(e){
        if(e.target === this) this.classList.remove('open');
    });
});

function openEditAdmin(a) {
    document.getElementById('ea_id').value      = a.id;
    document.getElementById('ea_name').value    = a.name;
    document.getElementById('ea_emp_id').value  = a.emp_id;
    document.getElementById('ea_email').value   = a.email;
    document.getElementById('ea_phone').value   = a.phone   || '';
    document.getElementById('ea_dept').value    = a.department || '';
    document.getElementById('ea_status').value  = a.status  || 'active';
    document.getElementById('editAdminModal').classList.add('open');
}
<?php if ($error): ?>
document.getElementById('addModal').classList.add('open');
<?php endif; ?>
</script>
<script src="<?= SITE_URL ?>/assets/js/theme.js"></script>
</body>
</html>