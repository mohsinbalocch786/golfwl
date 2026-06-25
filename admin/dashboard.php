<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();
include("../layout/header.php");
include("../layout/sidebar.php");

$ow   = ownershipWhere('');
$lWhere = ownershipWhere('l');
$oWhere = ownershipWhere('o');
$tWhere = ownershipWhere('t');
$cWhere = ownershipWhere('c');
$gWhere = ownershipWhere('');

// Template visibility
if(isSuperAdmin()){
    $tmplWhere = "1=1";
} else {
    $uid  = currentUserId();
    $mid  = currentManagerId();
    $tp   = array("visibility='global'", "user_id=$uid");
    if(canViewTeam()) $tp[] = "(visibility='team' AND manager_id=$mid)";
    $tmplWhere = "(" . implode(" OR ", $tp) . ")";
}

// ── KPI counts ────────────────────────────────────────────────
$total_contacts  = (int)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) n FROM contacts WHERE $cWhere"))['n'];
$total_groups    = (int)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) n FROM contact_groups WHERE $gWhere"))['n'];
$total_campaigns = (int)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) n FROM campaigns WHERE $ow"))['n'];
$total_leads     = (int)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) n FROM leads l WHERE $lWhere"))['n'];
$total_opps      = (int)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) n FROM opportunities o WHERE $oWhere"))['n'];
$open_tasks      = (int)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) n FROM tasks t WHERE $tWhere AND t.status='pending'"))['n'];
$overdue_tasks   = (int)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) n FROM tasks t WHERE $tWhere AND t.status='pending' AND t.due_date < NOW()"))['n'];
$unread_sms      = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) n FROM lead_sms m JOIN leads l ON l.id=m.lead_id WHERE m.direction='inbound' AND m.read_at IS NULL AND ($lWhere)"))['n'];

// Pipeline value
$pv = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(o.amount),0) v FROM opportunities o WHERE $oWhere AND o.stage NOT IN ('won','lost')"));
$pipeline_value = (float)$pv['v'];
$wv = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(o.amount),0) v FROM opportunities o WHERE $oWhere AND o.stage='won'"));
$won_value = (float)$wv['v'];

// Campaign status breakdown
$campStat = array();
$csq = mysqli_query($conn,"SELECT status, COUNT(*) n FROM campaigns WHERE $ow GROUP BY status");
while($row = mysqli_fetch_assoc($csq)) $campStat[$row['status']] = (int)$row['n'];

// ── Campaign queue stats (email) ──────────────────────────────
$qSent      = (int)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) n FROM campaign_queue q JOIN campaigns c ON c.id=q.campaign_id WHERE $ow AND q.status='sent'"))['n'];
$qOpened    = (int)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) n FROM campaign_queue q JOIN campaigns c ON c.id=q.campaign_id WHERE $ow AND q.opened=1"))['n'];
$qClicked   = (int)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) n FROM campaign_queue q JOIN campaigns c ON c.id=q.campaign_id WHERE $ow AND q.clicked=1"))['n'];
$qBounced   = (int)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) n FROM campaign_queue q JOIN campaigns c ON c.id=q.campaign_id WHERE $ow AND q.bounced=1"))['n'];
$qSmsSent   = (int)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) n FROM campaign_queue q JOIN campaigns c ON c.id=q.campaign_id WHERE $ow AND q.sms_sent=1"))['n'];
$qSmsDeliv  = (int)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) n FROM campaign_queue q JOIN campaigns c ON c.id=q.campaign_id WHERE $ow AND q.sms_delivered=1"))['n'];

// ── Leads by status ───────────────────────────────────────────
$leadStatus = array();
$lsq = mysqli_query($conn,"SELECT l.status, COUNT(*) n FROM leads l WHERE $lWhere GROUP BY l.status");
while($row = mysqli_fetch_assoc($lsq)) $leadStatus[$row['status']] = (int)$row['n'];

// ── Opportunities by stage ────────────────────────────────────
$oppStage = array();
$osq = mysqli_query($conn,"SELECT o.stage, COUNT(*) n, COALESCE(SUM(o.amount),0) v FROM opportunities o WHERE $oWhere GROUP BY o.stage");
while($row = mysqli_fetch_assoc($osq)){
    $oppStage[$row['stage']] = array('n'=>(int)$row['n'], 'v'=>(float)$row['v']);
}

// ── Last 12 months campaign sends (for line chart) ────────────
$monthlySends = array();
$mq = mysqli_query($conn,
    "SELECT DATE_FORMAT(q.sent_at,'%Y-%m') mo, COUNT(*) n
     FROM campaign_queue q
     JOIN campaigns c ON c.id=q.campaign_id
     WHERE $ow AND q.status='sent' AND q.sent_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
     GROUP BY mo ORDER BY mo ASC"
);
while($row = mysqli_fetch_assoc($mq)) $monthlySends[$row['mo']] = (int)$row['n'];

// Fill gaps for last 12 months
$months12 = array(); $counts12 = array();
for($i = 11; $i >= 0; $i--){
    $key = date('Y-m', strtotime("-$i months"));
    $months12[] = date('M Y', strtotime("-$i months"));
    $counts12[] = isset($monthlySends[$key]) ? $monthlySends[$key] : 0;
}

// ── Recent 5 campaigns ────────────────────────────────────────
$recentCamps = array();
$rcq = mysqli_query($conn,
    "SELECT c.id, c.name, c.type, c.status, c.schedule_datetime,
            COUNT(q.id) total_q,
            SUM(q.opened) opened,
            SUM(q.sms_delivered) sms_delivered
     FROM campaigns c
     LEFT JOIN campaign_queue q ON q.campaign_id=c.id
     WHERE $ow
     GROUP BY c.id
     ORDER BY c.id DESC LIMIT 5"
);
while($row = mysqli_fetch_assoc($rcq)) $recentCamps[] = $row;

// ── Recent leads ─────────────────────────────────────────────
$recentLeads = array();
$rlq = mysqli_query($conn,
    "SELECT l.id, l.first_name, l.last_name, l.status, l.created_at, l.source
     FROM leads l WHERE $lWhere ORDER BY l.id DESC LIMIT 5"
);
while($row = mysqli_fetch_assoc($rlq)) $recentLeads[] = $row;

// ── Overdue tasks ─────────────────────────────────────────────
$overdueTasks = array();
$otq = mysqli_query($conn,
    "SELECT t.id, t.title, t.due_date, t.priority, u.name owner_name
     FROM tasks t
     LEFT JOIN users u ON u.id=t.user_id
     WHERE $tWhere AND t.status='pending' AND t.due_date < NOW()
     ORDER BY t.due_date ASC LIMIT 5"
);
while($row = mysqli_fetch_assoc($otq)) $overdueTasks[] = $row;

$statusColors = array(
    'new'=>'secondary','contacted'=>'info','qualified'=>'primary',
    'proposal'=>'warning','won'=>'success','lost'=>'danger'
);

// ── Cron health check ─────────────────────────────────────────
$cronWarnings = array();
if (isSuperAdmin() || isManager()) {
    $cronJobs = array('send_campaigns', 'workflow_engine');
    foreach ($cronJobs as $job) {
        $hq = mysqli_query($conn, "SELECT last_run, status FROM cron_heartbeat WHERE job_name='$job' LIMIT 1");
        $hrow = $hq ? mysqli_fetch_assoc($hq) : null;
        if (!$hrow || is_null($hrow['last_run'])) {
            $cronWarnings[] = "⚠ Cron job <strong>$job</strong> has never run. Check your crontab.";
        } elseif (strtotime($hrow['last_run']) < (time() - 1800)) { // 30 min stale
            $ago = round((time() - strtotime($hrow['last_run'])) / 60);
            $cronWarnings[] = "⚠ Cron job <strong>$job</strong> last ran {$ago} minutes ago. May be stuck.";
        }
    }
}
?>

<section class="content-header">
<div class="container-fluid">
    <div class="row mb-2">
        <div class="col-sm-6"><h1>Dashboard</h1></div>
        <div class="col-sm-6">
            <a href="../leads/list.php" class="btn btn-sm btn-outline-secondary float-right" id="tour-btn">
                <i class="fas fa-question-circle mr-1"></i> Take a Tour
            </a>
        </div>
    </div>
</div>
</section>

<section class="content">
<div class="container-fluid">

<?php if (!empty($cronWarnings)): ?>
<div class="row">
    <div class="col-12">
        <?php foreach ($cronWarnings as $cw): ?>
        <div class="alert alert-warning alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            <i class="fas fa-exclamation-triangle mr-1"></i> <?php echo $cw; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ── Row 1: KPI Cards ───────────────────────────────────── -->
<div class="row" id="tour-kpi">

    <div class="col-lg-2 col-md-4 col-6">
        <a href="../contacts/list.php" class="text-decoration-none">
        <div class="small-box bg-info">
            <div class="inner"><h3><?php echo $total_contacts; ?></h3><p>Contacts</p></div>
            <div class="icon"><i class="fas fa-users"></i></div>
            <span class="small-box-footer">View <i class="fas fa-arrow-circle-right"></i></span>
        </div></a>
    </div>

    <div class="col-lg-2 col-md-4 col-6">
        <a href="../leads/list.php" class="text-decoration-none">
        <div class="small-box bg-primary">
            <div class="inner"><h3><?php echo $total_leads; ?></h3><p>Leads</p></div>
            <div class="icon"><i class="fas fa-bullseye"></i></div>
            <span class="small-box-footer">View <i class="fas fa-arrow-circle-right"></i></span>
        </div></a>
    </div>

    <div class="col-lg-2 col-md-4 col-6">
        <a href="../opportunities/pipeline.php" class="text-decoration-none">
        <div class="small-box bg-warning">
            <div class="inner"><h3>$<?php echo number_format($pipeline_value,0); ?></h3><p>Pipeline Value</p></div>
            <div class="icon"><i class="fas fa-funnel-dollar"></i></div>
            <span class="small-box-footer">Pipeline <i class="fas fa-arrow-circle-right"></i></span>
        </div></a>
    </div>

    <div class="col-lg-2 col-md-4 col-6">
        <a href="../campaigns/list.php" class="text-decoration-none">
        <div class="small-box bg-success">
            <div class="inner"><h3><?php echo $total_campaigns; ?></h3><p>Campaigns</p></div>
            <div class="icon"><i class="fas fa-bullhorn"></i></div>
            <span class="small-box-footer">View <i class="fas fa-arrow-circle-right"></i></span>
        </div></a>
    </div>

    <div class="col-lg-2 col-md-4 col-6">
        <a href="../tasks/list.php?status=pending" class="text-decoration-none">
        <div class="small-box <?php echo $overdue_tasks > 0 ? 'bg-danger' : 'bg-secondary'; ?>">
            <div class="inner">
                <h3><?php echo $open_tasks; ?></h3>
                <p>Open Tasks <?php if($overdue_tasks){ echo '<small>('.$overdue_tasks.' overdue)</small>'; } ?></p>
            </div>
            <div class="icon"><i class="fas fa-tasks"></i></div>
            <span class="small-box-footer">View <i class="fas fa-arrow-circle-right"></i></span>
        </div></a>
    </div>

    <div class="col-lg-2 col-md-4 col-6">
        <a href="../leads/list.php" class="text-decoration-none">
        <div class="small-box <?php echo $unread_sms > 0 ? 'bg-danger' : 'bg-secondary'; ?>">
            <div class="inner"><h3><?php echo $unread_sms; ?></h3><p>Unread SMS</p></div>
            <div class="icon"><i class="fas fa-sms"></i></div>
            <span class="small-box-footer">View Chats <i class="fas fa-arrow-circle-right"></i></span>
        </div></a>
    </div>

</div>

<!-- ── Row 2: Email stats + Sends chart ──────────────────── -->
<div class="row" id="tour-charts">

    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-envelope mr-1"></i> Email Performance</h3></div>
            <div class="card-body p-2">
                <canvas id="emailPerfChart" height="180"></canvas>
            </div>
            <div class="card-footer p-2 d-flex justify-content-around text-center">
                <div><div class="text-primary font-weight-bold"><?php echo $qSent; ?></div><small>Sent</small></div>
                <div><div class="text-success font-weight-bold"><?php echo $qOpened; ?></div><small>Opened</small></div>
                <div><div class="text-info font-weight-bold"><?php echo $qClicked; ?></div><small>Clicked</small></div>
                <div><div class="text-danger font-weight-bold"><?php echo $qBounced; ?></div><small>Bounced</small></div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-sms mr-1"></i> SMS Performance</h3></div>
            <div class="card-body p-2">
                <canvas id="smsPerfChart" height="180"></canvas>
            </div>
            <div class="card-footer p-2 d-flex justify-content-around text-center">
                <div><div class="text-primary font-weight-bold"><?php echo $qSmsSent; ?></div><small>Sent</small></div>
                <div><div class="text-success font-weight-bold"><?php echo $qSmsDeliv; ?></div><small>Delivered</small></div>
                <div><div class="text-warning font-weight-bold"><?php echo $unread_sms; ?></div><small>Unread Replies</small></div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-chart-pie mr-1"></i> Lead Status</h3></div>
            <div class="card-body p-2">
                <canvas id="leadStatusChart" height="180"></canvas>
            </div>
        </div>
    </div>

</div>

<!-- ── Row 3: Monthly sends line + Opportunity stages ─────── -->
<div class="row">

    <div class="col-md-7">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-chart-line mr-1"></i> Messages Sent (Last 12 Months)</h3></div>
            <div class="card-body p-2">
                <canvas id="monthlySendsChart" height="100"></canvas>
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-funnel-dollar mr-1"></i> Pipeline by Stage</h3>
                <div class="card-tools">
                    <span class="badge badge-success">Won: $<?php echo number_format($won_value,0); ?></span>
                </div>
            </div>
            <div class="card-body p-2">
                <canvas id="pipelineChart" height="150"></canvas>
            </div>
        </div>
    </div>

</div>

<!-- ── Row 4: Recent campaigns + Recent leads + Overdue tasks ─ -->
<div class="row" id="tour-recent">

    <!-- Recent Campaigns -->
    <div class="col-md-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0"><i class="fas fa-bullhorn mr-1"></i> Recent Campaigns</h3>
                <a href="../campaigns/list.php" class="btn btn-xs btn-outline-secondary">View All</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-borderless mb-0">
                    <thead class="thead-light"><tr><th>Name</th><th>Type</th><th>Status</th><th>Sent</th><th>Opened</th></tr></thead>
                    <tbody>
                    <?php foreach($recentCamps as $c){ ?>
                    <tr>
                        <td><a href="../campaigns/edit.php?id=<?php echo $c['id']; ?>" style="display:none;"></a>
                            <?php echo htmlspecialchars($c['name']); ?></td>
                        <td><span class="badge badge-<?php echo $c['type']==='email'?'primary':'success'; ?>"><?php echo strtoupper($c['type']); ?></span></td>
                        <td>
                            <?php
                            $sc = array('pending'=>'secondary','in-progress'=>'warning','completed'=>'success');
                            $cls = isset($sc[$c['status']]) ? $sc[$c['status']] : 'secondary';
                            echo '<span class="badge badge-'.$cls.'">'.ucfirst($c['status']).'</span>';
                            ?>
                        </td>
                        <td><?php echo (int)$c['total_q']; ?></td>
                        <td><?php echo $c['type']==='email' ? (int)$c['opened'] : (int)$c['sms_delivered']; ?></td>
                    </tr>
                    <?php } ?>
                    <?php if(empty($recentCamps)){ ?>
                    <tr><td colspan="5" class="text-center text-muted small p-3">No campaigns yet.</td></tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Leads -->
    <div class="col-md-4" id="tour-leads">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0"><i class="fas fa-bullseye mr-1"></i> Recent Leads</h3>
                <a href="../leads/list.php" class="btn btn-xs btn-outline-secondary">View All</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-borderless mb-0">
                    <thead class="thead-light"><tr><th>Name</th><th>Source</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach($recentLeads as $l){ ?>
                    <tr>
                        <td><a href="../leads/view.php?id=<?php echo $l['id']; ?>"><?php echo htmlspecialchars($l['first_name'].' '.$l['last_name']); ?></a></td>
                        <td><small><?php echo htmlspecialchars($l['source']); ?></small></td>
                        <td>
                            <?php $scl = isset($statusColors[$l['status']]) ? $statusColors[$l['status']] : 'secondary'; ?>
                            <span class="badge badge-<?php echo $scl; ?>"><?php echo ucfirst($l['status']); ?></span>
                        </td>
                    </tr>
                    <?php } ?>
                    <?php if(empty($recentLeads)){ ?>
                    <tr><td colspan="3" class="text-center text-muted small p-3">No leads yet.</td></tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Overdue Tasks -->
    <div class="col-md-3" id="tour-tasks">
        <div class="card <?php echo $overdue_tasks > 0 ? 'card-outline card-danger' : ''; ?>">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0"><i class="fas fa-exclamation-triangle mr-1 text-danger"></i> Overdue Tasks</h3>
                <a href="../tasks/list.php?status=pending" class="btn btn-xs btn-outline-secondary">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if(empty($overdueTasks)){ ?>
                <p class="text-muted text-center small p-3 mb-0"><i class="fas fa-check-circle text-success"></i> All clear!</p>
                <?php } else { ?>
                <ul class="list-unstyled mb-0">
                <?php foreach($overdueTasks as $t){ ?>
                    <li class="border-bottom px-3 py-2">
                        <a href="../tasks/form.php?id=<?php echo $t['id']; ?>" class="small font-weight-bold text-danger">
                            <?php echo htmlspecialchars($t['title']); ?>
                        </a>
                        <div class="text-muted" style="font-size:.7rem;">
                            Due: <?php echo date('M j g:iA', strtotime($t['due_date'])); ?>
                            <?php if($t['owner_name']){ echo '· '.$t['owner_name']; } ?>
                        </div>
                    </li>
                <?php } ?>
                </ul>
                <?php } ?>
            </div>
        </div>
    </div>

</div>
<!-- /row 4 -->

</div>
</section>

<?php include("../layout/footer.php"); ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<!-- Shepherd.js tour -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/shepherd.js@10.0.1/dist/css/shepherd.css">
<script src="https://cdn.jsdelivr.net/npm/shepherd.js@10.0.1/dist/js/shepherd.min.js"></script>

<script>
// ── Charts ─────────────────────────────────────────────────────────────────

// Email performance doughnut
new Chart(document.getElementById('emailPerfChart'), {
    type: 'doughnut',
    data: {
        labels: ['Sent','Opened','Clicked','Bounced'],
        datasets:[{ data:[<?php echo $qSent.','.$qOpened.','.$qClicked.','.$qBounced; ?>],
            backgroundColor:['#007bff','#28a745','#17a2b8','#dc3545'],
            borderWidth: 2 }]
    },
    options:{ plugins:{ legend:{ position:'bottom', labels:{ boxWidth:10, font:{ size:11 }}}}, cutout:'65%' }
});

// SMS performance doughnut
new Chart(document.getElementById('smsPerfChart'), {
    type: 'doughnut',
    data: {
        labels: ['Sent','Delivered','Unread Replies'],
        datasets:[{ data:[<?php echo $qSmsSent.','.$qSmsDeliv.','.$unread_sms; ?>],
            backgroundColor:['#6f42c1','#20c997','#ffc107'],
            borderWidth: 2 }]
    },
    options:{ plugins:{ legend:{ position:'bottom', labels:{ boxWidth:10, font:{ size:11 }}}}, cutout:'65%' }
});

// Lead status pie
var leadLabels = <?php
    $ll = array('new','contacted','qualified','proposal','won','lost');
    echo json_encode(array_map('ucfirst', $ll));
?>;
var leadData = <?php
    $ld = array();
    foreach(array('new','contacted','qualified','proposal','won','lost') as $s){
        $ld[] = isset($leadStatus[$s]) ? $leadStatus[$s] : 0;
    }
    echo json_encode($ld);
?>;
new Chart(document.getElementById('leadStatusChart'), {
    type: 'pie',
    data: {
        labels: leadLabels,
        datasets:[{ data: leadData,
            backgroundColor:['#6c757d','#17a2b8','#007bff','#ffc107','#28a745','#dc3545'],
            borderWidth: 2 }]
    },
    options:{ plugins:{ legend:{ position:'bottom', labels:{ boxWidth:10, font:{ size:11 }}}}}
});

// Monthly sends line chart
new Chart(document.getElementById('monthlySendsChart'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode($months12); ?>,
        datasets:[{
            label: 'Messages Sent',
            data: <?php echo json_encode($counts12); ?>,
            borderColor:'#007bff', backgroundColor:'rgba(0,123,255,.1)',
            fill: true, tension: 0.3, pointRadius: 4, pointHoverRadius: 6
        }]
    },
    options:{
        plugins:{ legend:{ display:false } },
        scales:{
            x:{ grid:{ display:false }, ticks:{ font:{ size:10 }}},
            y:{ beginAtZero:true, ticks:{ font:{ size:10 }, precision:0 }}
        }
    }
});

// Pipeline by stage bar chart
var stageLabels = ['New','Qualified','Proposal','Negotiation','Won','Lost'];
var stageValues = <?php
    $sv = array();
    foreach(array('new','qualified','proposal','negotiation','won','lost') as $s){
        $sv[] = isset($oppStage[$s]) ? $oppStage[$s]['v'] : 0;
    }
    echo json_encode($sv);
?>;
var stageCounts = <?php
    $sc2 = array();
    foreach(array('new','qualified','proposal','negotiation','won','lost') as $s){
        $sc2[] = isset($oppStage[$s]) ? $oppStage[$s]['n'] : 0;
    }
    echo json_encode($sc2);
?>;
new Chart(document.getElementById('pipelineChart'), {
    type: 'bar',
    data: {
        labels: stageLabels,
        datasets:[{
            label: 'Value ($)',
            data: stageValues,
            backgroundColor:['#6c757d','#17a2b8','#ffc107','#6f42c1','#28a745','#dc3545'],
            borderRadius: 4
        }]
    },
    options:{
        plugins:{ legend:{ display:false },
            tooltip:{ callbacks:{ label: function(ctx){ return ' $'+ctx.parsed.y.toLocaleString()+' ('+stageCounts[ctx.dataIndex]+' deals)'; }}}},
        scales:{
            x:{ grid:{ display:false }, ticks:{ font:{ size:10 }}},
            y:{ beginAtZero:true, ticks:{ font:{ size:10 }, callback: function(v){ return '$'+v.toLocaleString(); }}}
        }
    }
});

// ── Shepherd.js Tour ────────────────────────────────────────────────────────
var tour = new Shepherd.Tour({
    useModalOverlay: true,
    defaultStepOptions: {
        classes: 'shadow-md bg-purple',
        scrollTo: { behavior: 'smooth', block: 'center' },
        cancelIcon: { enabled: true },
        buttons: [
            { text: 'Back',   action: function(){ tour.back();  }, secondary: true },
            { text: 'Next →', action: function(){ tour.next();  } }
        ]
    }
});

tour.addStep({ id:'welcome', title:'👋 Welcome to Your Dashboard',
    text:'This is your command centre. Let\'s take a quick tour of all the key sections.',
    attachTo:{ element: 'h1', on: 'bottom' },
    buttons:[{ text:'Start Tour', action: function(){ tour.next(); } }]
});

tour.addStep({ id:'kpi', title:'📊 KPI Cards',
    text:'At a glance: total contacts, leads, pipeline value, campaigns, open tasks, and unread SMS replies. Click any card to drill in.',
    attachTo:{ element:'#tour-kpi', on:'bottom' }
});

tour.addStep({ id:'charts', title:'📈 Performance Charts',
    text:'Email open/click/bounce rates, SMS delivery stats, and lead status breakdown — all scoped to your own data (or your team\'s if you\'re a manager).',
    attachTo:{ element:'#tour-charts', on:'bottom' }
});

tour.addStep({ id:'monthly', title:'📅 Monthly Sends',
    text:'The line chart tracks messages sent over the last 12 months so you can spot trends and seasonal patterns.',
    attachTo:{ element:'#monthlySendsChart', on:'top' }
});

tour.addStep({ id:'pipeline', title:'💼 Pipeline by Stage',
    text:'Each bar shows the total dollar value of opportunities in that stage. Hover for deal count. The "Won" total is shown in green.',
    attachTo:{ element:'#pipelineChart', on:'top' }
});

tour.addStep({ id:'recent', title:'🗂 Recent Activity',
    text:'Your latest campaigns, newest leads, and any overdue tasks are shown here so nothing falls through the cracks.',
    attachTo:{ element:'#tour-recent', on:'top' }
});

tour.addStep({ id:'leads-nav', title:'🎯 Leads Module',
    text:'Use the Leads link in the sidebar to manage your sales pipeline. Each lead has a full view with SMS chat, email, tasks, and a pipeline progress bar.',
    attachTo:{ element:'.nav-link.<?php echo isActive("leads") ? "active" : ""; ?>', on:'right' }
});

tour.addStep({ id:'sms-badge', title:'💬 Live SMS Badge',
    text:'The red badge on Leads shows how many unread inbound SMS replies you have. It refreshes every 30 seconds automatically.',
    attachTo:{ element:'#sms-unread-badge', on:'right' }
});

tour.addStep({ id:'done', title:'✅ You\'re all set!',
    text:'You can restart this tour anytime using the "Take a Tour" button in the top right of the dashboard.',
    attachTo:{ element:'#tour-btn', on:'bottom' },
    buttons:[
        { text: 'Back',   action: function(){ tour.back(); }, secondary: true },
        { text: 'Finish', action: function(){ tour.complete(); } }
    ]
});

document.getElementById('tour-btn').addEventListener('click', function(){ tour.start(); });

// Auto-start on first visit (session-based via localStorage)
if(!localStorage.getItem('dashboard_tour_done')){
    setTimeout(function(){ tour.start(); }, 800);
    tour.on('complete', function(){ localStorage.setItem('dashboard_tour_done','1'); });
    tour.on('cancel',   function(){ localStorage.setItem('dashboard_tour_done','1'); });
}
</script>
