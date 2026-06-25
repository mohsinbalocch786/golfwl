<?php
/**
 * AJAX POST: log a manual interaction (note/call-note) to lead_interactions.
 * Also syncs to lead_notes for backward compat.
 *
 * POST: lead_id, type (note|call|email|sms), subject, body
 */
include("../../config/db.php");
include("../../config/auth.php");
requireLogin();

header('Content-Type: application/json');

$lead_id = (int)(isset($_POST['lead_id']) ? $_POST['lead_id'] : 0);
$type    = in_array(isset($_POST['type']) ? $_POST['type'] : '', ['note','call','email','sms']) ? $_POST['type'] : 'note';
$subject = mysqli_real_escape_string($conn, trim(isset($_POST['subject']) ? $_POST['subject'] : ''));
$body    = mysqli_real_escape_string($conn, trim(isset($_POST['body']) ? $_POST['body'] : ''));

if (!$lead_id || !$body) {
    echo json_encode(['ok' => false, 'error' => 'Missing lead_id or body']);
    exit;
}

$uid = currentUserId();

mysqli_query($conn, "INSERT INTO lead_interactions
    (lead_id, user_id, type, direction, subject, body, status, created_at)
    VALUES
    ('$lead_id', '$uid', '$type', 'outbound', '$subject', '$body', 'sent', '$currentTime')");

// Also keep lead_notes in sync for the Notes tab
if ($type === 'note') {
    mysqli_query($conn, "INSERT INTO lead_notes
        (lead_id, user_id, note, created_at)
        VALUES ('$lead_id', '$uid', '$body', '$currentTime')");
}

echo json_encode(['ok' => true, 'id' => mysqli_insert_id($conn)]);