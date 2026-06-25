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
            <!-- <div class="col-md-6">
                <div class="form-group">
                    <label for="" class="form-label col-sm-3">AM : </label>
                    <select class="form-control" name="assign_am" id="assign_am"  style="width:35%" >
                        <option>Please Select</option>
                    </select>
                </div>
            </div> -->
            <div class="col-md-6">
                <div class="form-group">
                    <label for="" class="form-label col-sm-3">Status<span class="text-danger">*</span> :</label>            
                    <select class="form-control" name="status" id="status" style="width:35%" required>
                       <option value="">Please Select</option>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="" class="form-label col-sm-3">Template<span class="text-danger">*</span> : </label>
                    <textarea name="template" id="template" class="form-control" maxlength="160" required></textarea>
                    <span class="hint pull-right">Max Char : 160</span>
                </div>
            </div>
             <div class="col-md-8">
                <div class="form-group">
                    <label for="" class="form-label col-sm-2">Note : </label>
                    <spna class="form-label col-sm-9">Nationwide. To get started, Reply YES to receive a call. Reply STOP to opt out.</spna>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="" class="form-label col-sm-3">Image : </label>
                    <input type="file" id="image" accept="image/*" name="image" >
                    <img id="preview" src="" style="width: 175px;height: 150px;" class="d-none hide">
                </div>
                <br>
                Adding an attachment will convert the SMS text into an MMS.
            </div>
            <strong>
                <h5>Suggested Tags :- </h5>
                <p>{{ USDOT }}, {{ LEGALNAME }}, {{ CONTACT1 }}, {{ CONTACT2 }}, {{ ADDRESS }}, {{ CITY }}, {{ STATE }}, {{ ZIP  }}, {{ PHONE }}, {{ EMAIL  }}</p>
                 <p id="portal">
                    {{OO_NAME}}, {{CELL_PHONE}}, {{AM}}, {{AM_PHONE}}, {{AM_EMAIL}}, {{USERNAME}}
                </p>
                <!-- , {{PASSWORD}}, {{AM_SIGN}}, {{COMPANY_NAME}}, {{OWNER_NAME}}, {{DATE_HUMAN_READBLE}}, {{DATE_MMDDYY}}, {{MAIL_ADDRESS1}} , {{MAIL_CITY1}}, {{MAIL_STATE1}}, {{MAIL_ZIP1}}, {{PHY_ADDRESS1}} , {{PHY_CITY1}}, {{PHY_STATE1}}, {{PHY_ZIP1}} -->
            </strong>



<button class="btn btn-primary">
<i class="fas fa-save"></i> Save Template
</button>

<a href="list.php" class="btn btn-secondary">Back</a>

</form>

</div>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js"></script>

<script>
tinymce.init({
selector:'#editor',
height:350,
plugins:'link image code lists table',
toolbar:'undo redo | bold italic | alignleft aligncenter alignright | bullist numlist | link image | code'
});
</script>

<?php include("../layout/footer.php"); ?>