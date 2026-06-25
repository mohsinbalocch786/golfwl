<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

include("../layout/header.php");
include("../layout/sidebar.php");

$where = ownershipWhere('o');

// Owner filter (manager/admin only)
$ownerFilter = "";
if(!empty($_GET['owner']) && (isManager() || isSuperAdmin())){
    $owner_id = (int)$_GET['owner'];
    $ownerFilter = " AND o.user_id = $owner_id";
}

$stages = [
    'new'         => ['label'=>'New',         'color'=>'#6c757d'],
    'qualified'   => ['label'=>'Qualified',   'color'=>'#17a2b8'],
    'proposal'    => ['label'=>'Proposal',    'color'=>'#ffc107'],
    'negotiation' => ['label'=>'Negotiation', 'color'=>'#6f42c1'],
    'won'         => ['label'=>'Won',         'color'=>'#28a745'],
    'lost'        => ['label'=>'Lost',        'color'=>'#dc3545'],
];

$r = mysqli_query($conn, "
    SELECT o.*, l.first_name, l.last_name, l.company, u.name as owner_name
    FROM opportunities o
    LEFT JOIN leads l ON l.id = o.lead_id
    LEFT JOIN users u ON u.id = o.user_id
    WHERE $where $ownerFilter
    ORDER BY o.created_at DESC
");

$grouped = array_fill_keys(array_keys($stages), []);
$allOpps = [];
while($row = mysqli_fetch_assoc($r)){
    $allOpps[] = $row;
    if(isset($grouped[$row['stage']])){
        $grouped[$row['stage']][] = $row;
    }
}

// Pipeline value (exclude 'lost')
$totalValue = 0;
foreach($allOpps as $o){
    if($o['stage'] !== 'lost') $totalValue += (float)$o['amount'];
}

$members = (isManager() || isSuperAdmin()) ? teamMembers($conn) : [];

// CSRF token for AJAX stage update
if(empty($_SESSION['csrf'])){
    $_SESSION['csrf'] = bin2hex(openssl_random_pseudo_bytes(16));
}
?>

<div class="row">
    <div class="col-12">

        <div class="card">

            <div class="card-header">
                <h3 class="card-title">Opportunity Pipeline</h3>

                <div class="card-tools">
                    <span class="mr-3"><strong>Pipeline Value:</strong> $<?= number_format($totalValue, 2) ?></span>
                    <a href="form.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> New Opportunity
                    </a>
                </div>
            </div>

            <div class="card-body">

                <?php if(!empty($members) && (isManager() || isSuperAdmin())){ ?>
                <form method="GET" class="mb-3">
                    <div class="row">
                        <div class="col-md-2">
                            <label>Owner</label>
                            <select name="owner" class="form-control" onchange="this.form.submit()">
                                <option value="">All</option>
                                <?php foreach($members as $m){ ?>
                                <option value="<?= $m['id'] ?>" <?= (isset($_GET['owner']) && (int)$_GET['owner']===(int)$m['id']) ? 'selected':'' ?>>
                                    <?= htmlspecialchars($m['name']) ?>
                                </option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                </form>
                <?php } ?>

                <div class="kanban-board d-flex" style="overflow-x:auto; gap:12px;">
                <?php foreach($stages as $stageKey => $stage){ ?>
                    <div class="kanban-column" data-stage="<?= $stageKey ?>" style="min-width:240px; background:#f4f6f9; border-radius:6px; flex-shrink:0;">

                        <div class="kanban-header" style="border-top:3px solid <?= $stage['color'] ?>; padding:10px; background:#fff; border-radius:6px 6px 0 0; display:flex; justify-content:space-between; align-items:center;">
                            <strong><?= $stage['label'] ?></strong>
                            <span class="badge badge-secondary kanban-count"><?= count($grouped[$stageKey]) ?></span>
                        </div>

                        <div class="kanban-total text-muted small p-2">
                            $<?= number_format(array_sum(array_column($grouped[$stageKey],'amount')), 0) ?>
                        </div>

                        <div class="kanban-cards" data-stage="<?= $stageKey ?>" style="min-height:150px; padding:8px; display:flex; flex-direction:column; gap:8px;">
                        <?php foreach($grouped[$stageKey] as $opp){ ?>
                            <div class="kanban-card card" draggable="true" data-id="<?= $opp['id'] ?>" style="cursor:grab;">
                                <div class="card-body p-2">
                                    <div class="font-weight-bold small mb-1"><?= htmlspecialchars($opp['title']) ?></div>
                                    <?php if($opp['first_name']){ ?>
                                    <div class="text-muted small">👤 <?= htmlspecialchars($opp['first_name'].' '.$opp['last_name']) ?></div>
                                    <?php } ?>
                                    <?php if($opp['company']){ ?>
                                    <div class="text-muted small">🏢 <?= htmlspecialchars($opp['company']) ?></div>
                                    <?php } ?>
                                    <?php if(canViewTeam() || isSuperAdmin()){ ?>
                                    <div class="text-muted small">Owner: <?= htmlspecialchars(isset($opp['owner_name']) ? $opp['owner_name'] : '-') ?></div>
                                    <?php } ?>
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <strong class="text-primary">$<?= number_format($opp['amount'],0) ?></strong>
                                        <span class="badge badge-light"><?= $opp['probability'] ?>%</span>
                                        <span class="text-muted small"><?= $opp['expected_close_date'] ? date('M d', strtotime($opp['expected_close_date'])) : '' ?></span>
                                    </div>
                                    <div class="mt-2 d-flex justify-content-between">
                                        <a href="form.php?id=<?= $opp['id'] ?>" class="btn btn-info btn-sm">Edit</a>
                                        <a href="delete.php?id=<?= $opp['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete?')">Del</a>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                        </div>

                    </div>
                <?php } ?>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include("../layout/footer.php"); ?>

<script>
(function() {
    let dragCard = null;
    const csrf = "<?= $_SESSION['csrf'] ?>";

    document.querySelectorAll('.kanban-card').forEach(card => {
        card.addEventListener('dragstart', () => {
            dragCard = card;
            card.classList.add('opacity-50');
        });
        card.addEventListener('dragend', () => card.classList.remove('opacity-50'));
    });

    document.querySelectorAll('.kanban-cards').forEach(col => {
        col.addEventListener('dragover', e => e.preventDefault());

        col.addEventListener('drop', e => {
            e.preventDefault();
            if(!dragCard) return;

            const newStage = col.dataset.stage;
            const id = dragCard.dataset.id;

            // move counts
            const oldCol = dragCard.closest('.kanban-cards');
            col.appendChild(dragCard);

            updateCount(oldCol);
            updateCount(col);

            fetch('update_stage.php', {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: `id=${encodeURIComponent(id)}&stage=${encodeURIComponent(newStage)}&csrf=${encodeURIComponent(csrf)}`
            });
        });
    });

    function updateCount(colEl){
        const column = colEl.closest('.kanban-column');
        const count = colEl.querySelectorAll('.kanban-card').length;
        column.querySelector('.kanban-count').textContent = count;
    }
})();
</script>