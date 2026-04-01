<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();
$admin_notif_count = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE emp_id=" . (int)$_SESSION['user_id'] . " AND is_read=0")->fetchColumn();

// ── Month/Year Filter ──
$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));

$month_name  = date('F', mktime(0,0,0,$month,1,$year));
$date_from   = "$year-" . str_pad($month,2,'0',STR_PAD_LEFT) . "-01";
$date_to     = date('Y-m-t', strtotime($date_from));

// ── Ticket Summary ──
$tkt = $pdo->prepare("SELECT
    COUNT(*) as total,
    SUM(CASE WHEN status='open' THEN 1 ELSE 0 END) as open_c,
    SUM(CASE WHEN status='in-progress' THEN 1 ELSE 0 END) as inprog,
    SUM(CASE WHEN status='resolved' THEN 1 ELSE 0 END) as resolved,
    SUM(CASE WHEN status='closed' THEN 1 ELSE 0 END) as closed,
    SUM(CASE WHEN priority='critical' THEN 1 ELSE 0 END) as critical,
    SUM(CASE WHEN priority='high' THEN 1 ELSE 0 END) as high_p,
    SUM(CASE WHEN priority='medium' THEN 1 ELSE 0 END) as medium_p,
    SUM(CASE WHEN priority='low' THEN 1 ELSE 0 END) as low_p
    FROM tickets WHERE created_at BETWEEN ? AND ?");
$tkt->execute([$date_from.' 00:00:00', $date_to.' 23:59:59']);
$tkt = $tkt->fetch();

// ── Employee-wise ──
$emp_data = $pdo->prepare("SELECT e.name, e.emp_id, e.department,
    COUNT(t.id) as total,
    SUM(CASE WHEN t.status='resolved' THEN 1 ELSE 0 END) as resolved,
    SUM(CASE WHEN t.status='open' OR t.status='in-progress' THEN 1 ELSE 0 END) as pending
    FROM employees e
    LEFT JOIN tickets t ON e.id=t.emp_id AND t.created_at BETWEEN ? AND ?
    WHERE e.role='employee'
    GROUP BY e.id ORDER BY total DESC");
$emp_data->execute([$date_from.' 00:00:00', $date_to.' 23:59:59']);
$emp_data = $emp_data->fetchAll();

// ── Department-wise ──
$dept_data = $pdo->prepare("SELECT e.department,
    COUNT(t.id) as total,
    SUM(CASE WHEN t.status='resolved' THEN 1 ELSE 0 END) as resolved,
    SUM(CASE WHEN t.status='open' OR t.status='in-progress' THEN 1 ELSE 0 END) as pending
    FROM tickets t JOIN employees e ON t.emp_id=e.id
    WHERE t.created_at BETWEEN ? AND ?
    GROUP BY e.department ORDER BY total DESC");
$dept_data->execute([$date_from.' 00:00:00', $date_to.' 23:59:59']);
$dept_data = $dept_data->fetchAll();

// ── Asset Summary ──
$asset_data = $pdo->query("SELECT
    COUNT(*) as total,
    SUM(CASE WHEN status='Available' THEN 1 ELSE 0 END) as available,
    SUM(CASE WHEN status='Assigned' THEN 1 ELSE 0 END) as assigned,
    SUM(CASE WHEN status='Under Repair' THEN 1 ELSE 0 END) as repair,
    SUM(CASE WHEN status='Damaged' THEN 1 ELSE 0 END) as damaged,
    SUM(CASE WHEN status='Retired' THEN 1 ELSE 0 END) as retired
    FROM assets")->fetch();

// Asset category breakdown
$asset_cat = $pdo->query("SELECT category, COUNT(*) as cnt FROM assets GROUP BY category ORDER BY cnt DESC")->fetchAll();

// ── Year list for dropdown ──
$years = range(date('Y'), date('Y')-3);
$months = [1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',
           7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'];

$current_page = 'reports.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Monthly Report — TicketDesk</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css"/>
<style>
@media print {
    .topbar,.sidebar,.no-print,.td-toggle-btn,.td-login-toggle { display:none !important; }
    .shell { display:block !important; }
    main { padding:0 !important; }
    .card { box-shadow:none !important; border:1px solid #ddd !important; break-inside:avoid; }
    body { background:#fff !important; color:#000 !important; }
    .print-header { display:block !important; }
    th { background:#f5f5f5 !important; color:#333 !important; }
    td { color:#000 !important; }
    .dept-badge { border:1px solid #ccc !important; color:#333 !important; background:#f9f9f9 !important; }
    .stat-value { color:#c62828 !important; }
    .page-break { page-break-before:always; }
}
.print-header { display:none; text-align:center; margin-bottom:1.5rem; }
.print-header h2 { font-size:1.4rem; font-weight:700; }
.print-header p  { font-size:0.85rem; color:#555; margin-top:4px; }

.report-stat { background:var(--bg-card); border:1px solid var(--border); border-radius:8px; padding:1rem 1.2rem; text-align:center; }
.report-stat .val { font-size:1.8rem; font-weight:700; font-family:'IBM Plex Mono',monospace; }
.report-stat .lbl { font-size:0.68rem; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-muted); margin-top:4px; }
.pct-bar { height:6px; background:var(--border); border-radius:3px; margin-top:6px; overflow:hidden; }
.pct-fill { height:100%; border-radius:3px; transition:width 0.5s; }
.section-title { font-size:0.95rem; font-weight:700; color:var(--text-main); margin-bottom:1rem; padding-bottom:0.5rem; border-bottom:2px solid var(--primary); display:flex; align-items:center; gap:8px; }
</style>
</head>
<body>
<div class="topbar no-print">
  <div class="logo"><div class="logo-icon"><i class="fa-solid fa-computer"></i></div>Ticket<span>Desk</span> <span style="font-size:0.7rem;color:var(--text-muted);margin-left:6px">ADMIN</span></div>
  <div class="topbar-nav">
    <a href="dashboard.php">Dashboard</a>
    <a href="tickets.php">Tickets</a>
    <a href="assets.php">Assets</a>
    <a href="employees.php">Employees</a>
    <a href="reports.php" class="active">Reports</a>
  </div>
  <div class="topbar-right">
    <a href="<?= SITE_URL ?>/admin/admin_notifications.php" style="position:relative;text-decoration:none;font-size:1.2rem;padding:4px 8px" title="Notifications"><i class="fa-solid fa-bell"></i><?php if($admin_notif_count>0): ?><span style="position:absolute;top:0;right:0;background:#EF4444;color:#fff;font-size:0.55rem;font-weight:700;padding:1px 4px;border-radius:10px"><?= $admin_notif_count ?></span><?php endif; ?></a>
    <a href="<?= SITE_URL ?>/logout.php" class="btn btn-ghost btn-sm">Logout</a>
  </div>
</div>

<div class="shell">
  <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>
  <main>

    <!-- Print Header (only shows when printing) -->
    <div class="print-header">
      <h2>TicketDesk — Monthly Report</h2>
      <p><?= $month_name ?> <?= $year ?> &nbsp;|&nbsp; Generated on <?= date('d M Y, h:i A') ?></p>
    </div>

    <div class="page-header no-print">
      <div class="breadcrumb">TICKETDESK / <span>REPORTS</span></div>
      <h1><i class="fa-solid fa-chart-bar"></i> Monthly Reports</h1>
      <p>Ticket summary, employee performance and asset overview</p>
    </div>

    <!-- Filter + Export Bar -->
    <div class="no-print" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:1.5rem">
      <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <div style="display:flex;align-items:center;gap:8px">
          <label style="font-size:0.78rem;font-weight:600;color:var(--text-muted);text-transform:uppercase">Month</label>
          <select name="month" style="padding:8px 12px;border-radius:5px;border:1px solid var(--border);background:var(--bg-input);color:var(--text-main);font-size:0.84rem">
            <?php foreach($months as $m=>$mn): ?>
            <option value="<?= $m ?>" <?= $m==$month?'selected':'' ?>><?= $mn ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="display:flex;align-items:center;gap:8px">
          <label style="font-size:0.78rem;font-weight:600;color:var(--text-muted);text-transform:uppercase">Year</label>
          <select name="year" style="padding:8px 12px;border-radius:5px;border:1px solid var(--border);background:var(--bg-input);color:var(--text-main);font-size:0.84rem">
            <?php foreach($years as $y): ?>
            <option value="<?= $y ?>" <?= $y==$year?'selected':'' ?>><?= $y ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-magnifying-glass"></i> Generate</button>
      </form>
      <div style="margin-left:auto;display:flex;gap:8px">
        <button onclick="window.print()" class="btn btn-ghost btn-sm"><i class="fa-solid fa-print"></i> Print / PDF</button>
        <button onclick="downloadExcel()" class="btn btn-primary btn-sm"><i class="fa-solid fa-download"></i> Excel</button>
      </div>
    </div>

    <div style="font-size:0.78rem;color:var(--text-muted);margin-bottom:1.5rem">
      <i class="fa-regular fa-calendar"></i> Report Period: <strong style="color:var(--text-main)"><?= $month_name ?> <?= $year ?></strong>
      &nbsp;(<?= $date_from ?> to <?= $date_to ?>)
    </div>

    <!-- ══ SECTION 1: TICKET SUMMARY ══ -->
    <div class="card">
      <div class="card-body">
        <div class="section-title"><i class="fa-solid fa-ticket"></i> Ticket Summary — <?= $month_name ?> <?= $year ?></div>
        <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:1rem;margin-bottom:1.5rem">
          <div class="report-stat"><div class="val" style="color:var(--primary)"><?= $tkt['total'] ?></div><div class="lbl">Total Tickets</div></div>
          <div class="report-stat"><div class="val" style="color:#c62828"><?= $tkt['open_c'] ?></div><div class="lbl">Open</div></div>
          <div class="report-stat"><div class="val" style="color:var(--orange)"><?= $tkt['inprog'] ?></div><div class="lbl">In Progress</div></div>
          <div class="report-stat"><div class="val" style="color:var(--green)"><?= $tkt['resolved'] ?></div><div class="lbl">Resolved</div></div>
          <div class="report-stat"><div class="val" style="color:var(--text-muted)"><?= $tkt['closed'] ?></div><div class="lbl">Closed</div></div>
        </div>
        <div style="font-size:0.8rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:0.8rem">Priority Breakdown</div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem">
          <?php
          $pri = [
            ['Critical', $tkt['critical'], '#c62828'],
            ['High',     $tkt['high_p'],   '#e65100'],
            ['Medium',   $tkt['medium_p'], '#f57f17'],
            ['Low',      $tkt['low_p'],    '#2e7d32'],
          ];
          foreach($pri as [$label,$count,$color]):
            $pct = $tkt['total'] > 0 ? round(($count/$tkt['total'])*100) : 0;
          ?>
          <div class="report-stat">
            <div class="val" style="color:<?= $color ?>"><?= $count ?></div>
            <div class="lbl"><?= $label ?></div>
            <div class="pct-bar"><div class="pct-fill" style="width:<?= $pct ?>%;background:<?= $color ?>"></div></div>
            <div style="font-size:0.68rem;color:var(--text-muted);margin-top:3px"><?= $pct ?>%</div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- ══ SECTION 2: EMPLOYEE-WISE ══ -->
    <div class="card">
      <div class="card-body">
        <div class="section-title"><i class="fa-solid fa-users"></i> Employee-wise Ticket Count</div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr><th>#</th><th>Employee</th><th>Emp ID</th><th>Department</th><th>Total Tickets</th><th>Resolved</th><th>Pending</th><th>Resolution %</th></tr>
            </thead>
            <tbody>
              <?php if(empty($emp_data)): ?>
              <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:1.5rem">No data for this period.</td></tr>
              <?php else: ?>
              <?php foreach($emp_data as $i=>$e):
                $res_pct = $e['total'] > 0 ? round(($e['resolved']/$e['total'])*100) : 0;
              ?>
              <tr>
                <td style="color:var(--text-muted)"><?= $i+1 ?></td>
                <td style="font-weight:500"><?= sanitize($e['name']) ?></td>
                <td class="ticket-id"><?= sanitize($e['emp_id']) ?></td>
                <td><span class="dept-badge"><?= sanitize($e['department']) ?></span></td>
                <td><strong style="color:var(--primary)"><?= $e['total'] ?></strong></td>
                <td style="color:var(--green)"><?= $e['resolved'] ?></td>
                <td style="color:<?= $e['pending']>0?'var(--orange)':'var(--text-muted)' ?>"><?= $e['pending'] ?></td>
                <td>
                  <div style="display:flex;align-items:center;gap:8px">
                    <div class="pct-bar" style="flex:1;height:8px"><div class="pct-fill" style="width:<?= $res_pct ?>%;background:var(--green)"></div></div>
                    <span style="font-size:0.78rem;font-weight:600;color:var(--green);min-width:35px"><?= $res_pct ?>%</span>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ══ SECTION 3: DEPARTMENT-WISE ══ -->
    <div class="card">
      <div class="card-body">
        <div class="section-title"><i class="fa-solid fa-building"></i> Department-wise Tickets</div>
        <?php if(empty($dept_data)): ?>
          <p style="color:var(--text-muted);font-size:0.85rem">No ticket data for this period.</p>
        <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr><th>#</th><th>Department</th><th>Total</th><th>Resolved</th><th>Pending</th><th>Resolution %</th></tr>
            </thead>
            <tbody>
              <?php foreach($dept_data as $i=>$d):
                $pct = $d['total']>0 ? round(($d['resolved']/$d['total'])*100) : 0;
              ?>
              <tr>
                <td style="color:var(--text-muted)"><?= $i+1 ?></td>
                <td><span class="dept-badge" style="font-size:0.8rem"><?= sanitize($d['department'] ?: 'Unassigned') ?></span></td>
                <td><strong style="color:var(--primary)"><?= $d['total'] ?></strong></td>
                <td style="color:var(--green)"><?= $d['resolved'] ?></td>
                <td style="color:<?= $d['pending']>0?'var(--orange)':'var(--text-muted)' ?>"><?= $d['pending'] ?></td>
                <td>
                  <div style="display:flex;align-items:center;gap:8px">
                    <div class="pct-bar" style="flex:1;height:8px"><div class="pct-fill" style="width:<?= $pct ?>%;background:var(--green)"></div></div>
                    <span style="font-size:0.78rem;font-weight:600;color:var(--green);min-width:35px"><?= $pct ?>%</span>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ══ SECTION 4: ASSET SUMMARY ══ -->
    <div class="card page-break">
      <div class="card-body">
        <div class="section-title"><i class="fa-solid fa-server"></i> Asset Status Summary</div>
        <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:1rem;margin-bottom:1.5rem">
          <div class="report-stat"><div class="val" style="color:var(--primary)"><?= $asset_data['total'] ?></div><div class="lbl">Total</div></div>
          <div class="report-stat"><div class="val" style="color:var(--green)"><?= $asset_data['available'] ?></div><div class="lbl">Available</div></div>
          <div class="report-stat"><div class="val" style="color:#1565c0"><?= $asset_data['assigned'] ?></div><div class="lbl">Assigned</div></div>
          <div class="report-stat"><div class="val" style="color:var(--orange)"><?= $asset_data['repair'] ?></div><div class="lbl">Repair</div></div>
          <div class="report-stat"><div class="val" style="color:#c62828"><?= $asset_data['damaged'] ?></div><div class="lbl">Damaged</div></div>
          <div class="report-stat"><div class="val" style="color:var(--text-muted)"><?= $asset_data['retired'] ?></div><div class="lbl">Retired</div></div>
        </div>
        <?php if(!empty($asset_cat)): ?>
        <div style="font-size:0.8rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:0.8rem">Category Breakdown</div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Category</th><th>Count</th><th>Distribution</th></tr></thead>
            <tbody>
              <?php foreach($asset_cat as $ac):
                $pct = $asset_data['total']>0 ? round(($ac['cnt']/$asset_data['total'])*100) : 0;
              ?>
              <tr>
                <td style="font-weight:500"><?= sanitize($ac['category']) ?></td>
                <td><strong style="color:var(--primary)"><?= $ac['cnt'] ?></strong></td>
                <td style="min-width:200px">
                  <div style="display:flex;align-items:center;gap:8px">
                    <div class="pct-bar" style="flex:1;height:8px"><div class="pct-fill" style="width:<?= $pct ?>%;background:var(--primary)"></div></div>
                    <span style="font-size:0.78rem;color:var(--text-muted);min-width:35px"><?= $pct ?>%</span>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <div style="text-align:center;color:var(--text-muted);font-size:0.75rem;margin-top:1rem;padding-bottom:2rem">
      Generated by TicketDesk &nbsp;|&nbsp; <?= date('d M Y, h:i A') ?> &nbsp;|&nbsp; <?= $month_name ?> <?= $year ?> Report
    </div>

  </main>
</div>

<!-- Excel Download Script -->
<script>
function downloadExcel() {
    var month = '<?= $month_name ?>';
    var year  = '<?= $year ?>';

    // Build CSV data
    var lines = [];

    lines.push('TICKETDESK MONTHLY REPORT - ' + month + ' ' + year);
    lines.push('Generated: <?= date('d M Y h:i A') ?>');
    lines.push('');

    // Section 1
    lines.push('TICKET SUMMARY');
    lines.push('Total,Open,In Progress,Resolved,Closed,Critical,High,Medium,Low');
    lines.push([
        <?= $tkt['total'] ?>,<?= $tkt['open_c'] ?>,<?= $tkt['inprog'] ?>,
        <?= $tkt['resolved'] ?>,<?= $tkt['closed'] ?>,<?= $tkt['critical'] ?>,
        <?= $tkt['high_p'] ?>,<?= $tkt['medium_p'] ?>,<?= $tkt['low_p'] ?>
    ].join(','));
    lines.push('');

    // Section 2
    lines.push('EMPLOYEE-WISE TICKET COUNT');
    lines.push('Name,Emp ID,Department,Total Tickets,Resolved,Pending,Resolution %');
    <?php foreach($emp_data as $e):
      $res_pct2 = $e['total']>0 ? round(($e['resolved']/$e['total'])*100) : 0;
    ?>
    lines.push('"<?= addslashes($e['name']) ?>","<?= $e['emp_id'] ?>","<?= addslashes($e['department']) ?>",<?= $e['total'] ?>,<?= $e['resolved'] ?>,<?= $e['pending'] ?>,<?= $res_pct2 ?>%');
    <?php endforeach; ?>
    lines.push('');

    // Section 3
    lines.push('DEPARTMENT-WISE TICKETS');
    lines.push('Department,Total,Resolved,Pending,Resolution %');
    <?php foreach($dept_data as $d):
      $dpct2 = $d['total']>0 ? round(($d['resolved']/$d['total'])*100) : 0;
    ?>
    lines.push('"<?= addslashes($d['department'] ?: 'Unassigned') ?>",<?= $d['total'] ?>,<?= $d['resolved'] ?>,<?= $d['pending'] ?>,<?= $dpct2 ?>%');
    <?php endforeach; ?>
    lines.push('');

    // Section 4
    lines.push('ASSET STATUS SUMMARY');
    lines.push('Total,Available,Assigned,Under Repair,Damaged,Retired');
    lines.push([<?= $asset_data['total'] ?>,<?= $asset_data['available'] ?>,<?= $asset_data['assigned'] ?>,<?= $asset_data['repair'] ?>,<?= $asset_data['damaged'] ?>,<?= $asset_data['retired'] ?>].join(','));
    lines.push('');
    lines.push('ASSET CATEGORY BREAKDOWN');
    lines.push('Category,Count,Percentage');
    <?php foreach($asset_cat as $ac):
      $apct2 = $asset_data['total']>0 ? round(($ac['cnt']/$asset_data['total'])*100) : 0;
    ?>
    lines.push('"<?= $ac['category'] ?>",<?= $ac['cnt'] ?>,<?= $apct2 ?>%');
    <?php endforeach; ?>

    // Download
    var csv     = lines.join('\n');
    var blob    = new Blob([csv], {type:'text/csv;charset=utf-8;'});
    var url     = URL.createObjectURL(blob);
    var a       = document.createElement('a');
    a.href      = url;
    a.download  = 'TicketDesk_Report_' + month + '_' + year + '.csv';
    a.click();
    URL.revokeObjectURL(url);
}
</script>
<script src="<?= SITE_URL ?>/assets/js/theme.js"></script>
</body>
</html>