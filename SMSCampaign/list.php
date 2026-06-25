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


$sql = "SELECT 
    email_campaign.*, 
    (SELECT COUNT(*) FROM email_campaign_list WHERE campaign_id = email_campaign.id) AS lead_count,
    (SELECT COUNT(*) FROM email_campaign_list WHERE campaign_id = email_campaign.id AND (sendgrid_message_id != NULL OR sendgrid_message_id != '')) AS sent_count,
    email_templates.name AS template_name, contacts.name, contacts.phone
FROM 
    email_campaign
LEFT JOIN email_templates ON email_campaign.template_id = email_templates.id

LEFT JOIN contacts AS contacts ON contacts.id = email_campaign.id

WHERE email_campaign.type = 'SMS'";
$assign_am = '';
if(!empty($_GET['assign_am'])){
    $assign_am = $_GET['assign_am'];
    $sql .= " AND email_campaign.assign_am = '$assign_am'";
}
$status = '';
if (!empty($_GET['status'])) {
    $status = $_GET['status'];
    $sql .= " AND email_campaign.status = '$status'";
} else {
    $_GET['status'] = 'Pending';
    $status = $_GET['status'];
    $sql .= " AND email_campaign.status = '$status'";
}

$sql .= " ORDER BY email_campaign.created_at DESC";

$result = mysqli_query($conn, $sql);

$datas = array();

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $datas[] = $row;
    }
}

echo "<pre>"; print_r($datas);die;


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
                            <th>Campaign</th>
                            <th>Template</th>
                            <th>Twilio Number</th>
                            <th>Start</th>
                            <th>Status</th>
                            <th>Leads/Sent</th>
                            <th>Assign AM</th>
                            <th>Repeat Days</th>
                            <th>Send Limit</th>
                            <th>Import Name</th>
                            <!--<th>Created By</th>
                            <th>Created At</th>-->
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
        <?php if (!empty($datas)) {
            foreach ($datas as $data) { ?>
                <tr>
                    <td>
                        <?php if ($data['status'] == 'Completed') { ?>
                        <?= $data['name']; ?>
                        <?php } else {?>
                            <a href="sms_campaign_edit_new.php?id=<?= $data['id']; ?>"><?= $data['name']; ?></a>
                            <?php }?>
                            </br>
                        <a href="fetch_sms_campaigns_new.php?action=clone&id=<?= $data['id']; ?>" title="Clone"><i class="fa fa-clone" aria-hidden="true"></i> Clone</a>
                    </td>
                    <td>
                        <a target="_blank"
                            href="sms_template_edit.php?id=<?= $data['template_id']; ?>"><?= $data['template_name']; ?></a>
                    </td>
                    <td>
                        <?= $data['twilio_number']; ?>                    
                    </td>
                    <!-- <td data-sort="<?= !empty($data['scheduled']) && $data['scheduled'] != '0000-00-00 00:00:00' ? strtotime($data['scheduled']) : '' ?>">
                        <?= !empty($data['scheduled']) && $data['scheduled'] != '0000-00-00 00:00:00' ? date('m/d/Y h:i A', strtotime($data['scheduled'])) : '' ?>
                    </td> -->

                    <?php 
                        if($data['status'] != 'Completed'){
                            $class= 'scheduled-cell text-primary text-decoration-underline';
                            $style = 'cursor: pointer;';
                        } else {
                            $class= '';
                            $style = '';
                        }
                    ?>
                    <td class="<?= $class ?>" style="<?php echo $style; ?>" data-id="<?= $data['id'] ?>" data-status = <?= $data['status']; ?> data-sort="<?= !empty($data['scheduled']) && $data['scheduled'] != '0000-00-00 00:00:00' ?  strtotime($data['scheduled']) : '' ?>">
                       <span class="scheduled-display"> <?= !empty($data['scheduled']) && $data['scheduled'] != '0000-00-00 00:00:00' ? date('m/d/Y h:i A', strtotime($data['scheduled'])) : '' ?></span>
                        <?php if ($data['status'] != 'Completed'){ ?>
                        <div class="scheduled-edit d-none">
                            <input type="date" class="form-control form-control-sm date-input mb-2" style="width: 130px; display: inline-block;">
                            <select name="time" class="form-control mb-2 time-input">
                            <?php
                            $start = strtotime("05:00 AM");
                            $end = strtotime("05:00 PM");

                            for ($time = $start; $time <= $end; $time += 15 * 60) {
                                $display = date("g:i A", $time);  // 5:00 AM format
                                $value = date("H:i", $time);      // 24-hour format for form value
                                echo "<option value=\"$value\">$display</option>";
                            }
                            ?>
                            
                        </select>
                        <div class="cell-loader d-none">
                            <button class="btn btn-sm btn-primary " disabled>Update <i class="fas fa-spinner fa-spin" style="margin-left: 10px;"></i></button>
                        </div>
                            <button class="btn btn-sm btn-primary save-schedule">Update</button>
                            <button class="btn btn-sm btn-secondary cancel-schedule">Cancel</button>
                        </div>
                        <?php } ?>
                    </td>


                    <td>
                        <button type="button"
                            class="btn btn-<?= $data['status'] == 'Completed' ? 'success' : ($data['status'] == 'Pending' ? 'warning' : 'primary'); ?>"><?= $data['status'] == 'Pending' ? 'Waiting' : $data['status']; ?></button>
                    </td>
                    <td>
                        <a target="_blank"
                            href="marketing_list.php?id=<?= $data['marketing_id']; ?>"><?= $data['lead_count']; ?>/<?= $data['sent_count']; ?></a>
                    </td>
                    <td>
                        <?= $data['assign_am_name']; ?></br>
                        <?= !empty($data['assign_TWILIO_NUM'])?$data['assign_TWILIO_NUM']:$data['assign_OFFICE_NUM']; ?>
                    </td>
                    <td>
                        <a href="#" title="<?=$data['repeat_days']?>"><?= $data['repeat_days']=="MON,TUE,WED,THU,FRI,SAT,SUN" ? 'ALL' :$data['repeat_days']; ?></a>
                    </td>
                    <td>
                        <?= $data['send_limit']; ?>
                    </td>
                    <td>
                        <?= $data['marketing_name']; ?>
                    </td>
                    <!--<td>
                        <?= $data['created_by_am_name']; ?>
                    </td>
                    <td>
                        <?= !empty($data['created_at']) && $data['created_at'] != '0000-00-00' ? date('m/d/Y', strtotime($data['created_at'])) : '' ?>
                    </td>-->
                    <td>
                        <div class="btn-group">
                            <?php if ($data['status'] == 'Completed' || $data['status'] == 'InProgress') { ?>
                            <a href="truck_open_sms_campaign.php?id=<?= $data['id']; ?>" class="btn btn-info mx-1">Delivery Status</a>
                            <?php } else if($data['status'] != 'InProgress'){ ?>
                                <a href="sms_campaign_edit_new.php?id=<?= $data['id']; ?>" class="btn btn-primary mx-1">Edit</a>
                            <?php } 
                            if($data['status'] != 'Completed'){ ?>
                                <a href="fetch_sms_campaigns_new.php?action=deleteSmsCampaign&id=<?= $data['id']; ?>"
                                    class="btn btn-danger mx-1"
                                    onclick="return confirm('Are You Sure You Want to Delete ?');">Delete</a>
                            <?php } ?>
                        </div>
                    </td>
                </tr>
            <?php }
        } ?>
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