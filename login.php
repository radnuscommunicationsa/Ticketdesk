<?php
require_once __DIR__ . '/includes/config.php';

if (isLoggedIn()) {
    redirect(isAdmin() ? SITE_URL . '/admin/dashboard.php' : SITE_URL . '/employee/dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email && $password) {
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
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
        } else {
            $error = 'Invalid email or password.';
        }
    } else {
        $error = 'Please enter email and password.';
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
        <label>Email Address</label>
        <input type="email" name="email" placeholder="you@company.com" value="<?= sanitize($_POST['email'] ?? '') ?>" required autofocus/>
      </div>
      <div class="form-group" style="margin-top:1rem">
        <label>Password</label>
        <input type="password" name="password" placeholder="Enter your password" required/>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:1.5rem">
        🔐 Sign In
      </button>
    </form>

<div class="login-footer">
  <p>Contact IT Support for login help</p>
  <p style="margin-top:4px">📧 itsupport@yourcompany.com</p>
</div>
</div>
</div>
<script src="<?= SITE_URL ?>/assets/js/theme.js"></script>
</body>
</html>
