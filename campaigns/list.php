<?php

include("../config/db.php");
include("../config/auth.php");
requireLogin();

include("../layout/header.php");
include("../layout/sidebar.php");

// ── Ownership scoping ────────────────────────────────────────────
$where = [ ownershipWhere('c') ];

// TYPE[] FILTER
if (!empty($_GET['type']) && is_array($_GET['type'])) {
    $types = [];
    foreach ($_GET['type'] as $t) {
        if (in_array($t, ['email', 'sms'])) {
            $types[] = mysqli_real_escape_string($conn, $t);
        }
    }
    if (!empty($types)) {
        $where[] = "c.type IN ('" . implode("', '", $types) . "')";
    }
}

// STATUS[] FILTER
if (!empty($_GET['status']) && is_array($_GET['status'])) {
    $statuses = [];
    foreach ($_GET['status'] as $s) {
        if (in_array($s, ['pending', 'in-progress', 'completed'])) {
            $statuses[] = mysqli_real_escape_string($conn, $s);
        }
    }
    if (!empty($statuses)) {
        $where[] = "c.status IN ('" . implode("', '", $statuses) . "')";
    }
}

// GROUP[] FILTER
if (!empty($_GET['cgroup']) && is_array($_GET['cgroup'])) {
    $group_ids = [];
    foreach ($_GET['cgroup'] as $g) {
        if (is_numeric($g)) {
            $group_ids[] = (int)$g;
        }
    }
    if (!empty($group_ids)) {
        $where[] = "c.id IN (
            SELECT campaign_id FROM campaign_groups WHERE group_id IN (" . implode(", ", $group_ids) . ")
        )";
    }
}

// DATE RANGE FILTER
if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
    $start = mysqli_real_escape_string($conn, date('Y-m-d', strtotime($_GET['start_date'])));
    $end   = mysqli_real_escape_string($conn, date('Y-m-d', strtotime($_GET['end_date'])));
    $where[] = "DATE(c.schedule_datetime) BETWEEN '$start' AND '$end'";
}

$where_sql = "WHERE " . implode(" AND ", $where);

// FINAL QUERY
// ── Pagination ────────────────────────────────────────────────
$perPage    = 50;
$page       = max(1, (int)(isset($_GET['page']) ? $_GET['page'] : 1));
$offset     = ($page - 1) * $perPage;
$cntQ       = mysqli_query($conn, "SELECT COUNT(*) n FROM campaigns c $where_sql");
$totalRows  = $cntQ ? (int)mysqli_fetch_assoc($cntQ)['n'] : 0;
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$q = mysqli_query($conn, "
    SELECT c.*, t.name as template_name, u.name as owner_name
    FROM campaigns c
    LEFT JOIN templates t ON t.id = c.template_id
    LEFT JOIN users u ON u.id = c.user_id
    $where_sql
    ORDER BY c.id DESC
    LIMIT $perPage OFFSET $offset
");

// groups for the filter dropdown - scoped to ownership
$gw = ownershipWhere('');
$filterGroups = mysqli_query($conn, "SELECT id, name FROM contact_groups WHERE $gw ORDER BY name");
?>

<div class="row">
    <div class="col-12">

        <div class="card">

            <div class="card-header">

                <h3 class="card-title">Campaigns</h3>

                <div class="card-tools">
                    <a href="add.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Create Campaign
                    </a>
                </div>

            </div>



            <div class="card-body">

                <form method="GET" id="filterForm" class="mb-3">
                    <div class="row">

                        <!-- Type -->
                        <div class="col-md-2">
                            <label>Type</label>
                            <select name="type[]" class="form-control select2" multiple>
                                <option value="email"
                                    <?= (isset($_GET['type']) && in_array('email', (array)$_GET['type'])) ? 'selected' : '' ?>>
                                    Email</option>
                                <option value="sms"
                                    <?= (isset($_GET['type']) && in_array('sms', (array)$_GET['type'])) ? 'selected' : '' ?>>
                                    SMS</option>
                            </select>
                        </div>

                        <!-- Status -->
                        <div class="col-md-2">
                            <label>Status</label>
                            <select name="status[]" class="form-control select2" multiple>
                                <option value="pending"
                                    <?= (isset($_GET['status']) && in_array('pending', (array)$_GET['status'])) ? 'selected' : '' ?>>
                                    Pending</option>
                                <option value="in-progress"
                                    <?= (isset($_GET['status']) && in_array('in-progress', (array)$_GET['status'])) ? 'selected' : '' ?>>
                                    In Progress</option>
                                <option value="completed"
                                    <?= (isset($_GET['status']) && in_array('completed', (array)$_GET['status'])) ? 'selected' : '' ?>>
                                    Completed</option>
                            </select>
                        </div>

                        <!-- group by select2 -->
                        <div class="col-md-2">
                            <label>Group</label>
                            <select name="cgroup[]" class="form-control select2" multiple>
                                <?php
                                while($g=mysqli_fetch_assoc($filterGroups)){
                                    echo '<option value="'.$g['id'].'" '.(isset($_GET['cgroup']) && in_array($g['id'], (array)$_GET['cgroup']) ? 'selected' : '').'>'.htmlspecialchars($g['name']).'</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Date Range -->
                        <div class="col-md-2">
                            <label>Schedule Date</label>
                            <input type="text" id="daterange" class="form-control" placeholder="MM/DD/YYYY - MM/DD/YYYY">

                            <!-- Hidden fields -->
                            <input type="hidden" name="start_date" id="start_date">
                            <input type="hidden" name="end_date" id="end_date">
                        </div>

                        <!-- Submit -->
                        <div class="col-md-2" style="margin-top: 2rem;">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary w-50">Filter</button>
                                <a href="list.php" id="resetBtn" class="btn btn-secondary w-50 ml-2">Reset</a>
                            </div>
                        </div>

                    </div>
                </form>

                <table id="campaignTable" class="table table-bordered table-striped">

                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Template</th>
                            <th>Send/Day</th>
                            <th>Schedule</th>
                            <th>Status</th>
                            <th>Group</th>
                            <?php if(canViewTeam() || isSuperAdmin()){ ?>
                            <th>Owner</th>
                            <?php } ?>
                            <th width="160">Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php while($r=mysqli_fetch_assoc($q)){ ?>

                        <tr>


                            <td><?php echo htmlspecialchars($r['name']); ?></td>
                            <td><?php echo strtoupper($r['type']); ?></td>
                            <td><?php echo htmlspecialchars(isset($r['template_name']) ? $r['template_name'] : ''); ?></td>
                            <td><?php echo $r['send_per_day']; ?></td>
                            <td
                                data-sort="<?php if(!empty($r['schedule_datetime'] && $r['schedule_datetime'] != '0000-00-00 00:00:00')){ echo strtotime($r['schedule_datetime']); } ?>">
                                <?php echo date('m/d/Y', strtotime($r['schedule_datetime'])); ?></td>

                            <td>

                                <?php

                                    if($r['status']=="pending")
                                    echo '<span class="badge badge-warning">Pending</span>';

                                    if($r['status']=="in-progress")
                                    echo '<span class="badge badge-info">In Progress</span>';

                                    if($r['status']=="completed")
                                    echo '<span class="badge badge-success">Completed</span>';

                                    ?>

                            </td>
                            <td>
                                <?php 
                                    $id = $r['id'];
                                    $groups_q = mysqli_query($conn, "SELECT group_id FROM campaign_groups WHERE campaign_id='$id'");
                                    $group_ids = [];
                                    while($g=mysqli_fetch_assoc($groups_q)){
                                    $group_ids[] = $g['group_id'];
                                    }

                                    $gname = '-';
                                    if(!empty($group_ids)){
                                        $getGroups = mysqli_query($conn, "SELECT name FROM contact_groups WHERE id IN (".implode(",", $group_ids).")");
                                        $group_names = [];
                                        while($gn=mysqli_fetch_assoc($getGroups)){
                                        $group_names[] = $gn['name'];
                                        }
                                        $gname = implode(", ", $group_names);
                                    }

                                    echo !empty($gname) ? htmlspecialchars($gname) : '-';
                                ?>
                            </td>
                            <?php if(canViewTeam() || isSuperAdmin()){ ?>
                            <td><?php echo htmlspecialchars(isset($r['owner_name']) ? $r['owner_name'] : '-'); ?></td>
                            <?php } ?>
                            <td>

                                <?php
                                    $canEdit = isSuperAdmin() || (int)$r['user_id']===currentUserId() || (isManager() && (int)$r['manager_id']===currentManagerId());
                                ?>
                                <?php if($canEdit){ ?>
                                <a href="stats.php?id=<?php echo $r['id']; ?>" class="btn btn-secondary btn-sm" title="Stats">
                                    <i class="fas fa-chart-bar"></i>
                                </a>
                                <a href="edit.php?id=<?php echo $r['id']; ?>" class="btn btn-info btn-sm">

                                    <i class="fas fa-edit"></i>

                                </a>

                                <a href="delete.php?id=<?php echo $r['id']; ?>" class="btn btn-danger btn-sm"
                                    onclick="return confirm('Delete campaign?')">

                                    <i class="fas fa-trash"></i>

                                </a>
                                <?php } ?>

                            </td>



                        </tr>

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
    <small class="text-muted">Showing <?php echo number_format($offset+1); ?>–<?php echo number_format(min($offset+$perPage,$totalRows)); ?> of <?php echo number_format($totalRows); ?> campaigns</small>
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
    $('select[name="type[]"]').select2();
    $('select[name="status[]"]').select2();
    $('select[name="cgroup[]"]').select2();

});
</script>
<script>
$(document).ready(function() {
    $('#campaignTable').DataTable({
        pageLength: 10,
        order: [
            [0, "desc"]
        ],
        processing: true,

        dom: 'Brtip', // IMPORTANT for buttons

        buttons: [{
                extend: 'csv',
                exportOptions: {
                    columns: ':not(:last-child)'
                }
            },
            {
                extend: 'excel',
                exportOptions: {
                    columns: ':not(:last-child)'
                }
            },
            {
                extend: 'print',
                exportOptions: {
                    columns: ':not(:last-child)'
                }
            }
        ],

        columnDefs: [{
                orderable: false,
                targets: -1
            } // Disable sorting on Actions column
        ]
    });
});
</script>

<script>
$(function() {

    let startVal = "<?= !empty($_GET['start_date']) ? date('m/d/Y', strtotime($_GET['start_date'])) : '' ?>";
    let endVal   = "<?= !empty($_GET['end_date']) ? date('m/d/Y', strtotime($_GET['end_date'])) : '' ?>";

    function setDates(start, end) {
        $('#daterange').val(start.format('MM/DD/YYYY') + ' - ' + end.format('MM/DD/YYYY'));
        $('#start_date').val(start.format('YYYY-MM-DD'));
        $('#end_date').val(end.format('YYYY-MM-DD'));
    }

    $('#daterange').daterangepicker({
        autoUpdateInput: false, // ❗ VERY IMPORTANT
        locale: {
            format: 'MM/DD/YYYY',
            cancelLabel: 'Clear'
        },
        ranges: { 
            'Today': [moment(), moment()],
            'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            'Last 7 Days': [moment().subtract(6, 'days'), moment()], 
            'Last 30 Days': [moment().subtract(29, 'days'), moment()], 
            'This Month': [moment().startOf('month'), moment().endOf('month')], 
            'Last Month': [ moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month') ] },
    });

    // Apply
    $('#daterange').on('apply.daterangepicker', function(ev, picker) {
        setDates(picker.startDate, picker.endDate);
    });

    // Clear
    $('#daterange').on('cancel.daterangepicker', function(ev, picker) {
        $(this).val('');
        $('#start_date').val('');
        $('#end_date').val('');
    });

    // ✅ If filter already applied (GET), then show it
    if(startVal && endVal){
        $('#daterange').val(startVal + ' - ' + endVal);
        $('#start_date').val(startVal);
        $('#end_date').val(endVal);
    }

});
</script>
