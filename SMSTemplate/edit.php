<?php

include("../config/db.php");
include("../layout/header.php");
include("../layout/sidebar.php");

$id=$_GET['id'];



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
        if(!empty($_POST['old_image'])){
            $imageName=$_POST['old_image'];
        }
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image'];
            $file_name = $file['name'];
            $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);            
            $imageName = uniqid() . '_' . time() . '.' . $file_extension;//basename($file['name']);
            $uploadDir = 'smsimage/'; // Ensure this directory exists and is writable
            $uploadFile = $uploadDir . $imageName;
            move_uploaded_file($file['tmp_name'], $uploadFile);            
        }

mysqli_query($conn,"UPDATE email_templates SET `name` = '" . $name . "', `status` = '" . $status . "', `template` = '" . $template . "', `image` = '" . $imageName . "' WHERE id='$id'");

$msg="SMS/MMS Template Updated";

}
$r=mysqli_query($conn,"SELECT * FROM email_templates WHERE id='$id'");
$row=mysqli_fetch_assoc($r);

?>

<div class="row">
<div class="col-md-10">

<div class="card card-primary">

<div class="card-header">
<h3 class="card-title">Edit SMS/MMS Template</h3>
</div>

<div class="card-body">

<?php if($msg!=""){ ?>
<div class="alert alert-success"><?php echo $msg; ?></div>
<?php } ?>

<form method="post"  enctype="multipart/form-data">


            <div class="col-md-6">
                <div class="form-group">
                    <label for="" class="form-label col-sm-3">Name<span class="text-danger">*</span> : </label>
                    <input type="text" name="name" class="form-control" value="<?php echo $row['name']; ?>" required>
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
                    <option value="1"  <?php if($row['status'] == '1'){echo 'selected=selected';}?>>Active</option>
                    <option value="0" <?php if($row['status'] == '0'){echo 'selected=selected';}?>>Inactive</option>
                </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="" class="form-label col-sm-3">Template<span class="text-danger">*</span> : </label>
                    <textarea name="template" id="template" class="form-control" maxlength="160" required><?php echo $row['template']; ?></textarea>
                    <span class="hint pull-right">Max Char : 160</span>
                </div>
            </div>
             <div class="col-md-8">
                <div class="form-group">
                    <label for="" class="form-label col-sm-2">Note : </label>
                    <span class="col-sm-9"> Nationwide. To get started, Reply YES to receive a call. Reply STOP to opt out.</span>
                </div>
            </div>
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
        <div class="col-md-6">
            <div class="form-group">
                <label for="" class="form-label col-sm-3">Image : </label>
                <input type="file" id="image" accept="image/*" name="image" />
                <input type="hidden" name="old_image" value="<?php echo $row['image'];?>" />
                <img id="preview" src="<?php echo $uploadDir . $oldImagePath ?>" style="width: 175px;height: 150px;" class="<?php echo $class;?>">
                <br>
                Adding an attachment will convert the SMS text into an MMS.
            </div>
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
<i class="fas fa-save"></i> Update Template
</button>

<a href="list.php" class="btn btn-secondary">Back</a>

</form>

</div>
</div>
</div>
</div>
<!-- 1. jQuery FIRST -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/scroller/2.0.5/js/dataTables.scroller.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.1.0/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.1.0/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.1.0/js/buttons.print.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.3/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.1.0/css/buttons.dataTables.min.css">
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