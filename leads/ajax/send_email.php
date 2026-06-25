<?php
/**
 * AJAX POST: send email to lead, log to lead_interactions.
 *
 * POST: lead_id, to_email, subject, body, from_name, from_email
 */
include("../../config/db.php");
include("../../config/auth.php");
requireLogin();

header('Content-Type: application/json');

$lead_id    = (int)(isset($_POST['lead_id']) ? $_POST['lead_id'] : 0);
$to_email   = trim(isset($_POST['to_email']) ? $_POST['to_email'] : '');
$subject    = trim(isset($_POST['subject']) ? $_POST['subject'] : '');
$body       = isset($_POST['body']) ? $_POST['body'] : '';
$from_name  = trim(isset($_POST['from_name']) ? $_POST['from_name'] : '');
$from_email = trim(isset($_POST['from_email']) ? $_POST['from_email'] : '');

if (!$lead_id || !filter_var($to_email, FILTER_VALIDATE_EMAIL) || $subject === '') {
    echo json_encode(['ok' => false, 'error' => 'Missing required fields (lead_id, valid email, subject)']);
    exit;
}

// Fetch SendGrid settings
$sq = mysqli_query($conn, "SELECT * FROM settings LIMIT 1");
$settings = mysqli_fetch_assoc($sq);
$apiKey    = $settings['sendgrid_api_key'] ? $settings['sendgrid_api_key']: '';
$defFrom   = $settings['from_email']       ? $settings['from_email'] : 'noreply@example.com';
$defName   = $settings['from_name']        ? $settings['from_name'] : 'CRM';

if (empty($apiKey)) {
    echo json_encode(['ok' => false, 'error' => 'SendGrid API key not configured in Settings']);
    exit;
}

$from_email = isset($from_email) ? $from_email : $defFrom;
$from_name  = isset($from_name) ? $from_name : $defName;

// --- Send via SendGrid HTTP API (no SDK required) ---
$payload = [
    'personalizations' => [[
        'to'      => [['email' => $to_email]],
        'subject' => $subject,
    ]],
    'from'    => ['email' => $from_email, 'name' => $from_name],
    'content' => [['type' => 'text/html', 'value' => $body]],
];

$ch = curl_init('https://api.sendgrid.com/v3/mail/send');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
    ],
]);
$result     = curl_exec($ch);
$httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError  = curl_error($ch);
curl_close($ch);

$success   = ($httpCode >= 200 && $httpCode < 300);
$status    = $success ? 'sent' : 'failed';
$errorMsg  = $success ? '' : "HTTP $httpCode: $result $curlError";

// --- Log to lead_interactions ---
$uid         = currentUserId();
$subjectEsc  = mysqli_real_escape_string($conn, $subject);
$bodyEsc     = mysqli_real_escape_string($conn, $body);
$statusEsc   = mysqli_real_escape_string($conn, $status);
$toEsc       = mysqli_real_escape_string($conn, $to_email);

mysqli_query($conn, "INSERT INTO lead_interactions
    (lead_id, user_id, type, direction, subject, body, status, created_at)
    VALUES
    ('$lead_id', '$uid', 'email', 'outbound', '$subjectEsc', '$bodyEsc', '$statusEsc', '$currentTime')");

if ($success) {
    echo json_encode(['ok' => true, 'message' => "Email sent to $to_email"]);
} else {
    echo json_encode(['ok' => false, 'error' => "Send failed: $errorMsg"]);
}