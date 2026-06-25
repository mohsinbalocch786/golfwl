<?php

include("../config/db.php");
include("../layout/header.php");
include("../layout/sidebar.php");

if (!empty($_GET['action']) && !empty($_GET['id'])) {

    $action = $_GET['action'];
    $id = (int)$_GET['id'];

    switch ($action) {

        case 'deleteSmsTemplate':

            mysqli_query($conn, "DELETE FROM email_templates WHERE id = $id");

            $_SESSION['successMessage'] = 'SMS Template Deleted Successfully.';

           echo "<script>
            window.location='list.php';
            </script>";
            exit;

        case 'archive':

            mysqli_query($conn, "UPDATE email_templates SET deleted = 1 WHERE id = $id");

            $_SESSION['successMessage'] = 'SMS Template Archived Successfully.';

            echo "<script>
            window.location='list.php';
            </script>";
            exit;
        case 'restore':

            mysqli_query($conn, "UPDATE email_templates SET deleted = 0 WHERE id = $id");

            $_SESSION['successMessage'] = 'SMS Template Restored Successfully.';
            echo "<script>
            window.location='list.php?status=1';
            </script>";
            exit;


        case 'clone':

            $cloneSql = "
                INSERT INTO email_templates
                (
                    name,
                    subject,
                    template,
                    image,
                    created_by,
                    created_at,
                    from_name,
                    from_email,
                    type,
                    deleted,
                    status,
                    note
                )
                SELECT
                    CONCAT(name, '_Clone'),
                    subject,
                    template,
                    image,
                    created_by,
                    '$currentTime',
                    from_name,
                    from_email,
                    type,
                    deleted,
                    status,
                    note
                FROM email_templates
                WHERE id = $id
            ";

            mysqli_query($conn, $cloneSql);

            $newId = mysqli_insert_id($conn);

            $_SESSION['successMessage'] = 'SMS Template Cloned Successfully.';
echo "<script>
window.location='edit.php?id=".$newId."';
</script>";
exit;
      

        default:
            break;
    }
}
// $userID = CurrentUserID();
$created_by = $_GET['AM'];
$status = !empty($_GET['status']) ? $_GET['status'] : 0;
$activeStatus = isset($_GET['activeStatus']) ? $_GET['activeStatus'] : '';

/*$sql = "SELECT 
            email_templates.*, 
            membership.ContactName AS assign_am_contact_name, 
            membership.OFFICE_NUM,
            membership.TWILIO_NUM
        FROM email_templates
        LEFT JOIN membership 
            ON email_templates.assign_am = membership.ID
        WHERE email_templates.type = 'SMS'
        AND email_templates.deleted = '$status'";*/


$sql = "SELECT 
            email_templates.*
           
        FROM email_templates
        
        WHERE email_templates.type = 'SMS'
        ";
if ($status != "") {
    $sql .= " AND email_templates.deleted = '$status'";
}
if ($created_by != "") {
    $sql .= " AND email_templates.assign_am = '$created_by'";
}

if ($activeStatus != "") {
    $sql .= " AND email_templates.status = '$activeStatus'";
}

$sql .= " ORDER BY email_templates.created_at DESC";
// echo $sql;die;

$result = mysqli_query($conn, $sql);

$datas = array();

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $datas[] = $row;
    }
}
// echo "<pre>"; print_r($datas);die;
/* Membership Query */
// $MembershipResults = array();

// if ($created_by != "") {
    
//     $Memberships = "SELECT * 
//                     FROM membership 
//                     WHERE ID = '$created_by'";

//     $membershipResult = mysqli_query($conn, $Memberships);

//     if ($membershipResult) {
//         $MembershipResults = mysqli_fetch_assoc($membershipResult);
//     }
// }



?>


<div class="row">
    <div class="col-12">

        <div class="card">

            <div class="card-header">

                <h3 class="card-title">SMS/MMS Templates</h3>

                <div class="card-tools">
                    <a href="add.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Add SMS/MMS Template
                    </a>
                </div>

            </div>

            <div class="card-body">

            

                

              

                 <form method="GET" id="filterForm" class="mb-3">
                    <div class="row">
                        <!-- Name -->
                         <!-- <div class="col-md-2">
                            <label class="">AM</label><br>
                            <select class="js-example-data-ajax form-control" name="AM" id="createdBy" style="width:100%">
                                <option value="<?php echo $created_by; ?>" <?php if ($created_by == $_GET['createdBy']) {
                                    echo "selected=selected";
                                } ?>><?php echo $MembershipResults['ContactName']; ?></option>
                            </select>
                        </div> -->
                        <div class="col-md-2">
                            <label class="">Archive</label><br>
                            <select class="js-example-data-ajax form-control" name="status" id="status" style="width:100%">
                                <option value="0" <?php if ("0" == $_GET['status']) {echo "selected=selected";} ?>>All</option>
                                <option value="1" <?php if ("1" == $_GET['status']) {echo "selected=selected";} ?>>Archive</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="">Status</label><br>
                            <select class="js-example-data-ajax form-control" name="activeStatus" id="activeStatus" style="width:100%">
                                <option value="">Please Select</option>
                                <option value="0" <?php if ("0" == $_GET['activeStatus']) {echo "selected=selected";} ?>>Inactive</option>
                                <option value="1" <?php if ("1" == $_GET['activeStatus']) {echo "selected=selected";} ?>>Active</option>
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

               

               



                <table id="templateTable" class="table table-bordered table-striped">

                    <thead>
                        <tr>
                            <th>Name</th>            
                            <!-- <th>Assign AM</th> -->
                            <th>Status</th>
                            <th>Image</th>
                            <th width="160">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (!empty($datas)) {
                                    foreach($datas as $data){
                                        $activeStatus = '';
                                        if($data['status'] == '1'){
                                            $activeStatus = 'Active';
                                        }else if($data['status'] == '0'){
                                            $activeStatus = 'Inactive';
                                        }
                                        ?>
                                        <tr>
                                            <td>
                                                <a href="sms_template_edit.php?id=<?= $data['id']; ?>"><?= $data['name']; ?></a><br/>
                                                <?php if($data['deleted']==0){?>
                                                <a href="list.php?action=clone&id=<?= $data['id']; ?>" title="Clone"><i class="fa fa-clone" aria-hidden="true"></i> Clone</a>&nbsp;
                                                <a href="list.php?action=archive&id=<?= $data['id']; ?>" title="Archive"><i class="fa fa-archive" aria-hidden="true"></i> Archive</a>
                                                <?php } ?>
                                            </td>
                                            
                                            <!-- <td>
                                                <?= $data['assign_am_contact_name']; ?></br>
                                                <?= !empty($data['TWILIO_NUM']) ? $data['TWILIO_NUM'] : $data['OFFICE_NUM']; ?>
                                            </td> -->
                                            <td><?php echo $activeStatus?></td>
                                            <td>
                                            <?php 
                                            $uploadDir = '';
                                            $oldImagePath  = '';
                                            $class = 'd-none hide';
                                            if(!empty($data['image'])){
                                                $class= "";
                                                $uploadDir = 'smsimage';
                                                $oldImagePath  = '/'.$data['image'];
                                            }
                                            ?>
                                                <img id="preview" src="<?php echo $uploadDir . $oldImagePath ?>" style="width: 100px;height: 85px;" class="<?php echo $class;?>">
                                            </td>
                                        
                                            <td>
                                                <!-- <a href="sms_template_edit.php?id=<?= $data['id']; ?>" class="btn btn-primary">Edit</a>
                                                <a href="fetch_sms_templates.php?action=deleteSmsTemplate&id=<?= $data['id']; ?>" class="btn btn-danger"
                                                    onclick="return confirm('Are You Sure You Want to Delete ?');">Delete</a>
                                                <a href="javascript:void(0);" onclick="sendTemplateModal('<?= $data['id']; ?>');"
                                                    class="btn btn-info">Send Test Template</a> -->
                                                    <?php if($data['deleted']==0){?>
                                                <div class="dropdown">
                                                    <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton"
                                                        data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                        Action
                                                    </button>
                                                    <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                                        <a class="dropdown-item" href="edit.php?id=<?= $data['id']; ?>">Edit</a>
                                                        <a class="dropdown-item"
                                                            href="list.php?action=deleteSmsTemplate&id=<?= $data['id']; ?>"
                                                            onclick="return confirm('Are You Sure You Want to Delete ?');">Delete</a>
                                                    
                                                    </div>
                                                </div>
                                                <?php } else {?>
                                                <a href="list.php?action=restore&id=<?= $data['id']; ?>" title="Restore"><i class="fa fa-undo" aria-hidden="true"></i> Restore</a>
                                                <a href="edit.php?id=<?= $data['id']; ?>"><i class="fa fa-edit" aria-hidden="true"></i> Edit</a>
                                                <a href="list.php?action=deleteSmsTemplate&id=<?= $data['id']; ?>" onclick="return confirm('Are You Sure You Want to Delete ?');" title="Delete"><i class="fa fa-trash" aria-hidden="true"></i> Delete</a>
                                                <?php }?>
                                            </td>
                                        </tr>
                                    <?php }
                                }else{ ?>
                              
                                          <tr>
                                              <td>Not Found</td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
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
    $('select[name="type[]"]').select2();
    $('select[name="status[]"]').select2();

    $('#templateTable').DataTable({
        pageLength: 10,
        processing: true,
    
        order: [],
    
        dom: 'Bfrtip',
    
        buttons: [
            {
                extend: 'csv',
                exportOptions: {
                    columns: [0,1]
                }
            },
            {
                extend: 'excel',
                exportOptions: {
                    columns: [0,1]
                }
            },
            {
                extend: 'print',
                exportOptions: {
                    columns: [0,1]
                }
            }
        ],
    
        columnDefs: [
            {
                orderable: false,
                targets: [2,3]
            }
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