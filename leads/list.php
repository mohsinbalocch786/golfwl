<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

include("../layout/header.php");
include("../layout/sidebar.php");

$where = [];
$where[] = ownershipWhere('l');

// Search
if(!empty($_GET['q'])){
    $q = mysqli_real_escape_string($conn, $_GET['q']);
    $where[] = "(l.first_name LIKE '%$q%' OR l.last_name LIKE '%$q%' OR l.email LIKE '%$q%' OR l.company LIKE '%$q%')";
}

// Status filter
$statuses = ['new','contacted','qualified','proposal','won','lost'];
if(!empty($_GET['status']) && in_array($_GET['status'], $statuses)){
    $status = mysqli_real_escape_string($conn, $_GET['status']);
    $where[] = "l.status = '$status'";
}

// Owner filter (manager/admin only)
if(!empty($_GET['owner']) && (isManager() || isSuperAdmin())){
    $owner_id = (int)$_GET['owner'];
    $where[] = "l.user_id = $owner_id";
}

$where_sql = "WHERE " . implode(" AND ", $where);

// ── Pagination ────────────────────────────────────────────────
$perPage    = 50;
$page       = max(1, (int)(isset($_GET['page']) ? $_GET['page'] : 1));
$offset     = ($page - 1) * $perPage;
$cntQ       = mysqli_query($conn, "SELECT COUNT(*) n FROM leads l $where_sql");
$totalRows  = $cntQ ? (int)mysqli_fetch_assoc($cntQ)['n'] : 0;
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$r = mysqli_query($conn, "
    SELECT l.*, u.name as owner_name
    FROM leads l
    LEFT JOIN users u ON u.id = l.user_id
    $where_sql
    ORDER BY l.id DESC
    LIMIT $perPage OFFSET $offset
");

// Status summary cards (scoped, ignoring status filter)
$baseWhere = "WHERE " . ownershipWhere('l');
$countQ = mysqli_query($conn, "
    SELECT l.status, COUNT(*) as total
    FROM leads l
    $baseWhere
    GROUP BY l.status
");
$statusCounts = array_fill_keys($statuses, 0);
while($row = mysqli_fetch_assoc($countQ)){
    $statusCounts[$row['status']] = $row['total'];
}

$statusColors = [
    'new'=>'secondary',
    'contacted'=>'info',
    'qualified'=>'primary',
    'proposal'=>'warning',
    'won'=>'success',
    'lost'=>'danger'
];

$members = (isManager() || isSuperAdmin()) ? teamMembers($conn) : [];
?>

<div class="row">
<?php foreach($statuses as $s){ ?>
    <div class="col-lg-2 col-6">
        <a href="?status=<?= $s ?>" class="text-decoration-none">
            <div class="small-box bg-<?= $statusColors[$s] ?>">
                <div class="inner">
                    <h3><?= $statusCounts[$s] ?></h3>
                    <p><?= ucfirst($s) ?></p>
                </div>
                <div class="icon"><i class="fas fa-bullseye"></i></div>
            </div>
        </a>
    </div>
<?php } ?>
</div>

<div class="row">
    <div class="col-12">

        <div class="card">

            <div class="card-header">

                <h3 class="card-title">Leads</h3>

                <div class="card-tools">
                    <a href="form.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Add Lead
                    </a>
                </div>

            </div>

            <div class="card-body">

                <form method="GET" id="filterForm" class="mb-3">
                    <div class="row">

                        <div class="col-md-3">
                            <label>Search</label>
                            <input type="text" name="q" class="form-control" placeholder="Name, email, company" value="<?= htmlspecialchars(isset($_GET['q']) ? $_GET['q'] : '') ?>">
                        </div>

                        <div class="col-md-2">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="">All Statuses</option>
                                <?php foreach($statuses as $s){ ?>
                                <option value="<?= $s ?>" <?= ((isset($_GET['status']) ? $_GET['status'] : '')===$s)?'selected':'' ?>><?= ucfirst($s) ?></option>
                                <?php } ?>
                            </select>
                        </div>

                        <?php if(!empty($members) && (isManager() || isSuperAdmin())){ ?>
                        <div class="col-md-2">
                            <label>Owner</label>
                            <select name="owner" class="form-control">
                                <option value="">All</option>
                                <?php foreach($members as $m){ ?>
                                <option value="<?= $m['id'] ?>" <?= (isset($_GET['owner']) && (int)$_GET['owner']===(int)$m['id']) ? 'selected':'' ?>>
                                    <?= htmlspecialchars($m['name']) ?>
                                </option>
                                <?php } ?>
                            </select>
                        </div>
                        <?php } ?>

                        <div class="col-md-2" style="margin-top: 2rem;">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary w-50">Filter</button>
                                <a href="list.php" class="btn btn-secondary w-50 ml-2">Reset</a>
                            </div>
                        </div>

                    </div>
                </form>

                <table id="leadTable" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Company</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Source</th>
                            <th>Status</th>
                            <?php if(canViewTeam() || isSuperAdmin()){ ?><th>Owner</th><?php } ?>
                            <th>Created</th>
                            <th width="170">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row=mysqli_fetch_assoc($r)){ ?>
                        <tr>
                            <td><?= htmlspecialchars(trim(isset($row['first_name']) ? $row['first_name'] : '').' '.htmlspecialchars(isset($row['last_name']) ? $row['last_name'] : '')) ?></td>
                            <td><?= htmlspecialchars(isset($row['company']) ? $row['company'] : '') ?></td>
                            <td><?= htmlspecialchars(isset($row['email']) ? $row['email'] : '') ?></td>
                            <td><?= htmlspecialchars(isset($row['phone']) ? $row['phone'] : '') ?></td>
                            <td><?= htmlspecialchars(isset($row['source']) ? $row['source'] : '') ?></td>
                            <td><span class="badge badge-<?= $statusColors[isset($row['status']) ? $row['status'] : 'new' ] ?>"><?= ucfirst(isset($row['status']) ? $row['status'] : 'new') ?></span></td>
                            <?php if(canViewTeam() || isSuperAdmin()){ ?>
                            <td><?= htmlspecialchars(isset($row['owner_name']) ? $row['owner_name'] : '-') ?></td>
                            <?php } ?>
                            <td data-sort="<?= !empty($row['created_at']) ? strtotime($row['created_at']) : 0 ?>"><?= !empty($row['created_at']) ? date('m/d/Y', strtotime($row['created_at'])) : '' ?></td>
                            <td>
                                <a href="view.php?id=<?= $row['id'] ?>" class="btn btn-secondary btn-sm"><i class="fas fa-eye"></i></a>
                                <a href="form.php?id=<?= $row['id'] ?>" class="btn btn-info btn-sm"><i class="fas fa-edit"></i></a>

                                <?php if(!in_array($row['status'], ['won','lost'])){ ?>
                                <a href="convert.php?id=<?= $row['id'] ?>" class="btn btn-success btn-sm" onclick="return confirm('Convert this lead to an opportunity?')">
                                    <i class="fas fa-exchange-alt"></i> Convert
                                </a>
                                <?php } ?>

                                <a href="delete.php?id=<?= $row['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this lead?')"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php } ?>

                        <?php if(mysqli_num_rows($r) == 0){ ?>
                        <tr><td colspan="9" class="text-center">No leads found.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>

            </div>
        </div>
    </div>
</div>

<?php
$qp = $_GET; unset($qp['page']); $qs = ($t=http_build_query($qp)) ? '&'.$t : '';
if ($totalPages > 1): ?>
<div class="d-flex justify-content-between align-items-center mt-3 px-3">
    <small class="text-muted">Showing <?php echo number_format($offset+1); ?>–<?php echo number_format(min($offset+$perPage,$totalRows)); ?> of <?php echo number_format($totalRows); ?> leads</small>
    <nav><ul class="pagination pagination-sm mb-0">
        <?php if($page>1): ?><li class="page-item"><a class="page-link" href="?page=<?php echo $page-1; ?><?php echo $qs; ?>">‹</a></li><?php endif; ?>
        <?php for($p=max(1,$page-2);$p<=min($totalPages,$page+2);$p++): ?>
        <li class="page-item <?php echo $p===$page?'active':''; ?>"><a class="page-link" href="?page=<?php echo $p; ?><?php echo $qs; ?>"><?php echo $p; ?></a></li>
        <?php endfor; ?>
        <?php if($page<$totalPages): ?><li class="page-item"><a class="page-link" href="?page=<?php echo $page+1; ?><?php echo $qs; ?>">›</a></li><?php endif; ?>
    </ul></nav>
</div>
<?php endif; ?>
<?php include("../layout/footer.php"); ?>

<script>
$(document).ready(function() {
    $('#leadTable').DataTable({
        pageLength: 10,
        order: [[0, "desc"]],
        processing: true,
        dom: 'Brtip',
        buttons: [
            { extend: 'csv', exportOptions: { columns: ':not(:last-child)' } },
            { extend: 'excel', exportOptions: { columns: ':not(:last-child)' } },
            { extend: 'print', exportOptions: { columns: ':not(:last-child)' } }
        ],
        columnDefs: [{ orderable: false, targets: -1 }]
    });
});
</script>