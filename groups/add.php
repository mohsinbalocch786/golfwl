<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

include("../layout/header.php");
include("../layout/sidebar.php");

$msg="";

// contacts scoped to ownership (so you only assign your own contacts to your groups)
$cw = ownershipWhere('');
$contacts=mysqli_query($conn,"SELECT * FROM contacts WHERE $cw ORDER BY name");

$twilio_numbers = mysqli_query($conn,"SELECT * FROM twilio_numbers WHERE status='active' ORDER BY id");

if($_POST){
    verifyCsrf();

$name=mysqli_real_escape_string($conn,$_POST['name']);
$twilio_num_id = isset($_POST['twilio_numbers']) && $_POST['twilio_numbers'] !== '' ? (int)$_POST['twilio_numbers'] : 'NULL';

list($owner_uid, $owner_mid) = ownershipStamp();

mysqli_query($conn,"INSERT INTO contact_groups(name, twilio_num_id, created_at, user_id, manager_id)
VALUES('$name', $twilio_num_id, '$currentTime', '$owner_uid', '$owner_mid')");

$group_id=mysqli_insert_id($conn);

/* save contacts */

if(isset($_POST['contacts'])){

foreach($_POST['contacts'] as $cid){

$cid = (int)$cid;
mysqli_query($conn,"INSERT INTO contact_group_map(contact_id,group_id)
VALUES('$cid','$group_id')");

}

}

$msg="Group Created Successfully";

}

?>

<div class="row">
<div class="col-md-8">

<div class="card card-primary">

<div class="card-header">
<h3 class="card-title">Add Group</h3>
</div>

<div class="card-body">

<?php if($msg!=""){ ?>
<div class="alert alert-success"><?php echo $msg; ?></div>
<?php } ?>

<form method="post">
<?php echo csrfField(); ?>

<div class="form-group">
<label>Group Name</label>
<input type="text" name="name" class="form-control" required>
</div>

<div class="form-group">

<label>Select Contacts</label>

<select name="contacts[]" id="contacts" class="form-control select2" multiple>

    <?php while($c=mysqli_fetch_assoc($contacts)){ ?>

        <option value="<?php echo $c['id']; ?>">
            <?php echo htmlspecialchars($c['name']); ?> (<?php echo htmlspecialchars($c['email']); ?>)
        </option>

    <?php } ?>

</select>

</div>

<div class="form-group">

<label>Select Twilio Numbers</label>

<select name="twilio_numbers" id="twilio_numbers" class="form-control select2" >
    <option value="">-- Select Twilio Number --</option>
    <?php while($c=mysqli_fetch_assoc($twilio_numbers)){ ?>

        <option value="<?php echo $c['id']; ?>">
            <?php echo htmlspecialchars($c['phone']); ?>
        </option>

    <?php } ?>

</select>

</div>

<button class="btn btn-primary">
<i class="fas fa-save"></i> Save Group
</button>

<a href="list.php" class="btn btn-secondary">Back</a>

</form>

</div>
</div>

</div>
</div>

<?php include("../layout/footer.php"); ?>

<script>
$(document).ready(function() {
    $('select[name="contacts[]"]').select2();
});
</script>
