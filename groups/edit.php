<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

$id=(int)$_GET['id'];

$chk=mysqli_query($conn,"SELECT * FROM contact_groups WHERE id='$id'");
$group=mysqli_fetch_assoc($chk);
if(!$group){
    header("Location:list.php");
    exit;
}
assertOwnership($group);

include("../layout/header.php");
include("../layout/sidebar.php");

/* selected contacts */

$selected=[];

$m=mysqli_query($conn,"SELECT contact_id FROM contact_group_map WHERE group_id='$id'");

while($row=mysqli_fetch_assoc($m)){
$selected[]=$row['contact_id'];
}

/* contacts scoped to ownership */
$cw = ownershipWhere('');
$contacts=mysqli_query($conn,"SELECT * FROM contacts WHERE $cw ORDER BY name");
$twilio_numbers = mysqli_query($conn,"SELECT * FROM twilio_numbers WHERE status='active' ORDER BY id");


$msg="";

if($_POST){
    verifyCsrf();

$name=mysqli_real_escape_string($conn,$_POST['name']);
$twilio_num_id = isset($_POST['twilio_numbers']) && $_POST['twilio_numbers'] !== '' ? (int)$_POST['twilio_numbers'] : 'NULL';

mysqli_query($conn,"UPDATE contact_groups SET name='$name', twilio_num_id=$twilio_num_id WHERE id='$id'");

/* reset mapping */

mysqli_query($conn,"DELETE FROM contact_group_map WHERE group_id='$id'");

if(isset($_POST['contacts'])){

foreach($_POST['contacts'] as $cid){

$cid=(int)$cid;
mysqli_query($conn,"INSERT INTO contact_group_map(contact_id,group_id)
VALUES('$cid','$id')");

}

}

$msg="Group Updated";

header("Location: edit.php?id=".$id);
exit;

}
$r=mysqli_query($conn,"SELECT * FROM contact_groups WHERE id='$id'");
$group=mysqli_fetch_assoc($r);
?>

<div class="row">
<div class="col-md-8">

<div class="card card-primary">

<div class="card-header">
<h3 class="card-title">Edit Group</h3>
</div>

<div class="card-body">

<?php if($msg!=""){ ?>
<div class="alert alert-success"><?php echo $msg; ?></div>
<?php } ?>

<form method="post">
<?php echo csrfField(); ?>

<div class="form-group">
<label>Group Name</label>

<input type="text"
name="name"
class="form-control"
value="<?php echo htmlspecialchars($group['name']); ?>">
</div>

<div class="form-group">

<label>Contacts in Group</label>

<select name="contacts[]" id="contacts" class="form-control select2" multiple>

    <?php while($c=mysqli_fetch_assoc($contacts)){ ?>

        <option value="<?php echo $c['id']; ?>"
            <?php if(in_array($c['id'], $selected)) echo "selected"; ?>>

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

        <option value="<?php echo $c['id']; ?>" <?php if($c['id'] == $group['twilio_num_id']) echo "selected"; ?>>
            <?php echo htmlspecialchars($c['phone']); ?>
        </option>

    <?php } ?>

</select>

</div>

<button class="btn btn-primary">
<i class="fas fa-save"></i> Update Group
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
