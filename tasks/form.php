<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

$id     = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
$leadId = (int)(isset($_GET['lead_id']) ? $_GET['lead_id'] : 0);
$oppId  = (int)(isset($_GET['opp_id']) ? $_GET['opp_id'] : 0);
$task   = null;
$isEdit = false;

if($id){
    $r = mysqli_query($conn, "SELECT * FROM tasks WHERE id='$id'");
    $task = mysqli_fetch_assoc($r);
    if(!$task){
        header("Location:list.php");
        exit;
    }
    assertOwnership($task);
    $isEdit = true;
}

$msg = "";

if($_POST){
    verifyCsrf();


    $title     = mysqli_real_escape_string($conn, $_POST['title']);
    $due_date  = !empty($_POST['due_date']) ? "'".mysqli_real_escape_string($conn, str_replace('T',' ', $_POST['due_date']).':00')."'" : 'NULL';
    $priority  = isset($_POST['priority']) ? $_POST['priority'] : 'medium';
    $status    = isset($_POST['status']) ? $_POST['status'] : 'pending';
    $lead_id   = !empty($_POST['lead_id']) ? (int)$_POST['lead_id'] : 'NULL';
    $opp_id    = !empty($_POST['opp_id']) ? (int)$_POST['opp_id'] : 'NULL';

    if(!in_array($priority, ['low','medium','high'])) $priority = 'medium';
    if(!in_array($status, ['pending','completed'])) $status = 'pending';

    if($title == ''){
        $msg = "Title is required.";
    } else {

        if($isEdit){
            mysqli_query($conn,"UPDATE tasks SET
                title='$title',
                due_date=$due_date,
                priority='$priority',
                status='$status',
                lead_id=$lead_id,
                opportunity_id=$opp_id
                WHERE id='$id'");

            header("Location:list.php");
            exit;
        } else {

            list($owner_uid, $owner_mid) = ownershipStamp();

            mysqli_query($conn,"INSERT INTO tasks
                (user_id,manager_id,lead_id,opportunity_id,title,due_date,priority,status,created_at)
                VALUES
                ('$owner_uid','$owner_mid',$lead_id,$opp_id,'$title',$due_date,'$priority','pending','$currentTime')");

            header("Location:list.php");
            exit;
        }
    }
}

include("../layout/header.php");
include("../layout/sidebar.php");

// Leads / Opportunities dropdowns - scoped to ownership
$lw = ownershipWhere('');
$leads_q = mysqli_query($conn, "SELECT id, first_name, last_name FROM leads WHERE $lw ORDER BY first_name");

$ow = ownershipWhere('');
$opps_q = mysqli_query($conn, "SELECT id, title FROM opportunities WHERE $ow ORDER BY title");
?>

<div class="row">
<div class="col-md-8">

<div class="card card-primary">

<div class="card-header">
<h3 class="card-title"><?= $isEdit ? 'Edit Task' : 'New Task' ?></h3>
</div>

<div class="card-body">

<?php if($msg!=""){ ?>
<div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div>
<?php } ?>

<form method="post">
<?php echo csrfField(); ?>

<div class="form-group">
    <label>Task Title *</label>
    <input type="text" name="title" class="form-control" value="<?= isset($task['title']) ? htmlspecialchars($task['title']) : '' ?>" required>
</div>

<div class="form-row">
    <div class="form-group col-md-6">
        <label>Due Date/Time</label>
        <input type="datetime-local" name="due_date" class="form-control"
            value="<?= !empty($task['due_date']) ? date('Y-m-d\TH:i', strtotime($task['due_date'])) : '' ?>">
    </div>
    <div class="form-group col-md-6">
        <label>Priority</label>
        <select name="priority" class="form-control">
            <?php foreach(['low','medium','high'] as $p){ ?>
            <option value="<?= $p ?>" <?= ((isset($task['priority']) ? $task['priority'] : 'medium')===$p)?'selected':'' ?>><?= ucfirst($p) ?></option>
            <?php } ?>
        </select>
    </div>
</div>

<?php if($isEdit){ ?>
<div class="form-group">
    <label>Status</label>
    <select name="status" class="form-control">
        <option value="pending" <?= ((isset($task['status']) ? $task['status'] : 'pending')==='pending')?'selected':'' ?>>Pending</option>
        <option value="completed" <?= ((isset($task['status']) ? $task['status'] : 'pending')==='completed')?'selected':'' ?>>Completed</option>
    </select>
</div>
<?php } ?>

<div class="form-group">
    <label>Link to Lead</label>
    <select name="lead_id" class="form-control select2">
        <option value="">— None —</option>
        <?php while($l=mysqli_fetch_assoc($leads_q)){ ?>
        <option value="<?= $l['id'] ?>" <?= ((int)(isset($task['lead_id']) ? $task['lead_id'] : $leadId)===(int)$l['id'])?'selected':'' ?>>
            <?= htmlspecialchars($l['first_name'].' '.$l['last_name']) ?>
        </option>
        <?php } ?>
    </select>
</div>

<div class="form-group">
    <label>Link to Opportunity</label>
    <select name="opp_id" class="form-control select2">
        <option value="">— None —</option>
        <?php while($o=mysqli_fetch_assoc($opps_q)){ ?>
        <option value="<?= $o['id'] ?>" <?= ((int)(isset($task['opportunity_id']) ? $task['opportunity_id'] : $oppId)===(int)$o['id'])?'selected':'' ?>>
            <?= htmlspecialchars($o['title']) ?>
        </option>
        <?php } ?>
    </select>
</div>

<button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= $isEdit ? 'Update' : 'Create' ?> Task</button>
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