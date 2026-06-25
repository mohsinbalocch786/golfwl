<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

include("../layout/header.php");
include("../layout/sidebar.php");

$where = ownershipWhere('');

$rules = [];
$r = mysqli_query($conn, "SELECT * FROM workflow_rules WHERE $where ORDER BY id DESC");
while($row = mysqli_fetch_assoc($r)) $rules[] = $row;

$triggerLabels = [
    'lead_created'              => 'Lead Created',
    'lead_status_changed'       => 'Lead Status Changed',
    'task_overdue'              => 'Task Overdue',
    'opportunity_stage_changed' => 'Opportunity Stage Changed',
];

$flashSuccess = isset($_SESSION['flash_success']) ? $_SESSION['flash_success'] : null;
$flashLog     = isset($_SESSION['flash_log']) ? $_SESSION['flash_log'] : [];
unset($_SESSION['flash_success'], $_SESSION['flash_log']);
?>

<?php if($flashSuccess){ ?>
<div class="alert alert-success">
    <?= htmlspecialchars($flashSuccess) ?>
    <?php if(!empty($flashLog)){ ?>
    <ul class="mb-0 mt-2">
        <?php foreach($flashLog as $line){ ?>
        <li><?= htmlspecialchars($line) ?></li>
        <?php } ?>
    </ul>
    <?php } ?>
</div>
<?php } ?>

<div class="row">
    <div class="col-12">

        <div class="card">

            <div class="card-header">
                <h3 class="card-title">Workflow Automation</h3>

                <div class="card-tools">
                    <a href="form.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> New Rule
                    </a>
                    <a href="run_engine.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-play"></i> Run Engine Now
                    </a>
                </div>
            </div>

            <div class="card-body">

                <?php if(empty($rules)){ ?>
                <div class="text-center text-muted p-4">
                    <i class="fas fa-bolt fa-2x mb-2"></i>
                    <p>No automation rules yet.</p>
                    <a href="form.php" class="btn btn-primary">Create Your First Rule</a>
                </div>
                <?php } ?>

                <?php foreach($rules as $rule){
                    $conditions = json_decode(isset($rule['conditions']) ? $rule['conditions'] : '[]', true) ?: [];
                    $actions    = json_decode(isset($rule['actions']) ? $rule['actions'] : '[]', true) ?: [];
                ?>
                <div class="card mb-3 <?= $rule['is_active'] ? '' : 'bg-light' ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h5>
                                    <?= htmlspecialchars(isset($rule['name']) ? $rule['name'] : '') ?>
                                    <span class="badge badge-info"><?= isset($triggerLabels[$rule['trigger_event']]) ? $triggerLabels[$rule['trigger_event']] : $rule['trigger_event'] ?></span>
                                    <?php if($rule['is_active']){ ?>
                                    <span class="badge badge-success">Active</span>
                                    <?php } else { ?>
                                    <span class="badge badge-secondary">Inactive</span>
                                    <?php } ?>
                                </h5>

                                <?php if(!empty($conditions)){ ?>
                                <div class="mb-1">
                                    <strong>If:</strong>
                                    <?php foreach($conditions as $c){ ?>
                                    <span class="badge badge-light border"><?= htmlspecialchars($c['field'].' '.$c['operator'].' '.$c['value']) ?></span>
                                    <?php } ?>
                                </div>
                                <?php } ?>

                                <div>
                                    <strong>Then:</strong>
                                    <?php foreach($actions as $a){ ?>
                                        <?php if($a['type']==='create_task'){ ?>
                                        <span class="badge badge-primary">📝 Create task: <?= htmlspecialchars(isset($a['task_title']) ? $a['task_title'] : '') ?></span>
                                        <?php } elseif($a['type']==='send_email'){ ?>
                                        <span class="badge badge-warning">📧 Email: <?= htmlspecialchars(isset($a['to']) ? $a['to'] : '') ?> — <?= htmlspecialchars(isset($a['subject']) ? $a['subject'] : '') ?></span>
                                        <?php } elseif($a['type']==='update_field'){ ?>
                                        <span class="badge badge-success">✏️ Set <?= htmlspecialchars(isset($a['field']) ? $a['field'] : '') ?> = <?= htmlspecialchars(isset($a['value']) ? $a['value'] : '') ?></span>
                                        <?php } ?>
                                    <?php } ?>
                                </div>
                            </div>

                            <div>
                                <a href="form.php?id=<?= $rule['id'] ?>" class="btn btn-info btn-sm">Edit</a>
                                <a href="toggle.php?id=<?= $rule['id'] ?>" class="btn btn-secondary btn-sm"><?= $rule['is_active']?'Disable':'Enable' ?></a>
                                <a href="delete.php?id=<?= $rule['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this rule?')">Delete</a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php } ?>

            </div>
        </div>
    </div>
</div>

<?php include("../layout/footer.php"); ?>
