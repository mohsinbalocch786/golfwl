<?php

include("../config/db.php");
include("../config/auth.php");
requireLogin();

$id=(int)$_GET['id'];

$chk=mysqli_query($conn,"SELECT * FROM templates WHERE id='$id'");
$row=mysqli_fetch_assoc($chk);
if(!$row){
    header("Location:list.php");
    exit;
}
assertOwnership($row);

include("../layout/header.php");
include("../layout/sidebar.php");

$msg="";

if($_POST){
    verifyCsrf();

$name=mysqli_real_escape_string($conn,$_POST['name']);
$type=$_POST['type'];
$subject=mysqli_real_escape_string($conn,$_POST['subject']);
$content=mysqli_real_escape_string($conn,$_POST['content']);
$status=$_POST['status'];

$visibility = isset($_POST['visibility']) ? $_POST['visibility'] : (isset($row['visibility']) ? $row['visibility'] : 'private');
if(!in_array($visibility, ['private','team','global'])){
    $visibility = $row['visibility'];
}
if(!isSuperAdmin() && !isManager()){
    $visibility = $row['visibility']; // regular users cannot change visibility
}


$imageName = '';
$uploadError = '';
if($_POST['type'] == 'sms'){
        if(!empty($_POST['old_image'])){
            $imageName=$_POST['old_image'];
        }
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $newImage = secureImageUpload($_FILES['image'], 'smsimage/', $uploadError);
            if ($uploadError) {
                $msg = $uploadError;
            } else {
                $imageName = $newImage;
            }
        }
}

mysqli_query($conn,"UPDATE templates SET
name='$name',
type='$type',
subject='$subject',
content='$content',
status='$status',
image = '$imageName',
visibility = '$visibility'
WHERE id='$id'");

$msg="Template Updated";

// refresh row after update
$chk=mysqli_query($conn,"SELECT * FROM templates WHERE id='$id'");
$row=mysqli_fetch_assoc($chk);

}

?>

<div class="row">
<div class="col-md-10">

<div class="card card-primary">

<div class="card-header">
<h3 class="card-title">Edit Template</h3>
</div>

<div class="card-body">

<?php if($msg!=""){ ?>
<div class="alert alert-success"><?php echo $msg; ?></div>
<?php } ?>

<form method="post" enctype="multipart/form-data">
<?php echo csrfField(); ?>

<div class="form-group">
<label>Template Name</label>
<input type="text"
name="name"
class="form-control"
value="<?php echo htmlspecialchars($row['name']); ?>">
</div>

<div class="form-group">
<label>Type</label>

<select id="type" name="type" class="form-control">

<option value="email" <?php if($row['type']=="email") echo "selected"; ?>>
Email
</option>

<option value="sms" <?php if($row['type']=="sms") echo "selected"; ?>>
SMS
</option>

</select>

</div>

<div class="form-group">
<label>Subject</label>

<input type="text"
name="subject"
class="form-control"
value="<?php echo htmlspecialchars(isset($row['subject']) ? $row['subject'] : ''); ?>">

</div>

<div class="form-group">
<label>Status</label>

<select name="status" class="form-control">

<option value="active" <?php if($row['status']=="active") echo "selected"; ?>>
Active
</option>

<option value="inactive" <?php if($row['status']=="inactive") echo "selected"; ?>>
Inactive
</option>

<option value="archived" <?php if($row['status']=="archived") echo "selected"; ?>>
Archived
</option>

</select>

</div>

<?php if(isSuperAdmin() || isManager()){ ?>
<div class="form-group">
<label>Visibility</label>

<?php $vis = isset($row['visibility']) ? $row['visibility'] : 'global'; ?>
<select name="visibility" class="form-control">
<option value="private" <?php if($vis=="private") echo "selected"; ?>>Private (only me)</option>
<option value="team" <?php if($vis=="team") echo "selected"; ?>>Team (my team)</option>
<option value="global" <?php if($vis=="global") echo "selected"; ?>>Global (everyone)</option>
</select>
<small class="form-text text-muted">
    Controls who else can see and use this template.
</small>

</div>
<?php } else { ?>
<input type="hidden" name="visibility" value="<?php echo htmlspecialchars(isset($row['visibility']) ? $row['visibility'] : 'private'); ?>">
<?php } ?>

<div class="form-group">

<label>Content</label>

<textarea name="content" id="editor" class="form-control" rows="10">
<?php echo isset($row['content']) ? $row['content'] : ''; ?>
</textarea>

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
            <label class="form-label">
                Image :
            </label>

            <input type="file" id="image" accept=".jpg,.jpeg,.png,.gif" name="image">
            <br><span class="hint pull-right">Adding an attachment will convert the SMS text into an MMS.</span>
            <br><span><i class="fa fa-exclamation-circle"></i> Supported file types: jpeg. png and gif</span> 
            <br><span><i class="fa fa-exclamation-circle"></i> Total size should be less than 600KB</span><br>
             <input type="hidden" id="old_image" name="old_image" value="<?php echo $row['image'];?>" />
               
               <?php  if($row['image'] != ''){ ?>
                 <div id="image-container-<?php echo $row['id']; ?>">
                    <img id="preview" src="<?php echo $uploadDir . $oldImagePath ?>" style="width: 175px;height: 150px;" class="<?php echo $class;?> image-preview">
                
                    <br><br>
                
                    <button
                        type="button"
                        class="btn btn-danger btn-sm remove-image"
                        data-id="<?php echo $row['id']; ?>">
                        <i class="fa fa-trash"></i> Remove Image
                    </button>
                
                </div> <?php } ?>
        </div>
         
    </div>

</div>



<strong>
    <h5>Suggested Tags :- </h5>
    <p>{{ NAME }}, {{ EMAIL }}, {{ PHONE }}</p>
</strong>
<button class="btn btn-primary">
<i class="fas fa-save"></i> Update Template
</button>

<a href="list.php" class="btn btn-secondary">Back</a>

</form>


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

   setTimeout(function() {
    console.log('2');
   toggleSmsFields();
}, 500);

    $('#type').on('change', function () {
        
        toggleSmsFields();
    });
    
    
    $(document).on('click', '.image-preview', function () {

    var imageSrc = $(this).attr('src');

    $('#modalImage').attr('src', imageSrc);

    $('#imageModal').modal('show');
});


$(document).on('click', '.remove-image', function () {

    var id = $(this).data('id');
    var container = $('#image-container-' + id);

    if (confirm('Are you sure you want to delete this image?')) {

        $.ajax({
            url: 'delete_image.php',
            type: 'POST',
            data: {
                id: id
            },
            success: function (response) {

                if ($.trim(response) == 'success') {

                    container.fadeOut(300, function () {
                        $(this).remove();
                        $("#old_image").val('');
                    });

                } else {
                    alert('Unable to delete image.');
                }

            }
        });

    }

});

});

</script>

<?php include("../layout/footer.php"); ?>
