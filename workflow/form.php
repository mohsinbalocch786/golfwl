<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

$id     = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
$rule   = null;
$isEdit = false;

if($id){
    $r = mysqli_query($conn, "SELECT * FROM workflow_rules WHERE id='$id'");
    $rule = mysqli_fetch_assoc($r);
    if(!$rule){
        header("Location:list.php");
        exit;
    }
    assertOwnership($rule);
    $isEdit = true;
}

if($_POST){
    verifyCsrf();

    $name          = mysqli_real_escape_string($conn, $_POST['name']);
    $trigger_event = $_POST['trigger_event'];

    $validTriggers = ['lead_created','lead_status_changed','task_overdue','opportunity_stage_changed'];
    if(!in_array($trigger_event, $validTriggers)) $trigger_event = 'lead_created';

    // Conditions
    $conditions = [];
    if(!empty($_POST['cond_field'])){
        foreach($_POST['cond_field'] as $i => $field){
            $field = trim($field);
            if($field !== ''){
                $conditions[] = [
                    'field'    => $field,
                    'operator' => isset($_POST['cond_op'][$i]) ? $_POST['cond_op'][$i] : '=',
                    'value'    => isset($_POST['cond_val'][$i]) ? $_POST['cond_val'][$i] : '',
                ];
            }
        }
    }

    // Actions
    $actions = [];
    if(!empty($_POST['action_type'])){
        foreach($_POST['action_type'] as $i => $type){
            if($type === '') continue;

            $action = ['type' => $type];

            if($type === 'create_task'){
                $action['task_title']    = isset($_POST['action_task_title'][$i]) ? $_POST['action_task_title'][$i] : '';
                $action['task_priority'] = isset($_POST['action_task_priority'][$i]) ? $_POST['action_task_priority'][$i] : 'medium';
                $action['due_days']      = (int)(isset($_POST['action_due_days'][$i]) ? $_POST['action_due_days'][$i] : 1);
            } elseif($type === 'send_email'){
                $action['to']      = isset($_POST['action_email_to'][$i]) ? $_POST['action_email_to'][$i] : '';
                $action['subject'] = isset($_POST['action_email_subject'][$i]) ? $_POST['action_email_subject'][$i] : '';
                $action['body']    = isset($_POST['action_email_body'][$i]) ? $_POST['action_email_body'][$i] : '';
            } elseif($type === 'update_field'){
                $action['field'] = isset($_POST['action_uf_field'][$i]) ? $_POST['action_uf_field'][$i] : '';
                $action['value'] = isset($_POST['action_uf_value'][$i]) ? $_POST['action_uf_value'][$i] : '';
            }

            $actions[] = $action;
        }
    }

    $conditionsJson = mysqli_real_escape_string($conn, json_encode($conditions));
    $actionsJson    = mysqli_real_escape_string($conn, json_encode($actions));

    if($isEdit){
        mysqli_query($conn,"UPDATE workflow_rules SET
            name='$name',
            trigger_event='$trigger_event',
            conditions='$conditionsJson',
            actions='$actionsJson'
            WHERE id='$id'");
    } else {
        list($owner_uid, $owner_mid) = ownershipStamp();

        mysqli_query($conn,"INSERT INTO workflow_rules
            (user_id,manager_id,name,trigger_event,conditions,actions,is_active,created_at)
            VALUES
            ('$owner_uid','$owner_mid','$name','$trigger_event','$conditionsJson','$actionsJson',1,'$currentTime')");
    }

    header("Location:list.php");
    exit;
}

include("../layout/header.php");
include("../layout/sidebar.php");

$triggers = [
    'lead_created'              => 'Lead Created',
    'lead_status_changed'       => 'Lead Status Changed',
    'task_overdue'              => 'Task Overdue',
    'opportunity_stage_changed' => 'Opportunity Stage Changed',
];

$conditions = $isEdit ? (json_decode(isset($rule['conditions']) ? $rule['conditions'] : '[]', true) ?: [['field'=>'','operator'=>'=','value'=>'']]) : [['field'=>'','operator'=>'=','value'=>'']];
$actions    = $isEdit ? (json_decode(isset($rule['actions']) ? $rule['actions'] : '[]', true) ?: [['type'=>'']]) : [['type'=>'']];
?>

<div class="row">
<div class="col-md-9">

<div class="card card-primary">
<div class="card-header">
<h3 class="card-title"><?= $isEdit ? 'Edit Workflow Rule' : 'New Workflow Rule' ?></h3>
</div>

<div class="card-body">

<form method="post" id="wfForm">
<?php echo csrfField(); ?>

<div class="form-group">
    <label>Rule Name *</label>
    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars(isset($rule['name']) ? $rule['name'] : '') ?>" required>
</div>

<div class="form-group">
    <label>Trigger Event</label>
    <select name="trigger_event" class="form-control" required>
        <option value="">— Select trigger —</option>
        <?php foreach($triggers as $k=>$v){ ?>
        <option value="<?= $k ?>" <?= ((isset($rule['trigger_event']) ? $rule['trigger_event'] : '')===$k)?'selected':'' ?>><?= $v ?></option>
        <?php } ?>
    </select>
</div>

<h5>Conditions (optional)</h5>
<div id="conditions">
<?php foreach($conditions as $c){ ?>
<div class="form-row condition-row align-items-center mb-2">
    <div class="col-md-4">
        <input type="text" name="cond_field[]" class="form-control" placeholder="Field (e.g. status)" value="<?= htmlspecialchars($c['field']) ?>">
    </div>
    <div class="col-md-3">
        <select name="cond_op[]" class="form-control">
            <?php foreach(['=','!=','>','<','contains'] as $op){ ?>
            <option value="<?= $op ?>" <?= ($c['operator']===$op)?'selected':'' ?>><?= $op ?></option>
            <?php } ?>
        </select>
    </div>
    <div class="col-md-4">
        <input type="text" name="cond_val[]" class="form-control" placeholder="Value" value="<?= htmlspecialchars($c['value']) ?>">
    </div>
    <div class="col-md-1">
        <button type="button" class="btn btn-danger btn-sm remove-row">✕</button>
    </div>
</div>
<?php } ?>
</div>
<button type="button" class="btn btn-sm btn-secondary mb-3" id="addCondition">+ Add Condition</button>

<h5>Actions</h5>
<div id="actions">
<?php foreach($actions as $a){ ?>
<div class="card mb-2 action-row">
    <div class="card-body">
        <div class="form-row align-items-center">
            <div class="col-md-3">
                <select name="action_type[]" class="form-control action-type-sel">
                    <option value="">— Action —</option>
                    <option value="create_task"  <?= ((isset($a['type']) ? $a['type'] : '')==='create_task')?'selected':'' ?>>Create Task</option>
                    <option value="send_email"   <?= ((isset($a['type']) ? $a['type'] : '')==='send_email')?'selected':'' ?>>Send Email</option>
                    <option value="update_field" <?= ((isset($a['type']) ? $a['type'] : '')==='update_field')?'selected':'' ?>>Update Field</option>
                </select>
            </div>

            <div class="col-md-8 action-fields action-create_task" style="display:<?= ((isset($a['type']) ? $a['type'] : '')==='create_task')?'flex':'none' ?>; gap:8px;">
                <input type="text" name="action_task_title[]" class="form-control" placeholder="Task title" value="<?= htmlspecialchars(isset($a['task_title']) ? $a['task_title'] : '') ?>">
                <select name="action_task_priority[]" class="form-control">
                    <?php foreach(['low','medium','high'] as $p){ ?>
                    <option value="<?= $p ?>" <?= ((isset($a['task_priority']) ? $a['task_priority'] : 'medium')===$p)?'selected':'' ?>><?= ucfirst($p) ?></option>
                    <?php } ?>
                </select>
                <input type="number" name="action_due_days[]" class="form-control" placeholder="Due in N days" min="0" value="<?= isset($a['due_days']) ? $a['due_days'] : 1 ?>">
            </div>

            <div class="col-md-8 action-fields action-send_email" style="display:<?= ((isset($a['type']) ? $a['type'] : '')==='send_email')?'flex':'none' ?>; gap:8px;">
                <input type="text" name="action_email_to[]" class="form-control" placeholder="To email (or {{LEAD_EMAIL}})" value="<?= htmlspecialchars(isset($a['to']) ? $a['to'] : '') ?>">
                <input type="text" name="action_email_subject[]" class="form-control" placeholder="Subject" value="<?= htmlspecialchars(isset($a['subject']) ? $a['subject'] : '') ?>">
                <input type="text" name="action_email_body[]" class="form-control" placeholder="Body" value="<?= htmlspecialchars(isset($a['body']) ? $a['body'] : '') ?>">
            </div>

            <div class="col-md-8 action-fields action-update_field" style="display:<?= ((isset($a['type']) ? $a['type'] : '')==='update_field')?'flex':'none' ?>; gap:8px;">
                <input type="text" name="action_uf_field[]" class="form-control" placeholder="Field name" value="<?= htmlspecialchars(isset($a['field']) ? $a['field'] : '') ?>">
                <input type="text" name="action_uf_value[]" class="form-control" placeholder="New value" value="<?= htmlspecialchars(isset($a['value']) ? $a['value'] : '') ?>">
            </div>

            <div class="col-md-1">
                <button type="button" class="btn btn-danger btn-sm remove-row">✕</button>
            </div>
        </div>
    </div>
</div>
<?php } ?>
</div>
<button type="button" class="btn btn-sm btn-secondary mb-3" id="addAction">+ Add Action</button>

<div class="alert alert-info">
    <strong>Tip:</strong> For "Send Email" actions, the <code>to</code> field supports
    <code>{{LEAD_EMAIL}}</code> / <code>{{CONTACT_EMAIL}}</code> placeholders which are
    replaced with the triggering record's email at runtime. Subject/body also support
    <code>{{NAME}}</code>, <code>{{EMAIL}}</code>, <code>{{PHONE}}</code>, <code>{{COMPANY}}</code>.
</div>

<br>
<button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= $isEdit ? 'Update' : 'Save' ?> Rule</button>
<a href="list.php" class="btn btn-secondary">Cancel</a>

</form>

</div>
</div>
</div>
</div>

<?php include("../layout/footer.php"); ?>

<script>
function conditionRowHtml(){
    return `<div class="form-row condition-row align-items-center mb-2">
        <div class="col-md-4"><input type="text" name="cond_field[]" class="form-control" placeholder="Field"></div>
        <div class="col-md-3">
            <select name="cond_op[]" class="form-control">
                <option value="=">=</option><option value="!=">!=</option>
                <option value=">">&gt;</option><option value="<">&lt;</option>
                <option value="contains">contains</option>
            </select>
        </div>
        <div class="col-md-4"><input type="text" name="cond_val[]" class="form-control" placeholder="Value"></div>
        <div class="col-md-1"><button type="button" class="btn btn-danger btn-sm remove-row">✕</button></div>
    </div>`;
}

function actionRowHtml(){
    return `<div class="card mb-2 action-row">
        <div class="card-body">
            <div class="form-row align-items-center">
                <div class="col-md-3">
                    <select name="action_type[]" class="form-control action-type-sel">
                        <option value="">— Action —</option>
                        <option value="create_task">Create Task</option>
                        <option value="send_email">Send Email</option>
                        <option value="update_field">Update Field</option>
                    </select>
                </div>
                <div class="col-md-8 action-fields action-create_task" style="display:none; gap:8px;">
                    <input type="text" name="action_task_title[]" class="form-control" placeholder="Task title">
                    <select name="action_task_priority[]" class="form-control">
                        <option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option>
                    </select>
                    <input type="number" name="action_due_days[]" class="form-control" placeholder="Due in N days" min="0" value="1">
                </div>
                <div class="col-md-8 action-fields action-send_email" style="display:none; gap:8px;">
                    <input type="text" name="action_email_to[]" class="form-control" placeholder="To email (or {{LEAD_EMAIL}})">
                    <input type="text" name="action_email_subject[]" class="form-control" placeholder="Subject">
                    <input type="text" name="action_email_body[]" class="form-control" placeholder="Body">
                </div>
                <div class="col-md-8 action-fields action-update_field" style="display:none; gap:8px;">
                    <input type="text" name="action_uf_field[]" class="form-control" placeholder="Field name">
                    <input type="text" name="action_uf_value[]" class="form-control" placeholder="New value">
                </div>
                <div class="col-md-1"><button type="button" class="btn btn-danger btn-sm remove-row">✕</button></div>
            </div>
        </div>
    </div>`;
}

function toggleActionFields(sel){
    const row = sel.closest('.action-row');
    row.querySelectorAll('.action-fields').forEach(f => f.style.display = 'none');
    if(sel.value){
        const target = row.querySelector('.action-' + sel.value);
        if(target) target.style.display = 'flex';
    }
}

document.getElementById('addCondition').addEventListener('click', function(){
    document.getElementById('conditions').insertAdjacentHTML('beforeend', conditionRowHtml());
});

document.getElementById('addAction').addEventListener('click', function(){
    document.getElementById('actions').insertAdjacentHTML('beforeend', actionRowHtml());
});

document.addEventListener('change', function(e){
    if(e.target.classList.contains('action-type-sel')){
        toggleActionFields(e.target);
    }
});

document.addEventListener('click', function(e){
    if(e.target.classList.contains('remove-row')){
        e.target.closest('.condition-row, .action-row').remove();
    }
});
</script>
