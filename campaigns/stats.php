<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

$id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
if (!$id) { header("Location:list.php"); exit; }

$r = mysqli_query($conn, "SELECT c.*, t.name as template_name FROM campaigns c LEFT JOIN templates t ON t.id=c.template_id WHERE c.id='$id'");
$camp = mysqli_fetch_assoc($r);
if (!$camp) { header("Location:list.php"); exit; }
assertOwnership($camp);

include("../layout/header.php");
include("../layout/sidebar.php");

// ── Aggregate stats ───────────────────────────────────────────
$sq = mysqli_query($conn, "
    SELECT
        COUNT(*) as total,
        SUM(status='sent')        as sent,
        SUM(status='failed')      as failed,
        SUM(status='pending')     as pending,
        SUM(opened=1)             as opened,
        SUM(clicked=1)            as clicked,
        SUM(bounced=1)            as bounced,
        SUM(unsubscribed=1)       as unsubscribed,
        SUM(spam_report=1)        as spam,
        SUM(sms_delivered=1)      as sms_delivered,
        SUM(sms_failed=1)         as sms_failed
    FROM campaign_queue WHERE campaign_id='$id'
");
$stats = mysqli_fetch_assoc($sq);

// ── Per-contact queue rows (paginated) ───────────────────────
$perPage = 100;
$page    = max(1, (int)(isset($_GET['page']) ? $_GET['page'] : 1));
$offset  = ($page - 1) * $perPage;

// Status filter
$qWhere = "campaign_id='$id'";
$statusFilter = isset($_GET['filter']) ? $_GET['filter'] : '';
$allowedFilters = ['sent','failed','pending','opened','bounced','unsubscribed'];
if ($statusFilter === 'opened')       $qWhere .= " AND opened=1";
elseif ($statusFilter === 'bounced')  $qWhere .= " AND bounced=1";
elseif ($statusFilter === 'unsubscribed') $qWhere .= " AND unsubscribed=1";
elseif (in_array($statusFilter, ['sent','failed','pending'])) $qWhere .= " AND status='$statusFilter'";

$cntQ  = mysqli_query($conn, "SELECT COUNT(*) n FROM campaign_queue WHERE $qWhere");
$total = $cntQ ? (int)mysqli_fetch_assoc($cntQ)['n'] : 0;
$totalPages = max(1, (int)ceil($total / $perPage));

$rows = [];
$dq = mysqli_query($conn, "
    SELECT q.*, c.name, c.email, c.phone
    FROM campaign_queue q
    LEFT JOIN contacts c ON c.id = q.contact_id
    WHERE $qWhere
    ORDER BY q.id DESC
    LIMIT $perPage OFFSET $offset
");
while ($row = mysqli_fetch_assoc($dq)) $rows[] = $row;
?>

<section class="content-header">
<div class="container-fluid">
    <h1>Campaign Stats: <?php echo htmlspecialchars($camp['name']); ?></h1>
    <small class="text-muted">
        Type: <?php echo strtoupper($camp['type']); ?> &bull;
        Template: <?php echo htmlspecialchars((isset($camp['template_name']) ? $camp['template_name'] : '')); ?> &bull;
        Scheduled: <?php echo !empty($camp['schedule_datetime']) ? date('m/d/Y H:i', strtotime($camp['schedule_datetime'])) : '—'; ?>
    </small>
</div>
</section>

<section class="content"><div class="container-fluid">

<!-- Summary Cards -->
<div class="row mb-3">
<?php
$cards = [
    ['Total',       $stats['total'],       'secondary', ''],
    ['Sent',        $stats['sent'],        'primary',   'sent'],
    ['Pending',     $stats['pending'],     'warning',   'pending'],
    ['Failed',      $stats['failed'],      'danger',    'failed'],
    ['Opened',      $stats['opened'],      'success',   'opened'],
    ['Clicked',     $stats['clicked'],     'info',      ''],
    ['Bounced',     $stats['bounced'],     'danger',    'bounced'],
    ['Unsubscribed',$stats['unsubscribed'],'warning',   'unsubscribed'],
];
if ($camp['type'] === 'sms') {
    $cards = [
        ['Total',       $stats['total'],        'secondary', ''],
        ['Sent',        $stats['sent'],         'primary',   'sent'],
        ['Delivered',   $stats['sms_delivered'],'success',   ''],
        ['Failed',      $stats['sms_failed'],   'danger',    'failed'],
        ['Pending',     $stats['pending'],      'warning',   'pending'],
    ];
}
foreach ($cards as $c):
    $rate = $stats['total'] > 0 ? round($c[1] / $stats['total'] * 100, 1) : 0;
    $active = ($statusFilter === $c[3]) ? 'border-primary' : '';
    $href   = $c[3] ? "?id=$id&filter={$c[3]}" : "?id=$id";
?>
<div class="col-md-3 col-6">
    <a href="<?php echo $href; ?>" class="text-decoration-none">
    <div class="info-box <?php echo $active; ?>">
        <span class="info-box-icon bg-<?php echo $c[1]; ?>"><i class="fas fa-envelope"></i></span>
        <div class="info-box-content">
            <span class="info-box-text"><?php echo $c[0]; ?></span>
            <span class="info-box-number"><?php echo number_format($c[1]); ?></span>
            <div class="progress"><div class="progress-bar" style="width:<?php echo $rate; ?>%"></div></div>
            <span class="progress-description"><?php echo $rate; ?>%</span>
        </div>
    </div></a>
</div>
<?php endforeach; ?>
</div>

<!-- Per-contact table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">Recipients
            <?php if ($statusFilter): ?><span class="badge badge-primary ml-2"><?php echo ucfirst($statusFilter); ?></span><?php endif; ?>
        </h3>
        <a href="export.php?id=<?php echo $id; ?>&filter=<?php echo urlencode($statusFilter); ?>" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-download"></i> Export CSV
        </a>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm table-bordered mb-0">
            <thead class="thead-light">
                <tr>
                    <th>Name</th>
                    <th>Email / Phone</th>
                    <th>Status</th>
                    <th>Sent At</th>
                    <?php if ($camp['type']==='email'): ?>
                    <th>Opened</th><th>Clicked</th><th>Bounced</th><th>Unsub</th>
                    <?php else: ?>
                    <th>SMS Status</th><th>Delivered</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
            <tr>
                <td><?php echo htmlspecialchars((isset($row['name']) ? $row['name'] : '—')); ?></td>
                <td><?php echo htmlspecialchars($row['email'] ?: $row['phone']); ?></td>
                <td>
                    <?php
                    $sc = ['sent'=>'primary','failed'=>'danger','pending'=>'secondary'];
                    $cls = isset($sc[$row['status']]) ? $sc[$row['status']] : 'secondary';
                    echo '<span class="badge badge-'.$cls.'">'.ucfirst($row['status']).'</span>';
                    ?>
                </td>
                <td><?php echo $row['sent_at'] ? date('m/d H:i', strtotime($row['sent_at'])) : '—'; ?></td>
                <?php if ($camp['type']==='email'): ?>
                <td><?php echo $row['opened'] ? '<i class="fas fa-check text-success"></i>' : '—'; ?></td>
                <td><?php echo $row['clicked'] ? '<i class="fas fa-check text-info"></i>' : '—'; ?></td>
                <td><?php echo $row['bounced'] ? '<i class="fas fa-times text-danger"></i>' : '—'; ?></td>
                <td><?php echo $row['unsubscribed'] ? '<i class="fas fa-ban text-warning"></i>' : '—'; ?></td>
                <?php else: ?>
                <td><?php echo htmlspecialchars((isset($row['smsstatus']) ? $row['smsstatus'] : '—')); ?></td>
                <td><?php echo $row['sms_delivered'] ? '<i class="fas fa-check text-success"></i>' : '—'; ?></td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
            <tr><td colspan="8" class="text-center text-muted p-3">No records found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1):
        $qp = $_GET; unset($qp['page']); $qs = ($t=http_build_query($qp)) ? '&'.$t : '';
    ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">Showing <?php echo number_format($offset+1); ?>–<?php echo number_format(min($offset+$perPage,$total)); ?> of <?php echo number_format($total); ?></small>
        <nav><ul class="pagination pagination-sm mb-0">
            <?php if($page>1): ?><li class="page-item"><a class="page-link" href="?page=<?php echo $page-1; ?><?php echo $qs; ?>">‹</a></li><?php endif; ?>
            <?php for($p=max(1,$page-2);$p<=min($totalPages,$page+2);$p++): ?>
            <li class="page-item <?php echo $p===$page?'active':''; ?>"><a class="page-link" href="?page=<?php echo $p; ?><?php echo $qs; ?>"><?php echo $p; ?></a></li>
            <?php endfor; ?>
            <?php if($page<$totalPages): ?><li class="page-item"><a class="page-link" href="?page=<?php echo $page+1; ?><?php echo $qs; ?>">›</a></li><?php endif; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<a href="list.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Campaigns</a>

</div></section>

<?php include("../layout/footer.php"); ?>
