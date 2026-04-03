<?php
require_once __DIR__ . '/includes/config.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect(isAdmin() ? SITE_URL . '/admin/dashboard.php' : SITE_URL . '/employee/dashboard.php');
}

$error = '';
$success = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $identifier = trim($_POST['identifier'] ?? ''); // Can be email or employee ID

    // Validation
    if (empty($identifier)) {
        $errors['identifier'] = 'Please enter your Email or Employee ID.';
    } elseif (!filter_var($identifier, FILTER_VALIDATE_EMAIL) && !preg_match('/^[A-Z0-9\-]+$/i', $identifier)) {
        $errors['identifier'] = 'Please enter a valid Email or Employee ID.';
    }

    if (empty($errors)) {
        // Check if employee exists
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE emp_id = ? OR email = ?");
        $stmt->execute([$identifier, $identifier]);
        $employee = $stmt->fetch();

        if (!$employee) {
            // Don't reveal that user doesn't exist (security best practice)
            $success = 'If an account exists, a password reset link has been sent to the registered email address.';
        } elseif ($employee['status'] !== 'active') {
            $errors['identifier'] = 'Your account is inactive. Contact your administrator.';
        } else {
            // Generate reset token
            $token = generateResetToken($pdo, $employee['id']);

            // Send reset email
            $emailSent = sendPasswordResetEmail($employee['email'], $employee['name'], $token);

            if ($emailSent) {
                $success = 'A password reset link has been sent to your email address. Please check your inbox (and spam folder).';
            } else {
                $error = 'Failed to send reset email. Please try again or contact support.';
            }
        }
    } else {
        $error = 'Please fix the errors below.';
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Forgot Password — TicketDesk</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"/>
<style>
.forgot-password-container {
    max-width: 450px;
    margin: 0 auto;
}
.forgot-box {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 2.5rem;
    box-shadow: var(--shadow-lg);
}
.forgot-header {
    text-align: center;
    margin-bottom: 2rem;
}
.forgot-header .logo-icon {
    width: 64px; height: 64px;
    background: var(--primary);
    border-radius: var(--radius-md);
    display: flex; align-items: center; justify-content: center;
    font-size: 28px; color: #fff;
    margin: 0 auto 1rem;
    box-shadow: 0 4px 12px var(--primary-glow);
}
.forgot-header h2 {
    font-size: 1.5rem;
    color: var(--text-main);
    margin-bottom: 4px;
}
.forgot-header p {
    font-size: 0.85rem;
    color: var(--text-muted);
}
.help-text {
    font-size: 0.78rem;
    color: var(--text-muted);
    margin-top: 4px;
}
</style>
</head>
<body>
<div class="login-wrap">
  <div class="login-box forgot-password-container">
    <div class="forgot-header">
      <div class="logo-icon"><i class="fa-solid fa-key"></i></div>
      <h2>Reset Your Password</h2>
      <p>Enter your email or employee ID to receive reset instructions</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error">⚠️ <?= sanitize($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success">
        <i class="fa-solid fa-check-circle"></i>
        <?= sanitize($success) ?>
      </div>
      <div style="text-align: center; margin-top: 1.5rem;">
        <a href="<?= SITE_URL ?>/login.php" class="btn btn-primary">
          <i class="fa-solid fa-right-to-bracket"></i> Back to Login
        </a>
      </div>
    <?php else: ?>

      <form method="POST" autocomplete="off">
        <?= csrf_input() ?>

        <div class="form-group">
          <label>Email Address or Employee ID</label>
          <input type="text" name="identifier"
                 placeholder="you@company.com or EMP-001"
                 value="<?= sanitize($_POST['identifier'] ?? '') ?>"
                 class="<?= isset($errors['identifier']) ? 'input-invalid' : '' ?>"
                 autofocus
                 autocomplete="username"/>
          <?php if (isset($errors['identifier'])): ?>
            <span class="field-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= sanitize($errors['identifier']) ?></span>
          <?php endif; ?>
          <div class="help-text">We'll send a reset link to your registered email address.</div>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:1.5rem">
          <i class="fa-solid fa-paper-plane"></i> Send Reset Link
        </button>
      </form>

      <div style="text-align: center; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-mid);">
        <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.5rem;">
          Remember your password?
        </p>
        <a href="<?= SITE_URL ?>/login.php" class="btn btn-ghost btn-sm">
          <i class="fa-solid fa-right-to-bracket"></i> Back to Login
        </a>
      </div>

    <?php endif; ?>

    <div class="login-footer" style="margin-top: 1.5rem;">
      <p style="font-size: 0.8rem; color: var(--text-muted);">
        <i class="fa-solid fa-shield-halved"></i> Secure reset - Link expires in 1 hour
      </p>
      <p style="margin-top: 4px; font-size: 0.8rem;">
        Need help? Contact IT Support: <a href="mailto:radnuscommunicationsa@gmail.com" style="color: var(--primary);">radnuscommunicationsa@gmail.com</a>
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
