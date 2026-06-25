<?php
include("../config/db.php");

$sid          = isset($_POST['MessageSid'])     ? mysqli_real_escape_string($conn, $_POST['MessageSid'])     : '';
$status       = isset($_POST['MessageStatus'])  ? mysqli_real_escape_string($conn, $_POST['MessageStatus'])  : '';
$fromPhone    = isset($_POST['From'])           ? mysqli_real_escape_string($conn, $_POST['From'])           : '';
$toPhone      = isset($_POST['To'])             ? mysqli_real_escape_string($conn, $_POST['To'])             : '';
$body         = isset($_POST['Body'])           ? trim($_POST['Body'])                                       : '';
$errorCode    = isset($_POST['ErrorCode'])      ? mysqli_real_escape_string($conn, $_POST['ErrorCode'])      : '';
$errorMessage = isset($_POST['ErrorMessage'])   ? mysqli_real_escape_string($conn, $_POST['ErrorMessage'])   : '';
$currentDateTime = date('Y-m-d H:i:s');

// ── INBOUND MESSAGE HANDLING ────────────────────────────────────────────
// Twilio sends inbound messages here too if this is set as the messaging webhook
if (!empty($fromPhone) && !empty($body) && empty($sid)) {
    // Handled by dedicated sms_inbound.php — fall through to status handling
}

// ── STOP / OPT-OUT DETECTION ────────────────────────────────────────────
// Twilio already handles compliance opt-out, but we mirror it locally
$stopWords = ['STOP','STOPALL','UNSUBSCRIBE','CANCEL','END','QUIT'];
$bodyUpper = strtoupper(trim($body));
if (in_array($bodyUpper, $stopWords) && !empty($fromPhone)) {
    $phoneEsc = mysqli_real_escape_string($conn, $fromPhone);
    mysqli_query($conn, "
        INSERT IGNORE INTO do_not_contact (phone, reason, source, created_at)
        VALUES ('$phoneEsc', 'stop_sms', 'twilio_inbound', '$currentDateTime')
    ");
}

// ── STATUS UPDATE ─────────────────────────────────────────────────────
switch ($status) {
    case 'queued':
        mysqli_query($conn,"
        UPDATE campaign_queue
        SET smsstatus='queued'
        WHERE message_id='$sid'
        ");
        break;
    case 'accepted':
        mysqli_query($conn,"
        UPDATE campaign_queue
        SET smsstatus='accepted'
        WHERE message_id='$sid'
        ");
        break;
    case 'sent':
        mysqli_query($conn,"
        UPDATE campaign_queue
        SET smsstatus='sent',
            sms_sent=1,
            sms_sent_at='$currentDateTime'
        WHERE message_id='$sid'
        ");
        break;
    case 'delivered':
        mysqli_query($conn,"
        UPDATE campaign_queue
        SET smsstatus='delivered',
            sms_delivered=1,
            sms_delivered_at='$currentDateTime'
        WHERE message_id='$sid'
        ");
        break;
    case 'undelivered':
        mysqli_query($conn,"
        UPDATE campaign_queue
        SET smsstatus='undelivered',
            sms_undelivered=1,
            sms_error_code='$errorCode',
            sms_error_message='$errorMessage'
        WHERE message_id='$sid'
        ");
        break;
    case 'failed':
        mysqli_query($conn,"
        UPDATE campaign_queue
        SET smsstatus='failed',
            sms_failed=1,
            sms_failed_at='$currentDateTime',
            sms_error_code='$errorCode',
            sms_error_message='$errorMessage'
        WHERE message_id='$sid'
        ");
        break;
    default:
        if ($sid) {
            mysqli_query($conn,"
            UPDATE campaign_queue
            SET smsstatus='$status'
            WHERE message_id='$sid'
            ");
        }
        break;
}

http_response_code(200);
echo 'OK';
?>
