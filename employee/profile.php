<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
if (isAdmin()) redirect(SITE_URL . '/admin/dashboard.php');

$uid     = $_SESSION['user_id'];
$success = $error = '';

// Get current user data
$user = $pdo->prepare("SELECT * FROM employees WHERE id=?");
$user->execute([$uid]);
$user = $user->fetch();

// Unread notification count
$notif_count = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE emp_id=? AND is_read=0");
$notif_count->execute([$uid]);
$notif_count = (int)$notif_count->fetchColumn();

// ── Update Profile ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'profile') {
    $name  = trim($_POST['name']  ?? '');
    $phone = trim($_POST['phone'] ?? '');
    if (!$name) {
        $error = 'Name is required.';
    } else {
        $pdo->prepare("UPDATE employees SET name=?, phone=? WHERE id=?")
            ->execute([$name, $phone, $uid]);
        $_SESSION['name'] = $name;
        $user['name']     = $name;
        $user['phone']    = $phone;
        $success = 'Profile updated successfully!';
    }
}

// ── Change Password ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'password') {
    $current  = trim($_POST['current_password'] ?? '');
    $new_pass = trim($_POST['new_password']     ?? '');
    $confirm  = trim($_POST['confirm_password'] ?? '');

    if (!$current || !$new_pass || !$confirm) {
        $error = 'All password fields are required.';
    } elseif (!password_verify($current, $user['password'])) {
        $error = 'Current password is incorrect.';
    } elseif ($new_pass !== $confirm) {
        $error = 'New passwords do not match.';
    } elseif (strlen($new_pass) < 6) {
        $error = 'New password must be at least 6 characters.';
    } else {
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE employees SET password=? WHERE id=?")->execute([$hashed, $uid]);
        $success = 'Password changed successfully!';
    }
}

function initials($n){$p=explode(' ',$n);return strtoupper(substr($p[0],0,1).(isset($p[1])?substr($p[1],0,1):''));}
function avatarColor($n){$c=['#c62828','#6a1b9a','#00695c','#e65100','#2e7d32','#37474f','#4527a0','#1565c0'];$h=0;foreach(str_split($n)as $ch)$h+=ord($ch);return $c[$h%count($c)];}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>My Profile — TicketDesk</title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"/>
<style>
.profile-avatar { width:72px;height:72px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.6rem;font-weight:700;color:#fff;margin:0 auto 1rem;box-shadow:0 4px 16px var(--red-glow); }
.notif-badge { background:#c62828;color:#fff;font-size:0.6rem;font-weight:700;padding:1px 5px;border-radius:10px;margin-left:4px;vertical-align:top; }
.btn-disabled { opacity:0.5 !important; cursor:not-allowed !important; pointer-events:none !important; }
</style>
</head>
<body>
<div class="topbar">
  <div class="logo"><div class="logo-icon">🖥</div>Ticket<span>Desk</span></div>
  <div class="topbar-nav">
    <a href="dashboard.php">My Tickets</a>
    <a href="raise_ticket.php">Raise Ticket</a>
    <a href="profile.php" class="active">Profile</a>
  </div>
  <div class="topbar-right">
    <a href="notifications.php" style="position:relative;text-decoration:none;font-size:1.2rem;padding:4px 8px" title="Notifications">
      🔔<?php if($notif_count>0): ?><span style="position:absolute;top:0;right:0;background:#c62828;color:#fff;font-size:0.55rem;font-weight:700;padding:1px 4px;border-radius:10px"><?= $notif_count ?></span><?php endif; ?>
    </a>
    <div class="user">
      <div class="avatar"><?= initials($_SESSION['name']) ?></div>
      <?= sanitize($_SESSION['name']) ?>
    </div>
    <a href="<?= SITE_URL ?>/logout.php" class="btn btn-ghost btn-sm">Logout</a>
  </div>
</div>

<div class="shell">
  <div class="sidebar">
    <div class="side-section">
      <div class="side-label">My Account</div>
      <a href="dashboard.php" class="side-item"><span class="side-icon">📋</span> My Tickets</a>
      <a href="raise_ticket.php" class="side-item"><span class="side-icon">➕</span> Raise Ticket</a>
      <a href="notifications.php" class="side-item"><span class="side-icon">🔔</span> Notifications <?php if($notif_count>0): ?><span class="side-badge"><?= $notif_count ?></span><?php endif; ?></a>
      <a href="profile.php" class="side-item active"><span class="side-icon">👤</span> My Profile</a>
    </div>
    <div class="side-section">
      <div class="side-label">Account</div>
      <a href="<?= SITE_URL ?>/logout.php" class="side-item" style="color:var(--red)"><span class="side-icon">🚪</span> Logout</a>
    </div>
  </div>

  <main>
    <div class="page-header">
      <div class="breadcrumb">TICKETDESK / <span>MY PROFILE</span></div>
      <h1>👤 My Profile</h1>
      <p>Update your personal info and password</p>
    </div>

    <?php if ($success): ?><div class="alert alert-success">✅ <?= $success ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error">⚠️ <?= sanitize($error) ?></div><?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">

      <!-- Profile Info Card -->
      <div class="card">
        <div class="card-header"><div class="card-title">👤 Personal Information</div></div>
        <div class="card-body">
          <div style="text-align:center;margin-bottom:1.5rem">
            <div class="profile-avatar" style="background:<?= avatarColor($user['name']) ?>"><?= initials($user['name']) ?></div>
            <div style="font-size:0.75rem;color:var(--text-muted)"><?= sanitize($user['emp_id']) ?> &nbsp;·&nbsp; <span class="dept-badge"><?= sanitize($user['department']) ?></span></div>
          </div>
          <form method="POST">
            <input type="hidden" name="action" value="profile"/>
            <div class="form-group" style="margin-bottom:1rem">
              <label>Full Name *</label>
              <input type="text" name="name" value="<?= sanitize($user['name']) ?>" required/>
            </div>
            <div class="form-group" style="margin-bottom:1rem">
              <label>Email Address</label>
              <input type="email" value="<?= sanitize($user['email']) ?>" disabled style="opacity:0.6;cursor:not-allowed"/>
              <small style="color:var(--text-muted);font-size:0.7rem">Email cannot be changed. Contact admin.</small>
            </div>
            <div class="form-group" style="margin-bottom:1rem">
              <label>Phone Number</label>
              <input type="tel" name="phone" value="<?= sanitize($user['phone'] ?? '') ?>" placeholder="Your phone number"/>
            </div>
            <div class="form-group" style="margin-bottom:1rem">
              <label>Department</label>
              <input type="text" value="<?= sanitize($user['department']) ?>" disabled style="opacity:0.6;cursor:not-allowed"/>
              <small style="color:var(--text-muted);font-size:0.7rem">Department set by admin.</small>
            </div>
            <div style="display:flex;justify-content:flex-end">
              <button type="submit" class="btn btn-primary">💾 Save Changes</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Change Password Card -->
      <div class="card">
        <div class="card-header"><div class="card-title">🔒 Change Password</div></div>
        <div class="card-body">
          <form method="POST" id="pass-form">
            <input type="hidden" name="action" value="password"/>
            <div class="form-group" style="margin-bottom:1rem">
              <label>Current Password *</label>
              <input type="password" name="current_password" placeholder="Enter current password" required id="cp-current"/>
            </div>
            <div class="form-group" style="margin-bottom:1rem">
              <label>New Password *</label>
              <input type="password" name="new_password" placeholder="Min 6 characters" required id="np"/>
            </div>
            <div class="form-group" style="margin-bottom:1rem">
              <label>Confirm New Password *</label>
              <input type="password" name="confirm_password" placeholder="Re-enter new password" required id="cp"/>
            </div>
            <!-- Password strength bar -->
            <div style="margin-bottom:1rem">
              <div style="font-size:0.7rem;color:var(--text-muted);margin-bottom:4px">Password Strength</div>
              <div style="height:6px;background:var(--border);border-radius:3px;overflow:hidden">
                <div id="strength-bar" style="height:100%;width:0%;border-radius:3px;transition:all 0.3s"></div>
              </div>
              <div id="strength-label" style="font-size:0.7rem;margin-top:3px;color:var(--text-muted)"></div>
            </div>
            <div style="display:flex;justify-content:flex-end">
              <button type="submit" class="btn btn-primary btn-disabled" id="pass-btn" disabled>🔒 Update Password</button>
            </div>
          </form>
        </div>
      </div>

    </div>

    <!-- Account Stats -->
    <div class="card" style="margin-top:1.5rem">
      <div class="card-header"><div class="card-title">📊 My Ticket Stats</div></div>
      <div class="card-body">
        <?php
        $stats = $pdo->prepare("SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status='open' THEN 1 ELSE 0 END) as open_c,
            SUM(CASE WHEN status='in-progress' THEN 1 ELSE 0 END) as inprog,
            SUM(CASE WHEN status='resolved' OR status='closed' THEN 1 ELSE 0 END) as resolved
            FROM tickets WHERE emp_id=?");
        $stats->execute([$uid]);
        $stats = $stats->fetch();
        ?>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem">
          <div style="text-align:center;padding:1rem;background:var(--bg-mid);border-radius:8px;border:1px solid var(--border)">
            <div style="font-size:1.8rem;font-weight:700;color:var(--red-primary)"><?= $stats['total'] ?></div>
            <div style="font-size:0.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.1em">Total Raised</div>
          </div>
          <div style="text-align:center;padding:1rem;background:var(--bg-mid);border-radius:8px;border:1px solid var(--border)">
            <div style="font-size:1.8rem;font-weight:700;color:#c62828"><?= $stats['open_c'] ?></div>
            <div style="font-size:0.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.1em">Open</div>
          </div>
          <div style="text-align:center;padding:1rem;background:var(--bg-mid);border-radius:8px;border:1px solid var(--border)">
            <div style="font-size:1.8rem;font-weight:700;color:var(--orange)"><?= $stats['inprog'] ?></div>
            <div style="font-size:0.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.1em">In Progress</div>
          </div>
          <div style="text-align:center;padding:1rem;background:var(--bg-mid);border-radius:8px;border:1px solid var(--border)">
            <div style="font-size:1.8rem;font-weight:700;color:var(--green)"><?= $stats['resolved'] ?></div>
            <div style="font-size:0.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.1em">Resolved</div>
          </div>
        </div>
      </div>
    </div>

  </main>
</div>

<script>
var passBtn = document.getElementById('pass-btn');
var npInput = document.getElementById('np');

function checkPasswordReady() {
    var v = npInput.value;
    var bar = document.getElementById('strength-bar');
    var lbl = document.getElementById('strength-label');
    var score = 0;

    if(v.length >= 6)  score++;
    if(v.length >= 10) score++;
    if(/[A-Z]/.test(v)) score++;
    if(/[0-9]/.test(v)) score++;
    if(/[^A-Za-z0-9]/.test(v)) score++;

    var colors = ['#e53935','#e53935','#fb8c00','#f9a825','#2e7d32'];
    var labels = ['','Very Weak','Weak','Good','Strong'];

    bar.style.width = (score * 20) + '%';
    bar.style.background = colors[score] || '#e53935';
    lbl.textContent = labels[score] || '';
    lbl.style.color = colors[score] || '#e53935';

    // Enable button only if 6+ characters
    if(v.length >= 6) {
        passBtn.disabled = false;
        passBtn.classList.remove('btn-disabled');
        passBtn.style.opacity = '1';
        passBtn.style.cursor = 'pointer';
        passBtn.style.pointerEvents = 'auto';
    } else {
        passBtn.disabled = true;
        passBtn.classList.add('btn-disabled');
        passBtn.style.opacity = '0.5';
        passBtn.style.cursor = 'not-allowed';
        passBtn.style.pointerEvents = 'none';
    }
}

npInput.addEventListener('input', checkPasswordReady);
</script>
<script src="<?= SITE_URL ?>/assets/js/theme.js"></script>
</body>
</html>