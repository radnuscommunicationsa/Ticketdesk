<?php
require_once __DIR__ . '/includes/config.php';

if (isLoggedIn()) {
    redirect(isAdmin() ? SITE_URL . '/admin/dashboard.php' : SITE_URL . '/employee/dashboard.php');
}

$error = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $login    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? ''; // ✅ Do NOT trim password — spaces matter

    // ── Validation ──
    if (empty($login)) {
        $errors['email'] = 'Employee ID or Email is required.';
    } elseif (strpos($login, '@') !== false && !filter_var($login, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if (empty($password)) {
        $errors['password'] = 'Password is required.';
    }

    // ── Attempt login only if no validation errors ──
    if (empty($errors)) {

        // ✅ Search by emp_id OR email — no status filter yet
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE emp_id = ? OR email = ?");
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();

        if (!$user) {
            // No account found at all
            $errors['email'] = 'No account found with this Employee ID or Email.';
        } elseif ($user['status'] !== 'active') {
            // Account exists but is inactive
            $errors['email'] = 'Your account is inactive. Contact your administrator.';
        } elseif (!password_verify($password, $user['password'])) {
            // Wrong password
            $errors['password'] = 'Incorrect password. Please try again.';
        } else {
            // ✅ All good — log in
            session_regenerate_id(true); // Prevent session fixation
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['emp_id']  = $user['emp_id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['dept']    = $user['department'];
            $_SESSION['role']    = $user['role'];

            redirect($user['role'] === 'admin'
                ? SITE_URL . '/admin/dashboard.php'
                : SITE_URL . '/employee/dashboard.php');
        }
    }

    if (!empty($errors)) {
        $error = 'Please fix the errors below and try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Login — TicketDesk</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"/>
<style>
.field-error{color:#FCA5A5;font-size:0.72rem;margin-top:3px;display:block;}
.input-invalid{border-color:#EF4444 !important;}
</style>
</head>
<body>
<div class="login-wrap">
  <div class="login-box">
    <div class="login-logo">
      <div class="logo-icon flex" style="justify-content:center"><i class="fa-solid fa-computer"></i></div>
      <h2>Ticket<span style="color:var(--primary)">Desk</span></h2>
      <p>IT Support Portal — Sign In</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error">⚠️ <?= sanitize($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <?= csrf_input() ?>
      <div class="form-group">
        <label>Employee ID / Email</label>
        <input type="text" name="email"
               placeholder="ADMIN-001 or you@company.com"
               value="<?= sanitize($_POST['email'] ?? '') ?>"
               class="<?= isset($errors['email']) ? 'input-invalid' : '' ?>"
               autofocus/>
        <?php if (isset($errors['email'])): ?>
          <span class="field-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= sanitize($errors['email']) ?></span>
        <?php endif; ?>
      </div>

      <div class="form-group" style="margin-top:1rem">
        <label>Password</label>
        <div class="pw-wrap">
          <input type="password" name="password"
                 placeholder="Enter your password" id="login-pw"
                 class="<?= isset($errors['password']) ? 'input-invalid' : '' ?>"/>
          <button type="button" class="pw-toggle" onclick="togglePw('login-pw', this)" title="Show password">
            <i class="fa-regular fa-eye"></i>
          </button>
        </div>
        <?php if (isset($errors['password'])): ?>
          <span class="field-error"><i class="fa-solid fa-circle-exclamation"></i> <?= sanitize($errors['password']) ?></span>
        <?php endif; ?>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:1.5rem">
        <i class="fa-solid fa-right-to-bracket"></i> Sign In
      </button>
    </form>

    <div class="login-footer">
      <p style="margin-top:0">
        <a href="<?= SITE_URL ?>/forgot_password.php" style="color: var(--primary); font-weight: 500;">
          <i class="fa-solid fa-key"></i> Forgot Password?
        </a>
      </p>
      <p style="margin-top:8px">Need help? Contact IT Support:<br>
        <a href="mailto:radnuscommunicationsa@gmail.com" style="color: var(--primary);">radnuscommunicationsa@gmail.com</a>
      </p>
    </div>
  </div>
</div>
<script>
function togglePw(id, btn) {
    var inp = document.getElementById(id);
    if (inp.type === 'password') {
        inp.type = 'text';
        btn.innerHTML = '<i class="fa-regular fa-eye-slash"></i>';
    } else {
        inp.type = 'password';
        btn.innerHTML = '<i class="fa-regular fa-eye"></i>';
    }
}
</script>
<script src="<?= SITE_URL ?>/assets/js/theme.js"></script>
</body>
</html>