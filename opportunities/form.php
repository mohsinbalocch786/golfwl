<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

$id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
$opp = null;
$isEdit = false;

if($id){
    $r = mysqli_query($conn, "SELECT * FROM opportunities WHERE id='$id'");
    $opp = mysqli_fetch_assoc($r);
    if(!$opp){
        header("Location:pipeline.php");
        exit;
    }
    assertOwnership($opp);
    $isEdit = true;
}

$msg = "";

if($_POST){
    verifyCsrf();


    $title       = mysqli_real_escape_string($conn, $_POST['title']);
    $amount      = (float)$_POST['amount'];
    $stage       = $_POST['stage'];
    $probability = (int)$_POST['probability'];
    $close_date  = !empty($_POST['expected_close_date']) ? "'".mysqli_real_escape_string($conn, $_POST['expected_close_date'])."'" : 'NULL';
    $lead_id     = !empty($_POST['lead_id']) ? (int)$_POST['lead_id'] : 'NULL';

    $validStages = ['new','qualified','proposal','negotiation','won','lost'];
    if(!in_array($stage, $validStages)) $stage = 'new';

    if($title == ''){
        $msg = "Title is required.";
    } else {

        if($isEdit){
            mysqli_query($conn,"UPDATE opportunities SET
                title='$title',
                amount='$amount',
                stage='$stage',
                probability='$probability',
                expected_close_date=$close_date,
                lead_id=$lead_id,
                updated_at='$currentTime'
                WHERE id='$id'");

            // reflect won/lost back onto the linked lead
            if(in_array($stage, ['won','lost']) && $lead_id !== 'NULL'){
                mysqli_query($conn,"UPDATE leads SET status='$stage', updated_at='$currentTime' WHERE id='".(int)$_POST['lead_id']."'");
            }

            header("Location:pipeline.php");
            exit;
        } else {

            list($owner_uid, $owner_mid) = ownershipStamp();

            mysqli_query($conn,"INSERT INTO opportunities
                (lead_id,user_id,manager_id,title,amount,stage,probability,expected_close_date,created_at,updated_at)
                VALUES
                ($lead_id,'$owner_uid','$owner_mid','$title','$amount','$stage','$probability',$close_date,'$currentTime','$currentTime')");

            header("Location:pipeline.php");
            exit;
        }
    }
}

include("../layout/header.php");
include("../layout/sidebar.php");

// Leads dropdown - scoped to ownership
$lw = ownershipWhere('');
$leads_q = mysqli_query($conn, "SELECT id, first_name, last_name, company FROM leads WHERE $lw ORDER BY first_name");

$stages = ['new','qualified','proposal','negotiation','won','lost'];
?>

<div class="row">
<div class="col-md-8">

<div class="card card-primary">

<div class="card-header">
<h3 class="card-title"><?= $isEdit ? 'Edit Opportunity' : 'New Opportunity' ?></h3>
</div>

<div class="card-body">

<?php if($msg!=""){ ?>
<div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div>
<?php } ?>

<form method="post">
<?php echo csrfField(); ?>

<div class="form-group">
    <label>Title *</label>
    <input type="text" name="title" class="form-control" value="<?= htmlspecialchars(isset($opp['title']) ? $opp['title'] : '') ?>" required>
</div>

<div class="form-row">
    <div class="form-group col-md-6">
        <label>Stage</label>
        <select name="stage" class="form-control">
            <?php foreach($stages as $s){ ?>
            <option value="<?= $s ?>" <?= ((isset($opp['stage']) ? $opp['stage'] : 'new')===$s)?'selected':'' ?>><?= ucfirst($s) ?></option>
            <?php } ?>
        </select>
    </div>
    <div class="form-group col-md-6">
        <label>Probability (%)</label>
        <input type="number" name="probability" min="0" max="100" class="form-control" value="<?= isset($opp['probability']) ? $opp['probability'] : 10 ?>">
    </div>
</div>

<div class="form-row">
    <div class="form-group col-md-6">
        <label>Amount</label>
        <input type="number" name="amount" step="0.01" min="0" class="form-control" value="<?= isset($opp['amount']) ? $opp['amount'] : 0 ?>">
    </div>
    <div class="form-group col-md-6">
        <label>Expected Close Date</label>
        <input type="date" name="expected_close_date" class="form-control" value="<?= isset($opp['expected_close_date']) ? $opp['expected_close_date'] : '' ?>">
    </div>
</div>

<div class="form-group">
    <label>Linked Lead</label>
    <select name="lead_id" class="form-control select2">
        <option value="">— None —</option>
        <?php while($l=mysqli_fetch_assoc($leads_q)){ ?>
        <option value="<?= $l['id'] ?>" <?= ((int)(isset($opp['lead_id']) ? $opp['lead_id'] : 0)===(int)$l['id'])?'selected':'' ?>>
            <?= htmlspecialchars(trim($l['first_name'].' '.$l['last_name']).($l['company'] ? ' - '.$l['company'] : '')) ?>
        </option>
        <?php } ?>
    </select>
</div>

<button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= $isEdit ? 'Update' : 'Create' ?> Opportunity</button>
<a href="pipeline.php" class="btn btn-secondary">Back</a>

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