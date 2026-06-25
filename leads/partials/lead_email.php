<?php
// $lead, $id, $conn available from view.php
$to_email = isset($lead['email']) ? $lead['email'] : (isset($lead['contact_email']) ? $lead['contact_email'] : '');

// Load EMAIL templates scoped to visibility
$uid = currentUserId(); $mid = currentManagerId();
if(isSuperAdmin()){
    $tplWhere = " status='active' AND type='EMAIL'";
} else {
    $tplWhere = " status='active' AND type='EMAIL'";
    // templates has no visibility column, show all active to all users
}
$tplQ = mysqli_query($conn, "SELECT id, name, subject FROM templates WHERE $tplWhere ORDER BY name");
$emailTemplates = [];
while($t = mysqli_fetch_assoc($tplQ)) $emailTemplates[] = $t;
?>

<div class="card card-outline card-primary mt-2">
    <div class="card-header py-2">
        <h3 class="card-title"><i class="fas fa-envelope mr-1"></i> Send Email</h3>
    </div>
    <div class="card-body">

        <div id="email-alert"></div>

        <!-- Template picker -->
        <div class="form-group">
            <label class="small font-weight-bold">Load Template</label>
            <select id="email-template-sel" class="form-control form-control-sm select2">
                <option value="">— Select template (optional) —</option>
                <?php foreach($emailTemplates as $t){ ?>
                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                <?php } ?>
            </select>
        </div>

        <div class="form-row">
            <div class="form-group col-md-6">
                <label class="small font-weight-bold">From Name</label>
                <input type="text" id="email-from-name" class="form-control form-control-sm" value="">
            </div>
            <div class="form-group col-md-6">
                <label class="small font-weight-bold">From Email</label>
                <input type="email" id="email-from-email" class="form-control form-control-sm" value="">
            </div>
        </div>

        <div class="form-group">
            <label class="small font-weight-bold">To <span class="text-danger">*</span></label>
            <input type="email" id="email-to" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($to_email) ?>" required>
        </div>

        <div class="form-group">
            <label class="small font-weight-bold">Subject <span class="text-danger">*</span></label>
            <input type="text" id="email-subject" class="form-control form-control-sm" required>
        </div>

        <div class="form-group">
            <label class="small font-weight-bold">Body <span class="text-danger">*</span></label>
            <textarea id="email-body" class="form-control" rows="7"></textarea>
        </div>

        <button type="button" id="btn-send-email" class="btn btn-primary btn-sm">
            <i class="fas fa-paper-plane"></i> Send Email
        </button>

    </div>
</div>

