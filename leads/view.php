<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

$id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
if (!$id) {
    header("Location: list.php");
    exit;
}

$r = mysqli_query($conn, "SELECT l.*, c.name AS contact_name, c.email AS contact_email, c.phone AS contact_phone
    FROM leads l
    LEFT JOIN contacts c ON c.id = l.contact_id
    WHERE l.id = '$id'");
$lead = mysqli_fetch_assoc($r);

if (!$lead) {
    header("Location: list.php");
    exit;
}
assertOwnership($lead);

// ── Handle quick status update via POST ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrf();

    if ($_POST['action'] === 'update_status') {
        $validStatuses = ['new', 'contacted', 'qualified', 'proposal', 'won', 'lost'];
        $new_status = isset($_POST['status']) ? $_POST['status'] : '';
        if (in_array($new_status, $validStatuses)) {
            mysqli_query($conn, "UPDATE leads SET status='$new_status', updated_at='$currentTime' WHERE id='$id'");
            $lead['status'] = $new_status;
        }
    }

    if ($_POST['action'] === 'add_note') {
        $note_text = mysqli_real_escape_string($conn, trim(isset($_POST['note_text']) ? $_POST['note_text'] : ''));
        if ($note_text !== '') {
            $uid = currentUserId();
            // log to unified interactions table
            mysqli_query($conn, "INSERT INTO lead_interactions
                (lead_id, user_id, type, direction, subject, body, status, created_at)
                VALUES ('$id', '$uid', 'note', 'outbound', 'Note', '$note_text', 'sent', '$currentTime')");
            // also write to lead_notes for backward compat
            mysqli_query($conn, "INSERT INTO lead_notes (lead_id, user_id, note, created_at)
                VALUES ('$id', '$uid', '$note_text', '$currentTime')");
        }
    }

    if ($_POST['action'] === 'delete_note') {
        $note_id = (int)(isset($_POST['note_id']) ? $_POST['note_id'] : 0);
        mysqli_query($conn, "DELETE FROM lead_notes WHERE id='$note_id' AND lead_id='$id'");
    }

    if ($_POST['action'] === 'delete_interaction') {
        $int_id = (int)(isset($_POST['int_id']) ? $_POST['int_id'] : 0);
        mysqli_query($conn, "DELETE FROM lead_interactions WHERE id='$int_id' AND lead_id='$id'");
    }

    header("Location: view.php?id=$id");
    exit;
}

// ── Fetch unified interactions timeline ──────────────────────────────────────
$iq = mysqli_query($conn, "
    SELECT i.*, u.name AS author
    FROM lead_interactions i
    LEFT JOIN users u ON u.id = i.user_id
    WHERE i.lead_id = '$id'
    ORDER BY i.created_at DESC
");
$interactions = [];
while ($row = mysqli_fetch_assoc($iq)) $interactions[] = $row;

// ── Helpers ──────────────────────────────────────────────────────────────────
$statusColors = [
    'new'       => 'secondary',
    'contacted' => 'info',
    'qualified' => 'primary',
    'proposal'  => 'warning',
    'won'       => 'success',
    'lost'      => 'danger',
];
$statuses = ['new', 'contacted', 'qualified', 'proposal', 'won', 'lost'];

$pipelineStages = [
    ['key' => 'new',       'label' => 'Incoming Lead'],
    ['key' => 'contacted', 'label' => 'Contacted'],
    ['key' => 'qualified', 'label' => 'Qualified'],
    ['key' => 'proposal',  'label' => 'Proposal Sent'],
    ['key' => 'won',       'label' => 'Won'],
    ['key' => 'lost',      'label' => 'Lost'],
];
$stageOrder   = array_column($pipelineStages, 'key');
$currentIndex = array_search($lead['status'], $stageOrder);

$typeIcons = [
    'note'  => ['icon'=>'fas fa-sticky-note', 'color'=>'bg-info',    'label'=>'Note'],
    'email' => ['icon'=>'fas fa-envelope',    'color'=>'bg-primary', 'label'=>'Email'],
    'sms'   => ['icon'=>'fas fa-sms',         'color'=>'bg-success', 'label'=>'SMS'],
    'call'  => ['icon'=>'fas fa-phone-alt',   'color'=>'bg-warning', 'label'=>'Call'],
];

function statusBadge($status, array $map) {
    $cls = isset($map[$status]) ? $map[$status] : 'secondary';
    return '<span class="badge badge-' . $cls . '">' . ucfirst(htmlspecialchars($status)) . '</span>';
}

include("../layout/header.php");
include("../layout/sidebar.php");
?>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>
                    <?= htmlspecialchars(isset($lead['first_name']) ? $lead['first_name'] : '').' '.htmlspecialchars(isset($lead['last_name']) ? $lead['last_name'] : '') ?>
                    <?= statusBadge(isset($lead['status']) ? $lead['status'] : 'new', $statusColors) ?>
                </h1>
                <small class="text-muted">
                    Lead #<?= $id ?> &bull;
                    Created <?= !empty(isset($lead['created_at']) ? $lead['created_at'] : '') ? date('M j, Y', strtotime(isset($lead['created_at']) ? $lead['created_at'] : '')) : '—' ?>
                    &bull; Updated <?= !empty(isset($lead['updated_at']) ? $lead['updated_at'] : '') ? date('M j, Y g:i A', strtotime(isset($lead['updated_at']) ? $lead['updated_at'] : '')) : '—' ?>
                </small>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="../admin/dashboard.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="list.php">Leads</a></li>
                    <li class="breadcrumb-item active">View Lead</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
<div class="container-fluid">

<!-- ── Pipeline bar ──────────────────────────────────────────────────── -->
<div class="card card-outline card-primary mb-3">
    <div class="card-header py-2">
        <h3 class="card-title"><i class="fas fa-stream mr-1"></i> Sales Pipeline</h3>
    </div>
    <div class="card-body p-2">
        <div class="d-flex flex-wrap" style="gap:6px">
            <?php foreach ($pipelineStages as $i => $stage):
                $reached   = ($currentIndex !== false && $i <= $currentIndex);
                $isCurrent = ($stage['key'] === $lead['status']);
                if($isCurrent)       $btnClass = 'btn-primary';
                elseif($reached)     $btnClass = 'btn-outline-primary';
                else                 $btnClass = 'btn-outline-secondary';
            ?>
            <form method="post" style="margin:0">
<?php echo csrfField(); ?>
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="status" value="<?= $stage['key'] ?>">
                <button type="submit" class="btn btn-sm <?= $btnClass ?>"
                        <?= $isCurrent ? 'disabled' : '' ?>>
                    <?php if ($isCurrent): ?>
                        <i class="fas fa-circle mr-1" style="font-size:.6rem;vertical-align:middle"></i>
                    <?php elseif ($reached): ?>
                        <i class="fas fa-check mr-1"></i>
                    <?php endif; ?>
                    <?= htmlspecialchars($stage['label']) ?>
                </button>
            </form>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ── Two-column layout ─────────────────────────────────────────────── -->
<div class="row">

<!-- ── LEFT COLUMN ─────────────────────────────────────────────────── -->
<div class="col-md-4">

    <!-- Lead Info -->
    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-user mr-1"></i> Lead Info</h3>
            <div class="card-tools">
                <a href="form.php?id=<?= $id ?>" class="btn btn-sm btn-warning">
                    <i class="fas fa-edit"></i> Edit
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-borderless mb-0">
                <tr>
                    <th class="pl-3" width="38%">Name</th>
                    <td><?= htmlspecialchars(isset($lead['first_name']) ? $lead['first_name'] : '').' '.htmlspecialchars(isset($lead['last_name']) ? $lead['last_name'] : '') ?></td>
                </tr>
                <?php if (!empty(isset($lead['company']) ? $lead['company'] : '')): ?>
                <tr>
                    <th class="pl-3">Company</th>
                    <td><?= htmlspecialchars(isset($lead['company']) ? $lead['company'] : '') ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty(isset($lead['email']) ? $lead['email'] : '')): ?>
                <tr>
                    <th class="pl-3">Email</th>
                    <td><a href="mailto:<?= htmlspecialchars(isset($lead['email']) ? $lead['email'] : '') ?>"><?= htmlspecialchars(isset($lead['email']) ? $lead['email'] : '') ?></a></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty(isset($lead['phone']) ? $lead['phone'] : '')): ?>
                <tr>
                    <th class="pl-3">Phone</th>
                    <td><a href="tel:<?= htmlspecialchars(isset($lead['phone']) ? $lead['phone'] : '') ?>"><?= htmlspecialchars(isset($lead['phone']) ? $lead['phone'] : '') ?></a></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty(isset($lead['source']) ? $lead['source'] : '')): ?>
                <tr>
                    <th class="pl-3">Source</th>
                    <td><?= htmlspecialchars(isset($lead['source']) ? $lead['source'] : '') ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th class="pl-3">Status</th>
                    <td><?= statusBadge(isset($lead['status']) ? $lead['status'] : 'new', $statusColors) ?></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Linked Contact -->
    <?php if (!empty(isset($lead['contact_id']) ? $lead['contact_id'] : '')): ?>
    <div class="card card-info card-outline">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-address-book mr-1"></i> Linked Contact</h3>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-borderless mb-0">
                <tr>
                    <th class="pl-3" width="38%">Name</th>
                    <td>
                        <a href="../contacts/edit.php?id=<?= (int)$lead['contact_id'] ?>">
                            <?= htmlspecialchars(isset($lead['contact_name']) ? $lead['contact_name'] : '—') ?>
                        </a>
                    </td>
                </tr>
                <?php if (!empty(isset($lead['contact_email']) ? $lead['contact_email'] : '')): ?>
                <tr>
                    <th class="pl-3">Email</th>
                    <td><a href="mailto:<?= htmlspecialchars(isset($lead['contact_email']) ? $lead['contact_email'] : '') ?>"><?= htmlspecialchars(isset($lead['contact_email']) ? $lead['contact_email'] : '') ?></a></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty(isset($lead['contact_phone']) ? $lead['contact_phone'] : '')): ?>
                <tr>
                    <th class="pl-3">Phone</th>
                    <td><a href="tel:<?= htmlspecialchars(isset($lead['contact_phone']) ? $lead['contact_phone'] : '') ?>"><?= htmlspecialchars(isset($lead['contact_phone']) ? $lead['contact_phone'] : '') ?></a></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Twilio Browser Call Widget ──────────────────────────────── -->
    <?php
    $callPhone = isset($lead['phone']) ? $lead['phone'] : (isset($lead['contact_phone']) ? $lead['contact_phone'] : '');
    $numQ2 = mysqli_query($conn, "SELECT id, phone FROM twilio_numbers WHERE status='active' ORDER BY phone");
    $twilioNums = [];
    while($n = mysqli_fetch_assoc($numQ2)) $twilioNums[] = $n;
    ?>
    <div class="card card-outline card-warning" id="call-widget">
        <div class="card-header py-2">
            <h3 class="card-title"><i class="fas fa-phone-alt mr-1"></i> Call Lead</h3>
        </div>
        <div class="card-body">

            <div id="call-alert"></div>

            <div class="form-group mb-2">
                <label class="small font-weight-bold">From Number</label>
                <select id="call-from-number" class="form-control form-control-sm">
                    <option value="">— Default —</option>
                    <?php foreach($twilioNums as $n){ ?>
                    <option value="<?= $n['id'] ?>"><?= htmlspecialchars($n['phone']) ?></option>
                    <?php } ?>
                </select>
            </div>

            <div class="form-group mb-2">
                <label class="small font-weight-bold">To Number</label>
                <input type="text" id="call-to-phone" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($callPhone) ?>">
            </div>

            <!-- Call state UI -->
            <div id="call-idle">
                <button type="button" id="btn-start-call" class="btn btn-warning btn-sm btn-block">
                    <i class="fas fa-phone"></i> Start Call
                </button>
            </div>

            <div id="call-active" style="display:none;">
                <div class="alert alert-success mb-2 py-1 px-2 small">
                    <i class="fas fa-circle text-success"></i>
                    <strong>Call Active</strong> — <span id="call-timer">00:00</span>
                    <br><small id="call-sid-display" class="text-muted"></small>
                </div>
                <button type="button" id="btn-end-call" class="btn btn-danger btn-sm btn-block">
                    <i class="fas fa-phone-slash"></i> End Call
                </button>
            </div>

            <div id="call-note-form" style="display:none;" class="mt-2">
                <label class="small font-weight-bold">Call Notes</label>
                <textarea id="call-notes" class="form-control form-control-sm" rows="3"
                          placeholder="What was discussed?"></textarea>
                <button type="button" id="btn-save-call-note" class="btn btn-info btn-sm mt-1">
                    <i class="fas fa-save"></i> Save Call Notes
                </button>
            </div>

        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card card-outline card-secondary">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-bolt mr-1"></i> Quick Actions</h3>
        </div>
        <div class="card-body p-2 d-flex flex-wrap" style="gap:6px">
            <a href="form.php?id=<?= $id ?>" class="btn btn-sm btn-warning">
                <i class="fas fa-edit"></i> Edit
            </a>
            <?php if(!in_array($lead['status'], ['won','lost'])){ ?>
            <a href="convert.php?id=<?= $id ?>" class="btn btn-sm btn-success"
               onclick="return confirm('Convert this lead to an opportunity?')">
                <i class="fas fa-exchange-alt"></i> Convert
            </a>
            <?php } ?>
            <a href="list.php" class="btn btn-sm btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button type="button" class="btn btn-sm btn-danger" data-toggle="modal" data-target="#deleteModal">
                <i class="fas fa-trash"></i> Delete
            </button>
        </div>
    </div>

</div>
<!-- /LEFT COLUMN -->

<!-- ── RIGHT COLUMN ─────────────────────────────────────────────────── -->
<div class="col-md-8">

    <!-- ── Tabs ─────────────────────────────────────────────────────── -->
    <ul class="nav nav-tabs" id="leadTabs">
        <li class="nav-item">
            <a class="nav-link active" data-toggle="tab" href="#tab-notes">
                <i class="fas fa-sticky-note mr-1"></i> Notes
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#tab-email">
                <i class="fas fa-envelope mr-1"></i> Email
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#tab-sms">
                <i class="fas fa-sms mr-1"></i> SMS
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-toggle="tab" href="#tab-tasks">
                <i class="fas fa-tasks mr-1"></i> Tasks
            </a>
        </li>
    </ul>

    <div class="tab-content border border-top-0 p-3" style="background:#fff; border-radius:0 0 4px 4px;">

        <!-- Notes Tab -->
        <div class="tab-pane fade show active" id="tab-notes">
            <?php include('partials/lead_notes.php'); ?>
        </div>

        <!-- Email Tab -->
        <div class="tab-pane fade" id="tab-email">
            <?php include('partials/lead_email.php'); ?>
        </div>

        <!-- SMS Tab -->
        <div class="tab-pane fade" id="tab-sms">
            <?php include('partials/lead_sms.php'); ?>
        </div>

        <!-- Tasks Tab -->
        <div class="tab-pane fade" id="tab-tasks">
            <?php include('partials/lead_tasks.php'); ?>
        </div>

    </div>

    <!-- ── Unified Interactions Timeline ─────────────────────────── -->
    <div class="card card-outline card-secondary mt-3">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-history mr-1"></i> Interactions
                <span class="badge badge-secondary ml-1"><?= count($interactions) ?></span>
            </h3>
            <div class="card-tools">
                <div class="btn-group btn-group-sm" id="timeline-filter">
                    <button class="btn btn-outline-secondary active" data-filter="all">All</button>
                    <button class="btn btn-outline-secondary" data-filter="note">Notes</button>
                    <button class="btn btn-outline-secondary" data-filter="email">Email</button>
                    <button class="btn btn-outline-secondary" data-filter="sms">SMS</button>
                    <button class="btn btn-outline-secondary" data-filter="call">Calls</button>
                </div>
            </div>
        </div>
        <div class="card-body p-0">

            <?php if (empty($interactions)): ?>
            <p class="text-muted p-3 mb-0">No interactions yet. Add a note, send an email/SMS, or make a call.</p>
            <?php else: ?>

            <div class="timeline px-3 pt-3" id="interactions-timeline">

                <?php
                $lastDate = null;
                foreach ($interactions as $item):
                    $itemDate = date('Y-m-d', strtotime($item['created_at']));
                    $ti = isset($typeIcons[$item['type']]) ? $typeIcons[$item['type']] : ['icon'=>'fas fa-circle','color'=>'bg-secondary','label'=>ucfirst($item['type'])];
                ?>

                <?php if ($itemDate !== $lastDate): ?>
                <div class="time-label" data-type="<?= $item['type'] ?>">
                    <span class="bg-primary" style="font-size:.7rem">
                        <?= date('M j, Y', strtotime($item['created_at'])) ?>
                    </span>
                </div>
                <?php $lastDate = $itemDate; endif; ?>

                <div class="timeline-interaction" data-type="<?= $item['type'] ?>">
                    <i class="<?= $ti['icon'] ?> <?= $ti['color'] ?>"></i>

                    <div class="timeline-item">
                        <span class="time">
                            <i class="fas fa-clock"></i>
                            <?= date('g:i A', strtotime($item['created_at'])) ?>
                        </span>

                        <h3 class="timeline-header">
                            <span class="badge badge-secondary badge-sm mr-1"><?= $ti['label'] ?></span>
                            <strong><?= htmlspecialchars(isset($item['author']) ? $item['author'] : 'System') ?></strong>
                            <?php if(!empty(isset($item['subject']) ? $item['subject'] : '') && (isset($item['subject']) ? $item['subject'] : '') !== 'Note'): ?>
                            — <span class="text-muted"><?= htmlspecialchars(isset($item['subject']) ? $item['subject'] : '') ?></span>
                            <?php endif; ?>

                            <?php if(!empty(isset($item['status']) ? $item['status'] : '')): ?>
                                <?php if((isset($item['status']) ? $item['status'] : '')==='sent'): ?>
                                <span class="badge badge-success ml-1">Sent</span>
                                <?php elseif((isset($item['status']) ? $item['status'] : '')==='failed'): ?>
                                <span class="badge badge-danger ml-1">Failed</span>
                                <?php elseif((isset($item['status']) ? $item['status'] : '')==='pending'): ?>
                                <span class="badge badge-warning ml-1">Pending</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </h3>

                        <?php if(!empty(isset($item['body']) ? $item['body'] : '')): ?>
                        <div class="timeline-body" style="white-space:pre-wrap;max-height:120px;overflow:hidden;" id="body-<?= $item['id'] ?>">
                            <?php if((isset($item['type']) ? $item['type'] : '')==='email'): ?>
                            <div class="email-preview-text"><?= nl2br(htmlspecialchars(strip_tags(isset($item['body']) ? $item['body'] : ''))) ?></div>
                            <a href="#" class="show-full-email small" data-id="<?= $item['id'] ?>">Show full email</a>
                            <?php else: ?>
                            <?= nl2br(htmlspecialchars(isset($item['body']) ? $item['body'] : '')) ?>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php if(!empty(isset($item['ext_id']) ? $item['ext_id'] : '')): ?>
                        <div class="mt-1">
                            <small class="text-muted">Ref: <code><?= htmlspecialchars(isset($item['ext_id']) ? $item['ext_id'] : '') ?></code></small>
                        </div>
                        <?php endif; ?>

                        <div class="timeline-footer mt-1">
                            <form method="post" style="display:inline"
                                  onsubmit="return confirm('Delete this interaction?')">
<?php echo csrfField(); ?>
                                <input type="hidden" name="action"  value="delete_interaction">
                                <input type="hidden" name="int_id"  value="<?= (int)$item['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-xs">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <?php endforeach; ?>

                <div><i class="fas fa-clock bg-gray"></i></div>
            </div>

            <?php endif; ?>
        </div>
    </div>
    <!-- /timeline -->

</div>
<!-- /RIGHT COLUMN -->

</div>
<!-- /row -->

</div>
</section>

<!-- ── Delete confirmation modal ─────────────────────────────────────── -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle mr-1"></i> Delete Lead</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                Are you sure you want to permanently delete
                <strong><?= htmlspecialchars($lead['first_name'] . ' ' . $lead['last_name']) ?></strong>?
                This cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <a href="delete.php?id=<?= $id ?>" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Yes, Delete
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Full email modal -->
<div class="modal fade" id="emailBodyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Email Content</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" id="emailBodyContent"></div>
        </div>
    </div>
</div>

<?php include("../layout/footer.php"); ?>

<!-- Editor -->
<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js"></script>

<script>
tinymce.init({
selector:'#email-body',
height:350,
plugins:'link image code lists table',
toolbar:'undo redo | bold italic | alignleft aligncenter alignright | bullist numlist | link image | code'
});
</script>
<!-- /Editor -->
<script>
// ── Global helpers ──────────────────────────────────────────────────────────
window.showAlert = function(scope, type, msg) {
    var html = '<div class="alert alert-' + type + ' alert-dismissible fade show py-2">'
             + '<button type="button" class="close py-1" data-dismiss="alert"><span>&times;</span></button>'
             + msg + '</div>';
    $('#' + scope + '-alert').html(html);
};

$(function(){

    // Init select2 on email template
    if($.fn.select2){
        $('#email-template-sel').select2({ width:'100%' });
        $('#sms-template-sel').select2({ width:'100%' });
    }

    // ── Timeline type filter ────────────────────────────────────────────────
    $('#timeline-filter button').on('click', function(){
        var filter = $(this).data('filter');
        $('#timeline-filter button').removeClass('active');
        $(this).addClass('active');

        if(filter === 'all'){
            $('.timeline-interaction, .time-label').show();
        } else {
            $('.timeline-interaction').each(function(){
                $(this).toggle($(this).data('type') === filter);
            });
            // Show/hide date labels based on whether any items below them are visible
            $('.time-label').each(function(){
                var next = $(this).nextAll('.timeline-interaction:visible').first();
                $(this).toggle(next.length > 0);
            });
        }
    });

    // ── Show full email body in modal ───────────────────────────────────────
    $(document).on('click', '.show-full-email', function(e){
        e.preventDefault();
        var id = $(this).data('id');
        var body = $('#body-' + id).find('.email-preview-text').html();
        $('#emailBodyContent').html(body || 'No content');
        $('#emailBodyModal').modal('show');
    });

    // ── Twilio browser call ─────────────────────────────────────────────────
    var callTimer, callSeconds = 0, activeSid = null;

    function startTimer(){
        callSeconds = 0;
        clearInterval(callTimer);
        callTimer = setInterval(function(){
            callSeconds++;
            var m = String(Math.floor(callSeconds / 60)).padStart(2,'0');
            var s = String(callSeconds % 60).padStart(2,'0');
            $('#call-timer').text(m + ':' + s);
        }, 1000);
    }

    $('#btn-start-call').on('click', function(){
        var toPhone       = $('#call-to-phone').val().trim();
        var fromNumberId  = $('#call-from-number').val();
        if(!toPhone){
            showAlert('call', 'danger', 'Please enter a phone number to call.');
            return;
        }

        var $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Dialing…');

        $.post('ajax/make_call.php', {
            lead_id:        <?= $id ?>,
            to_phone:       toPhone,
            from_number_id: fromNumberId,
        }, function(res){
            if(res.ok){
                activeSid = res.sid;
                $('#call-sid-display').text('SID: ' + (res.sid || '—'));
                $('#call-idle').hide();
                $('#call-active').show();
                startTimer();
                showAlert('call', 'success', '📞 Call initiated to ' + toPhone);
            } else {
                showAlert('call', 'danger', res.error);
                $btn.prop('disabled', false).html('<i class="fas fa-phone"></i> Start Call');
            }
        }, 'json').fail(function(){
            showAlert('call', 'danger', 'Request failed.');
            $btn.prop('disabled', false).html('<i class="fas fa-phone"></i> Start Call');
        });
    });

    $('#btn-end-call').on('click', function(){
        clearInterval(callTimer);
        $('#call-active').hide();
        $('#call-note-form').show();
    });

    $('#btn-save-call-note').on('click', function(){
        var notes = $('#call-notes').val().trim();
        var duration = $('#call-timer').text();
        var body = 'Call duration: ' + duration + (notes ? '\n\n' + notes : '');

        $.post('../ajax/log_interaction.php', {
            lead_id: <?= $id ?>,
            type:    'call',
            subject: 'Call to <?= addslashes(htmlspecialchars(isset($lead['phone']) ? $lead['phone'] : (isset($lead['contact_phone']) ? $lead['contact_phone'] : ''))) ?>',
            body:    body,
        }, function(res){
            if(res.ok){
                location.reload();
            } else {
                alert('Could not save call notes: ' + res.error);
            }
        }, 'json');
    });

});
</script>
<!-- /Page-specific scripts email script -->
 <script>
$(function(){

    // Init select2 for template picker
    $('#email-template-sel').select2({ width: '100%' });

    // Load template on selection
    $('#email-template-sel').on('change', function(){
        var tid = $(this).val();
        if(!tid) return;
        $.getJSON('ajax/get_template.php', { template_id: tid, lead_id: <?= $id ?> }, function(res){
            if(!res.ok){ alert(res.error); return; }
            $('#email-subject').val(res.subject);
            $('#email-body').val(res.body);
            tinymce.triggerSave();
            tinymce.get('email-body').setContent(res.body);
            if(res.from_name)  $('#email-from-name').val(res.from_name);
            if(res.from_email) $('#email-from-email').val(res.from_email);
        });
    });

    // Send email
    $('#btn-send-email').on('click', function(){
        var $btn = $(this).prop('disabled', true).text('Sending…');
        var data = {
            lead_id:    <?= $id ?>,
            to_email:   $('#email-to').val().trim(),
            subject:    $('#email-subject').val().trim(),
            body:       $('#email-body').val(),
            from_name:  $('#email-from-name').val().trim(),
            from_email: $('#email-from-email').val().trim(),
        };
        if(!data.to_email || !data.subject || !data.body){
            showAlert('email', 'danger', 'To, Subject and Body are required.');
            $btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Send Email');
            return;
        }
        $.post('ajax/send_email.php', data, function(res){
            if(res.ok){
                showAlert('email', 'success', res.message);
                $('#email-subject').val('');
                $('#email-body').val('');
                $('#email-template-sel').val('').trigger('change.select2');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert('email', 'danger', res.error);
            }
            $btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Send Email');
        }, 'json').fail(function(){
            showAlert('email', 'danger', 'Request failed. Check console.');
            $btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Send Email');
        });
    });
});
</script>
<!-- /Page-specific scripts email end -->

<!-- /Page-specific scripts SMS start -->
 
<script>
(function () {
    var LEAD_ID   = <?php echo (int)$id; ?>;
    var TO_PHONE  = <?php echo json_encode($to_phone); ?>;
    var lastId    = <?php echo (int)$lastChatId; ?>;
    var pollTimer = null;
    var POLL_MS   = 5000; // poll every 5s

    var $window   = $('#sms-chat-window');
    var $input    = $('#chat-input');
    var $sendBtn  = $('#btn-chat-send');
    var $counter  = $('#chat-char-count');
    var $status   = $('#chat-poll-status');

    // ── Scroll to bottom ────────────────────────────────────────────
    function scrollBottom() {
        $window.scrollTop($window[0].scrollHeight);
    }
    scrollBottom();

    // ── Render a single bubble ───────────────────────────────────────
    function renderBubble(msg) {
        var isOut  = (msg.direction === 'outbound');
        var align  = isOut ? 'flex-end'    : 'flex-start';
        var bg     = isOut ? '#007bff'      : '#fff';
        var color  = isOut ? '#fff'          : '#333';
        var radius = isOut ? '18px 18px 4px 18px' : '18px 18px 18px 4px';
        var textAlign = isOut ? 'right' : 'left';
        var tick   = '';
        if (isOut) {
            tick = msg.status === 'sent'
                ? '<i class="fas fa-check ml-1 text-success"></i>'
                : (msg.status === 'failed' ? '<i class="fas fa-times ml-1 text-danger"></i>' : '');
        }
        var mediaHtml = '';
        if (msg.media_url) {
            var linkColor = isOut ? 'text-white' : 'text-primary';
            mediaHtml = '<br><a href="' + msg.media_url + '" target="_blank" class="small ' + linkColor + '">'
                      + '<i class="fas fa-image"></i> View Media</a>';
        }
        var body = msg.body.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                            .replace(/\n/g,'<br>');
        return '<div class="chat-bubble" data-id="' + msg.id + '" '
             + 'style="display:flex;justify-content:' + align + ';">'
             + '<div style="max-width:75%;">'
             + '<div style="background:' + bg + ';color:' + color + ';padding:8px 12px;'
             + 'border-radius:' + radius + ';font-size:.875rem;word-break:break-word;'
             + 'box-shadow:0 1px 2px rgba(0,0,0,.1);">'
             + body + mediaHtml
             + '</div>'
             + '<div class="text-muted" style="font-size:.7rem;margin-top:2px;text-align:' + textAlign + ';">'
             + msg.time + ' ' + msg.date + ' ' + tick
             + '</div></div></div>';
    }

    // ── Poll for new messages ────────────────────────────────────────
    function poll() {
        $.getJSON('../sms_chat/poll.php', { lead_id: LEAD_ID, since_id: lastId }, function (res) {
            if (!res.ok) return;
            if (res.messages && res.messages.length) {
                $('#chat-empty').hide();
                $.each(res.messages, function (i, msg) {
                    if ($('[data-id="' + msg.id + '"]').length === 0) {
                        $window.append(renderBubble(msg));
                        if (msg.direction === 'inbound') {
                            showInboundNotification(msg);
                        }
                    }
                });
                lastId = res.max_id;
                scrollBottom();
            }
            $status.html('<span style="color:#28a745;">● Live</span>');
        }).fail(function () {
            $status.html('<span style="color:#dc3545;">● Offline</span>');
        });
    }

    // ── Inbound notification toast ───────────────────────────────────
    function showInboundNotification(msg) {
        var toast = $('<div>')
            .css({ position:'fixed', bottom:'80px', right:'20px', zIndex:9999,
                   background:'#28a745', color:'#fff', padding:'10px 16px',
                   borderRadius:'8px', boxShadow:'0 4px 12px rgba(0,0,0,.3)',
                   maxWidth:'280px', cursor:'pointer', fontSize:'.85rem' })
            .html('<i class="fas fa-sms mr-1"></i><strong>New SMS</strong><br>'
                + '<small>' + msg.time + '</small><br>'
                + msg.body.substring(0, 80) + (msg.body.length > 80 ? '…' : ''))
            .appendTo('body')
            .on('click', function () { $(this).remove(); });
        setTimeout(function () { toast.fadeOut(400, function () { $(this).remove(); }); }, 6000);
    }

    // Start polling immediately and then every 5s
    poll();
    pollTimer = setInterval(poll, POLL_MS);

    // ── Character counter ────────────────────────────────────────────
    $input.on('input', function () {
        var len  = $(this).val().length;
        var segs = Math.ceil(len / 160) || 1;
        $counter.text(len + '/' + (segs * 160));
        $counter.toggleClass('text-danger', len > 160);
    });

    // ── Enter to send ────────────────────────────────────────────────
    $input.on('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            $sendBtn.trigger('click');
        }
    });

    // ── Template picker ──────────────────────────────────────────────
    $('#chat-template-sel').on('change', function () {
        var tid = $(this).val();
        if (!tid) return;
        $.getJSON('ajax/get_template.php', { template_id: tid, lead_id: LEAD_ID }, function (res) {
            if (!res.ok) { alert(res.error); return; }
            $input.val(res.body).trigger('input');
            $input.focus();
        });
    });

    // ── Send message ─────────────────────────────────────────────────
    $sendBtn.on('click', function () {
        var msg = $input.val().trim();
        if (!msg) return;

        $sendBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        $('#sms-chat-alert').html('');

        $.post('../sms_chat/send.php', {
            lead_id:        LEAD_ID,
            to_phone:       TO_PHONE,
            body:           msg,
            from_number_id: $('#chat-from-number').val(),
        }, function (res) {
            if (res.ok) {
                $('#chat-empty').hide();
                $window.append(renderBubble(res.message));
                if (res.message.id > lastId) lastId = res.message.id;
                scrollBottom();
                $input.val('').trigger('input');
                $('#chat-template-sel').val('');
            } else {
                $('#sms-chat-alert').html(
                    '<div class="alert alert-danger alert-sm py-1 px-2 m-2">' + res.error + '</div>'
                );
            }
            $sendBtn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i>');
        }, 'json').fail(function () {
            $('#sms-chat-alert').html(
                '<div class="alert alert-danger alert-sm py-1 px-2 m-2">Request failed.</div>'
            );
            $sendBtn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i>');
        });
    });

    // Stop polling when leaving page
    $(window).on('beforeunload', function () {
        clearInterval(pollTimer);
    });
})();
</script>
<!-- /Page-specific scripts SMS end -->
