<?php
// $lead, $id, $conn available from view.php
$tq = mysqli_query($conn, "
    SELECT t.*, u.name AS owner_name
    FROM tasks t
    LEFT JOIN users u ON u.id = t.user_id
    WHERE t.lead_id = '$id'
    ORDER BY
        CASE WHEN t.status='pending' THEN 0 ELSE 1 END,
        t.due_date ASC
");
$lead_tasks = [];
while($row = mysqli_fetch_assoc($tq)) $lead_tasks[] = $row;
$priorityColors = ['low'=>'success','medium'=>'warning','high'=>'danger'];
?>

<div class="card card-outline card-warning mt-2">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0"><i class="fas fa-tasks mr-1"></i> Tasks</h3>
        <a target="_blank" href="../tasks/form.php?lead_id=<?= $id ?>" class="btn btn-warning btn-xs">
            <i class="fas fa-plus"></i> Add Task
        </a>
    </div>
    <div class="card-body p-0">

        <?php if(empty($lead_tasks)): ?>
        <p class="text-muted small p-3 mb-0">No tasks linked to this lead.</p>
        <?php else: ?>

        <table class="table table-sm table-borderless mb-0">
            <thead class="thead-light">
                <tr>
                    <th>Title</th>
                    <th>Priority</th>
                    <th>Due</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($lead_tasks as $t):
                $isOverdue = $t['status']==='pending' && !empty($t['due_date']) && $t['due_date'] < date('Y-m-d H:i:s');
            ?>
            <tr class="<?= $isOverdue ? 'table-danger' : '' ?>">
                <td>
                    <?= htmlspecialchars($t['title']) ?>
                    <?php if($isOverdue){ ?><span class="badge badge-danger badge-sm">Overdue</span><?php } ?>
                </td>
                <td><span class="badge badge-<?= $priorityColors[$t['priority']] ?>"><?= ucfirst($t['priority']) ?></span></td>
                <td class="small"><?= !empty($t['due_date']) ? date('M j, g:iA', strtotime($t['due_date'])) : '—' ?></td>
                <td>
                    <?php if($t['status']==='completed'){ ?>
                    <span class="badge badge-success">Done</span>
                    <?php } else { ?>
                    <span class="badge badge-secondary">Pending</span>
                    <?php } ?>
                </td>
                <td class="text-right">
                    <?php if($t['status']==='pending'){ ?>
                    <a href="../tasks/complete.php?id=<?= $t['id'] ?>" class="btn btn-xs btn-success" title="Mark done">
                        <i class="fas fa-check"></i>
                    </a>
                    <?php } ?>
                    <a href="../tasks/form.php?id=<?= $t['id'] ?>" class="btn btn-xs btn-info">
                        <i class="fas fa-edit"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php endif; ?>
    </div>
</div>