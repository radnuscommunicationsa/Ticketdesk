<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
if (isAdmin()) redirect(SITE_URL . '/admin/dashboard.php');

$uid     = $_SESSION['user_id'];
$success = $error = '';
$errors  = [];

// Unread notification count
$notif_count = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE emp_id=? AND is_read=0");
$notif_count->execute([$uid]);
$notif_count = (int)$notif_count->fetchColumn();

// ✅ Allowed values — must exactly match your DB ENUM
$allowed_categories = [
    'Hardware Issue',
    'Software / Application',
    'Network / Connectivity',
    'Email / Communication',
    'Access / Permissions',
    'Password Reset',
    'New Equipment Request',
    'Security Incident',
    'Other',
];
$allowed_priorities   = ['critical', 'high', 'medium', 'low'];
// ✅ Fixed: use simple values that match DB ENUM exactly
$allowed_contact_pref = ['Email', 'Phone', 'In-Person'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category    = trim($_POST['category']     ?? '');
    $priority    = trim($_POST['priority']     ?? '');
    $subject     = trim($_POST['subject']      ?? '');
    $description = trim($_POST['description']  ?? '');
    $asset       = trim($_POST['asset']        ?? '');
    $contact     = trim($_POST['contact_pref'] ?? 'Email');
    $attachment  = null;

    // ── Validation ──
    if (empty($category)) {
        $errors['category'] = 'Please select an issue category.';
    } elseif (!in_array($category, $allowed_categories)) {
        $errors['category'] = 'Invalid category selected.';
    }

    if (empty($priority)) {
        $errors['priority'] = 'Please select a priority level.';
    } elseif (!in_array($priority, $allowed_priorities)) {
        $errors['priority'] = 'Invalid priority selected.';
    }

    if (empty($subject)) {
        $errors['subject'] = 'Ticket subject is required.';
    } elseif (strlen($subject) < 5) {
        $errors['subject'] = 'Subject must be at least 5 characters.';
    } elseif (strlen($subject) > 255) {
        $errors['subject'] = 'Subject must be under 255 characters.';
    }

    if (empty($description)) {
        $errors['description'] = 'Please describe your issue in detail.';
    } elseif (strlen($description) < 10) {
        $errors['description'] = 'Description must be at least 10 characters.';
    } elseif (strlen($description) > 5000) {
        $errors['description'] = 'Description is too long (max 5000 characters).';
    }

    if (!empty($asset) && strlen($asset) > 100) {
        $errors['asset'] = 'Asset name is too long (max 100 characters).';
    }

    // ✅ Fixed: validate contact_pref against allowed list
    if (empty($contact) || !in_array($contact, $allowed_contact_pref)) {
        $contact = 'Email'; // safe fallback if invalid value sent
    }

    // ── Handle file upload ──
    if (!empty($_FILES['attachment']['name'])) {
        $file     = $_FILES['attachment'];
        $allowed  = ['jpg','jpeg','png','gif','pdf','doc','docx','txt','xlsx','zip'];
        $max_size = 5 * 1024 * 1024; // 5MB
        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors['attachment'] = 'File upload failed. Please try again.';
        } elseif ($file['size'] > $max_size) {
            $errors['attachment'] = 'File too large. Max 5MB allowed.';
        } elseif (!in_array($ext, $allowed)) {
            $errors['attachment'] = 'File type not allowed. Allowed: JPG, PNG, PDF, DOC, XLSX, ZIP.';
        } else {
            $upload_dir = __DIR__ . '/../uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $safe_name  = time() . '_' . $uid . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
            if (move_uploaded_file($file['tmp_name'], $upload_dir . $safe_name)) {
                $attachment = $safe_name;
            } else {
                $errors['attachment'] = 'Could not save uploaded file.';
            }
        }
    }

    // ── Insert only if no errors ──
    if (empty($errors)) {
        try {
            $ticket_no = generateTicketNo($pdo);
            $pdo->prepare("INSERT INTO tickets (ticket_no,emp_id,category,priority,subject,description,asset,contact_pref,attachment) VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([$ticket_no, $uid, $category, $priority, $subject, $description, $asset, $contact, $attachment]);
            $new_id = $pdo->lastInsertId();
            logTicketAction($pdo, $new_id, 'Ticket created', $uid);

            // ── Notify all admins ──
            $emp_name  = $_SESSION['name'];
            $dept_name = $_SESSION['dept'];
            $pri_upper = strtoupper($priority);
            $notif_msg = 'New ' . $pri_upper . ' ticket ' . $ticket_no . ' raised by ' . $emp_name . ' (' . $dept_name . '): ' . $subject;
            $admins    = $pdo->query("SELECT id FROM employees WHERE role='admin' AND status='active'")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($admins as $admin_id) {
                $pdo->prepare("INSERT INTO notifications (emp_id, ticket_id, message) VALUES (?,?,?)")
                    ->execute([$admin_id, $new_id, $notif_msg]);
            }

            $success = "Ticket <strong>$ticket_no</strong> raised successfully! The IT team will respond within 4 business hours.";
            // Clear POST data after success
            $category = $priority = $subject = $description = $asset = '';
            $contact  = 'Email';

        } catch (PDOException $e) {
            $error = 'Failed to submit ticket. Error: ' . $e->getMessage();
        }
    } else {
        $error = 'Please fix the errors below before submitting.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Raise Ticket — TicketDesk</title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"/>
<style>
.upload-area {
    border: 2px dashed var(--border);
    border-radius: 8px;
    padding: 1.5rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    background: var(--bg-input);
    position: relative;
}
.upload-area:hover, .upload-area.drag { border-color: var(--red-primary); background: var(--red-glow); }
.upload-area input[type=file] { position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%; }
.upload-icon { font-size: 2rem; margin-bottom: 6px; }
.upload-text { font-size: 0.83rem; color: var(--text-sub); }
.upload-sub  { font-size: 0.72rem; color: var(--text-muted); margin-top: 3px; }
.file-preview { display:none; margin-top:10px; padding:8px 12px; background:var(--bg-mid); border-radius:6px; border:1px solid var(--border); font-size:0.8rem; color:var(--text-main); align-items:center; gap:8px; }
.file-preview.show { display:flex; }
.field-error { color:#ef9a9a; font-size:0.72rem; margin-top:3px; display:block; }
.input-invalid { border-color:#c62828 !important; }
.char-count { font-size:0.68rem; color:var(--text-muted); text-align:right; margin-top:2px; }
</style>
</head>
<body>
<div class="topbar">
  <div class="logo"><div class="logo-icon">🖥</div>Ticket<span>Desk</span></div>
  <div class="topbar-nav">
    <a href="dashboard.php">My Tickets</a>
    <a href="raise_ticket.php" class="active">Raise Ticket</a>
    <a href="profile.php">Profile</a>
  </div>
  <div class="topbar-right">
    <a href="notifications.php" style="position:relative;text-decoration:none;font-size:1.2rem;padding:4px 8px" title="Notifications">
      🔔<?php if($notif_count>0): ?><span style="position:absolute;top:0;right:0;background:#c62828;color:#fff;font-size:0.55rem;font-weight:700;padding:1px 4px;border-radius:10px"><?= $notif_count ?></span><?php endif; ?>
    </a>
    <div class="user">
      <div class="avatar"><?php $p=explode(' ',$_SESSION['name']); echo strtoupper(substr($p[0],0,1).(isset($p[1])?substr($p[1],0,1):'')); ?></div>
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
      <a href="raise_ticket.php" class="side-item active"><span class="side-icon">➕</span> Raise Ticket</a>
      <a href="notifications.php" class="side-item"><span class="side-icon">🔔</span> Notifications <?php if($notif_count>0): ?><span class="side-badge"><?= $notif_count ?></span><?php endif; ?></a>
      <a href="profile.php" class="side-item"><span class="side-icon">👤</span> My Profile</a>
    </div>
    <div class="side-section">
      <div class="side-label">Account</div>
      <a href="<?= SITE_URL ?>/logout.php" class="side-item" style="color:var(--red)"><span class="side-icon">🚪</span> Logout</a>
    </div>
  </div>

  <main>
    <div class="page-header">
      <div class="breadcrumb">TICKETDESK / <span>RAISE TICKET</span></div>
      <h1>Raise IT Support Ticket</h1>
      <p>Submit a new request — our team responds within 4 business hours</p>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success">✅ <?= $success ?> <a href="dashboard.php" style="color:#a5d6a7;margin-left:8px">View my tickets →</a></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-error">⚠️ <?= sanitize($error) ?></div>
    <?php endif; ?>

    <div class="card" style="margin-bottom:1rem">
      <div class="card-header"><div class="card-title">Your Details</div></div>
      <div class="card-body">
        <div class="detail-grid">
          <div class="detail-item"><div class="dl">Name</div><div class="dv"><?= sanitize($_SESSION['name']) ?></div></div>
          <div class="detail-item"><div class="dl">Employee ID</div><div class="dv mono"><?= sanitize($_SESSION['emp_id']) ?></div></div>
          <div class="detail-item"><div class="dl">Department</div><div class="dv"><span class="dept-badge"><?= sanitize($_SESSION['dept']) ?></span></div></div>
          <div class="detail-item"><div class="dl">Email</div><div class="dv"><?= sanitize($_SESSION['email']) ?></div></div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><div class="card-title">Ticket Details</div></div>
      <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
          <div class="form-grid">

            <!-- Category -->
            <div class="form-group">
              <label>Issue Category *</label>
              <select name="category" class="<?= isset($errors['category']) ? 'input-invalid' : '' ?>" required>
                <option value="">— Select Category —</option>
                <?php foreach ($allowed_categories as $cat): ?>
                  <option value="<?= htmlspecialchars($cat) ?>" <?= (($category ?? '') === $cat) ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                <?php endforeach; ?>
              </select>
              <?php if (isset($errors['category'])): ?><span class="field-error">⚠ <?= sanitize($errors['category']) ?></span><?php endif; ?>
            </div>

            <!-- Priority -->
            <div class="form-group">
              <label>Priority *</label>
              <select name="priority" class="<?= isset($errors['priority']) ? 'input-invalid' : '' ?>" required>
                <option value="">— Select Priority —</option>
                <option value="critical" <?= (($priority ?? '') === 'critical') ? 'selected' : '' ?>>🔴 Critical — Cannot work at all</option>
                <option value="high"     <?= (($priority ?? '') === 'high')     ? 'selected' : '' ?>>🟠 High — Major disruption</option>
                <option value="medium"   <?= (($priority ?? '') === 'medium')   ? 'selected' : '' ?>>🟡 Medium — Minor impact</option>
                <option value="low"      <?= (($priority ?? '') === 'low')      ? 'selected' : '' ?>>🟢 Low — Informational/request</option>
              </select>
              <?php if (isset($errors['priority'])): ?><span class="field-error">⚠ <?= sanitize($errors['priority']) ?></span><?php endif; ?>
            </div>

            <!-- Subject -->
            <div class="form-group full">
              <label>Subject / Title *</label>
              <input type="text" name="subject"
                     placeholder="Brief description of the issue"
                     value="<?= sanitize($subject ?? '') ?>"
                     maxlength="255"
                     class="<?= isset($errors['subject']) ? 'input-invalid' : '' ?>"
                     required/>
              <?php if (isset($errors['subject'])): ?><span class="field-error">⚠ <?= sanitize($errors['subject']) ?></span><?php endif; ?>
            </div>

            <!-- Description -->
            <div class="form-group full">
              <label>Detailed Description *</label>
              <textarea name="description"
                        id="desc_area"
                        placeholder="Describe in detail: what happened, when it started, any error messages..."
                        style="min-height:130px"
                        maxlength="5000"
                        class="<?= isset($errors['description']) ? 'input-invalid' : '' ?>"
                        required><?= sanitize($description ?? '') ?></textarea>
              <div class="char-count"><span id="desc_count"><?= strlen($description ?? '') ?></span> / 5000</div>
              <?php if (isset($errors['description'])): ?><span class="field-error">⚠ <?= sanitize($errors['description']) ?></span><?php endif; ?>
            </div>

            <!-- Asset -->
            <div class="form-group">
              <label>Asset / Device (optional)</label>
              <input type="text" name="asset"
                     placeholder="e.g. Dell Laptop-HR-042"
                     value="<?= sanitize($asset ?? '') ?>"
                     maxlength="100"
                     class="<?= isset($errors['asset']) ? 'input-invalid' : '' ?>"/>
              <?php if (isset($errors['asset'])): ?><span class="field-error">⚠ <?= sanitize($errors['asset']) ?></span><?php endif; ?>
            </div>

            <!-- ✅ Fixed contact_pref — explicit value attributes matching DB ENUM -->
            <div class="form-group">
              <label>Preferred Contact</label>
              <select name="contact_pref">
                <option value="Email"     <?= (($contact ?? 'Email') === 'Email')     ? 'selected' : '' ?>>Email</option>
                <option value="Phone"     <?= (($contact ?? 'Email') === 'Phone')     ? 'selected' : '' ?>>Phone</option>
                <option value="In-Person" <?= (($contact ?? 'Email') === 'In-Person') ? 'selected' : '' ?>>In-Person</option>
              </select>
            </div>

            <!-- File Upload -->
            <div class="form-group full">
              <label>Attachment (optional) <span style="font-weight:400;text-transform:none;color:var(--text-muted)">— Screenshot or file (max 5MB)</span></label>
              <div class="upload-area" id="uploadArea">
                <input type="file" name="attachment" id="fileInput" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt,.xlsx,.zip"/>
                <div class="upload-icon">📎</div>
                <div class="upload-text">Click to upload or drag & drop</div>
                <div class="upload-sub">JPG, PNG, PDF, DOC, XLSX, ZIP — Max 5MB</div>
              </div>
              <?php if (isset($errors['attachment'])): ?><span class="field-error">⚠ <?= sanitize($errors['attachment']) ?></span><?php endif; ?>
              <div class="file-preview" id="filePreview">
                <span id="fileIcon">📄</span>
                <span id="fileName"></span>
                <span id="fileSize" style="color:var(--text-muted);margin-left:auto"></span>
                <button type="button" onclick="clearFile()" style="background:none;border:none;color:#c62828;cursor:pointer;font-size:1rem">✕</button>
              </div>
            </div>

            <div class="form-group full form-actions">
              <a href="dashboard.php" class="btn btn-ghost">Cancel</a>
              <button type="submit" class="btn btn-primary">🎫 Submit Ticket</button>
            </div>

          </div>
        </form>
      </div>
    </div>
  </main>
</div>

<script>
var fileInput  = document.getElementById('fileInput');
var uploadArea = document.getElementById('uploadArea');
var preview    = document.getElementById('filePreview');
var descArea   = document.getElementById('desc_area');
var descCount  = document.getElementById('desc_count');

// Character counter
if (descArea && descCount) {
    descArea.addEventListener('input', function() {
        descCount.textContent = this.value.length;
    });
}

fileInput.addEventListener('change', function(){
    if (this.files[0]) showPreview(this.files[0]);
});

// Drag & drop
uploadArea.addEventListener('dragover',  function(e){ e.preventDefault(); this.classList.add('drag'); });
uploadArea.addEventListener('dragleave', function()  { this.classList.remove('drag'); });
uploadArea.addEventListener('drop', function(e){
    e.preventDefault(); this.classList.remove('drag');
    if (e.dataTransfer.files[0]) {
        fileInput.files = e.dataTransfer.files;
        showPreview(e.dataTransfer.files[0]);
    }
});

function showPreview(file) {
    var icons = {jpg:'🖼️',jpeg:'🖼️',png:'🖼️',gif:'🖼️',pdf:'📕',doc:'📘',docx:'📘',xlsx:'📗',zip:'📦',txt:'📄'};
    var ext = file.name.split('.').pop().toLowerCase();
    document.getElementById('fileIcon').textContent = icons[ext] || '📄';
    document.getElementById('fileName').textContent = file.name;
    document.getElementById('fileSize').textContent = (file.size/1024/1024).toFixed(2) + ' MB';
    preview.classList.add('show');
}

function clearFile() {
    fileInput.value = '';
    preview.classList.remove('show');
}
</script>
<script src="<?= SITE_URL ?>/assets/js/theme.js"></script>
</body>
</html>