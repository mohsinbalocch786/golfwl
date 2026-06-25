<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

include("../layout/header.php");
include("../layout/sidebar.php");
$where = [];
$join_filter = "";

// Ownership scoping
$where[] = ownershipWhere('c');

// Name
if(!empty($_GET['name'])){
    $name = mysqli_real_escape_string($conn, $_GET['name']);
    $where[] = "c.name LIKE '%$name%'";
}

// Email
if(!empty($_GET['email'])){
    $email = mysqli_real_escape_string($conn, $_GET['email']);
    $where[] = "c.email LIKE '%$email%'";
}

// Phone
if(!empty($_GET['phone'])){
    $phone = mysqli_real_escape_string($conn, $_GET['phone']);
    $where[] = "c.phone LIKE '%$phone%'";
}

// Group filter (multiple)
if(!empty($_GET['cgroup'])){
    $group_ids = array_map('intval', $_GET['cgroup']);
    $group_ids_str = implode(',', $group_ids);

    $join_filter = " INNER JOIN contact_group_map cgm_filter ON c.id = cgm_filter.contact_id ";
    $where[] = "cgm_filter.group_id IN ($group_ids_str)";
}

// Owner filter (manager/admin only)
if(!empty($_GET['owner']) && (isManager() || isSuperAdmin())){
    $owner_id = (int)$_GET['owner'];
    $where[] = "c.user_id = $owner_id";
}

// Final WHERE
$where_sql = '';
if(!empty($where)){
    $where_sql = "WHERE " . implode(" AND ", $where);
}

// ── Pagination ────────────────────────────────────────────────
$perPage   = 100;
$page      = max(1, (int)(isset($_GET['page']) ? $_GET['page'] : 1));
$offset    = ($page - 1) * $perPage;

$countR    = mysqli_query($conn, "
    SELECT COUNT(DISTINCT c.id) AS total
    FROM contacts c
    LEFT JOIN contact_group_map cgm ON c.id = cgm.contact_id
    LEFT JOIN contact_groups cg ON cgm.group_id = cg.id
    $join_filter
    $where_sql
");
$totalRows  = $countR ? (int)mysqli_fetch_assoc($countR)['total'] : 0;
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$r = mysqli_query($conn, "
    SELECT
        c.*,
        GROUP_CONCAT(DISTINCT cg.name SEPARATOR ', ') as group_names,
        u.name as owner_name
    FROM contacts c

    LEFT JOIN contact_group_map cgm ON c.id = cgm.contact_id
    LEFT JOIN contact_groups cg ON cgm.group_id = cg.id
    LEFT JOIN users u ON u.id = c.user_id

    $join_filter
    $where_sql

    GROUP BY c.id
    ORDER BY c.id DESC
    LIMIT $perPage OFFSET $offset
");

// Groups for filter dropdown — also scoped to ownership
$group_where = ownershipWhere('');
$groups_q = mysqli_query($conn, "SELECT id, name FROM contact_groups WHERE $group_where ORDER BY name");

// Team members for owner filter dropdown
$members = (isManager() || isSuperAdmin()) ? teamMembers($conn) : [];
?>


<div class="row">
    <div class="col-12">

        <div class="card">

            <div class="card-header">

                <h3 class="card-title">Contacts</h3>

                <div class="card-tools">
                    <div class="d-flex mb-3">

                        <a href="add.php" class="btn btn-primary mr-2">
                            Add Contact
                        </a>

                        <a href="../groups/list.php" class="btn btn-primary mr-2">
                            Groups
                        </a>

                        <a href="../contacts/import_csv.php" class="btn btn-primary">
                            <i class="nav-icon fas fa-file-upload"></i>
                            Import Contacts
                        </a>

                    </div>
                </div>

            </div>



            <div class="card-body">
                <form method="GET" id="filterForm" class="mb-3">
                    <div class="row">

                        <!-- Name -->
                        <div class="col-md-2">
                            <label>Name</label>
                            <input type="text" name="name" class="form-control" placeholder="Enter name" value="<?= isset($_GET['name']) ? htmlspecialchars($_GET['name']) : '' ?>">
                        </div>

                        <!-- Email -->
                        <div class="col-md-2">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" placeholder="Enter email" value="<?= isset($_GET['email']) ? htmlspecialchars($_GET['email']) : '' ?>">
                        </div>
                        <div class="col-md-2">
                            <label>Phone</label>
                            <input type="text" name="phone" class="form-control" placeholder="Enter phone" value="<?= isset($_GET['phone']) ? htmlspecialchars($_GET['phone']) : '' ?>">
                        </div>

                        <!-- group by select2 -->
                        <div class="col-md-2">
                            <label>Group</label>
                            <select name="cgroup[]" class="form-control select2" multiple>
                                <?php
                                    while($g=mysqli_fetch_assoc($groups_q)){
                                        echo '<option value="'.$g['id'].'" '.(isset($_GET['cgroup']) && in_array($g['id'], (array)$_GET['cgroup']) ? 'selected' : '').'>'.htmlspecialchars($g['name']).'</option>';
                                    }
                                    ?>
                            </select>
                        </div>

                        <?php if(!empty($members) && (isManager() || isSuperAdmin())){ ?>
                        <!-- Owner filter -->
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

                        <!-- Submit -->
                        <div class="col-md-2" style="margin-top: 2rem;">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary w-50">Filter</button>
                                <a href="list.php" id="resetBtn" class="btn btn-secondary w-50 ml-2">Reset</a>
                            </div>
                        </div>

                    </div>
                </form>

                <table id="contactTable" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Group</th>
                            <?php if(canViewTeam() || isSuperAdmin()){ ?><th>Owner</th><?php } ?>
                            <th>Actions</th>

                        </tr>
                    </thead>
                    <tbody>

                        <?php while($row=mysqli_fetch_assoc($r)){ ?>

                        <tr>

                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo htmlspecialchars($row['phone']); ?></td>
                            <td><?php echo $row['group_names'] ? htmlspecialchars($row['group_names']) : ''; ?></td>
                            <?php if(canViewTeam() || isSuperAdmin()){ ?>
                            <td><?php echo htmlspecialchars(isset($row['owner_name']) ? $row['owner_name'] : '-'); ?></td>
                            <?php } ?>

                            <td>

                                <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm">
                                    Edit
                                </a>

                                <a href="delete.php?id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this Contact?');">
                                    Delete
                                </a>
                                <?php if(!$row['converted']){ ?>
                                <a href="convert.php?id=<?= $row['id'] ?>" class="btn btn-success btn-sm" onclick="return confirm('Convert this contact to an lead?')">
                                    <i class="fas fa-exchange-alt"></i> Convert
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
// Build pagination query string (preserve existing filters)
$qp = $_GET;
unset($qp['page']);
$qs = http_build_query($qp);
$qs = $qs ? '&'.$qs : '';
if ($totalPages > 1): ?>
<div class="d-flex justify-content-between align-items-center mt-3 px-3">
    <small class="text-muted">
        Showing <?php echo number_format($offset+1); ?>–<?php echo number_format(min($offset+$perPage,$totalRows)); ?> of <?php echo number_format($totalRows); ?> contacts
    </small>
    <nav>
        <ul class="pagination pagination-sm mb-0">
            <?php if($page > 1): ?>
            <li class="page-item"><a class="page-link" href="?page=<?php echo $page-1; ?><?php echo $qs; ?>">‹ Prev</a></li>
            <?php endif; ?>
            <?php
            $start = max(1, $page-2); $end = min($totalPages, $page+2);
            for($p=$start;$p<=$end;$p++):
            ?>
            <li class="page-item <?php echo $p===$page?'active':''; ?>">
                <a class="page-link" href="?page=<?php echo $p; ?><?php echo $qs; ?>"><?php echo $p; ?></a>
            </li>
            <?php endfor; ?>
            <?php if($page < $totalPages): ?>
            <li class="page-item"><a class="page-link" href="?page=<?php echo $page+1; ?><?php echo $qs; ?>">Next ›</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</div>
<?php endif; ?>
                <?php include("../layout/footer.php"); ?>
                <script>
                $(document).ready(function() {
                    $('select[name="cgroup[]"]').select2();
                    $('#contactTable').DataTable({
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