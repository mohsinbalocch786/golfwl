<?php

include("../config/db.php");
include("../config/auth.php");
requireLogin();

$msg="";
if($_POST){
    verifyCsrf();
$name=mysqli_real_escape_string($conn,$_POST['name']);
$type=$_POST['type'];
$subject=mysqli_real_escape_string($conn,$_POST['subject']);
$content=mysqli_real_escape_string($conn,$_POST['content']);
$status=$_POST['status'];

// visibility: regular users can only create private templates;
// managers/admins can choose team/global
$visibility = isset($_POST['visibility']) ? $_POST['visibility'] : 'private';
if(!in_array($visibility, ['private','team','global'])){
    $visibility = 'private';
}
if(!isSuperAdmin() && !isManager()){
    $visibility = 'private';
}

$imageName = '';
$uploadError = '';
if($_POST['type'] == 'sms'){
    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $imageName = secureImageUpload($_FILES['image'], 'smsimage/', $uploadError);
        if ($uploadError) {
            $msg = $uploadError;
            // fall through to re-render form with error
        }
    }
}

// ownership stamp
list($owner_uid, $owner_mid) = ownershipStamp();

 $sql = "INSERT INTO templates
(name,type,subject,content,status, image, created_at, user_id, manager_id, visibility)
VALUES
('$name','$type','$subject','$content','$status', '$imageName', '$currentTime', '$owner_uid', '$owner_mid', '$visibility')";
mysqli_query($conn, $sql);

$msg="Template Saved";
header("Location:list.php");
exit;
}
include("../layout/header.php");
include("../layout/sidebar.php");
?>
<div class="row">
<div class="col-md-10">

<div class="card card-primary">

<div class="card-header">
<h3 class="card-title">Add Template</h3>
</div>

<div class="card-body">

<?php if($msg!=""){ ?>
<div class="alert alert-success"><?php echo $msg; ?></div>
<?php } ?>

<form method="post" enctype="multipart/form-data">
<?php echo csrfField(); ?>

<div class="form-group">
<label>Template Name</label>
<input type="text" name="name" class="form-control" required>
</div>

<div class="form-group">
<label>Type</label>

<select id="type" name="type" class="form-control">
<option value="email">Email</option>
<option value="sms">SMS</option>
</select>

</div>

<div class="form-group">
<label>Subject</label>
<input type="text" name="subject" class="form-control">
</div>

<div class="form-group">
<label>Status</label>

<select name="status" class="form-control">
<option value="active">Active</option>
<option value="inactive">Inactive</option>
<option value="archived">Archived</option>
</select>

</div>

<?php if(isSuperAdmin() || isManager()){ ?>
<div class="form-group">
<label>Visibility</label>

<select name="visibility" class="form-control">
<option value="private">Private (only me)</option>
<option value="team">Team (my team)</option>
<option value="global" selected>Global (everyone)</option>
</select>
<small class="form-text text-muted">
    Controls who else can see and use this template.
</small>

</div>
<?php } else { ?>
<input type="hidden" name="visibility" value="private">
<?php } ?>

<div class="form-group">
<label>Content</label>

<textarea name="content" id="editor" class="form-control" rows="10"></textarea>



</div>
<div id="smsFields" class="d-none">

    <div class="col-md-6">
        <div class="form-group">
            <label class="form-label ">
                Note :
            </label>

            <span class="hint pull-right">
                All Template will Append, Reply YES to receive a call.
                Reply STOP to opt out.
            </span>
        </div>
    </div>

    <div class="col-md-6">
        <div class="form-group">
            <label class="form-label">
                Image :
            </label>

            <input type="file" id="image" accept=".jpg,.jpeg,.png,.gif" name="image">
            <br><span class="hint pull-right">Adding an attachment will convert the SMS text into an MMS.</span>
            <br><span><i class="fa fa-exclamation-circle"></i> Supported file types: jpeg. png and gif</span> 
            <br><span><i class="fa fa-exclamation-circle"></i> Total size should be less than 600KB</span><br>
            <img id="preview"
                 src=""
                 style="width:175px;height:150px;"
                 class="d-none">
        </div>
         
    </div>

</div>
<strong>
    <h5>Suggested Tags :- </h5>
    <p>{{ NAME }}, {{ EMAIL }}, {{ PHONE }}</p>
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
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js"></script>

<script>
tinymce.init({
selector:'#editor',
height:350,
plugins:'link image code lists table',
toolbar:'undo redo | bold italic | alignleft aligncenter alignright | bullist numlist | link image | code'
});
</script>

<script>
    
    $(document).ready(function () {
        
        
        $("#image").on("change", function () {

    var file = this.files[0];

    if (!file) {
        return;
    }

    // Allowed file types
    var allowedTypes = [
        "image/jpeg",
        "image/jpg",
        "image/png",
        "image/gif"
    ];

    // Validate file type
    if ($.inArray(file.type, allowedTypes) === -1) {
        alert("Only JPG, JPEG, PNG and GIF files are allowed.");
        $(this).val('');
        $("#preview").hide().attr("src", "");
        return false;
    }

    // Validate file size (600 KB)
    var maxSize = 600 * 1024; // 600 KB

    if (file.size > maxSize) {
        alert("File size must be less than 600 KB.");
        $(this).val('');
        $("#preview").hide().attr("src", "");
        return false;
    }

    // Preview image
    var reader = new FileReader();

    reader.onload = function (e) {
        $("#preview")
            .attr("src", e.target.result)
            .show();
    };

    reader.readAsDataURL(file);
});
        
        
        

    function toggleSmsFields() {

        if ($('#type').val() == 'sms') {

            $('#smsFields').removeClass('d-none');

            //$('#image').prop('required', true);

        } else {

            $('#smsFields').addClass('d-none');

           // $('#image').prop('required', false);
        }
    }

    toggleSmsFields();

    $('#type').on('change', function () {
        
        toggleSmsFields();
    });

});

</script>

<?php include("../layout/footer.php"); ?>
