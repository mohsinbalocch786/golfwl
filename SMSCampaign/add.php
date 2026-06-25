<?php

include("../config/db.php");
include("../layout/header.php");
include("../layout/sidebar.php");

$msg="";

if($_POST){

$name=mysqli_real_escape_string($conn,$_POST['name']);
$type=$_POST['type'];
// $subject=mysqli_real_escape_string($conn,$_POST['subject']);
$content=mysqli_real_escape_string($conn,$_POST['content']);
$template = mysqli_real_escape_string($conn,$_POST['template']);
// $note = mysqli_real_escape_string($conn,$_POST['note']);
$status=$_POST['status'];
$type = 'SMS';
$imageName = '';
// echo "<pre>";print_r($_FILES);die;
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['image'];
    $file_name = $file['name'];
    $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);            
    $imageName = uniqid() . '_' . time() . '.' . $file_extension;// basename($file['name']);
    $uploadDir = 'smsimage/';
    $uploadFile = $uploadDir . $imageName;
    move_uploaded_file($file['tmp_name'], $uploadFile);            
}


mysqli_query($conn,"INSERT INTO email_templates
(`name`,  `template`, `image`,`type`, `status`, `note`,created_at)
VALUES
('$name', '$template', '$imageName', '$type', '$status',  '$currentTime')");

$msg="SMS/MMS Template Saved";

}

?>

<div class="row">
<div class="col-md-10">

<div class="card card-primary">

<div class="card-header">
<h3 class="card-title">Add SMS/MMS Template</h3>
</div>

<div class="card-body">

<?php if($msg!=""){ ?>
<div class="alert alert-success"><?php echo $msg; ?></div>
<?php } ?>

<form method="post" enctype="multipart/form-data">


<input type="hidden" name="mode" value="ADD">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="" class="form-label col-sm-3">Name<span class="text-danger">*</span> : </label>
                    <input type="text" name="name" class="form-control" value="" required>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="" class="form-label col-sm-3">Contact Group<span class="text-danger">*</span> : </label>
                    <select class="form-control" name="group_id" id="group_id"  required>
                        <option>Please Select</option>
                         <?php
                            $result = mysqli_query($conn,"SELECT id,name FROM contact_groups ORDER BY name");
                        
                            while($row = mysqli_fetch_assoc($result)){
                            ?>
                                <option value="<?= $row['id']; ?>">
                                    <?= $row['name']; ?>
                                </option>
                            <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="" class="form-label col-sm-3">Contact Number<span class="text-danger">*</span> : </label>
                    <select class="form-control" name="contact_id" id="contact_id" required>
                    </select>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="form-group">
                    <label for="" class="form-label col-sm-3">Template<span class="text-danger">*</span> : </label>
                    <select name="template_id" id="template_id" class="form-control">
                        <option value="">Please Select</option>
                    
                        <?php
                        $sql = "SELECT id, name
                                FROM email_templates
                                WHERE status = 1
                                AND deleted = 0
                                AND type = 'SMS'
                                ORDER BY name ASC";
                    
                        $result = mysqli_query($conn, $sql);
                    
                        while ($row = mysqli_fetch_assoc($result)) {
                        ?>
                            <option value="<?= $row['id']; ?>">
                                <?= htmlspecialchars($row['name']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="" class="form-label col-sm-3">Start Date<span class="text-danger">*</span> : </label>
                    <input type="date" name="scheduled" class="form-control" onchange="getTime(this.value)" min="<?php echo date("Y-m-d")?>" required>
                    <!-- <span class="hint pull-right">Keep 15 Min Interval</span> -->
                </div>
            </div>
            <div class="col-md-8">
                <div class="form-group">
                    <input type="hidden" name="time" id="time" value="">
                    <label class="form-label col-sm-3">Select PST Time<span class="text-danger">*</span>:</label>
                    <!-- <select id="time" name="time" class="form-control" required>
                    
                    </select> -->
                    <div id="timeSlots" style="/*min-height:15em;*/">
        
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                <label for="" class="form-label col-sm-3">Repeat Days<span class="text-danger">*</span> : <br/>
                    Check All<input type="checkbox" id="checkALL" class="form-check-input" value="Check ALL" style="margin-left: 1em;" onclick="$('.form-check-input:checkbox').prop('checked', this.checked);$('.form-check-input:checkbox').attr('checked', this.checked);">
                    </label>
                    <div class="d-inline-flex" style="margin-left: 3%;">
                        <input type="checkbox" name="repeat_days[]" id="MON" class="form-check-input d-inline-block"
                            value="MON">
                        <label for="MON" class="d-inline-block mr-4">MON</label>
                    </div>
                    <div class="d-inline-flex">
                        <input type="checkbox" name="repeat_days[]" id="TUE" class="form-check-input d-inline-block"
                            value="TUE">
                        <label for="TUE" class="d-inline-block mr-4">TUE</label>
                    </div>
                    <div class="d-inline-flex">
                        <input type="checkbox" name="repeat_days[]" id="WED" class="form-check-input d-inline-block"
                            value="WED">
                        <label for="WED" class="d-inline-block mr-4">WED</label>
                    </div>
                    <div class="d-inline-flex">
                        <input type="checkbox" name="repeat_days[]" id="THU" class="form-check-input d-inline-block"
                            value="THU">
                        <label for="THU" class="d-inline-block mr-4">THU</label>
                    </div>
                    <div class="d-inline-flex">
                        <input type="checkbox" name="repeat_days[]" id="FRI" class="form-check-input d-inline-block"
                            value="FRI">
                        <label for="FRI" class="d-inline-block mr-4">FRI</label>
                    </div>
                    <div class="d-inline-flex">
                        <input type="checkbox" name="repeat_days[]" id="SAT" class="form-check-input d-inline-block"
                            value="SAT">
                        <label for="SAT" class="d-inline-block mr-4">SAT</label>
                    </div>
                    <div class="d-inline-flex">
                        <input type="checkbox" name="repeat_days[]" id="SUN" class="form-check-input d-inline-block"
                            value="SUN">
                        <label for="SUN" class="d-inline-block mr-4">SUN</label>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="" class="form-label col-sm-3">Limit : </label>
                    <select name="send_limit" id="send_limit" class="form-control">
                        <option value="250">250</option>
                        <option value="500">500</option>
                        <option value="750">750</option>
                        <option value="1000" selected>1000</option>
                        <option value="1250">1250</option>
                        <option value="1500">1500</option>
                        <option value="1750">1750</option>
                        <option value="2000">2000</option>
                        <option value="2250">2250</option>
                        <option value="2500">2500</option>
                    </select>
                </div>
            </div>
            <div class="col-md-6 hide d-none">
                <div class="form-group">
                    <label for="" class="form-label col-sm-3">Status : </label>
                    <select name="status" id="status" class="form-control">
                        <option value="Pending">Pending</option>
                        <option value="InProgress">InProgress</option>
                        <option value="Pause">Pause</option>
                        <option value="Completed">Completed</option>
                    </select><br>
                    <span class="hint" style="margin-left: 25%;">EMail will only start sending when status is
                        <b>InProgress</b></span>
                </div>
            </div>
            
            



<button class="btn btn-primary">
<i class="fas fa-save"></i> Save Template
</button>

<a href="list.php" class="btn btn-secondary">Back</a>

</form>

</div>
</div>
</div>
</div>
<!-- 1. jQuery FIRST -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- 2. Bootstrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- 3. DataTables -->
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>

<!-- 4. Select2 -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js"></script>

<script>
tinymce.init({
selector:'#editor',
height:350,
plugins:'link image code lists table',
toolbar:'undo redo | bold italic | alignleft aligncenter alignright | bullist numlist | link image | code'
});
</script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function () {
            
            // $('#time').select2();
            $('#marketing_id').select2();

            $('#campaign_form').on('submit', function () {

                if ($('#am_id').val() == '' || $('#am_id').val() == 'Please Select') {
                    toastr.error('Please fill AM field');
                    return false;
                }

                if ($('input[name="repeat_days[]"]:checked').length == 0) {
                    toastr.error('Please select at least one repeat day');
                    return false;
                }

                if ($('#marketing_id').val() == '' || $('#marketing_id').val() == 'Please Select') {
                    toastr.error('Please fill Import field');
                    return false;
                }

                if ($('#time').val() == '') {
                    toastr.error('Please select PST time');
                    return false;
                }

                if ($('#twilio_number').val() == '' || $('#twilio_number').val() == 'Please Select') {
                    toastr.error('Please select twilio number');
                    return false;
                }
                
                this.submit();
                return true;
            });

            $('#am_id').select2({
                ajax: {
                    url: 'searchMembershipwithNumber.php',
                    dataType: 'json',
                    data: function (params) {
                        var query = {
                            search: params.term,
                            page: params.page || 1,
                        }
                        return query;
                    },

                    processResults: function (data, params) {
                        params.page = params.page || 1;
                        return {
                            results: data.results,
                            pagination: {
                                more: (params.page * 10) < data.count_filtered
                            }
                        };
                    },

                    minimumInputLength: 2,
                    placeholder: 'Search for a User By Name',
                    select: function (e) {
                        console.log("NP");
                    }
                }
            });

            $('#addMarketingModal').on('shown.bs.modal', function () {
                $('#assign_am').select2({
                    dropdownParent: $('#addMarketingModal'),
                    ajax: {
                        url: 'searchMembership.php',
                        dataType: 'json',
                        data: function (params) {
                            var query = {
                                search: params.term,
                                page: params.page || 1,
                            }
                            return query;
                        },

                        processResults: function (data, params) {
                            params.page = params.page || 1;
                            return {
                                results: data.results,
                                pagination: {
                                    more: (params.page * 10) < data.count_filtered
                                }
                            };
                        },

                        minimumInputLength: 2,
                        placeholder: 'Search for a User By Name',
                        select: function (e) {
                            console.log("NP");
                        }
                    }
                });
            });

            $('#addMarketingModal').on('hidden.bs.modal', function () {
                $('#assign_am').select2('destroy');
            });
            
            
            
            
        });
        $('#group_id').change(function(){
            
                var group_id = $(this).val();
            
                $.ajax({
                    url:'get_contacts.php',
                    type:'POST',
                    data:{group_id:group_id},
                    success:function(response){
                        $('#contact_id').html(response);
                    }
                });
            
            });
    </script>
    <script>
        function showAddMarketingModal() {
            $('#name').val('');
            $('#import_data').val('');
            $('#assign_am').val('Please Select');
            $('#assign_am').trigger('change');
            $('#addMarketingModal').modal('show');
        }

        function getTime(date) {
            $.ajax({
                method: "GET",
                url: "get_campaign_time.php",
                data: {
                    date: date,
                    id: 0,
                    c_type: 'SMS'
                },
                beforeSend: function(){
                    $('#timeSlots').html('<center><i class="fas fa-spinner fa-spin"></i></center>');
                },
                success: function (data) {
                    $('#timeSlots').html(data);
                },
                error: function (error) {
                    toastr.error(error);
                },
            });
        }

        function selectTime(button, time) {
            $('#timeSlots .btn').removeClass('btn-secondary').addClass('btn-outline-secondary');
            
            $(button).removeClass('btn-outline-secondary').addClass('btn-secondary');
            
            $('#time').val(time);
        }
    </script>
    <script>
        function save() {
            if ($('#assign_am').val() == '' || $('#assign_am').val() == 'Please Select' || $('#name').val() == '' || $('#import_data').val() == '') {
                toastr.error('Please fill required field');
                return false;
            }

            var form = $('#addForm')[0];
            var formData = new FormData(form);

            $.ajax({
                url: 'fetch_marketings.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function () {
                    $('#saveBtn').html('Updating <i class="fas fa-spinner fa-spin"></i>');
                    $('#saveBtn').prop('disabled', true);
                },
                success: function (response) {
                    $('#saveBtn').html('Save');
                    $('#saveBtn').prop('disabled', false);
                    var res = JSON.parse(response);
                    if (res.status == true) {
                        toastr.success(res.message);
                        $('#addMarketingModal').modal('hide');
                        var newOption = $('<option></option>')
                            .val(res.data.id)
                            .text(res.data.name);
                        $('#marketing_id').append(newOption);
                        $('#marketing_id').val(res.data.id).trigger('change');
                    } else if (res.status == false) {
                        toastr.error(res.message);
                    } else {
                        toastr.error('OOPS! Something went wrong');
                    }
                },
                error: function (error) {
                    $('#saveBtn').html('Save');
                    $('#saveBtn').prop('disabled', false);
                    toastr.error('OOPS! Something went wrong');
                }
            });
        }

        function getTwilioNumbers(value){
            var am = value.value;
            $.ajax({
                url: 'get_twilio_numbers.php',
                type: 'GET',
                data: {
                    am : am
                },
                success: function (response) {
                    $('#twilio_number').empty();
                    //$('#twilio_number').append('<option>Please Select</option>');
                    $('#twilio_number').append(response);
                },
            });
        }
    </script>

<?php include("../layout/footer.php"); ?>