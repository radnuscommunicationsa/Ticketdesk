<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect(SITE_URL . '/admin/tickets.php');

$ticket = $pdo->prepare("SELECT t.*, e.name as emp_name, e.emp_id as emp_code, e.email as emp_email, e.department, e.phone
    FROM tickets t JOIN employees e ON t.emp_id = e.id WHERE t.id = ?");
$ticket->execute([$id]);
$ticket = $ticket->fetch();
if (!$ticket) redirect(SITE_URL . '/admin/tickets.php');

$logs = $pdo->prepare("SELECT tl.*, e.name as done_by_name FROM ticket_logs tl JOIN employees e ON tl.done_by = e.id WHERE tl.ticket_id = ? ORDER BY tl.created_at DESC");
$logs->execute([$id]);
$logs = $logs->fetchAll();

$success = $error = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_status = $_POST['status'] ?? '';
    $note       = trim($_POST['note'] ?? '');
    $valid_statuses = ['open','in-progress','resolved','closed'];

    if (in_array($new_status, $valid_statuses)) {
        $resolved_at = in_array($new_status, ['resolved','closed']) ? date('Y-m-d H:i:s') : null;
        $pdo->prepare("UPDATE tickets SET status=?, resolved_at=? WHERE id=?")->execute([$new_status, $resolved_at, $id]);
        logTicketAction($pdo, $id, "Status changed to '$new_status'", $_SESSION['user_id'], $note);

        // Send notification to employee
        $status_label = ucfirst(str_replace('-', ' ', $new_status));
        $notif_msg = 'Your ticket ' . $ticket['ticket_no'] . ' has been updated to: ' . $status_label;
        if ($note) $notif_msg .= ' - ' . $note;
        $pdo->prepare("INSERT INTO notifications (emp_id, ticket_id, message) VALUES (?,?,?)")
            ->execute([$ticket['emp_id'], $id, $notif_msg]);

        $success = 'Ticket updated successfully.';

        // Reload ticket & logs
        $stmt = $pdo->prepare("SELECT t.*, e.name as emp_name, e.emp_id as emp_code, e.email as emp_email, e.department, e.phone FROM tickets t JOIN employees e ON t.emp_id = e.id WHERE t.id = ?");
        $stmt->execute([$id]);
        $ticket = $stmt->fetch();
        $stmt2 = $pdo->prepare("SELECT tl.*, e.name as done_by_name FROM ticket_logs tl JOIN employees e ON tl.done_by = e.id WHERE tl.ticket_id = ? ORDER BY tl.created_at DESC");
        $stmt2->execute([$id]);
        $logs = $stmt2->fetchAll();
    } else {
        $error = 'Invalid status selected.';
    }
}

function avatarColor($n){$c=['#1565c0','#6a1b9a','#00695c','#c62828','#e65100','#2e7d32','#37474f','#4527a0'];$h=0;foreach(str_split($n)as $ch)$h+=ord($ch);return $c[$h%count($c)];}
function initials($n){$p=explode(' ',$n);return strtoupper(substr($p[0],0,1).(isset($p[1])?substr($p[1],0,1):''));}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title><?= $ticket['ticket_no'] ?> — TicketDesk Admin</title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"/>
<style>
.attach-preview { border:1px solid var(--border); border-radius:8px; overflow:hidden; background:var(--bg-input); }
.attach-img { width:100%; max-height:340px; object-fit:contain; display:block; cursor:zoom-in; background:#000; }
.attach-file { display:flex; align-items:center; gap:12px; padding:1rem 1.2rem; }
.attach-icon { font-size:2rem; }
.attach-name { font-size:0.85rem; font-weight:500; color:var(--text-main); word-break:break-all; }
.attach-meta { font-size:0.72rem; color:var(--text-muted); margin-top:2px; }
.lightbox { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.92); z-index:999; align-items:center; justify-content:center; }
.lightbox.open { display:flex; }
.lightbox img { max-width:92vw; max-height:92vh; object-fit:contain; border-radius:4px; box-shadow:0 8px 40px rgba(0,0,0,0.6); }
.lightbox-close { position:fixed; top:18px; right:22px; background:rgba(255,255,255,0.15); border:none; color:#fff; font-size:1.5rem; width:40px; height:40px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; }
.lightbox-close:hover { background:rgba(255,255,255,0.3); }
</style>
</head>
<body>
<div class="topbar">
  <div class="logo"><div class="logo-icon">🖥</div>Ticket<span>Desk</span> <span style="font-size:0.7rem;color:var(--text-muted);margin-left:6px;font-weight:400">ADMIN</span></div>
  <div class="topbar-nav">
    <a href="dashboard.php">Dashboard</a>
    <a href="tickets.php" class="active">Tickets</a>
    <a href="assets.php">Assets</a>
    <a href="employees.php">Employees</a>
    <a href="reports.php">Reports</a>
  </div>
  <div class="topbar-right">
    <a href="<?= SITE_URL ?>/logout.php" class="btn btn-ghost btn-sm">Logout</a>
  </div>
</div>
<div class="shell">
  <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>
  <main>
    <div class="page-header">
      <div class="breadcrumb">TICKETDESK / <a href="tickets.php" style="color:var(--text-muted)">TICKETS</a> / <span><?= $ticket['ticket_no'] ?></span></div>
      <div class="flex gap-2" style="align-items:flex-start;flex-wrap:wrap">
        <div>
          <h1 class="mono"><?= $ticket['ticket_no'] ?></h1>
          <p><?= sanitize($ticket['subject']) ?></p>
        </div>
        <div style="margin-left:auto;display:flex;gap:8px">
          <?= getPriorityBadge($ticket['priority']) ?>
          <?= getStatusBadge($ticket['status']) ?>
        </div>
      </div>
    </div>

    <?php if ($success): ?><div class="alert alert-success">✅ <?= $success ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error">⚠️ <?= $error ?></div><?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 340px;gap:1.5rem">

      <!-- LEFT COLUMN -->
      <div>
        <!-- Ticket Info -->
        <div class="card">
          <div class="card-header"><div class="card-title">Ticket Information</div></div>
          <div class="card-body">
            <div class="detail-grid">
              <div class="detail-item"><div class="dl">Ticket ID</div><div class="dv mono"><?= $ticket['ticket_no'] ?></div></div>
              <div class="detail-item"><div class="dl">Category</div><div class="dv"><?= sanitize($ticket['category']) ?></div></div>
              <div class="detail-item"><div class="dl">Contact Pref</div><div class="dv"><?= sanitize($ticket['contact_pref']) ?></div></div>
              <div class="detail-item"><div class="dl">Created</div><div class="dv mono" style="font-size:0.78rem"><?= date('d M Y, H:i', strtotime($ticket['created_at'])) ?></div></div>
              <div class="detail-item"><div class="dl">Last Updated</div><div class="dv mono" style="font-size:0.78rem"><?= date('d M Y, H:i', strtotime($ticket['updated_at'])) ?></div></div>
              <?php if ($ticket['resolved_at']): ?>
              <div class="detail-item"><div class="dl">Resolved At</div><div class="dv mono" style="font-size:0.78rem"><?= date('d M Y, H:i', strtotime($ticket['resolved_at'])) ?></div></div>
              <?php endif; ?>
              <?php if ($ticket['asset']): ?>
              <div class="detail-item"><div class="dl">Asset/Device</div><div class="dv"><?= sanitize($ticket['asset']) ?></div></div>
              <?php endif; ?>
            </div>
            <div class="divider"></div>
            <div class="dl" style="margin-bottom:8px">Description</div>
            <div class="desc-box"><?= nl2br(sanitize($ticket['description'])) ?></div>
          </div>
        </div>

        <!-- Attachment -->
        <?php if (!empty($ticket['attachment'])): ?>
        <?php
          $att_file  = $ticket['attachment'];
          $att_path  = __DIR__ . '/../uploads/' . $att_file;
          $att_url   = SITE_URL . '/uploads/' . rawurlencode($att_file);
          $ext       = strtolower(pathinfo($att_file, PATHINFO_EXTENSION));
          $img_exts  = ['jpg','jpeg','png','gif','webp'];
          $is_image  = in_array($ext, $img_exts);
          $icons     = ['pdf'=>'📕','doc'=>'📘','docx'=>'📘','xlsx'=>'📗','xls'=>'📗','zip'=>'📦','txt'=>'📄','csv'=>'📊'];
          $file_icon = $icons[$ext] ?? '📎';
          $file_size = file_exists($att_path) ? round(filesize($att_path)/1024, 1) . ' KB' : '';
        ?>
        <div class="card">
          <div class="card-header">
            <div class="card-title">📎 Attachment</div>
            <a href="<?= $att_url ?>" download class="btn btn-ghost btn-sm">⬇️ Download</a>
          </div>
          <div class="card-body" style="padding:0">
            <?php if ($is_image): ?>
              <div class="attach-preview">
                <img src="<?= $att_url ?>" alt="Attachment" class="attach-img" onclick="openLightbox(this.src)" title="Click to zoom"/>
              </div>
              <div style="padding:0.7rem 1.2rem;font-size:0.75rem;color:var(--text-muted);border-top:1px solid var(--border-mid)">
                📁 <?= sanitize($att_file) ?> <?= $file_size ? '· ' . $file_size : '' ?>
                &nbsp;·&nbsp; <a href="<?= $att_url ?>" target="_blank" style="color:var(--red-primary)">Open in new tab ↗</a>
              </div>
            <?php else: ?>
              <div class="attach-file">
                <div class="attach-icon"><?= $file_icon ?></div>
                <div>
                  <div class="attach-name"><?= sanitize($att_file) ?></div>
                  <div class="attach-meta"><?= strtoupper($ext) ?> file <?= $file_size ? '· ' . $file_size : '' ?></div>
                </div>
                <a href="<?= $att_url ?>" download class="btn btn-primary btn-sm" style="margin-left:auto">⬇️ Download</a>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Activity Log -->
        <div class="card">
          <div class="card-header"><div class="card-title">Activity Log</div></div>
          <div class="card-body">
            <?php if (empty($logs)): ?>
              <p class="text-muted">No activity recorded yet.</p>
            <?php else: ?>
              <div class="timeline">
                <?php foreach ($logs as $log): ?>
                <div class="tl-item">
                  <div class="tl-dot" style="background:var(--red-primary)"></div>
                  <div>
                    <div class="tl-text"><strong><?= sanitize($log['done_by_name']) ?></strong> — <?= sanitize($log['action']) ?>
                      <?php if ($log['note']): ?><br><span style="color:var(--text-muted)"><?= sanitize($log['note']) ?></span><?php endif; ?>
                    </div>
                    <div class="tl-time"><?= date('d M Y, H:i', strtotime($log['created_at'])) ?></div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- RIGHT COLUMN -->
      <div>
        <!-- Employee Info -->
        <div class="card">
          <div class="card-header"><div class="card-title">Employee</div></div>
          <div class="card-body">
            <div class="flex gap-2" style="margin-bottom:1rem">
              <div class="emp-avatar" style="background:<?= avatarColor($ticket['emp_name']) ?>;width:42px;height:42px;font-size:0.85rem"><?= initials($ticket['emp_name']) ?></div>
              <div>
                <div style="font-weight:600"><?= sanitize($ticket['emp_name']) ?></div>
                <div class="text-muted"><?= sanitize($ticket['emp_code']) ?></div>
              </div>
            </div>
            <div style="display:flex;flex-direction:column;gap:8px;font-size:0.82rem">
              <div><span class="text-muted">Dept:</span> <span class="dept-badge"><?= sanitize($ticket['department']) ?></span></div>
              <div><span class="text-muted">Email:</span> <a href="mailto:<?= $ticket['emp_email'] ?>"><?= sanitize($ticket['emp_email']) ?></a></div>
              <?php if ($ticket['phone']): ?>
              <div><span class="text-muted">Phone:</span> <?= sanitize($ticket['phone']) ?></div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Update Status -->
        <div class="card">
          <div class="card-header"><div class="card-title">Update Status</div></div>
          <div class="card-body">
            <form method="POST">
              <div class="form-group" style="margin-bottom:1rem">
                <label>New Status</label>
                <select name="status">
                  <option value="open"        <?= $ticket['status']==='open'        ? 'selected' : '' ?>>Open</option>
                  <option value="in-progress" <?= $ticket['status']==='in-progress' ? 'selected' : '' ?>>In Progress</option>
                  <option value="resolved"    <?= $ticket['status']==='resolved'    ? 'selected' : '' ?>>Resolved</option>
                  <option value="closed"      <?= $ticket['status']==='closed'      ? 'selected' : '' ?>>Closed</option>
                </select>
              </div>
              <div class="form-group" style="margin-bottom:1rem">
                <label>Note (optional)</label>
                <textarea name="note" placeholder="Add a note about this update..." style="min-height:70px"></textarea>
              </div>
              <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">Update Ticket</button>
            </form>
            <div class="divider"></div>
            <a href="tickets.php" class="btn btn-ghost btn-sm">← Back to List</a>
          </div>
        </div>
      </div>

    </div>
  </main>
</div>

<!-- Lightbox -->
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
  <button class="lightbox-close" onclick="closeLightbox()">✕</button>
  <img id="lightbox-img" src="" alt="Preview"/>
</div>
<script>
function openLightbox(src){ document.getElementById('lightbox-img').src=src; document.getElementById('lightbox').classList.add('open'); document.body.style.overflow='hidden'; }
function closeLightbox(){ document.getElementById('lightbox').classList.remove('open'); document.body.style.overflow=''; }
document.addEventListener('keydown',function(e){ if(e.key==='Escape') closeLightbox(); });
</script>
<script src="<?= SITE_URL ?>/assets/js/theme.js"></script>
</body>
</html>