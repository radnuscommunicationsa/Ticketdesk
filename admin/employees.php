<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();
$admin_notif_count = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE emp_id=" . (int)$_SESSION['user_id'] . " AND is_read=0")->fetchColumn();

$success = $error = '';
$errors = [];

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

    // ── Validation ──
    if (empty($data['name'])) {
        $errors['name'] = 'Full name is required.';
    } elseif (strlen($data['name']) < 2) {
        $errors['name'] = 'Name must be at least 2 characters.';
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $data['name'])) {
        $errors['name'] = 'Name must contain letters only.';
    }

    if (!empty($data['emp_id'])) {
        if (!preg_match('/^[a-zA-Z0-9\-]+$/', $data['emp_id'])) {
            $errors['emp_id'] = 'Employee ID can only contain letters, numbers, and hyphens.';
        } else {
            $check = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE emp_id = ?");
            $check->execute([$data['emp_id']]);
            if ($check->fetchColumn() > 0) {
                $errors['emp_id'] = 'This Employee ID is already taken. Please use a different one.';
            }
        }
    }

    if (!empty($data['email'])) {
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        } else {
            $check = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE email = ?");
            $check->execute([$data['email']]);
            if ($check->fetchColumn() > 0) {
                $errors['email'] = 'This email is already registered to another employee.';
            }
        }
    }

    if (empty($data['password'])) {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($data['password']) < 6) {
        $errors['password'] = 'Password must be at least 6 characters.';
    }

    if (!empty($data['phone']) && !preg_match('/^[0-9\+\-\s\(\)]{7,15}$/', $data['phone'])) {
        $errors['phone'] = 'Please enter a valid phone number.';
    }

    if (empty($data['department'])) {
        $errors['department'] = 'Please select a department.';
    }

    // ── Insert only if no errors ──
    if (empty($errors)) {
        if (empty($data['emp_id'])) {
            do {
                $max = $pdo->query("SELECT MAX(CAST(emp_id AS UNSIGNED)) FROM employees")->fetchColumn();
                $num = ($max ? (int)$max : 0) + 1;
                $data['emp_id'] = (string)$num;
                $exists = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE emp_id = ?");
                $exists->execute([$data['emp_id']]);
            } while ($exists->fetchColumn() > 0);
        }

        try {
            $hashed = password_hash($data['password'], PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO employees (emp_id,name,email,password,department,phone,role) VALUES (?,?,?,?,?,?,?)")
                ->execute([$data['emp_id'],$data['name'],$data['email'],$hashed,$data['department'],$data['phone'],$data['role']]);
            $success = "Employee {$data['name']} added successfully.";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'emp_id') !== false) {
                $errors['emp_id'] = 'Employee ID already exists. Please use a different ID.';
            } elseif (strpos($e->getMessage(), 'email') !== false) {
                $errors['email'] = 'This email is already registered to another employee.';
            } else {
                $error = 'Something went wrong: ' . $e->getMessage();
            }
        }
    }

    if (!empty($errors)) {
        $error = 'Please fix the errors below.';
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

    // ── Validation ──
    if (empty($name)) {
        $errors['edit_name'] = 'Full name is required.';
    } elseif (strlen($name) < 2) {
        $errors['edit_name'] = 'Name must be at least 2 characters.';
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $name)) {
        $errors['edit_name'] = 'Name must contain letters only.';
    }

    if (!empty($emp_id)) {
        if (!preg_match('/^[a-zA-Z0-9\-]+$/', $emp_id)) {
            $errors['edit_emp_id'] = 'Employee ID can only contain letters, numbers, and hyphens.';
        } else {
            $check = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE emp_id = ? AND id != ?");
            $check->execute([$emp_id, $edit_id]);
            if ($check->fetchColumn() > 0) {
                $errors['edit_emp_id'] = 'This Employee ID is already taken by another employee.';
            }
        }
    }

    if (!empty($email)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['edit_email'] = 'Please enter a valid email address.';
        } else {
            $check = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE email = ? AND id != ?");
            $check->execute([$email, $edit_id]);
            if ($check->fetchColumn() > 0) {
                $errors['edit_email'] = 'This email is already registered to another employee.';
            }
        }
    }

    if (!empty($new_pass) && strlen($new_pass) < 6) {
        $errors['edit_password'] = 'New password must be at least 6 characters.';
    }

    if (!empty($phone) && !preg_match('/^[0-9\+\-\s\(\)]{7,15}$/', $phone)) {
        $errors['edit_phone'] = 'Please enter a valid phone number.';
    }

    if (empty($dept)) {
        $errors['edit_department'] = 'Please select a department.';
    }

    if (!in_array($role, ['employee', 'admin'])) {
        $errors['edit_role'] = 'Invalid role selected.';
    }

    if (!in_array($status, ['active', 'inactive'])) {
        $errors['edit_status'] = 'Invalid status selected.';
    }

    // ── Update only if no errors ──
    if (empty($errors)) {
        if (empty($emp_id)) {
            do {
                $max = $pdo->query("SELECT MAX(CAST(emp_id AS UNSIGNED)) FROM employees")->fetchColumn();
                $num = ($max ? (int)$max : 0) + 1;
                $emp_id = (string)$num;
                $exists = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE emp_id = ? AND id != ?");
                $exists->execute([$emp_id, $edit_id]);
            } while ($exists->fetchColumn() > 0);
        }

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
            if (strpos($e->getMessage(), 'emp_id') !== false) {
                $errors['edit_emp_id'] = 'Employee ID already exists for another employee.';
            } elseif (strpos($e->getMessage(), 'email') !== false) {
                $errors['edit_email'] = 'This email is already registered to another employee.';
            } else {
                $error = 'Something went wrong: ' . $e->getMessage();
            }
        }
    }

    if (!empty($errors)) {
        $error = 'Please fix the errors below.';
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
.optional-tag{font-size:0.65rem;color:var(--text-muted);font-weight:400;text-transform:none;margin-left:4px;}
.field-error{color:#ef9a9a;font-size:0.72rem;margin-top:2px;display:block;}
.input-invalid{border-color:#c62828 !important;}
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

    <div style="display:flex;justify-content:flex-end;margin-bottom:1rem">
      <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('open')">➕ Add New Employee</button>
    </div>

    <!-- Employee Table -->
    <div class="card">
      <div class="card-header"><div class="card-title">All Employees (<?= count($employees) ?>)</div></div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Employee</th><th>ID</th><th>Department</th><th>Email</th><th>Tickets</th><th>Open</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($employees as $e): ?>
            <tr>
              <td>
                <div class="flex gap-2">
                  <div class="emp-avatar" style="background:<?= avatarColor($e['name']) ?>"><?= initials($e['name']) ?></div>
                  <div>
                    <div style="font-weight:500"><?= sanitize($e['name']) ?></div>
                    <div class="text-muted"><?= $e['status']==='active' ? '<span class="online-dot">● Active</span>' : '<span class="offline-dot">● Inactive</span>' ?></div>
                  </div>
                </div>
              </td>
              <td class="ticket-id"><?= sanitize($e['emp_id']) ?></td>
              <td><span class="dept-badge"><?= sanitize($e['department']) ?></span></td>
              <td style="font-size:0.78rem;color:var(--text-muted)"><?= sanitize($e['email']) ?></td>
              <td><span class="side-badge"><?= $e['ticket_count'] ?></span></td>
              <td><?= $e['open_tickets']>0 ? '<span class="side-badge red">'.$e['open_tickets'].'</span>' : '-' ?></td>
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
      <div class="card-header"><div class="card-title">🛡️ Admins (<?= count($admins) ?>)</div></div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Admin</th><th>ID</th><th>Email</th><th>Phone</th><th>Actions</th></tr></thead>
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
              <td><button class="btn btn-primary btn-xs" onclick="openEditAdmin(<?= htmlspecialchars(json_encode($a)) ?>)">✏️ Edit</button></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ADD MODAL -->
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
              <div class="fg">
                <label>Full Name *</label>
                <input type="text" name="name" placeholder="John Smith"
                       value="<?= sanitize($_POST['name'] ?? '') ?>"
                       class="<?= isset($errors['name']) ? 'input-invalid' : '' ?>" required/>
                <?php if (isset($errors['name'])): ?><span class="field-error">⚠ <?= sanitize($errors['name']) ?></span><?php endif; ?>
              </div>
              <div class="fg">
                <label>Employee ID <span class="optional-tag">(optional — auto generated)</span></label>
                <input type="text" name="emp_id" placeholder="e.g. 795 or leave blank"
                       value="<?= sanitize($_POST['emp_id'] ?? '') ?>"
                       class="<?= isset($errors['emp_id']) ? 'input-invalid' : '' ?>"/>
                <?php if (isset($errors['emp_id'])): ?><span class="field-error">⚠ <?= sanitize($errors['emp_id']) ?></span><?php endif; ?>
              </div>
            </div>
            <div class="fg">
              <label>Email <span class="optional-tag">(optional)</span></label>
              <input type="email" name="email" placeholder=""
                     value="<?= sanitize($_POST['email'] ?? '') ?>"
                     class="<?= isset($errors['email']) ? 'input-invalid' : '' ?>"/>
              <?php if (isset($errors['email'])): ?><span class="field-error">⚠ <?= sanitize($errors['email']) ?></span><?php endif; ?>
            </div>
            <div class="fg">
              <label>Password *</label>
              <input type="password" name="password" placeholder="Min. 6 characters"
                     class="<?= isset($errors['password']) ? 'input-invalid' : '' ?>" required/>
              <?php if (isset($errors['password'])): ?><span class="field-error">⚠ <?= sanitize($errors['password']) ?></span><?php endif; ?>
            </div>
            <div class="form-grid-2">
              <div class="fg">
                <label>Department</label>
                <select name="department" class="<?= isset($errors['department']) ? 'input-invalid' : '' ?>">
                  <option value="">— Select —</option>
                  <?php foreach($dept_list as $d): ?>
                    <option <?= (($_POST['department'] ?? '') === $d) ? 'selected' : '' ?>><?= $d ?></option>
                  <?php endforeach; ?>
                </select>
                <?php if (isset($errors['department'])): ?><span class="field-error">⚠ <?= sanitize($errors['department']) ?></span><?php endif; ?>
              </div>
              <div class="fg">
                <label>Role</label>
                <select name="role">
                  <option value="employee" <?= (($_POST['role'] ?? '') === 'employee') ? 'selected' : '' ?>>Employee</option>
                  <option value="admin" <?= (($_POST['role'] ?? '') === 'admin') ? 'selected' : '' ?>>Admin</option>
                </select>
              </div>
            </div>
            <div class="fg">
              <label>Phone</label>
              <input type="tel" name="phone" placeholder="Optional"
                     value="<?= sanitize($_POST['phone'] ?? '') ?>"
                     class="<?= isset($errors['phone']) ? 'input-invalid' : '' ?>"/>
              <?php if (isset($errors['phone'])): ?><span class="field-error">⚠ <?= sanitize($errors['phone']) ?></span><?php endif; ?>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:0.5rem">
              <button type="button" class="btn btn-ghost" onclick="document.getElementById('addModal').classList.remove('open')">Cancel</button>
              <button type="submit" class="btn btn-primary">➕ Add Employee</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- EDIT EMPLOYEE MODAL -->
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
              <div class="fg">
                <label>Full Name *</label>
                <input type="text" name="name" id="edit_name"
                       class="<?= isset($errors['edit_name']) ? 'input-invalid' : '' ?>" required/>
                <?php if (isset($errors['edit_name'])): ?><span class="field-error">⚠ <?= sanitize($errors['edit_name']) ?></span><?php endif; ?>
              </div>
              <div class="fg">
                <label>Employee ID <span class="optional-tag">(optional)</span></label>
                <input type="text" name="emp_id" id="edit_emp_id" placeholder="Leave blank to auto generate"
                       class="<?= isset($errors['edit_emp_id']) ? 'input-invalid' : '' ?>"/>
                <?php if (isset($errors['edit_emp_id'])): ?><span class="field-error">⚠ <?= sanitize($errors['edit_emp_id']) ?></span><?php endif; ?>
              </div>
            </div>
            <div class="fg">
              <label>Email <span class="optional-tag">(optional)</span></label>
              <input type="email" name="email" id="edit_email"
                     class="<?= isset($errors['edit_email']) ? 'input-invalid' : '' ?>"/>
              <?php if (isset($errors['edit_email'])): ?><span class="field-error">⚠ <?= sanitize($errors['edit_email']) ?></span><?php endif; ?>
            </div>
            <div class="form-grid-2">
              <div class="fg">
                <label>Department</label>
                <select name="department" id="edit_dept" class="<?= isset($errors['edit_department']) ? 'input-invalid' : '' ?>">
                  <option value="">— Select —</option>
                  <?php foreach($dept_list as $d): ?><option value="<?= $d ?>"><?= $d ?></option><?php endforeach; ?>
                </select>
                <?php if (isset($errors['edit_department'])): ?><span class="field-error">⚠ <?= sanitize($errors['edit_department']) ?></span><?php endif; ?>
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
              <div class="fg">
                <label>Phone</label>
                <input type="tel" name="phone" id="edit_phone"
                       class="<?= isset($errors['edit_phone']) ? 'input-invalid' : '' ?>"/>
                <?php if (isset($errors['edit_phone'])): ?><span class="field-error">⚠ <?= sanitize($errors['edit_phone']) ?></span><?php endif; ?>
              </div>
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
              <input type="password" name="new_password" placeholder="Min. 6 characters to change..."
                     class="<?= isset($errors['edit_password']) ? 'input-invalid' : '' ?>"/>
              <?php if (isset($errors['edit_password'])): ?><span class="field-error">⚠ <?= sanitize($errors['edit_password']) ?></span><?php endif; ?>
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

<!-- EDIT ADMIN MODAL -->
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
          <div class="fg">
            <label>Full Name *</label>
            <input type="text" name="name" id="ea_name" required/>
          </div>
          <div class="fg">
            <label>Admin ID <span class="optional-tag">(optional)</span></label>
            <input type="text" name="emp_id" id="ea_emp_id" placeholder="Leave blank to auto generate"/>
          </div>
        </div>
        <div class="fg">
          <label>Email <span class="optional-tag">(optional)</span></label>
          <input type="email" name="email" id="ea_email"/>
        </div>
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
    document.getElementById('edit_email').value   = e.email || '';
    document.getElementById('edit_phone').value   = e.phone || '';
    document.getElementById('edit_dept').value    = e.department || '';
    document.getElementById('edit_role').value    = e.role || 'employee';
    document.getElementById('edit_status').value  = e.status || 'active';
    document.getElementById('editModal').classList.add('open');
}
['addModal','editModal','editAdminModal'].forEach(function(id){
    document.getElementById(id).addEventListener('click', function(e){
        if(e.target === this) this.classList.remove('open');
    });
});
function openEditAdmin(a) {
    document.getElementById('ea_id').value      = a.id;
    document.getElementById('ea_name').value    = a.name;
    document.getElementById('ea_emp_id').value  = a.emp_id;
    document.getElementById('ea_email').value   = a.email || '';
    document.getElementById('ea_phone').value   = a.phone || '';
    document.getElementById('ea_dept').value    = a.department || '';
    document.getElementById('ea_status').value  = a.status || 'active';
    document.getElementById('editAdminModal').classList.add('open');
}
<?php if ($error && ($_POST['action'] ?? '') === 'add'): ?>
document.getElementById('addModal').classList.add('open');
<?php endif; ?>
<?php if ($error && ($_POST['action'] ?? '') === 'edit'): ?>
document.getElementById('editModal').classList.add('open');
<?php endif; ?>
</script>
<script src="<?= SITE_URL ?>/assets/js/theme.js"></script>
</body>
</html>