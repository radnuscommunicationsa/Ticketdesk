<?php
require_once __DIR__ . '/includes/config.php';

if (isLoggedIn()) {
    redirect(isAdmin() ? SITE_URL . '/admin/dashboard.php' : SITE_URL . '/employee/dashboard.php');
}

$error = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // ── Validation ──
    if (empty($login)) {
        $errors['email'] = 'Employee ID or Email is required.';
    } elseif (strlen($login) < 3) {
        $errors['email'] = 'Employee ID or Email must be at least 3 characters.';
    } elseif (strpos($login, '@') !== false && !filter_var($login, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if (empty($password)) {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 4) {
        $errors['password'] = 'Password must be at least 4 characters.';
    }

    // ── Attempt login only if no validation errors ──
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE (emp_id = ? OR email = ?) AND status = 'active'");
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['emp_id']    = $user['emp_id'];
            $_SESSION['name']      = $user['name'];
            $_SESSION['email']     = $user['email'];
            $_SESSION['dept']      = $user['department'];
            $_SESSION['role']      = $user['role'];

            redirect($user['role'] === 'admin'
                ? SITE_URL . '/admin/dashboard.php'
                : SITE_URL . '/employee/dashboard.php');
        } elseif ($user && !password_verify($password, $user['password'])) {
            $errors['password'] = 'Incorrect password. Please try again.';
        } elseif (!$user) {
            $errors['email'] = 'No active account found with this Employee ID or Email.';
        }
    }

    // Set general error summary
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
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"/>
<style>
.field-error{color:#ef9a9a;font-size:0.72rem;margin-top:3px;display:block;}
.input-invalid{border-color:#c62828 !important;}
</style>
</head>
<body>
<div class="login-wrap">
  <div class="login-box">
    <div class="login-logo">
      <div class="logo-icon flex" style="justify-content:center">🖥</div>
      <h2>Ticket<span style="color:var(--red-primary)">Desk</span></h2>
      <p>IT Support Portal — Sign In</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error">⚠️ <?= sanitize($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label>Employee ID / Email</label>
        <input type="text" name="email"
               placeholder="EMP-0001 or you@company.com"
               value="<?= sanitize($_POST['email'] ?? '') ?>"
               class="<?= isset($errors['email']) ? 'input-invalid' : '' ?>"
               autofocus/>
        <?php if (isset($errors['email'])): ?>
          <span class="field-error">⚠ <?= sanitize($errors['email']) ?></span>
        <?php endif; ?>
      </div>

      <div class="form-group" style="margin-top:1rem">
        <label>Password</label>
        <input type="password" name="password"
               placeholder="Enter your password"
               class="<?= isset($errors['password']) ? 'input-invalid' : '' ?>"/>
        <?php if (isset($errors['password'])): ?>
          <span class="field-error">⚠ <?= sanitize($errors['password']) ?></span>
        <?php endif; ?>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:1.5rem">
        🔐 Sign In
      </button>
    </form>

    <div class="login-footer">
      <p>Contact IT Support for login help</p>
      <p style="margin-top:4px">📧 radnuscommunicationsa@gmail.com</p>
    </div>
  </div>
</div>
<script src="<?= SITE_URL ?>/assets/js/theme.js"></script>
</body>
</html>