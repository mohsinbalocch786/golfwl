<?php
/**
 * SMS Chat Polling endpoint
 * Table: lead_sms (phone = other party number, message = body)
 * PHP 5.6 compatible
 *
 * GET: lead_id INT, since_id INT
 */
include("../config/db.php");
include("../config/auth.php");
requireLogin();

header('Content-Type: application/json');

$lead_id  = (int)(isset($_GET['lead_id'])  ? $_GET['lead_id']  : 0);
$since_id = (int)(isset($_GET['since_id']) ? $_GET['since_id'] : 0);

if (!$lead_id) {
    echo json_encode(array('ok' => false, 'messages' => array()));
    exit;
}

$r = mysqli_query($conn,
    "SELECT id, direction, phone, message, twilio_sid, status, media_url, created_at
     FROM lead_sms
     WHERE lead_id = '$lead_id'
     AND id > '$since_id'
     ORDER BY id ASC
     LIMIT 50"
);

$messages = array();
$max_id   = $since_id;

while ($row = mysqli_fetch_assoc($r)) {
    $messages[] = array(
        'id'        => (int)$row['id'],
        // phone stores the OTHER party's number
        'from_phone'=> $row['direction'] === 'inbound' ? $row['phone'] : '',
        'to_phone'  => $row['direction'] === 'outbound' ? $row['phone'] : '',
        'body'      => $row['message'],
        'direction' => $row['direction'],
        'status'    => $row['status'],
        'media_url' => $row['media_url'],
        'sid'       => $row['twilio_sid'],
        'time'      => date('g:i A', strtotime($row['created_at'])),
        'date'      => date('M j, Y', strtotime($row['created_at'])),
    );
    if ((int)$row['id'] > $max_id) {
        $max_id = (int)$row['id'];
    }
}

// Mark inbound messages as read
if (!empty($messages)) {
    mysqli_query($conn,
        "UPDATE lead_sms
         SET read_at = '$currentTime'
         WHERE lead_id = '$lead_id'
         AND direction = 'inbound'
         AND read_at IS NULL"
    );
}

echo json_encode(array(
    'ok'       => true,
    'messages' => $messages,
    'max_id'   => $max_id,
));
