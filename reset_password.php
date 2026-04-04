<?php
require_once __DIR__ . '/includes/config.php';

$token = $_GET['token'] ?? '';

$error = '';
$success = '';
$errors = [];

// Validate token first
if (empty($token)) {
    $error = 'Invalid reset token. Please request a new password reset link.';
} else {
    $resetData = verifyResetToken($pdo, $token);

    if (!$resetData) {
        $error = 'Invalid or expired reset token. Please request a new password reset link.';
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    verify_csrf();

    $password     = $_POST['password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    // Validation
    if (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters long.';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        $errors['password'] = 'Password must contain at least one uppercase letter.';
    }

    if (!preg_match('/[a-z]/', $password)) {
        $errors['password'] = 'Password must contain at least one lowercase letter.';
    }

    if (!preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Password must contain at least one number.';
    }

    if ($password !== $confirm_pass) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        // Update password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("UPDATE employees SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $resetData['employee_id']]);

        if ($stmt->rowCount() > 0) {
            // Mark token as used only if password was actually updated
            markTokenUsed($pdo, $token);
            $success = 'Your password has been successfully reset. You can now log in with your new password.';
        } else {
            // This shouldn't happen if token is valid, but handle it gracefully
            $error = 'Failed to update password. Please try again or contact support.';
            error_log('Password reset failed: No rows updated for employee_id=' . $resetData['employee_id'] . ' token=' . $token);
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
<title>Reset Password — TicketDesk</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"/>
<style>
.reset-container {
    max-width: 500px;
    margin: 0 auto;
}
.reset-box {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 2.5rem;
    box-shadow: var(--shadow-lg);
}
.reset-header {
    text-align: center;
    margin-bottom: 2rem;
}
.reset-header .logo-icon {
    width: 64px; height: 64px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-hover) 100%);
    border-radius: var(--radius-md);
    display: flex; align-items: center; justify-content: center;
    font-size: 28px; color: #fff;
    margin: 0 auto 1rem;
    box-shadow: 0 4px 12px var(--primary-glow);
}
.reset-header h2 {
    font-size: 1.5rem;
    color: var(--text-main);
    margin-bottom: 4px;
}
.reset-header p {
    font-size: 0.85rem;
    color: var(--text-muted);
}
.user-info {
    background: var(--bg-input);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 12px 16px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 12px;
}
.user-avatar {
    width: 42px; height: 42px;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; color: #fff;
    font-size: 0.9rem;
}
.user-details {
    flex: 1;
}
.user-name {
    font-weight: 600; font-size: 0.9rem; color: var(--text-main);
}
.user-role {
    font-size: 0.75rem; color: var(--text-muted);
}
.password-strength {
    margin-top: 4px; font-size: 0.7rem; color: var(--text-muted);
}
.password-strength ul {
    margin: 4px 0 0 14px;
    padding: 0;
}
.password-strength li {
    margin: 2px 0;
}
.password-strength li.valid {
    color: var(--green);
}
.password-strength li i {
    margin-right: 4px;
}
</style>
</head>
<body>
<div class="login-wrap">
  <div class="login-box reset-container">
    <div class="reset-header">
      <div class="logo-icon"><i class="fa-solid fa-lock-open"></i></div>
      <h2>Create New Password</h2>
      <p>Enter your new password below</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error">
        <i class="fa-solid fa-circle-exclamation"></i>
        <?= sanitize($error) ?>
      </div>
      <div style="text-align: center; margin-top: 1rem;">
        <a href="<?= SITE_URL ?>/forgot_password.php" class="btn btn-primary">
          <i class="fa-solid fa-arrow-left"></i> Request New Link
        </a>
      </div>
    <?php elseif ($success): ?>
      <div class="alert alert-success">
        <i class="fa-solid fa-check-circle"></i>
        <?= sanitize($success) ?>
      </div>
      <div style="text-align: center; margin-top: 1.5rem;">
        <a href="<?= SITE_URL ?>/login.php" class="btn btn-primary">
          <i class="fa-solid fa-right-to-bracket"></i> Go to Login
        </a>
      </div>
    <?php elseif ($resetData): ?>
      <!-- Show user info -->
      <div class="user-info">
        <div class="user-avatar">
          <?= strtoupper(substr($resetData['name'], 0, 1)) ?>
        </div>
        <div class="user-details">
          <div class="user-name"><?= sanitize($resetData['name']) ?></div>
          <div class="user-role"><?= sanitize($resetData['emp_id']) ?> · <?= sanitize($resetData['email']) ?></div>
        </div>
      </div>

      <?php if (!empty($error) && isset($errors)): ?>
        <div class="alert alert-error">⚠️ <?= sanitize($error) ?></div>
      <?php endif; ?>

      <form method="POST" autocomplete="off">
        <?= csrf_input() ?>
        <input type="hidden" name="token" value="<?= sanitize($token) ?>">

        <div class="form-group">
          <label>New Password</label>
          <div class="pw-wrap">
            <input type="password" name="password" id="new-password"
                   placeholder="Minimum 8 characters with uppercase, lowercase & number"
                   class="<?= isset($errors['password']) ? 'input-invalid' : '' ?>"
                   autocomplete="new-password"
                   oninput="checkPasswordStrength(this.value)"/>
            <button type="button" class="pw-toggle" onclick="togglePw('new-password', this)" title="Show password">
              <i class="fa-regular fa-eye"></i>
            </button>
          </div>
          <div class="password-strength" id="password-strength">
            <ul>
              <li id="length-check"><i class="fa-regular fa-circle"></i> At least 8 characters</li>
              <li id="uppercase-check"><i class="fa-regular fa-circle"></i> At least one uppercase (A-Z)</li>
              <li id="lowercase-check"><i class="fa-regular fa-circle"></i> At least one lowercase (a-z)</li>
              <li id="number-check"><i class="fa-regular fa-circle"></i> At least one number (0-9)</li>
            </ul>
          </div>
          <?php if (isset($errors['password'])): ?>
            <span class="field-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= sanitize($errors['password']) ?></span>
          <?php endif; ?>
        </div>

        <div class="form-group" style="margin-top:1rem">
          <label>Confirm New Password</label>
          <div class="pw-wrap">
            <input type="password" name="confirm_password" id="confirm-password"
                   placeholder="Re-enter your new password"
                   class="<?= isset($errors['confirm_password']) ? 'input-invalid' : '' ?>"
                   autocomplete="new-password"/>
            <button type="button" class="pw-toggle" onclick="togglePw('confirm-password', this)" title="Show password">
              <i class="fa-regular fa-eye"></i>
            </button>
          </div>
          <?php if (isset($errors['confirm_password'])): ?>
            <span class="field-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= sanitize($errors['confirm_password']) ?></span>
          <?php endif; ?>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:1.5rem">
          <i class="fa-solid fa-check"></i> Reset Password
        </button>
      </form>
    <?php endif; ?>
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

function checkPasswordStrength(password) {
    const checks = {
        length: password.length >= 8,
        uppercase: /[A-Z]/.test(password),
        lowercase: /[a-z]/.test(password),
        number: /[0-9]/.test(password)
    };

    // Update icons
    document.getElementById('length-check').innerHTML = '<i class="fa-solid ' + (checks.length ? 'fa-check' : 'fa-circle') + '"></i> At least 8 characters' + (checks.length ? ' ✓' : '');
    document.getElementById('uppercase-check').innerHTML = '<i class="fa-solid ' + (checks.uppercase ? 'fa-check' : 'fa-circle') + '"></i> At least one uppercase (A-Z)' + (checks.uppercase ? ' ✓' : '');
    document.getElementById('lowercase-check').innerHTML = '<i class="fa-solid ' + (checks.lowercase ? 'fa-check' : 'fa-circle') + '"></i> At least one lowercase (a-z)' + (checks.lowercase ? ' ✓' : '');
    document.getElementById('number-check').innerHTML = '<i class="fa-solid ' + (checks.number ? 'fa-check' : 'fa-circle') + '"></i> At least one number (0-9)' + (checks.number ? ' ✓' : '');
}

// Real-time validation on confirm password
document.getElementById('confirm-password').addEventListener('input', function() {
    const newPass = document.getElementById('new-password').value;
    const confirmPass = this.value;
    const confirmGroup = this.closest('.form-group');

    if (confirmPass && newPass !== confirmPass) {
        confirmGroup.querySelector('.field-error')?.remove();
        const error = document.createElement('span');
        error.className = 'field-error';
        error.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Passwords do not match';
        confirmGroup.appendChild(error);
        this.classList.add('input-invalid');
    } else {
        confirmGroup.querySelector('.field-error')?.remove();
        this.classList.remove('input-invalid');
    }
});
</script>
<script src="<?= SITE_URL ?>/assets/js/theme.js"></script>
</body>
</html>
