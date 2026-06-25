<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

include("../layout/header.php");
include("../layout/sidebar.php");

$where = [ ownershipWhere('cg') ];

if(!empty($_GET['cgroup'])){
    $group_ids = array_map('intval', $_GET['cgroup']);
    $where[] = "cg.id IN (" . implode(',', $group_ids) . ")";
}

$where_sql = "WHERE " . implode(" AND ", $where);

$q = "
SELECT
cg.id,
cg.name,
cg.created_at,
tn.phone as twilio_number,
u.name as owner_name,
COUNT(cgm.contact_id) as total_contacts

FROM contact_groups cg

LEFT JOIN contact_group_map cgm
ON cgm.group_id = cg.id

LEFT JOIN twilio_numbers tn
ON tn.id = cg.twilio_num_id

LEFT JOIN users u
ON u.id = cg.user_id

$where_sql

GROUP BY cg.id
ORDER BY cg.id DESC
";

$r=mysqli_query($conn,$q);

// dropdown for filter, scoped
$gw = ownershipWhere('');
$groups_q = mysqli_query($conn, "SELECT id, name FROM contact_groups WHERE $gw");
?>

<div class="row">

<div class="col-12">

<div class="card">

<div class="card-header">

<h3 class="card-title">Contact Groups</h3>

<div class="card-tools">

<a href="add.php" class="btn btn-primary btn-sm">
<i class="fas fa-plus"></i> Add Group
</a>

</div>

</div>

<div class="card-body">

    <form method="GET" id="filterForm" class="mb-3">
            <div class="row">

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

                <!-- Submit -->
                <div class="col-md-2" style="margin-top: 2rem;">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-50">Filter</button>
                        <a href="list.php" id="resetBtn" class="btn btn-secondary w-50 ml-2">Reset</a>
                    </div>
                </div>

            </div>
        </form>

<table  id="groupTable" class="table table-bordered table-striped">

<thead>

<tr>

<th>Group Name</th>
<th width="150">Contacts</th>
<th width="200">Twilio Number</th>
<?php if(canViewTeam() || isSuperAdmin()){ ?><th>Owner</th><?php } ?>
<th width="200">Created</th>
<th width="150">Actions</th>
</tr>

</thead>

<tbody>

<?php while($row=mysqli_fetch_assoc($r)){ ?>

<tr>

<td><?php echo htmlspecialchars($row['name']); ?></td>

<td><a href="../contacts/list.php?cgroup[]=<?php echo $row['id']; ?>" target="_blank">
    <span class="badge badge-info">
    <?php echo $row['total_contacts']; ?>
    </span></a>
</td>
<td><?php echo htmlspecialchars(isset($row['twilio_number']) ? $row['twilio_number'] : ''); ?></td>
<?php if(canViewTeam() || isSuperAdmin()){ ?>
<td><?php echo htmlspecialchars(isset($row['owner_name']) ? $row['owner_name'] : '-'); ?></td>
<?php } ?>

<td data-sort="<?php echo strtotime($row['created_at']); ?>"><?php echo date('m/d/Y', strtotime($row['created_at'])); ?></td>

<td>

<a href="edit.php?id=<?php echo $row['id']; ?>"
class="btn btn-info btn-sm">

<i class="fas fa-edit"></i>

</a>

<a href="delete.php?id=<?php echo $row['id']; ?>"
class="btn btn-danger btn-sm"
onclick="return confirm('Delete this group?')">

<i class="fas fa-trash"></i>

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
    $('select[name="cgroup[]"]').select2();
    $('#groupTable').DataTable({
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
