<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

// Only managers/admins can add users
if(!isSuperAdmin() && !isManager()){
    header("Location:list.php");
    exit;
}

$msg="";

if($_POST){
    verifyCsrf();

    $name  = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $status =  mysqli_real_escape_string($conn, $_POST['status']);
    $role   = $_POST['role'] === 'manager' ? 'manager' : 'user';
    $can_view_team = isset($_POST['can_view_team']) ? 1 : 0;
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    // manager_id assignment
    if (isSuperAdmin()) {
        // admin can pick any manager, or leave as 0/null for top-level manager
        $manager_id = !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null;
    } else {
        // a manager creates users under themselves
        $manager_id = ($role === 'user') ? currentUserId() : null;
        if ($role === 'manager') {
            // sub-managers report to the creating manager
            $manager_id = currentUserId();
        }
    }

    $created_by = isSuperAdmin() ? 0 : currentUserId();

    $manager_id_sql = $manager_id !== null ? "'$manager_id'" : "NULL";

    mysqli_query($conn,"INSERT INTO users
        (name,email,phone,status,password,role,manager_id,created_by,can_view_team,created_at)
        VALUES
        ('$name','$email','$phone','$status','$password','$role',$manager_id_sql,'$created_by','$can_view_team','$currentTime')");

    header("Location:list.php");
    exit;
}
include("../layout/header.php");
include("../layout/sidebar.php");

// for admin: list of managers to assign as parent
$managers = [];
if (isSuperAdmin()) {
    $mq = mysqli_query($conn, "SELECT id, name, email FROM users WHERE role='manager' ORDER BY name");
    while($m = mysqli_fetch_assoc($mq)) $managers[] = $m;
}
?>



<div class="card card-primary">

<div class="card-header">
<h3 class="card-title">New User</h3>
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
<input type="email" name="email" class="form-control" required>
</div>

<div class="form-group">
<label>Password</label>
<input type="password" name="password" class="form-control" required>
</div>

<div class="form-group">
<label>Phone</label>
<input type="text" name="phone" class="form-control">
</div>

<div class="form-group">
<label>Role</label>
<select name="role" id="role" class="form-control">
    <option value="user">User</option>
    <option value="manager">Manager</option>
</select>
<small class="form-text text-muted">
    Managers can view their team's contacts, campaigns, templates, and reports.
</small>
</div>

<?php if(isSuperAdmin()){ ?>
<div class="form-group" id="managerField">
    <label>Reports To (Manager)</label>
    <select name="manager_id" class="form-control">
        <option value="">-- None (top-level) --</option>
        <?php foreach($managers as $m){ ?>
        <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']).' ('.htmlspecialchars($m['email']).')' ?></option>
        <?php } ?>
    </select>
</div>
<?php } ?>

<div class="form-group">
    <div class="form-check">
        <input class="form-check-input" type="checkbox" name="can_view_team" id="can_view_team" value="1">
        <label class="form-check-label" for="can_view_team">
            Allow this user to view their team's data (read-only team view)
        </label>
    </div>
</div>

<div class="form-group">
<label>Status</label>
<select name="status" class="form-control">
    <option value="active">Active</option>
    <option value="inactive">Inactive</option>
</select>
</div>


<button type="submit" class="btn btn-primary">
<i class="fas fa-save"></i> Save User
</button>
<a href="list.php" class="btn btn-secondary">Back</a>

</form>

</div>

</div>

<?php include("../layout/footer.php"); ?>
<script>
$(document).ready(function() {
    function toggleManagerField(){
        if($('#role').val()==='manager'){
            $('#managerField').find('select').prop('disabled', false);
        }
    }
    toggleManagerField();
    $('#role').on('change', toggleManagerField);
});
</script>
