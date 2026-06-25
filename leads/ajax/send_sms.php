<?php
/**
 * AJAX POST: send SMS/MMS to lead via Twilio REST API (no SDK needed).
 *
 * POST: lead_id, to_phone, body, from_number (twilio_numbers.id or raw number)
 */
include("../../config/db.php");
include("../../config/auth.php");
requireLogin();

header('Content-Type: application/json');

$lead_id    = (int)(isset($_POST['lead_id']) ? $_POST['lead_id'] : 0);
$to_phone   = trim(isset($_POST['to_phone']) ? $_POST['to_phone'] : '');
$body       = trim(isset($_POST['body']) ? $_POST['body'] : '');
$from_num_id = (int)(isset($_POST['from_number_id']) ? $_POST['from_number_id'] : 0);

if (!$lead_id || !$to_phone || !$body) {
    echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
    exit;
}

// Fetch Twilio credentials from settings
$sq = mysqli_query($conn, "SELECT * FROM settings LIMIT 1");
$settings   = mysqli_fetch_assoc($sq);
$sid        = isset($settings['twilio_sid']) ? $settings['twilio_sid'] : '';
$token      = isset($settings['twilio_token']) ? $settings['twilio_token'] : '';
$defaultFrom = isset($settings['twilio_from']) ? $settings['twilio_from'] : '';

if (empty($sid) || empty($token)) {
    echo json_encode(['ok' => false, 'error' => 'Twilio credentials not configured in Settings']);
    exit;
}

// Resolve from-number: prefer selected twilio number, fall back to settings default
$from_number = $defaultFrom;
if ($from_num_id) {
    $nr = mysqli_query($conn, "SELECT phone FROM twilio_numbers WHERE id='$from_num_id' AND status='active' LIMIT 1");
    $nr_row = mysqli_fetch_assoc($nr);
    if ($nr_row) $from_number = $nr_row['phone'];
}

if (!$from_number) {
    echo json_encode(['ok' => false, 'error' => 'No Twilio sender number configured']);
    exit;
}

// Sanitise phone — Twilio needs E.164 (+1XXXXXXXXXX)
$clean_phone = preg_replace('/[^\d+]/', '', $to_phone);
if ((substr($clean_phone, 0, 1) !== '+')) {
    $clean_phone = '+1' . ltrim($clean_phone, '1'); // default US
}

// --- Append opt-out footer (matching existing campaign pattern) ---
$sms_body = $body;
$note_text = isset($settings['sms_footer']) ? $settings['sms_footer'] : ''; // optional footer from settings
if ($note_text) $sms_body .= "\n" . $note_text;

// --- Send via Twilio Messages REST API ---
$url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";

$post_data = [
    'To'   => $clean_phone,
    'From' => $from_number,
    'Body' => $sms_body,
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($post_data),
    CURLOPT_USERPWD        => "$sid:$token",
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
]);
$result    = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

$response  = json_decode($result, true);
$success   = ($httpCode >= 200 && $httpCode < 300) && !isset($response['code']);
$status    = $success ? 'sent' : 'failed';
$ext_id    = isset($response['sid']) ? $response['sid'] : null;
$errMsg    = $success ? '' : (isset($response['message']) ? $response['message'] : "HTTP $httpCode $curlError");

// --- Log to lead_interactions ---
$uid       = currentUserId();
$bodyEsc   = mysqli_real_escape_string($conn, $sms_body);
$statusEsc = mysqli_real_escape_string($conn, $status);
$extEsc    = mysqli_real_escape_string($conn, isset($ext_id) ? $ext_id : '');
$phoneEsc  = mysqli_real_escape_string($conn, $to_phone);
$subjectEsc = mysqli_real_escape_string($conn, "SMS to $to_phone");

mysqli_query($conn, "INSERT INTO lead_interactions
    (lead_id, user_id, type, direction, subject, body, status, ext_id, created_at)
    VALUES
    ('$lead_id', '$uid', 'sms', 'outbound', '$subjectEsc', '$bodyEsc', '$statusEsc', '$extEsc', '$currentTime')");

if ($success) {
    echo json_encode(['ok' => true, 'message' => "SMS sent to $to_phone", 'sid' => $ext_id]);
} else {
    echo json_encode(['ok' => false, 'error' => "Send failed: $errMsg"]);
}
