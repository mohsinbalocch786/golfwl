<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

include("../layout/header.php");
include("../layout/sidebar.php");

$where = [];
$where[] = ownershipWhere('t');

$today = date('Y-m-d');

// Status filter
if(!empty($_GET['status']) && in_array($_GET['status'], ['pending','completed'])){
    $status = mysqli_real_escape_string($conn, $_GET['status']);
    $where[] = "t.status = '$status'";
}

// Priority filter
if(!empty($_GET['priority']) && in_array($_GET['priority'], ['low','medium','high'])){
    $priority = mysqli_real_escape_string($conn, $_GET['priority']);
    $where[] = "t.priority = '$priority'";
}

// Owner filter (manager/admin only)
if(!empty($_GET['owner']) && (isManager() || isSuperAdmin())){
    $owner_id = (int)$_GET['owner'];
    $where[] = "t.user_id = $owner_id";
}

$where_sql = "WHERE " . implode(" AND ", $where);

$r = mysqli_query($conn, "
    SELECT t.*,
        l.first_name as lead_fn, l.last_name as lead_ln,
        o.title as opp_title,
        u.name as owner_name
    FROM tasks t
    LEFT JOIN leads l ON l.id = t.lead_id
    LEFT JOIN opportunities o ON o.id = t.opportunity_id
    LEFT JOIN users u ON u.id = t.user_id
    $where_sql
    ORDER BY
        CASE WHEN t.status='pending' THEN 0 ELSE 1 END,
        t.due_date ASC
");

// Overdue count (scoped, current user's own tasks only)
$overdueCount = 0;
$oc = mysqli_query($conn, "
    SELECT COUNT(*) as n FROM tasks
    WHERE user_id=".currentUserId()."
    AND status='pending'
    AND due_date IS NOT NULL
    AND due_date < '$currentTime'
");
if($oc) $overdueCount = mysqli_fetch_assoc($oc)['n'];

$priorityColors = ['low'=>'success','medium'=>'warning','high'=>'danger'];
$members = (isManager() || isSuperAdmin()) ? teamMembers($conn) : [];
?>

<?php if($overdueCount > 0){ ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-triangle"></i>
    You have <?= $overdueCount ?> overdue task(s)!
</div>
<?php } ?>

<div class="row">
    <div class="col-12">

        <div class="card">

            <div class="card-header">
                <h3 class="card-title">Tasks &amp; Follow-ups</h3>

                <div class="card-tools">
                    <a href="form.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> New Task
                    </a>
                </div>
            </div>

            <div class="card-body">

                <form method="GET" id="filterForm" class="mb-3">
                    <div class="row">

                        <div class="col-md-2">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="">All</option>
                                <option value="pending" <?= ((isset($_GET['status']) ? $_GET['status'] : '')==='pending')?'selected':'' ?>>Pending</option>
                                <option value="completed" <?= ((isset($_GET['status']) ? $_GET['status'] : '')==='completed')?'selected':'' ?>>Completed</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label>Priority</label>
                            <select name="priority" class="form-control">
                                <option value="">All</option>
                                <option value="high" <?= ((isset($_GET['priority']) ? $_GET['priority'] : '')==='high')?'selected':'' ?>>High</option>
                                <option value="medium" <?= ((isset($_GET['priority']) ? $_GET['priority'] : '')==='medium')?'selected':'' ?>>Medium</option>
                                <option value="low" <?= ((isset($_GET['priority']) ? $_GET['priority'] : '')==='low')?'selected':'' ?>>Low</option>
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

                <table id="taskTable" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Priority</th>
                            <th>Due Date</th>
                            <th>Linked To</th>
                            <?php if(canViewTeam() || isSuperAdmin()){ ?><th>Owner</th><?php } ?>
                            <th>Status</th>
                            <th width="170">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($t=mysqli_fetch_assoc($r)){
                            $isOverdue = $t['status']==='pending' && !empty($t['due_date']) && $t['due_date'] < date('Y-m-d H:i:s');
                        ?>
                        <tr class="<?= $isOverdue ? 'table-danger' : '' ?>">
                            <td>
                                <?= htmlspecialchars($t['title']) ?>
                                <?php if($isOverdue){ ?><span class="badge badge-danger">Overdue</span><?php } ?>
                            </td>
                            <td><span class="badge badge-<?= $priorityColors[$t['priority']] ?>"><?= ucfirst($t['priority']) ?></span></td>
                            <td data-sort="<?= $t['due_date'] ? strtotime($t['due_date']) : 0 ?>">
                                <?= $t['due_date'] ? date('m/d/Y H:i', strtotime($t['due_date'])) : '—' ?>
                            </td>
                            <td>
                                <?php if($t['lead_fn']){ ?>
                                <a href="../leads/form.php?id=<?= $t['lead_id'] ?>">Lead: <?= htmlspecialchars($t['lead_fn'].' '.$t['lead_ln']) ?></a>
                                <?php } elseif($t['opp_title']){ ?>
                                <a href="../opportunities/form.php?id=<?= $t['opportunity_id'] ?>">Opp: <?= htmlspecialchars($t['opp_title']) ?></a>
                                <?php } else { ?>—<?php } ?>
                            </td>
                            <?php if(canViewTeam() || isSuperAdmin()){ ?>
                            <td><?= htmlspecialchars(isset($t['owner_name']) ? $t['owner_name'] : '-') ?></td>
                            <?php } ?>
                            <td>
                                <?php if($t['status']==='pending'){ ?>
                                <span class="badge badge-secondary">Pending</span>
                                <?php } else { ?>
                                <span class="badge badge-success">Completed</span>
                                <?php } ?>
                            </td>
                            <td>
                                <?php if($t['status']==='pending'){ ?>
                                <a href="complete.php?id=<?= $t['id'] ?>" class="btn btn-success btn-sm" onclick="return confirm('Mark as done?')"><i class="fas fa-check"></i></a>
                                <?php } ?>
                                <a href="form.php?id=<?= $t['id'] ?>" class="btn btn-info btn-sm"><i class="fas fa-edit"></i></a>
                                <a href="delete.php?id=<?= $t['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this task?')"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>

            </div>
        </div>
    </div>
</div>

<?php include("../layout/footer.php"); ?>

<script>
$(document).ready(function() {
    $('#taskTable').DataTable({
        pageLength: 10,
        order: [[2, "asc"]],
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