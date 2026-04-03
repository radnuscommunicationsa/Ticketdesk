<?php
/**
 * Password Reset Test Page
 * This is a development tool to test the forgot password functionality
 * Shows recent reset tokens and allows manual testing
 */

require_once __DIR__ . '/includes/config.php';

$tests = [];

// Test 1: Check if table exists
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'password_reset_tokens'");
    $tests['Table exists'] = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    $tests['Table exists'] = false;
}

// Test 2: Check tokens
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM password_reset_tokens WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)");
    $row = $stmt->fetch();
    $tests['Tokens in last 24h'] = (int)$row['count'];
} catch (PDOException $e) {
    $tests['Tokens in last 24h'] = 'Error: ' . $e->getMessage();
}

// Test 3: Check employees
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM employees WHERE status = 'active'");
    $row = $stmt->fetch();
    $tests['Active employees'] = (int)$row['count'];
} catch (PDOException $e) {
    $tests['Active employees'] = 'Error: ' . $e->getMessage();
}

// Test 4: Generate a test token
if (isset($_GET['gen_token'])) {
    try {
        $stmt = $pdo->query("SELECT id FROM employees WHERE status = 'active' LIMIT 1");
        $emp = $stmt->fetch();
        if ($emp) {
            $token = generateResetToken($pdo, $emp['id']);
            $tests['Test token generated'] = $token;
            $tests['Email log'] = 'Check your error_log or console for the simulated email content';
        } else {
            $tests['Test token'] = 'No active employees found. Add an employee first.';
        }
    } catch (PDOException $e) {
        $tests['Test token error'] = $e->getMessage();
    }
}

// Test 5: Verify a token (if provided)
if (isset($_POST['verify_token'])) {
    $token = $_POST['token'] ?? '';
    if ($token) {
        $result = verifyResetToken($pdo, $token);
        if ($result) {
            $tests['Token valid for'] = $result['name'] . ' (' . $result['email'] . ') - ID: ' . $result['emp_id'];
        } else {
            $tests['Token verification'] = 'Invalid or expired token';
        }
    }
}

// Test 6: List recent tokens
try {
    $stmt = $pdo->query("
        SELECT prt.token, prt.created_at, prt.expires_at, prt.used,
               e.name, e.email, e.emp_id
        FROM password_reset_tokens prt
        JOIN employees e ON prt.emp_id = e.id
        ORDER BY prt.created_at DESC
        LIMIT 5
    ");
    $recentTokens = $stmt->fetchAll();
    $tests['Recent tokens'] = $recentTokens;
} catch (PDOException $e) {
    $tests['Recent tokens'] = 'Error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Password Reset Test Panel</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"/>
<style>
.test-panel {
    max-width: 800px;
    margin: 2rem auto;
    padding: 2rem;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg);
}
.test-section {
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: var(--bg-input);
    border-radius: var(--radius-sm);
}
.test-item {
    display: flex;
    justify-content: space-between;
    margin: 8px 0;
    padding: 8px 12px;
    background: var(--bg-mid);
    border-radius: 4px;
}
.test-item .pass { color: var(--green); font-weight: 600; }
.test-item .fail { color: var(--red); font-weight: 600; }
.test-item .info { color: var(--text-sub); font-size: 0.85rem; }
pre {
    background: #1e293b;
    color: #e2e8f0;
    padding: 12px;
    border-radius: var(--radius-sm);
    font-size: 0.8rem;
    overflow-x: auto;
    max-height: 300px;
    overflow-y: auto;
}
.form-inline {
    display: flex;
    gap: 8px;
    align-items: center;
}
.form-inline input {
    flex: 1;
    max-width: 400px;
}
</style>
</head>
<body>
<div class="test-panel">
  <div style="text-align: center; margin-bottom: 2rem;">
    <div class="logo-icon" style="width: 72px; height: 72px; margin: 0 auto 1rem;">
      <i class="fa-solid fa-screwdriver-wrench"></i>
    </div>
    <h1 style="margin: 0; color: var(--text-main);">Password Reset Test Panel</h1>
    <p style="color: var(--text-muted); margin-top: 8px;">Development & debugging tool for forgot password feature</p>
  </div>

  <!-- System Tests -->
  <div class="test-section">
    <h3 style="margin-top:0"><i class="fa-solid fa-check-circle"></i> System Status</h3>
    <?php foreach ($tests as $key => $value): ?>
      <?php if (is_array($value)): ?>
        <!-- Do nothing - handled below -->
      <?php elseif (is_bool($value)): ?>
        <div class="test-item">
          <span><?= sanitize($key) ?></span>
          <span class="<?= $value ? 'pass' : 'fail' ?>">
            <?= $value ? '✅ PASS' : '❌ FAIL' ?>
          </span>
        </div>
      <?php else: ?>
        <div class="test-item">
          <span><?= sanitize($key) ?></span>
          <span class="info"><?= sanitize($value) ?></span>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <!-- Generate Test Token -->
  <div class="test-section">
    <h3 style="margin-top:0"><i class="fa-solid fa-key"></i> Generate Test Token</h3>
    <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
      Generate a password reset token for the first active employee. Check your error log for the reset link.
    </p>
    <a href="?gen_token=1" class="btn btn-primary">
      <i class="fa-solid fa-plus"></i> Generate New Token
    </a>
    <?php if (isset($tests['Test token generated'])): ?>
      <div style="margin-top: 1rem; padding: 12px; background: var(--bg-mid); border-radius: var(--radius-sm);">
        <strong>Token:</strong> <code><?= sanitize($tests['Test token generated']) ?></code>
        <br><small style="color: var(--text-muted);">Use this token in reset_password.php?token=TOKEN</small>
      </div>
    <?php endif; ?>
  </div>

  <!-- Verify Token -->
  <div class="test-section">
    <h3 style="margin-top:0"><i class="fa-solid fa-magnifying-glass"></i> Verify Token</h3>
    <form method="POST" class="form-inline">
      <?= csrf_input() ?>
      <input type="text" name="token" placeholder="Paste reset token here" style="flex:1; max-width: 400px;"/>
      <button type="submit" name="verify_token" class="btn btn-primary">
        <i class="fa-solid fa-check"></i> Verify
      </button>
    </form>
    <?php if (isset($tests['Token valid for'])): ?>
      <div style="margin-top: 1rem; padding: 12px; background: #dcfce7; border-radius: var(--radius-sm); border-left: 3px solid var(--green);">
        <strong>✅ Valid!</strong> Token is active for: <?= sanitize($tests['Token valid for']) ?>
      </div>
    <?php elseif (isset($tests['Token verification'])): ?>
      <div style="margin-top: 1rem; padding: 12px; background: #fee2e2; border-radius: var(--radius-sm); border-left: 3px solid var(--red);">
        <strong>❌ Invalid:</strong> <?= sanitize($tests['Token verification']) ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Recent Tokens -->
  <div class="test-section">
    <h3 style="margin-top:0"><i class="fa-solid fa-clock-rotate-left"></i> Recent Reset Tokens</h3>
    <?php if (empty($recentTokens)): ?>
      <p style="color: var(--text-muted);">No tokens generated yet.</p>
    <?php else: ?>
      <table style="width: 100%; font-size: 0.85rem;">
        <thead>
          <tr style="border-bottom: 2px solid var(--border);">
            <th style="padding: 8px; text-align: left;">Token</th>
            <th style="padding: 8px;">Employee</th>
            <th style="padding: 8px;">Created</th>
            <th style="padding: 8px;">Expires</th>
            <th style="padding: 8px;">Used</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentTokens as $t): ?>
            <tr style="border-bottom: 1px solid var(--border);">
              <td style="padding: 8px;">
                <code style="font-size: 0.75rem;"><?= substr(sanitize($t['token']), 0, 16) ?>...</code>
              </td>
              <td style="padding: 8px; text-align: center;">
                <?= sanitize($t['name']) ?><br>
                <small style="color: var(--text-muted);"><?= sanitize($t['emp_id']) ?></small>
              </td>
              <td style="padding: 8px; text-align: center; font-size: 0.75rem;">
                <?= date('d M H:i', strtotime($t['created_at'])) ?>
              </td>
              <td style="padding: 8px; text-align: center; font-size: 0.75rem;">
                <?= date('d M H:i', strtotime($t['expires_at'])) ?>
              </td>
              <td style="padding: 8px; text-align: center;">
                <?= $t['used'] ? '<span style="color: var(--red);">Yes</span>' : '<span style="color: var(--green);">No</span>' ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <!-- Live Test Links -->
  <div class="test-section">
    <h3 style="margin-top:0"><i class="fa-solid fa-rocket"></i> Quick Test</h3>
    <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
      Click these links to test the flow:
    </p>
    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
      <a href="<?= SITE_URL ?>/forgot_password.php" class="btn btn-ghost" target="_blank">
        <i class="fa-solid fa-envelope-open"></i> Forgot Password Form
      </a>
      <?php if (!empty($recentTokens)): ?>
        <?php $testToken = $recentTokens[0]['token'] ?? ''; ?>
        <?php if ($testToken && $recentTokens[0]['used'] == 0): ?>
          <a href="<?= SITE_URL ?>/reset_password.php?token=<?= urlencode($testToken) ?>" class="btn btn-ghost" target="_blank">
            <i class="fa-solid fa-lock-open"></i> Reset Password (latest token)
          </a>
        <?php endif; ?>
      <?php endif; ?>
      <a href="<?= SITE_URL ?>/login.php" class="btn btn-ghost" target="_blank">
        <i class="fa-solid fa-right-to-bracket"></i> Login Page
      </a>
    </div>
  </div>

  <!-- Instructions -->
  <div class="test-section" style="background: var(--bg-card); border: 1px solid var(--primary);">
    <h3 style="margin-top:0; color: var(--primary);">
      <i class="fa-solid fa-circle-info"></i> How to Test
    </h3>
    <ol style="font-size: 0.85rem; line-height: 1.7; padding-left: 1.5rem;">
      <li>Make sure you have at least one <strong>active</strong> employee in the database.</li>
      <li>Click "Generate New Token" above to create a test token.</li>
      <li>Check your server error log (<code>php/error_log</code>) for the simulated email with reset link.</li>
      <li>Click "Forgot Password Form" to test the request page.</li>
      <li>Enter the employee's email/ID and submit. The system will log the reset email.</li>
      <li>Copy the token from the database or error log, then click "Verify Token" to test.</li>
      <li>Use the "Reset Password" link to actually reset the password.</li>
      <li>Log in with the employee account to verify the new password works.</li>
    </ol>
    <div style="margin-top: 1rem; padding: 12px; background: rgba(85,82,221,0.1); border-radius: var(--radius-sm); border: 1px dashed var(--primary);">
      <strong>Note:</strong> Currently, emails are logged to the PHP error log instead of being sent.
      To enable real email sending, edit <code>includes/config.php</code> and uncomment the mail() function in
      <code>sendPasswordResetEmail()</code> and configure your SMTP settings.
    </div>
  </div>
</div>
</body>
</html>
