<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

$id=(int)$_GET['id'];

// ownership check
$chk=mysqli_query($conn,"SELECT * FROM contacts WHERE id='$id'");
$row=mysqli_fetch_assoc($chk);
if(!$row){
    header("Location:list.php");
    exit;
}
assertOwnership($row);

$msg="";

if($_POST){
    verifyCsrf();

    $name  = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);

    $group_ids = isset($_POST['group_id']) ? $_POST['group_id'] : [];

    $ownerSql = "";
    if((isManager() || isSuperAdmin()) && !empty($_POST['owner_id'])){
        $owner_id = (int)$_POST['owner_id'];
        $ownerSql = ", user_id='$owner_id'";
    }

    // 1. Update contact
    mysqli_query($conn,"UPDATE contacts SET
        name='$name',
        email='$email',
        phone='$phone'
        $ownerSql
        WHERE id='$id'");

    // 2. Delete old mappings
    mysqli_query($conn,"DELETE FROM contact_group_map WHERE contact_id='$id'");

    // 3. Insert new mappings
    if(!empty($group_ids)){

        foreach($group_ids as $gid){

            $gid = intval($gid);
            if($gid > 0){

                mysqli_query($conn,"INSERT INTO contact_group_map
                    (contact_id, group_id)
                    VALUES
                    ('$id','$gid')");
            }
        }
    }

    header("Location:list.php");
    exit;
}

include("../layout/header.php");
include("../layout/sidebar.php");

// Groups scoped to ownership
$group_where = ownershipWhere('');
$gq = mysqli_query($conn, "SELECT id, name FROM contact_groups WHERE $group_where ORDER BY name");

$selected = [];
$q = mysqli_query($conn, "SELECT group_id FROM contact_group_map WHERE contact_id='$id'");
while($gr = mysqli_fetch_assoc($q)){
    $selected[] = $gr['group_id'];
}

$members = (isManager() || isSuperAdmin()) ? teamMembers($conn) : [];
?>



<div class="card card-primary">

<div class="card-header">
<h3 class="card-title">Edit Contact</h3>
</div>

<div class="card-body">


<?php if($msg!=""){ ?>
<div class="alert alert-success"><?php echo $msg; ?></div>
<?php } ?>

<form method="post">
<?php echo csrfField(); ?>

<div class="form-group">
<label>Name</label>
<input class="form-control" name="name" value="<?php echo htmlspecialchars($row['name']); ?>">
</div>

<div class="form-group">
<label>Email</label>
<input class="form-control" name="email" value="<?php echo htmlspecialchars($row['email']); ?>">
</div>

<div class="form-group">
<label>Phone</label>
<input class="form-control" name="phone" value="<?php echo htmlspecialchars($row['phone']); ?>">
</div>

<!-- ✅ Group Select -->
<div class="form-group">
<label>Group</label>
<select name="group_id[]" class="form-control select2" multiple>
    <?php
    while($g = mysqli_fetch_assoc($gq)){
        $selected_attr = in_array($g['id'], $selected) ? 'selected' : '';
        echo '<option value="'.$g['id'].'" '.$selected_attr.'>'.htmlspecialchars($g['name']).'</option>';
    }
    ?>
</select>
</div>

<?php if(!empty($members)){ ?>
<div class="form-group">
<label>Owner</label>
<select name="owner_id" class="form-control">
    <?php foreach($members as $m){ ?>
    <option value="<?= $m['id'] ?>" <?= (int)$m['id']===(int)$row['user_id'] ? 'selected':'' ?>>
        <?= htmlspecialchars($m['name']) ?>
    </option>
    <?php } ?>
</select>
</div>
<?php } ?>

<button class="btn btn-primary">Update</button>

<a href="list.php" class="btn btn-secondary">Back</a>

</form>

</div>

</div>

<?php include("../layout/footer.php"); ?>
<script>
$(document).ready(function() {
    $('select[name="group_id[]"]').select2();
});
</script>
