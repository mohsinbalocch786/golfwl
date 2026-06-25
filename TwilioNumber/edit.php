<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

// Only managers/admins can edit Twilio numbers (shared resource)
if(!isSuperAdmin() && !isManager()){
    header("Location:list.php");
    exit;
}

$id=(int)$_GET['id'];

$msg="";

if($_POST){
    verifyCsrf();

    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $status =  mysqli_real_escape_string($conn, $_POST['status']);

    mysqli_query($conn,"UPDATE twilio_numbers SET
        phone='$phone',
        status='$status'
        WHERE id='$id'");

    header("Location:list.php");
    exit;
}

$r=mysqli_query($conn,"SELECT * FROM twilio_numbers WHERE id='$id'");
$row=mysqli_fetch_assoc($r);

if(!$row){
    header("Location:list.php");
    exit;
}

include("../layout/header.php");
include("../layout/sidebar.php");


?>



<div class="card card-primary">

<div class="card-header">
<h3 class="card-title">Edit Twilio Number</h3>
</div>

<div class="card-body">


<?php if($msg!=""){ ?>
<div class="alert alert-success"><?php echo $msg; ?></div>
<?php } ?>

<form method="post">
<?php echo csrfField(); ?>

<div class="form-group">
<label>Phone</label>
<input class="form-control" name="phone" value="<?php echo htmlspecialchars($row['phone']); ?>">
</div>

<div class="form-group">
<label>Status</label>
<select name="status" class="form-control">
    <option value="active" <?php echo $row['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
    <option value="inactive" <?php echo $row['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
</select>
</div>


<button class="btn btn-primary">Update</button>

<a href="list.php" class="btn btn-secondary">Back</a>

</form>

</div>

</div>

<?php include("../layout/footer.php"); ?>
