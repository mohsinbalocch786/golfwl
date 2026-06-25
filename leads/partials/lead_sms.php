<?php
/**
 * Live SMS Chat partial — PHP 5.6 compatible
 * Included from leads/view.php ($lead, $id, $conn are available)
 */
$to_phone = isset($lead['phone']) && $lead['phone'] ? $lead['phone']
          : (isset($lead['contact_phone']) ? $lead['contact_phone'] : '');

// SMS templates from email_templates table
$smsQ = mysqli_query($conn,
    "SELECT id, name, template, note FROM email_templates
     WHERE deleted=0 AND status=1 AND type='SMS' ORDER BY name"
);
$smsTemplates = array();
while ($t = mysqli_fetch_assoc($smsQ)) {
    $smsTemplates[] = $t;
}

// Active Twilio numbers
$numQ = mysqli_query($conn,
    "SELECT id, phone FROM twilio_numbers WHERE status='active' ORDER BY phone"
);
$twilioNumbers = array();
while ($n = mysqli_fetch_assoc($numQ)) {
    $twilioNumbers[] = $n;
}

// Fetch all existing chat messages for this lead (initial load)
// lead_sms columns: direction, phone (other party), message, status, media_url, created_at
$chatQ = mysqli_query($conn,
    "SELECT id, direction, phone, message, status, media_url, created_at
     FROM lead_sms
     WHERE lead_id = '$id'
     ORDER BY id ASC
     LIMIT 100"
);
$chatMessages = array();
$lastChatId   = 0;
while ($m = mysqli_fetch_assoc($chatQ)) {
    $chatMessages[] = $m;
    if ((int)$m['id'] > $lastChatId) {
        $lastChatId = (int)$m['id'];
    }
}
?>

<div class="card card-outline card-success mt-2">

    <!-- Chat header -->
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">
            <i class="fas fa-comments mr-1"></i> SMS Chat
            <span class="badge badge-success ml-1" id="chat-live-badge" style="font-size:.6rem;">LIVE</span>
        </h3>
        <div class="d-flex align-items-center" style="gap:8px;">
            <select id="chat-from-number" class="form-control form-control-sm" style="width:auto;">
                <option value="">Default From</option>
                <?php foreach ($twilioNumbers as $n) { ?>
                <option value="<?php echo $n['id']; ?>"><?php echo htmlspecialchars($n['phone']); ?></option>
                <?php } ?>
            </select>
            <select id="chat-template-sel" class="form-control form-control-sm" style="width:160px;">
                <option value="">Template…</option>
                <?php foreach ($smsTemplates as $t) { ?>
                <option value="<?php echo $t['id']; ?>" data-note="<?php echo htmlspecialchars($t['note']); ?>">
                    <?php echo htmlspecialchars($t['name']); ?>
                </option>
                <?php } ?>
            </select>
        </div>
    </div>

    <!-- Chat window -->
    <div class="card-body p-0">
        <div id="sms-chat-window"
             style="height:360px; overflow-y:auto; background:#f0f2f5; padding:12px; display:flex; flex-direction:column; gap:8px;">

            <?php if (empty($chatMessages)) { ?>
            <div id="chat-empty" class="text-center text-muted small p-4">
                No messages yet. Start the conversation below.
            </div>
            <?php } else { ?>
            <div id="chat-empty" class="text-center text-muted small" style="display:none;"></div>
            <?php } ?>

            <?php foreach ($chatMessages as $msg) {
                $isOut  = ($msg['direction'] === 'outbound');
                $align  = $isOut ? 'flex-end'    : 'flex-start';
                $bg     = $isOut ? '#007bff'      : '#fff';
                $color  = $isOut ? '#fff'          : '#333';
                $radius = $isOut ? '18px 18px 4px 18px' : '18px 18px 18px 4px';
                $timeStr = date('M j g:i A', strtotime($msg['created_at']));
                // 'phone' = the other party (to on outbound, from on inbound)
                $msgBody = $msg['message'];
            ?>
            <div class="chat-bubble" data-id="<?php echo (int)$msg['id']; ?>"
                 style="display:flex; justify-content:<?php echo $align; ?>;">
                <div style="max-width:75%;">
                    <div style="background:<?php echo $bg; ?>; color:<?php echo $color; ?>;
                                padding:8px 12px; border-radius:<?php echo $radius; ?>;
                                font-size:.875rem; word-break:break-word; box-shadow:0 1px 2px rgba(0,0,0,.1);">
                        <?php echo nl2br(htmlspecialchars($msgBody)); ?>
                        <?php if (!empty($msg['media_url'])) { ?>
                        <br><a href="<?php echo htmlspecialchars($msg['media_url']); ?>" target="_blank"
                               class="small <?php echo $isOut ? 'text-white' : 'text-primary'; ?>">
                            <i class="fas fa-image"></i> View Media
                        </a>
                        <?php } ?>
                    </div>
                    <div class="text-muted" style="font-size:.7rem; margin-top:2px; text-align:<?php echo $isOut ? 'right' : 'left'; ?>;">
                        <?php if (!$isOut) { echo htmlspecialchars($msg['phone']) . ' · '; } ?>
                        <?php echo $timeStr; ?>
                        <?php if ($isOut && $msg['status'] === 'sent') { ?>
                        <i class="fas fa-check ml-1 text-success"></i>
                        <?php } elseif ($isOut && $msg['status'] === 'failed') { ?>
                        <i class="fas fa-times ml-1 text-danger"></i>
                        <?php } ?>
                    </div>
                </div>
            </div>
            <?php } ?>

        </div>

        <!-- Typing area -->
        <div id="sms-chat-alert"></div>
        <div style="border-top:1px solid #dee2e6; padding:10px; background:#fff;">
            <div class="d-flex" style="gap:8px;">
                <textarea id="chat-input" rows="2"
                    class="form-control form-control-sm"
                    placeholder="Type a message… (Enter to send, Shift+Enter for new line)"
                    style="resize:none; flex:1;"></textarea>
                <div class="d-flex flex-column justify-content-between">
                    <button id="btn-chat-send" class="btn btn-success btn-sm">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                    <small id="chat-char-count" class="text-muted text-center" style="font-size:.65rem;">0/160</small>
                </div>
            </div>
            <small class="text-muted" style="font-size:.7rem;">
                Sending to: <strong><?php echo htmlspecialchars($to_phone); ?></strong>
                &bull; <span id="chat-poll-status">● Live</span>
            </small>
        </div>
    </div>

</div>
