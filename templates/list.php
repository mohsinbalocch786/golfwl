<?php

include("../config/db.php");
include("../config/auth.php");
requireLogin();

include("../layout/header.php");
include("../layout/sidebar.php");

if (!empty($_GET['action']) && !empty($_GET['id'])) {
     $action = $_GET['action'];
    $id = (int)$_GET['id'];

    switch ($action) {
        case 'clone':

            // verify ownership/visibility before cloning
            $src = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM templates WHERE id=$id"));
            if(!$src){ header("Location:list.php"); exit; }

            $canSee = isSuperAdmin()
                || $src['visibility']==='global'
                || ($src['visibility']==='team' && canViewTeam() && (int)$src['manager_id']===currentManagerId())
                || (int)$src['user_id']===currentUserId();

            if(!$canSee){ header("Location:list.php"); exit; }

            list($owner_uid, $owner_mid) = ownershipStamp();

            $cloneSql = "
                INSERT INTO templates
                (
                    `name`, `type`, `subject`, `content`, `status`, `created_at`, `image`, `user_id`, `manager_id`, `visibility`
                )
                SELECT
                    CONCAT(name, '_Clone'),
                    `type`, `subject`, `content`, `status`, '$currentTime', `image`, '$owner_uid', '$owner_mid', 'private'
                FROM templates
                WHERE id = $id
            ";

            mysqli_query($conn, $cloneSql);

            $newId = mysqli_insert_id($conn);

            $_SESSION['successMessage'] = 'Template Cloned Successfully.';
echo "<script>
window.location='edit.php?id=".$newId."';
</script>";
exit;
      

        default:
            break;
    }
}

// ── Visibility-aware ownership scoping ──────────────────────────
// - global templates: visible to everyone
// - team templates: visible to the owning team (manager_id match) if canViewTeam
// - private templates: visible only to the owner (user_id match)
if(isSuperAdmin()){
    $where = ["1=1"];
} else {
    $uid = currentUserId();
    $mid = currentManagerId();
    $visParts = [];
    $visParts[] = "t.visibility='global'";
    $visParts[] = "t.user_id=$uid"; // always see your own regardless of visibility
    if(canViewTeam()){
        $visParts[] = "(t.visibility='team' AND t.manager_id=$mid)";
    }
    $where = ["(" . implode(" OR ", $visParts) . ")"];
}

// Name
if(!empty($_GET['name'])){
    $name = mysqli_real_escape_string($conn, $_GET['name']);
    $where[] = "t.name LIKE '%$name%'";
}

// Type (multi select)
if(!empty($_GET['type'])){

    // remove empty values
    $types = array_filter($_GET['type']);

    if(!empty($types)){
        $types = array_map(function($t) use ($conn){
            return mysqli_real_escape_string($conn, $t);
        }, $types);

        $type_str = "'" . implode("','", $types) . "'";
        $where[] = "t.type IN ($type_str)";
    }
}

// Status (multi select)
$statuses = array_filter((array)(isset($_GET['status']) ? $_GET['status'] : []));

if(!empty($statuses)){
    $statuses = array_map(function($s) use ($conn){
        return mysqli_real_escape_string($conn, $s);
    }, $statuses);

    $where[] = "t.status IN ('" . implode("','", $statuses) . "')";
}

// Date range
if(!empty($_GET['start_date']) && !empty($_GET['end_date'])){
    $start = mysqli_real_escape_string($conn, date('Y-m-d', strtotime($_GET['start_date'])));
    $end   = mysqli_real_escape_string($conn, date('Y-m-d', strtotime($_GET['end_date'])));

  $where[] = "DATE(created_at) BETWEEN '$start' AND '$end'";
}
/* else {
    $start = date('Y-m-01');
    $end   = date('Y-m-t');
}
  $where[] = "DATE(created_at) BETWEEN '$start' AND '$end'";*/

// Final WHERE
$where_sql = '';
if(!empty($where)){
    $where_sql = "WHERE " . implode(" AND ", $where);
}
 $query = "
    SELECT t.*, u.name as owner_name
    FROM templates t
    LEFT JOIN users u ON u.id = t.user_id
    $where_sql
    ORDER BY t.id DESC
";
$r = mysqli_query($conn, $query);
?>


<div class="row">
    <div class="col-12">

        <div class="card">

            <div class="card-header">

                <h3 class="card-title">Templates</h3>

                <div class="card-tools">
                    <a href="add.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Add Template
                    </a>
                </div>

            </div>

            <div class="card-body">

            

                

              

                 <form method="GET" id="filterForm" class="mb-3">
                    <div class="row">
                        <!-- Name -->
                        <div class="col-md-2">
                            <label>Name</label>
                            <input type="text" name="name" class="form-control" value="<?= isset($_GET['name']) ? htmlspecialchars($_GET['name']) : '' ?>">
                        </div>

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
                                <option value="active"
                                    <?= (isset($_GET['status']) && in_array('active', (array)$_GET['status'])) ? 'selected' : '' ?>>
                                    Active</option>
                                <option value="inactive"
                                    <?= (isset($_GET['status']) && in_array('inactive', (array)$_GET['status'])) ? 'selected' : '' ?>>
                                    Inactive</option>
                                <option value="archived"
                                    <?= (isset($_GET['status']) && in_array('archived', (array)$_GET['status'])) ? 'selected' : '' ?>>
                                    Archived</option>
                            </select>
                        </div>

                       

                        <!-- Date Range -->
                        <div class="col-md-2">
                            <label>Date</label>
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

               

               



                <table id="templateTable" class="table table-bordered table-striped">

                    <thead>
                        <tr>

                            <th>Name</th>
                            <th>Type</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Visibility</th>
                            <?php if(canViewTeam() || isSuperAdmin()){ ?><th>Owner</th><?php } ?>
                            <th>Image</th>
                            <th>Created</th>
                            <th width="160">Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php while($row=mysqli_fetch_assoc($r)){ ?>

                        <tr>


                            <td>
                                <?php echo htmlspecialchars($row['name']); ?>
                            </td>
                            <td><?php echo strtoupper($row['type']); ?></td>
                            <td><?php echo $row['subject']; ?></td>

                            <td>

                                <?php

                                    if($row['status']=="active"){
                                    echo '<span class="badge badge-success">Active</span>';
                                    }

                                    if($row['status']=="inactive"){
                                    echo '<span class="badge badge-warning">Inactive</span>';
                                    }

                                    if($row['status']=="archived"){
                                    echo '<span class="badge badge-secondary">Archived</span>';
                                    }

                                    ?>

                            </td>
                            <td>
                                <?php
                                    $visBadge = [
                                        'private'=>'badge-secondary',
                                        'team'=>'badge-info',
                                        'global'=>'badge-success',
                                    ];
                                    $vis = isset($row['visibility']) ? $row['visibility'] : 'global';
                                    echo '<span class="badge '.(isset($visBadge[$vis]) ? $visBadge[$vis] : 'badge-secondary').'">'.ucfirst($vis).'</span>';
                                ?>
                            </td>
                            <?php if(canViewTeam() || isSuperAdmin()){ ?>
                            <td><?php echo htmlspecialchars(isset($row['owner_name']) ? $row['owner_name'] : '-'); ?></td>
                            <?php } ?>
                            <td>
                                <?php  
                                    $uploadDir = '';
                                    $oldImagePath  = '';
                                    $class = 'd-none hide';
                                    if(!empty($row['image'])){
                                        $class= "";
                                        $uploadDir = 'smsimage';
                                        $oldImagePath  = '/'.$row['image'];
                                    }
                                    ?>
                                    <img id="preview" src="<?php echo $uploadDir . $oldImagePath ?>" style="width: 175px;height: 150px;" class="<?php echo $class;?> image-preview">
                            </td>

                            <td data-sort="<?php echo strtotime($row['created_at']); ?>">
                                <?php echo date('m/d/Y', strtotime($row['created_at'])); ?></td>

                            <td>
                                <?php
                                    $canEdit = isSuperAdmin() || (int)$row['user_id']===currentUserId() || (isManager() && (int)$row['manager_id']===currentManagerId());
                                ?>
                                <?php if($canEdit){ ?>
                                <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm">

                                    <i class="fas fa-edit"></i>


                                </a>

                                <a href="delete.php?id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm"
                                    onclick="return confirm('Delete template?')">

                                    <i class="fas fa-trash"></i>

                                </a>
                                <?php } ?>

                                <a href="list.php?action=clone&id=<?= $row['id']; ?>" class="btn btn-secondary btn-sm" title="Clone">
                                    <i class="fa fa-clone" aria-hidden="true"></i>
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
<div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Image Preview</h5>

                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body text-center">
                <img id="modalImage"
                     src=""
                     style="max-width:100%;height:auto;">
            </div>

        </div>
    </div>
</div>

<?php include("../layout/footer.php"); ?>

<script>
$(document).ready(function() {
    $('select[name="type[]"]').select2();
    $('select[name="status[]"]').select2();
    
      $(document).on('click', '.image-preview', function () {

    var imageSrc = $(this).attr('src');

    $('#modalImage').attr('src', imageSrc);

    $('#imageModal').modal('show');
});

    $('#templateTable').DataTable({
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
                targets: 5
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