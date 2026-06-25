<?php
/**
 * SMS Chat Send
 * Table: lead_sms (phone = to_phone for outbound, message = body)
 * PHP 5.6 compatible
 *
 * POST: lead_id, to_phone, body, from_number_id
 */
include("../config/db.php");
include("../config/auth.php");
requireLogin();

header('Content-Type: application/json');

$lead_id     = (int)(isset($_POST['lead_id'])        ? $_POST['lead_id']        : 0);
$to_phone    = trim(isset($_POST['to_phone'])     ? $_POST['to_phone']     : '');
$body        = trim(isset($_POST['body'])          ? $_POST['body']          : '');
$from_num_id = (int)(isset($_POST['from_number_id']) ? $_POST['from_number_id'] : 0);

if (!$lead_id || !$to_phone || !$body) {
    echo json_encode(array('ok' => false, 'error' => 'Missing required fields'));
    exit;
}

// ── Twilio credentials ────────────────────────────────────────────────────
$sq       = mysqli_query($conn, "SELECT * FROM settings LIMIT 1");
$settings = mysqli_fetch_assoc($sq);
$sid      = isset($settings['twilio_sid'])   ? $settings['twilio_sid']   : '';
$token    = isset($settings['twilio_token']) ? $settings['twilio_token'] : '';
$defFrom  = isset($settings['twilio_from'])  ? $settings['twilio_from']  : '';

if (!$sid || !$token) {
    echo json_encode(array('ok' => false, 'error' => 'Twilio credentials not configured in Settings'));
    exit;
}

// Resolve from number
$from_number = $defFrom;
if ($from_num_id) {
    $nr = mysqli_query($conn,
        "SELECT phone FROM twilio_numbers WHERE id='$from_num_id' AND status='active' LIMIT 1"
    );
    if ($nr && $nr_row = mysqli_fetch_assoc($nr)) {
        $from_number = $nr_row['phone'];
    }
}

// E.164 normalise
$clean = preg_replace('/[^0-9+]/', '', $to_phone);
if (substr($clean, 0, 1) !== '+') {
    $clean = '+1' . ltrim($clean, '1');
}

// ── Send via Twilio REST ──────────────────────────────────────────────────
$url       = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";
$post_data = http_build_query(array(
    'To'   => $clean,
    'From' => $from_number,
    'Body' => $body,
));

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST,           true);
curl_setopt($ch, CURLOPT_POSTFIELDS,     $post_data);
curl_setopt($ch, CURLOPT_USERPWD,        "$sid:$token");
curl_setopt($ch, CURLOPT_HTTPHEADER,     array('Content-Type: application/x-www-form-urlencoded'));
$result   = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$resp    = json_decode($result, true);
$success = ($httpCode >= 200 && $httpCode < 300 && !isset($resp['code']));
$twSid   = isset($resp['sid'])     ? $resp['sid']     : '';
$errMsg  = isset($resp['message']) ? $resp['message'] : "HTTP $httpCode";
$status  = $success ? 'sent' : 'failed';

$now     = date('Y-m-d H:i:s');
$uid     = (int)currentUserId();
// For outbound: phone = to_phone (the recipient)
$phoneEsc   = mysqli_real_escape_string($conn, $to_phone);
$bodyEsc    = mysqli_real_escape_string($conn, $body);
$sidEsc     = mysqli_real_escape_string($conn, $twSid);
$statusEsc  = mysqli_real_escape_string($conn, $status);

// ── Insert into lead_sms ──────────────────────────────────────────────────
mysqli_query($conn,
    "INSERT INTO lead_sms
     (lead_id, user_id, direction, phone, message, twilio_sid, status, created_at)
     VALUES
     ('$lead_id', '$uid', 'outbound', '$phoneEsc', '$bodyEsc', '$sidEsc', '$statusEsc', '$now')"
);
$new_id = (int)mysqli_insert_id($conn);

// ── Also log to lead_interactions ─────────────────────────────────────────
$subj = mysqli_real_escape_string($conn, "SMS to $to_phone");
mysqli_query($conn,
    "INSERT INTO lead_interactions
     (lead_id, user_id, type, direction, subject, body, status, ext_id, created_at)
     VALUES
     ('$lead_id', '$uid', 'sms', 'outbound', '$subj', '$bodyEsc', '$statusEsc', '$sidEsc', '$now')"
);

if ($success) {
    echo json_encode(array(
        'ok'  => true,
        'id'  => $new_id,
        'sid' => $twSid,
        'message' => array(
            'id'        => $new_id,
            'body'      => $body,
            'direction' => 'outbound',
            'status'    => $status,
            'media_url' => null,
            'time'      => date('g:i A'),
            'date'      => date('M j, Y'),
        ),
    ));
} else {
    echo json_encode(array('ok' => false, 'error' => $errMsg));
}
