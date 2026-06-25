<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

$id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
$lead = null;
$isEdit = false;

if($id){
    $r = mysqli_query($conn, "SELECT * FROM leads WHERE id='$id'");
    $lead = mysqli_fetch_assoc($r);
    if(!$lead){
        header("Location:list.php");
        exit;
    }
    assertOwnership($lead);
    $isEdit = true;
}

$msg = "";

if($_POST){
    verifyCsrf();


    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name  = mysqli_real_escape_string($conn, $_POST['last_name']);
    $email      = mysqli_real_escape_string($conn, $_POST['email']);
    $phone      = mysqli_real_escape_string($conn, $_POST['phone']);
    $company    = mysqli_real_escape_string($conn, $_POST['company']);
    $source     = mysqli_real_escape_string($conn, $_POST['source']);
    $status     = $_POST['status'];
    $notes      = mysqli_real_escape_string($conn, $_POST['notes']);
    $contact_id = !empty($_POST['contact_id']) ? (int)$_POST['contact_id'] : 'NULL';

    $validStatuses = ['new','contacted','qualified','proposal','won','lost'];
    if(!in_array($status, $validStatuses)) $status = 'new';

    if($first_name == ''){
        $msg = "First name is required.";
    } else {

        if($isEdit){
            mysqli_query($conn,"UPDATE leads SET
                first_name='$first_name',
                last_name='$last_name',
                email='$email',
                phone='$phone',
                company='$company',
                source='$source',
                status='$status',
                notes='$notes',
                contact_id=$contact_id,
                updated_at='$currentTime'
                WHERE id='$id'");

            header("Location:list.php");
            exit;
        } else {

            list($owner_uid, $owner_mid) = ownershipStamp();

            mysqli_query($conn,"INSERT INTO leads
                (user_id,manager_id,contact_id,first_name,last_name,email,phone,company,source,status,notes,created_at,updated_at)
                VALUES
                ('$owner_uid','$owner_mid',$contact_id,'$first_name','$last_name','$email','$phone','$company','$source','$status','$notes','$currentTime','$currentTime')");

            header("Location:list.php");
            exit;
        }
    }
}

include("../layout/header.php");
include("../layout/sidebar.php");

// Contacts dropdown - scoped to ownership
$cw = ownershipWhere('');
$contacts_q = mysqli_query($conn, "SELECT id, name, email FROM contacts WHERE $cw ORDER BY name");

$sources  = ['Website','Referral','Cold Call','Email','Social Media','Event','Other'];
$statuses = ['new','contacted','qualified','proposal','won','lost'];
?>

<div class="row">
<div class="col-md-8">

<div class="card card-primary">

<div class="card-header">
<h3 class="card-title"><?= $isEdit ? 'Edit Lead' : 'New Lead' ?></h3>
</div>

<div class="card-body">

<?php if($msg!=""){ ?>
<div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div>
<?php } ?>

<form method="post">
<?php echo csrfField(); ?>

<div class="form-row">
    <div class="form-group col-md-6">
        <label>First Name *</label>
        <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars(isset($lead['first_name']) ? $lead['first_name'] : '') ?>" required>
    </div>
    <div class="form-group col-md-6">
        <label>Last Name</label>
        <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars(isset($lead['last_name']) ? $lead['last_name'] : '') ?>">
    </div>
</div>

<div class="form-row">
    <div class="form-group col-md-6">
        <label>Email</label>
        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars(isset($lead['email']) ? $lead['email'] : '') ?>">
    </div>
    <div class="form-group col-md-6">
        <label>Phone</label>
        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars(isset($lead['phone']) ? $lead['phone'] : '') ?>">
    </div>
</div>

<div class="form-row">
    <div class="form-group col-md-6">
        <label>Company</label>
        <input type="text" name="company" class="form-control" value="<?= htmlspecialchars(isset($lead['company']) ? $lead['company'] : '') ?>">
    </div>
    <div class="form-group col-md-6">
        <label>Source</label>
        <select name="source" class="form-control">
            <option value="">— Select —</option>
            <?php foreach($sources as $src){ ?>
            <option value="<?= $src ?>" <?= ((isset($lead['source']) ? $lead['source'] : '')===$src)?'selected':'' ?>><?= $src ?></option>
            <?php } ?>
        </select>
    </div>
</div>

<div class="form-row">
    <div class="form-group col-md-6">
        <label>Status</label>
        <select name="status" class="form-control">
            <?php foreach($statuses as $s){ ?>
            <option value="<?= $s ?>" <?= ((isset($lead['status']) ? $lead['status'] : 'new')===$s)?'selected':'' ?>><?= ucfirst($s) ?></option>
            <?php } ?>
        </select>
    </div>
    <div class="form-group col-md-6">
        <label>Linked Contact</label>
        <select name="contact_id" id="contact_id" class="form-control select2">
            <option value="">— None —</option>
            <?php while($c=mysqli_fetch_assoc($contacts_q)){ ?>
            <option value="<?= $c['id'] ?>" <?= ((int)(isset($lead['contact_id']) ? $lead['contact_id'] : 0)===(int)$c['id'])?'selected':'' ?>>
                <?= htmlspecialchars($c['name'].' ('.$c['email'].')') ?>
            </option>
            <?php } ?>
        </select>
    </div>
</div>

<div class="form-group">
    <label>Notes</label>
    <textarea name="notes" class="form-control" rows="4"><?= htmlspecialchars(isset($lead['notes']) ? $lead['notes'] : '') ?></textarea>
</div>

<button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= $isEdit ? 'Update' : 'Create' ?> Lead</button>
<a href="list.php" class="btn btn-secondary">Back</a>

</form>

</div>
</div>
</div>
</div>

<?php include("../layout/footer.php"); ?>

<script>
$(document).ready(function() {
    $('.select2').select2();
});
</script>