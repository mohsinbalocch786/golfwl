<?php
/**
 * AJAX POST: Initiate outbound call via Twilio REST API.
 * Twilio calls the FROM number first (agent's phone), then connects to lead.
 * Logs to lead_interactions.
 *
 * POST: lead_id, to_phone, agent_phone (optional override), from_number_id
 */
include("../../config/db.php");
include("../../config/auth.php");
requireLogin();

header('Content-Type: application/json');

$lead_id      = (int)(isset($_POST['lead_id']) ? $_POST['lead_id'] : 0);
$to_phone     = trim(isset($_POST['to_phone']) ? $_POST['to_phone'] : '');
$from_num_id  = (int)(isset($_POST['from_number_id']) ? $_POST['from_number_id'] : 0);

if (!$lead_id || !$to_phone) {
    echo json_encode(['ok' => false, 'error' => 'Missing lead_id or to_phone']);
    exit;
}

// Twilio credentials
$sq = mysqli_query($conn, "SELECT * FROM settings LIMIT 1");
$settings = mysqli_fetch_assoc($sq);
$sid      = isset($settings['twilio_sid']) ? $settings['twilio_sid'] : '';
$token    = isset($settings['twilio_token']) ? $settings['twilio_token'] : '';
$defaultFrom = isset($settings['twilio_from']) ? $settings['twilio_from'] : '';

if (!$sid || !$token) {
    echo json_encode(['ok' => false, 'error' => 'Twilio credentials not configured']);
    exit;
}

// Resolve from-number
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

// Sanitise destination phone
$clean_to = preg_replace('/[^\d+]/', '', $to_phone);
if ((substr($clean_to, 0, 1) !== '+')) {
    $clean_to = '+1' . ltrim($clean_to, '1');
}

// TwiML URL — simple <Say> to announce, then <Dial> to connect lead's number.
// We use a public TwiML Bin URL embedded inline via Twilio's twiml parameter.
// The call flow: Twilio dials TO phone, plays message, connects.
$twiml  = '<?xml version="1.0" encoding="UTF-8"?>';
$twiml .= '<Response>';
$twiml .= '<Say>Connecting your call. Please wait.</Say>';
$twiml .= '<Dial callerId="' . htmlspecialchars($from_number) . '">';
$twiml .= '<Number>' . htmlspecialchars($clean_to) . '</Number>';
$twiml .= '</Dial>';
$twiml .= '</Response>';

// Create outbound call via Twilio API
$url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Calls.json";

$post_data = [
    'To'     => $clean_to,
    'From'   => $from_number,
    'Twiml'  => $twiml,
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($post_data),
    CURLOPT_USERPWD        => "$sid:$token",
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
]);
$result   = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$response = json_decode($result, true);
$success  = ($httpCode >= 200 && $httpCode < 300) && !isset($response['code']);
$status   = $success ? 'pending' : 'failed';
$ext_id   = isset($response['sid']) ? $response['sid'] : null;
$errMsg   = $success ? '' : (isset($response['message']) ? $response['message'] : "HTTP $httpCode");

// Log to lead_interactions
$uid       = currentUserId();
$subjectEsc = mysqli_real_escape_string($conn, "Call to $to_phone");
$bodyEsc   = mysqli_real_escape_string($conn, "Outbound call initiated to $clean_to from $from_number");
$statusEsc = mysqli_real_escape_string($conn, $status);
$extEsc    = mysqli_real_escape_string($conn, isset($ext_id) ? $ext_id : '');

mysqli_query($conn, "INSERT INTO lead_interactions
    (lead_id, user_id, type, direction, subject, body, status, ext_id, created_at)
    VALUES
    ('$lead_id', '$uid', 'call', 'outbound', '$subjectEsc', '$bodyEsc', '$statusEsc', '$extEsc', '$currentTime')");

if ($success) {
    echo json_encode([
        'ok'      => true,
        'message' => "Call initiated to $to_phone",
        'sid'     => $ext_id,
        'status'  => isset($response['status']) ? $response['status'] : 'queued',
    ]);
} else {
    echo json_encode(['ok' => false, 'error' => "Call failed: $errMsg"]);
}