<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

include("../layout/header.php");
include("../layout/sidebar.php");
$where = [];




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



// Final WHERE
$where_sql = '';
if(!empty($where)){
    $where_sql = "WHERE " . implode(" AND ", $where);
}
$r = mysqli_query($conn, "
    SELECT 
*      
    FROM twilio_numbers   
    $where_sql 
");


// while($row=mysqli_fetch_assoc($r)){
//     echo "<pre>"; print_r($row);echo "</pre>";
// }

$canManage = isSuperAdmin() || isManager();
?>


<div class="row">
    <div class="col-12">

        <div class="card">

            <div class="card-header">

                <h3 class="card-title">Twilio Numbers</h3>

                <?php if($canManage){ ?>
                <div class="card-tools">
                    <div class="d-flex mb-3">

                        <a href="add.php" class="btn btn-primary mr-2">
                            Add Twilio Number
                        </a>

                       
                    </div>
                </div>
                <?php } ?>

            </div>



            <div class="card-body">
                <form method="GET" id="filterForm" class="mb-3">
                    <div class="row">
                        
                        <div class="col-md-2">
                            <label>Phone</label>
                            <input type="text" name="phone" class="form-control" placeholder="Enter phone" value="<?= isset($_GET['phone']) ? $_GET['phone'] : '' ?>">
                        </div>
                        <div class="col-md-2">
                            <label>Status</label>
                            <select name="status" class="form-control select2" >
                                <option value="active" <?= isset($_GET['status']) && $_GET['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= isset($_GET['status']) && $_GET['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
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

                <table id="contactTable" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                          
                            <th>Phone</th>
                            <th>Status</th>
                            <?php if($canManage){ ?><th>Actions</th><?php } ?>

                        </tr>
                    </thead>
                    <tbody>

                        <?php while($row=mysqli_fetch_assoc($r)){ ?>

                        <tr>
                            <td><?php echo htmlspecialchars($row['phone']); ?></td>
                            <td><?php echo ucfirst($row['status']); ?></td>

                            <?php if($canManage){ ?>
                            <td>

                                <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm">
                                    Edit
                                </a>

                                <a href="delete.php?id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this Twilio Number?');">
                                    Delete
                                </a>

                            </td>
                            <?php } ?>

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


<script>
$(function() {

    let startVal = "<?= !empty($_GET['start_date']) ? date('m/d/Y', strtotime($_GET['start_date'])) : '' ?>";
    let endVal = "<?= !empty($_GET['end_date']) ? date('m/d/Y', strtotime($_GET['end_date'])) : '' ?>";

    let start, end;

    if (startVal && endVal) {
        start = moment(startVal, 'MM/DD/YYYY');
        end = moment(endVal, 'MM/DD/YYYY');
    } else {
        start = moment().startOf('month', 'MM/DD/YYYY');
        end = moment().endOf('month', 'MM/DD/YYYY');
    }

    function setDates(start, end) {
        $('#daterange').val(start.format('MM/DD/YYYY') + ' - ' + end.format('MM/DD/YYYY'));
        $('#start_date').val(start.format('MM/DD/YYYY'));
        $('#end_date').val(end.format('MM/DD/YYYY'));
    }

    $('#daterange').daterangepicker({
        startDate: start,
        endDate: end,

        ranges: {
            'Today': [moment(), moment()],
            'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            'Last 7 Days': [moment().subtract(6, 'days'), moment()],
            'Last 30 Days': [moment().subtract(29, 'days'), moment()],
            'This Month': [moment().startOf('month'), moment().endOf('month')],
            'Last Month': [
                moment().subtract(1, 'month').startOf('month'),
                moment().subtract(1, 'month').endOf('month')
            ]
        },

        locale: {
            format: 'MM/DD/YYYY'
        }
    }, setDates);

    setDates(start, end);

});
</script>
