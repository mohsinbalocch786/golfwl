<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

// Only managers/admins can add Twilio numbers (shared resource)
if(!isSuperAdmin() && !isManager()){
    header("Location:list.php");
    exit;
}

$msg="";

if($_POST){
    verifyCsrf();

    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $status =  mysqli_real_escape_string($conn, $_POST['status']);

    list($owner_uid, $owner_mid) = ownershipStamp();

    mysqli_query($conn,"INSERT INTO twilio_numbers
        (phone,status,created_at,user_id,manager_id)
        VALUES
        ('$phone','$status', '$currentTime', '$owner_uid', '$owner_mid')");

    header("Location:list.php");
    exit;
}
include("../layout/header.php");
include("../layout/sidebar.php");
?>



<div class="card card-primary">

<div class="card-header">
<h3 class="card-title">New Twilio Number</h3>
</div>

<div class="card-body">

<?php if($msg!=""){ ?>

<div class="alert alert-success">
<?php echo $msg; ?>
</div>

<?php } ?>

<form method="post">
<?php echo csrfField(); ?>

<div class="form-group">
<label>Phone</label>
<input type="text" name="phone" class="form-control">
</div>

<div class="form-group">
<label>Status</label>
<select name="status" class="form-control">
    <option value="active">Active</option>
    <option value="inactive">Inactive</option>
</select>
</div>


<button type="submit" class="btn btn-primary">
<i class="fas fa-save"></i> Save Twilio Number
</button>
<a href="list.php" class="btn btn-secondary">Back</a>

</form>

</div>

</div>

<?php include("../layout/footer.php"); ?>
