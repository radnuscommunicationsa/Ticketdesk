<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
if (isAdmin()) redirect(SITE_URL . '/admin/dashboard.php');

$id  = (int)($_GET['id'] ?? 0);
$uid = $_SESSION['user_id'];
if (!$id) redirect(SITE_URL . '/employee/dashboard.php');

$ticket = $pdo->prepare("SELECT * FROM tickets WHERE id=? AND emp_id=?");
$ticket->execute([$id, $uid]);
$ticket = $ticket->fetch();
if (!$ticket) redirect(SITE_URL . '/employee/dashboard.php');

$logs = $pdo->prepare("SELECT tl.*, e.name as done_by_name FROM ticket_logs tl JOIN employees e ON tl.done_by=e.id WHERE tl.ticket_id=? ORDER BY tl.created_at DESC");
$logs->execute([$id]);
$logs = $logs->fetchAll();

// Unread notification count
$notif_count = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE emp_id=? AND is_read=0");
$notif_count->execute([$uid]);
$notif_count = (int)$notif_count->fetchColumn();

// Attachment info
$att_file = $ticket['attachment'] ?? '';
$att_url  = $att_file ? SITE_URL . '/uploads/' . rawurlencode($att_file) : '';
$att_path = $att_file ? __DIR__ . '/../uploads/' . $att_file : '';
$ext      = $att_file ? strtolower(pathinfo($att_file, PATHINFO_EXTENSION)) : '';
$img_exts = ['jpg','jpeg','png','gif','webp'];
$is_image = in_array($ext, $img_exts);
$file_icons = [
    'pdf'  => 'fa-file-pdf',
    'doc'  => 'fa-file-word',
    'docx' => 'fa-file-word',
    'xlsx' => 'fa-file-excel',
    'xls'  => 'fa-file-excel',
    'zip'  => 'fa-file-zipper',
    'txt'  => 'fa-file-lines',
    'csv'  => 'fa-file-csv'
];
$file_icon  = $file_icons[$ext] ?? 'fa-file';
$file_size  = ($att_path && file_exists($att_path)) ? round(filesize($att_path)/1024, 1) . ' KB' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title><?= $ticket['ticket_no'] ?> — TicketDesk</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"/>
<style>
.attach-preview { border:1px solid var(--border); border-radius:8px; overflow:hidden; background:var(--bg-input); }
.attach-img { width:100%; max-height:320px; object-fit:contain; display:block; cursor:zoom-in; background:#000; }
.attach-file { display:flex; align-items:center; gap:12px; padding:1rem 1.2rem; }
.lightbox { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.92); z-index:999; align-items:center; justify-content:center; }
.lightbox.open { display:flex; }
.lightbox img { max-width:92vw; max-height:92vh; object-fit:contain; border-radius:4px; }
.lightbox-close { position:fixed; top:18px; right:22px; background:rgba(255,255,255,0.15); border:none; color:#fff; font-size:1.5rem; width:40px; height:40px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; }
</style>
</head>
<body>
<div class="topbar">
  <div class="logo"><div class="logo-icon"><i class="fa-solid fa-computer"></i></div>Ticket<span>Desk</span></div>
  <div class="topbar-nav">
    <a href="dashboard.php" class="active">My Tickets</a>
    <a href="raise_ticket.php">Raise Ticket</a>
    <a href="profile.php">Profile</a>
  </div>
  <div class="topbar-right">
    <a href="notifications.php" style="position:relative;text-decoration:none;font-size:1.2rem;padding:4px 8px" title="Notifications">
      <i class="fa-solid fa-bell"></i><?php if($notif_count>0): ?><span style="position:absolute;top:0;right:0;background:#EF4444;color:#fff;font-size:0.55rem;font-weight:700;padding:1px 4px;border-radius:10px"><?= $notif_count ?></span><?php endif; ?>
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
      <a href="dashboard.php" class="side-item active"><span class="side-icon"><i class="fa-solid fa-list-ul"></i></span> My Tickets</a>
      <a href="raise_ticket.php" class="side-item"><span class="side-icon"><i class="fa-solid fa-plus"></i></span> Raise Ticket</a>
      <a href="notifications.php" class="side-item"><span class="side-icon"><i class="fa-solid fa-bell"></i></span> Notifications
        <?php if($notif_count>0): ?><span class="side-badge"><?= $notif_count ?></span><?php endif; ?>
      </a>
      <a href="profile.php" class="side-item"><span class="side-icon"><i class="fa-solid fa-user"></i></span> My Profile</a>
    </div>
    <div class="side-section">
      <div class="side-label">Account</div>
      <a href="<?= SITE_URL ?>/logout.php" class="side-item" style="color:var(--primary)"><span class="side-icon"><i class="fa-solid fa-right-from-bracket"></i></span> Logout</a>
    </div>
  </div>

  <main>
    <div class="page-header">
      <div class="breadcrumb">TICKETDESK / <a href="dashboard.php" style="color:var(--text-muted)">MY TICKETS</a> / <span><?= $ticket['ticket_no'] ?></span></div>
      <div class="flex gap-2" style="flex-wrap:wrap;align-items:flex-start">
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

    <!-- Ticket Details -->
    <div class="card">
      <div class="card-header"><div class="card-title">Ticket Details</div></div>
      <div class="card-body">
        <div class="detail-grid">
          <div class="detail-item"><div class="dl">Ticket No</div><div class="dv mono"><?= $ticket['ticket_no'] ?></div></div>
          <div class="detail-item"><div class="dl">Category</div><div class="dv"><?= sanitize($ticket['category']) ?></div></div>
          <div class="detail-item"><div class="dl">Contact Pref</div><div class="dv"><?= sanitize($ticket['contact_pref']) ?></div></div>
          <div class="detail-item"><div class="dl">Created</div><div class="dv mono" style="font-size:0.77rem"><?= date('d M Y, H:i', strtotime($ticket['created_at'])) ?></div></div>
          <div class="detail-item"><div class="dl">Last Updated</div><div class="dv mono" style="font-size:0.77rem"><?= date('d M Y, H:i', strtotime($ticket['updated_at'])) ?></div></div>
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
    <?php if ($att_file): ?>
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fa-regular fa-paperclip"></i> Your Attachment</div>
        <a href="<?= $att_url ?>" download class="btn btn-ghost btn-sm"><i class="fa-solid fa-download"></i> Download</a>
      </div>
      <div class="card-body" style="padding:0">
        <?php if ($is_image): ?>
          <div class="attach-preview">
            <img src="<?= $att_url ?>" alt="Attachment" class="attach-img" onclick="openLightbox(this.src)" title="Click to zoom"/>
          </div>
          <div style="padding:0.7rem 1.2rem;font-size:0.75rem;color:var(--text-muted);border-top:1px solid var(--border-mid)">
            📁 <?= sanitize($att_file) ?> <?= $file_size ? "· $file_size" : '' ?>
            &nbsp;·&nbsp; <a href="<?= $att_url ?>" target="_blank" style="color:var(--primary)">Open in new tab ↗</a>
          </div>
        <?php else: ?>
          <div class="attach-file">
            <div style="font-size:2rem"><i class="fa-regular <?= $file_icon ?>"></i></div>
            <div>
              <div style="font-size:0.85rem;font-weight:500;color:var(--text-main)"><?= sanitize($att_file) ?></div>
              <div style="font-size:0.72rem;color:var(--text-muted)"><?= strtoupper($ext) ?> file <?= $file_size ? "· $file_size" : '' ?></div>
            </div>
            <a href="<?= $att_url ?>" download class="btn btn-primary btn-sm" style="margin-left:auto"><i class="fa-solid fa-download"></i> Download</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Activity / Updates -->
    <div class="card">
      <div class="card-header"><div class="card-title"><i class="fa-solid fa-bell"></i> Activity / Updates</div></div>
      <div class="card-body">
        <?php if (empty($logs)): ?>
          <p class="text-muted">No updates yet. The IT team will update this ticket shortly.</p>
        <?php else: ?>
          <div class="timeline">
            <?php foreach ($logs as $log): ?>
            <div class="tl-item">
              <div class="tl-dot" style="background:var(--primary)"></div>
              <div>
                <div class="tl-text">
                  <strong><?= sanitize($log['done_by_name']) ?></strong> — <?= sanitize($log['action']) ?>
                  <?php if ($log['note']): ?><br><span style="color:var(--text-muted)"><?= sanitize($log['note']) ?></span><?php endif; ?>
                </div>
                <div class="tl-time"><?= date('d M Y, H:i', strtotime($log['created_at'])) ?></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <div class="divider"></div>
        <a href="dashboard.php" class="btn btn-ghost btn-sm"><i class="fa-solid fa-arrow-left"></i> Back to My Tickets</a>
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