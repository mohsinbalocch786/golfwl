<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

if(!isSuperAdmin() && !isManager()){
    header("Location:list.php");
    exit;
}

$id=(int)$_GET['id'];

// fetch target row for ownership check
$chk = mysqli_query($conn, "SELECT * FROM users WHERE id='$id'");
$target = mysqli_fetch_assoc($chk);
if(!$target){
    header("Location:list.php");
    exit;
}

// A manager can only edit themselves or users where manager_id = their id
if(!isSuperAdmin()){
    $uid = currentUserId();
    if((int)$target['id'] !== $uid && (int)(isset($target['manager_id']) ? $target['manager_id'] : -1) !== $uid){
        header("Location:list.php");
        exit;
    }
}

$msg="";

if($_POST){
    verifyCsrf();

    $name  = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $status =  mysqli_real_escape_string($conn, $_POST['status']);
    $can_view_team = isset($_POST['can_view_team']) ? 1 : 0;

    $extra = "";
    if(!empty($_POST['password'])){
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $password = mysqli_real_escape_string($conn, $password);
        $extra = ", password='$password'";
    }

    // role/manager only editable by managers/admins, and not on self for role
    $roleSql = "";
    if(isSuperAdmin() || isManager()){
        $role = $_POST['role'] === 'manager' ? 'manager' : 'user';
        $roleSql = ", role='$role', can_view_team='$can_view_team'";

        if(isSuperAdmin() && isset($_POST['manager_id'])){
            $manager_id = $_POST['manager_id'] !== '' ? (int)$_POST['manager_id'] : null;
            $roleSql .= $manager_id !== null ? ", manager_id='$manager_id'" : ", manager_id=NULL";
        }
    }

    mysqli_query($conn,"UPDATE users SET
        name='$name',
        email='$email',
        phone='$phone',
        status='$status'
        $roleSql
        $extra
        WHERE id='$id'");

    header("Location:list.php");
    exit;
}

$r=mysqli_query($conn,"SELECT * FROM users WHERE id='$id'");
$row=mysqli_fetch_assoc($r);

include("../layout/header.php");
include("../layout/sidebar.php");

$managers = [];
if (isSuperAdmin()) {
    $mq = mysqli_query($conn, "SELECT id, name, email FROM users WHERE role='manager' AND id != $id ORDER BY name");
    while($m = mysqli_fetch_assoc($mq)) $managers[] = $m;
}
?>



<div class="card card-primary">

<div class="card-header">
<h3 class="card-title">Edit User</h3>
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
<label>Password</label>
<input type="password" name="password" class="form-control" placeholder="Leave blank to keep current password">
</div>

<div class="form-group">
<label>Phone</label>
<input class="form-control" name="phone" value="<?php echo htmlspecialchars($row['phone']); ?>">
</div>

<?php if(isSuperAdmin() || isManager()){ ?>
<div class="form-group">
<label>Role</label>
<select name="role" class="form-control">
    <option value="user"    <?= (isset($row['role']) ? $row['role'] : 'user')==='user'    ? 'selected':'' ?>>User</option>
    <option value="manager" <?= (isset($row['role']) ? $row['role'] : 'user')==='manager' ? 'selected':'' ?>>Manager</option>
</select>
</div>

<?php if(isSuperAdmin()){ ?>
<div class="form-group">
<label>Reports To (Manager)</label>
<select name="manager_id" class="form-control">
    <option value="">-- None (top-level) --</option>
    <?php foreach($managers as $m){ ?>
    <option value="<?= $m['id'] ?>" <?= (int)(isset($row['manager_id']) ? $row['manager_id'] : 0)===(int)$m['id'] ? 'selected':'' ?>>
        <?= htmlspecialchars($m['name']).' ('.htmlspecialchars($m['email']).')' ?>
    </option>
    <?php } ?>
</select>
</div>
<?php } ?>

<div class="form-group">
    <div class="form-check">
        <input class="form-check-input" type="checkbox" name="can_view_team" id="can_view_team" value="1" <?= !empty($row['can_view_team']) ? 'checked':'' ?>>
        <label class="form-check-label" for="can_view_team">
            Allow this user to view their team's data (read-only team view)
        </label>
    </div>
</div>
<?php } ?>

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
