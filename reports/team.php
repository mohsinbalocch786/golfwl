<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

// Only managers / users with can_view_team / super admins
if(!isSuperAdmin() && !canViewTeam()){
    header("Location:../admin/dashboard.php");
    exit;
}

include("../layout/header.php");
include("../layout/sidebar.php");

// ── Team members scoping ────────────────────────────────────────
$members = teamMembers($conn);
$memberIds = array_column($members, 'id');
$idList = !empty($memberIds) ? implode(",", array_map('intval', $memberIds)) : "0";

// Date range filter (defaults to current month)
$start = !empty($_GET['start_date']) ? date('Y-m-d', strtotime($_GET['start_date'])) : date('Y-m-01');
$end   = !empty($_GET['end_date'])   ? date('Y-m-d', strtotime($_GET['end_date']))   : date('Y-m-t');

// ── Per-member contact / group / template counts ───────────────
$contactCounts = [];
$cq = mysqli_query($conn, "
    SELECT user_id, COUNT(*) as total
    FROM contacts
    WHERE user_id IN ($idList)
    GROUP BY user_id
");
while($row = mysqli_fetch_assoc($cq)){
    $contactCounts[$row['user_id']] = $row['total'];
}

$groupCounts = [];
$gq = mysqli_query($conn, "
    SELECT user_id, COUNT(*) as total
    FROM contact_groups
    WHERE user_id IN ($idList)
    GROUP BY user_id
");
while($row = mysqli_fetch_assoc($gq)){
    $groupCounts[$row['user_id']] = $row['total'];
}

$templateCounts = [];
$tq = mysqli_query($conn, "
    SELECT user_id, COUNT(*) as total
    FROM templates
    WHERE user_id IN ($idList)
    GROUP BY user_id
");
while($row = mysqli_fetch_assoc($tq)){
    $templateCounts[$row['user_id']] = $row['total'];
}

// ── Campaign counts & status breakdown per member (date scoped) ─
$campCounts = [];
$campStatusCounts = [];
$campq = mysqli_query($conn, "
    SELECT user_id, status, COUNT(*) as total
    FROM campaigns
    WHERE user_id IN ($idList)
    AND DATE(created_at) BETWEEN '$start' AND '$end'
    GROUP BY user_id, status
");
while($row = mysqli_fetch_assoc($campq)){
    $uid = $row['user_id'];
    $campCounts[$uid] = (isset($campCounts[$uid]) ? $campCounts[$uid] : 0) + $row['total'];
    $campStatusCounts[$uid][$row['status']] = $row['total'];
}

// ── Queue / send performance per member (date scoped) ──────────
$queueStats = [];
$qq = mysqli_query($conn, "
    SELECT
        q.user_id,
        COUNT(*) as total,
        SUM(CASE WHEN q.status='sent' THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN q.status='failed' THEN 1 ELSE 0 END) as failed,
        SUM(CASE WHEN q.status='pending' THEN 1 ELSE 0 END) as pending,
        SUM(q.opened) as opened,
        SUM(q.clicked) as clicked,
        SUM(q.bounced) as bounced
    FROM campaign_queue q
    WHERE q.user_id IN ($idList)
    AND DATE(q.sent_at) BETWEEN '$start' AND '$end'
    GROUP BY q.user_id
");
while($row = mysqli_fetch_assoc($qq)){
    $queueStats[$row['user_id']] = $row;
}

// ── Overall totals ───────────────────────────────────────────────
$totalContacts  = array_sum($contactCounts);
$totalGroups    = array_sum($groupCounts);
$totalTemplates = array_sum($templateCounts);
$totalCampaigns = array_sum($campCounts);
$totalSent      = array_sum(array_column($queueStats, 'sent'));
$totalOpened    = array_sum(array_column($queueStats, 'opened'));
$totalClicked   = array_sum(array_column($queueStats, 'clicked'));
?>

<div class="row">
    <div class="col-12">

        <div class="card card-primary">

            <div class="card-header">
                <h3 class="card-title">Team Reports</h3>
            </div>

            <div class="card-body">

                <!-- Date Range Filter -->
                <form method="GET" id="filterForm" class="mb-4">
                    <div class="row">
                        <div class="col-md-2">
                            <label>Date Range</label>
                            <input type="text" id="daterange" class="form-control" placeholder="MM/DD/YYYY - MM/DD/YYYY">
                            <input type="hidden" name="start_date" id="start_date" value="<?= htmlspecialchars($start) ?>">
                            <input type="hidden" name="end_date" id="end_date" value="<?= htmlspecialchars($end) ?>">
                        </div>
                        <div class="col-md-2" style="margin-top: 2rem;">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary w-50">Filter</button>
                                <a href="team.php" class="btn btn-secondary w-50 ml-2">Reset</a>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Overview Cards -->
                <div class="row">

                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h3><?= $totalContacts ?></h3>
                                <p>Total Contacts</p>
                            </div>
                            <div class="icon"><i class="fas fa-users"></i></div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h3><?= $totalGroups ?></h3>
                                <p>Total Groups</p>
                            </div>
                            <div class="icon"><i class="fas fa-layer-group"></i></div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-warning">
                            <div class="inner">
                                <h3><?= $totalCampaigns ?></h3>
                                <p>Campaigns (in range)</p>
                            </div>
                            <div class="icon"><i class="fas fa-bullhorn"></i></div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-danger">
                            <div class="inner">
                                <h3><?= $totalSent ?></h3>
                                <p>Messages Sent (in range)</p>
                                <div class="mt-2">
                                    <span class="badge badge-light">Opened: <?= $totalOpened ?></span>
                                    <span class="badge badge-light">Clicked: <?= $totalClicked ?></span>
                                </div>
                            </div>
                            <div class="icon"><i class="fas fa-paper-plane"></i></div>
                        </div>
                    </div>

                </div>

                <!-- Per Team Member breakdown -->
                <h4 class="mt-3">Team Member Breakdown</h4>

                <table id="teamTable" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Contacts</th>
                            <th>Groups</th>
                            <th>Templates</th>
                            <th>Campaigns</th>
                            <th>Pending</th>
                            <th>In-Progress</th>
                            <th>Completed</th>
                            <th>Sent</th>
                            <th>Opened</th>
                            <th>Clicked</th>
                            <th>Failed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($members as $m){
                            $uid = $m['id'];
                            $qs = isset($queueStats[$uid]) ? $queueStats[$uid] : ['sent'=>0,'opened'=>0,'clicked'=>0,'failed'=>0,'pending'=>0];
                            $cs = isset($campStatusCounts[$uid]) ? $campStatusCounts[$uid] : [];
                        ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($m['name']) ?>
                                <?= $uid == currentUserId() ? '<span class="badge badge-secondary">me</span>' : '' ?>
                            </td>
                            <td>
                                <?php if($m['role']=='manager'){ ?>
                                <span class="badge badge-primary">Manager</span>
                                <?php } else { ?>
                                <span class="badge badge-secondary">User</span>
                                <?php } ?>
                            </td>
                            <td><?= isset($contactCounts[$uid]) ? $contactCounts[$uid] : 0 ?></td>
                            <td><?= isset($groupCounts[$uid]) ? $groupCounts[$uid] : 0 ?></td>
                            <td><?= isset($templateCounts[$uid]) ? $templateCounts[$uid] : 0 ?></td>
                            <td><?= isset($campCounts[$uid]) ? $campCounts[$uid] : 0 ?></td>
                            <td><?= isset($cs['pending']) ? $cs['pending'] : 0 ?></td>
                            <td><?= isset($cs['in-progress']) ? $cs['in-progress'] : 0 ?></td>
                            <td><?= isset($cs['completed']) ? $cs['completed'] : 0 ?></td>
                            <td><?= isset($qs['sent']) ? $qs['sent'] : 0 ?></td>
                            <td><?= isset($qs['opened']) ? $qs['opened'] : 0 ?></td>
                            <td><?= isset($qs['clicked']) ? $qs['clicked'] : 0 ?></td>
                            <td><?= isset($qs['failed']) ? $qs['failed'] : 0 ?></td>
                        </tr>
                        <?php } ?>

                        <?php if(empty($members)){ ?>
                        <tr><td colspan="13" class="text-center">No team members found.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>

            </div>
        </div>
    </div>
</div>

<?php include("../layout/footer.php"); ?>

<script>
$(document).ready(function() {
    $('#teamTable').DataTable({
        pageLength: 25,
        order: [[0, "asc"]],
        processing: true,
        dom: 'Brtip',
        buttons: [
            { extend: 'csv' },
            { extend: 'excel' },
            { extend: 'print' }
        ]
    });
});

$(function() {

    let startVal = "<?= date('m/d/Y', strtotime($start)) ?>";
    let endVal   = "<?= date('m/d/Y', strtotime($end)) ?>";

    function setDates(start, end) {
        $('#daterange').val(start.format('MM/DD/YYYY') + ' - ' + end.format('MM/DD/YYYY'));
        $('#start_date').val(start.format('YYYY-MM-DD'));
        $('#end_date').val(end.format('YYYY-MM-DD'));
    }

    $('#daterange').daterangepicker({
        autoUpdateInput: false,
        locale: { format: 'MM/DD/YYYY', cancelLabel: 'Clear' },
        ranges: {
            'Today': [moment(), moment()],
            'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            'Last 7 Days': [moment().subtract(6, 'days'), moment()],
            'Last 30 Days': [moment().subtract(29, 'days'), moment()],
            'This Month': [moment().startOf('month'), moment().endOf('month')],
            'Last Month': [ moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month') ]
        },
    });

    $('#daterange').on('apply.daterangepicker', function(ev, picker) {
        setDates(picker.startDate, picker.endDate);
        $('#filterForm').submit();
    });

    $('#daterange').on('cancel.daterangepicker', function(ev, picker) {
        $(this).val('');
    });

    // initialize display value
    $('#daterange').val(startVal + ' - ' + endVal);
});
</script>
