<?php
include("../config/db.php");
include("../config/auth.php");

include("../layout/header.php");
include("../layout/sidebar.php");

// Only managers/admins manage users
if(!isSuperAdmin() && !isManager()){
    echo '<div class="alert alert-danger">Access denied.</div>';
    include("../layout/footer.php");
    exit;
}

$where = [];

// scope: admin sees all, manager sees self + their team
$where[] = ownershipWhere('');

// Name
if(!empty($_GET['name'])){
    $name = mysqli_real_escape_string($conn, $_GET['name']);
    $where[] = "name LIKE '%$name%'";
}

// Email
if(!empty($_GET['email'])){
    $email = mysqli_real_escape_string($conn, $_GET['email']);
    $where[] = "email LIKE '%$email%'";
}

// Phone
if(!empty($_GET['phone'])){
    $phone = mysqli_real_escape_string($conn, $_GET['phone']);
    $where[] = "phone LIKE '%$phone%'";
}

// Status filter
if(!empty($_GET['status'])){
    $status = mysqli_real_escape_string($conn, $_GET['status']);
    $where[] = "status = '$status'";
}

$where_sql = '';
if(!empty($where)){
    $where_sql = "WHERE " . implode(" AND ", $where);
}

$r = mysqli_query($conn, "
    SELECT u.*, m.name as manager_name
    FROM users u
    LEFT JOIN users m ON m.id = u.manager_id
    $where_sql
    ORDER BY u.id DESC
");
?>


<div class="row">
    <div class="col-12">

        <div class="card">

            <div class="card-header">

                <h3 class="card-title">Users</h3>

                <div class="card-tools">
                    <div class="d-flex mb-3">

                        <a href="add.php" class="btn btn-primary mr-2">
                            Add User
                        </a>

                    </div>
                </div>

            </div>

            <div class="card-body">
                <form method="GET" id="filterForm" class="mb-3">
                    <div class="row">

                        <div class="col-md-2">
                            <label>Name</label>
                            <input type="text" name="name" class="form-control" placeholder="Enter name" value="<?= isset($_GET['name']) ? htmlspecialchars($_GET['name']) : '' ?>">
                        </div>

                        <div class="col-md-2">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" placeholder="Enter email" value="<?= isset($_GET['email']) ? htmlspecialchars($_GET['email']) : '' ?>">
                        </div>
                        <div class="col-md-2">
                            <label>Phone</label>
                            <input type="text" name="phone" class="form-control" placeholder="Enter phone" value="<?= isset($_GET['phone']) ? htmlspecialchars($_GET['phone']) : '' ?>">
                        </div>

                        <div class="col-md-2">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="">All</option>
                                <option value="active" <?= isset($_GET['status']) && $_GET['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= isset($_GET['status']) && $_GET['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>

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
                            <th>Role</th>
                            <th>Manager</th>
                            <th>View Team</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>

                        <?php while($row=mysqli_fetch_assoc($r)){ ?>

                        <tr>

                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo htmlspecialchars($row['phone']); ?></td>
                            <td>
                                <span class="badge <?= isset($row['role']) && $row['role']==='manager' ? 'badge-primary' : 'badge-secondary' ?>">
                                    <?= ucfirst(isset($row['role']) ? $row['role'] : 'user') ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars(isset($row['manager_name']) ? $row['manager_name'] : '-'); ?></td>
                            <td><?= isset($row['can_view_team']) && $row['can_view_team'] ? '<span class="badge badge-success">Yes</span>' : '-' ?></td>
                            <td><?php echo ucfirst(isset($row['status']) ? $row['status'] : ''); ?></td>

                            <td>

                                <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm">
                                    Edit
                                </a>

                                <a href="delete.php?id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this User?');">
                                    Delete
                                </a>

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
                    $('#contactTable').DataTable({
                        pageLength: 10,
                        order: [
                            [0, "desc"]
                        ],
                        processing: true,

                        dom: 'Brtip',

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
                                targets: 7
                            }
                        ]
                    });
                });
                </script>
