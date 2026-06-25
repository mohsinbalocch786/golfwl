<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

$msg="";

if($_POST){
    verifyCsrf();

    $name  = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);

    $group_ids = isset($_POST['group_id']) ? $_POST['group_id'] : [];

    // Ownership stamp
    list($owner_uid, $owner_mid) = ownershipStamp();

    // manager/admin can assign contact to a team member
    if((isManager() || isSuperAdmin()) && !empty($_POST['owner_id'])){
        $owner_uid = (int)$_POST['owner_id'];
    }

    // 1. Insert Contact
    mysqli_query($conn,"INSERT INTO contacts
        (name,email,phone,status,created_at,user_id,manager_id)
        VALUES
        ('$name','$email','$phone','active','$currentTime','$owner_uid','$owner_mid')");

    $contact_id = mysqli_insert_id($conn);

    // 2. Insert Multiple Group Mapping
    if(!empty($group_ids)){

        foreach($group_ids as $gid){

            $gid = intval($gid);
            if($gid > 0){

                mysqli_query($conn,"INSERT INTO contact_group_map
                    (contact_id, group_id)
                    VALUES
                    ('$contact_id','$gid')");
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

// team members for owner assignment
$members = (isManager() || isSuperAdmin()) ? teamMembers($conn) : [];
?>



<div class="card card-primary">

<div class="card-header">
<h3 class="card-title">New Contact</h3>
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
<label>Name</label>
<input type="text" name="name" class="form-control" required>
</div>

<div class="form-group">
<label>Email</label>
<input type="email" name="email" class="form-control">
</div>

<div class="form-group">
<label>Phone</label>
<input type="text" name="phone" class="form-control">
</div>

<!-- ✅ Group Select2 -->
<div class="form-group">
<label>Group</label>
<select name="group_id[]" class="form-control " multiple>
    <?php
    while($g = mysqli_fetch_assoc($gq)){
        echo '<option value="'.$g['id'].'">'.htmlspecialchars($g['name']).'</option>';
    }
    ?>
</select>
</div>

<?php if(!empty($members)){ ?>
<div class="form-group">
<label>Assign To (Owner)</label>
<select name="owner_id" class="form-control">
    <?php foreach($members as $m){ ?>
    <option value="<?= $m['id'] ?>" <?= (int)$m['id']===currentUserId() ? 'selected':'' ?>>
        <?= htmlspecialchars($m['name']) ?> <?= (int)$m['id']===currentUserId() ? '(me)':'' ?>
    </option>
    <?php } ?>
</select>
</div>
<?php } ?>

<button type="submit" class="btn btn-primary">
<i class="fas fa-save"></i> Save Contact
</button>
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
