<?php
/**
 * Unread inbound SMS count scoped to current user's leads.
 * Table: lead_sms
 * PHP 5.6 compatible
 */
include("../config/db.php");
include("../config/auth.php");
requireLogin();

header('Content-Type: application/json');

$ow = ownershipWhere('l');

$r = mysqli_query($conn,
    "SELECT COUNT(*) AS n
     FROM lead_sms m
     JOIN leads l ON l.id = m.lead_id
     WHERE m.direction = 'inbound'
     AND m.read_at IS NULL
     AND ($ow)"
);
$row   = mysqli_fetch_assoc($r);
$count = $row ? (int)$row['n'] : 0;

$recent = array();
if ($count > 0) {
    $rr = mysqli_query($conn,
        "SELECT m.id, m.phone, m.message, m.created_at,
                l.id AS lead_id,
                CONCAT(l.first_name,' ',l.last_name) AS lead_name
         FROM lead_sms m
         JOIN leads l ON l.id = m.lead_id
         WHERE m.direction = 'inbound'
         AND m.read_at IS NULL
         AND ($ow)
         ORDER BY m.created_at DESC
         LIMIT 5"
    );
    while ($row2 = mysqli_fetch_assoc($rr)) {
        $body = $row2['message'];
        $recent[] = array(
            'id'        => (int)$row2['id'],
            'lead_id'   => (int)$row2['lead_id'],
            'lead_name' => $row2['lead_name'],
            'from'      => $row2['phone'],
            'body'      => mb_strlen($body) > 60 ? mb_substr($body, 0, 60) . '…' : $body,
            'time'      => date('g:i A', strtotime($row2['created_at'])),
        );
    }
}

echo json_encode(array('count' => $count, 'recent' => $recent));
