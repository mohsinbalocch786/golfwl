<?php
/**
 * Mark all unread inbound SMS as read for the current user's leads.
 * Called when user opens the notification dropdown.
 * PHP 5.6 compatible.
 */
include("../config/db.php");
include("../config/auth.php");
requireLogin();

header('Content-Type: application/json');

$ow = ownershipWhere('l');

mysqli_query($conn,
    "UPDATE lead_sms m
     JOIN leads l ON l.id = m.lead_id
     SET m.read_at = '$currentTime'
     WHERE m.direction = 'inbound'
     AND m.read_at IS NULL
     AND ($ow)"
);

echo json_encode(array('ok' => true, 'updated' => mysqli_affected_rows($conn)));
