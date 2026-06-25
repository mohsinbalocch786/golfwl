<?php
include("../config/db.php");
date_default_timezone_set('America/Los_Angeles');
$data = file_get_contents("php://input");
if (empty($data)) {
    http_response_code(200);
    exit;
}
$events = json_decode($data, true);
if (!is_array($events)) {
    http_response_code(200);
    exit;
}
$currentDateTime = date('Y-m-d H:i:s');  
foreach ($events as $e) {
    $event = isset($e['event']) ? $e['event'] : '';
    // Save exact message id from SendGrid
    $message_id = mysqli_real_escape_string(
        $conn,
        isset($e['sg_message_id']) ? $e['sg_message_id'] : ''
    );
    if (empty($message_id)) {
        continue;
    }
    switch ($event) {
        /* =====================================================
           PROCESSED
        ===================================================== */
        case 'processed':
            mysqli_query($conn,"
                UPDATE campaign_queue
                SET processed = 1,
                    processed_at = '$currentDateTime'
                WHERE message_id = '$message_id'
            ");
            break;
        /* =====================================================
           DELIVERED
        ===================================================== */
        case 'delivered':
            mysqli_query($conn,"
                UPDATE campaign_queue
                SET delivered = 1,
                    delivered_at = '$currentDateTime'
                WHERE message_id = '$message_id'
            ");
            break;
        /* =====================================================
           OPEN
        ===================================================== */
        case 'open':
            mysqli_query($conn,"
                UPDATE campaign_queue
                SET opened = 1,
                    opened_at = IFNULL(opened_at,'$currentDateTime'),
                    open_count = open_count + 1
                WHERE message_id = '$message_id'
            ");
            break;
        /* =====================================================
           CLICK
        ===================================================== */
        case 'click':
            mysqli_query($conn,"
                UPDATE campaign_queue
                SET clicked = 1,
                    clicked_at = IFNULL(clicked_at,'$currentDateTime'),
                    click_count = click_count + 1
                WHERE message_id = '$message_id'
            ");
            break;
        /* =====================================================
           BOUNCE
        ===================================================== */
        case 'bounce':
            $reason = '';
            if(isset($e['reason']))
                $reason = mysqli_real_escape_string($conn,$e['reason']);

            mysqli_query($conn,"
                UPDATE campaign_queue
                SET bounced = 1,
                    bounced_at = '$currentDateTime',
                    bounce_reason = '$reason'
                WHERE message_id = '$message_id'
            ");
            // Hard bounce - add to DNC so we never send again
            $bounceType = isset($e['type']) ? $e['type'] : '';
            if ($bounceType === 'bounce' && !empty($email)) {
                mysqli_query($conn,"
                    INSERT IGNORE INTO do_not_contact (email, reason, source, created_at)
                    VALUES ('$email', 'bounce', 'sendgrid_webhook', '$currentDateTime')
                ");
            }
            break;
        /* =====================================================
           BLOCKED
        ===================================================== */
        case 'blocked':
            $reason = '';
            if(isset($e['reason']))
                $reason = mysqli_real_escape_string($conn,$e['reason']);

            mysqli_query($conn,"
                UPDATE campaign_queue
                SET blocked = 1,
                    bounce_reason = '$reason'
                WHERE message_id = '$message_id'
            ");

            break;


        /* =====================================================
           DROPPED
        ===================================================== */
        case 'dropped':
            $reason = '';
            if(isset($e['reason']))
                $reason = mysqli_real_escape_string($conn,$e['reason']);

            mysqli_query($conn,"
                UPDATE campaign_queue
                SET dropped = 1,
                    bounce_reason = '$reason'
                WHERE message_id = '$message_id'
            ");

            break;


        /* =====================================================
           DEFERRED
        ===================================================== */
        case 'deferred':
            mysqli_query($conn,"
                UPDATE campaign_queue
                SET deferred = 1
                WHERE message_id = '$message_id'
            ");
            break;
        /* =====================================================
           UNSUBSCRIBE
        ===================================================== */
        case 'unsubscribe':
            mysqli_query($conn,"
                UPDATE campaign_queue
                SET unsubscribed = 1,
                    unsubscribed_at = '$currentDateTime'
                WHERE message_id = '$message_id'
            ");
            // Add to do_not_contact suppression list
            if (!empty($email)) {
                mysqli_query($conn,"
                    INSERT IGNORE INTO do_not_contact (email, reason, source, created_at)
                    VALUES ('$email', 'unsubscribed', 'sendgrid_webhook', '$currentDateTime')
                ");
            }
            break;
        /* =====================================================
           SPAM REPORT
        ===================================================== */
        case 'spamreport':
            $reason = 'Marked as Spam';
            mysqli_query($conn,"
                UPDATE campaign_queue
                SET spam_report = 1,
                    spam_reason = '$reason'
                WHERE message_id = '$message_id'
            ");
            // Add to do_not_contact
            if (!empty($email)) {
                mysqli_query($conn,"
                    INSERT IGNORE INTO do_not_contact (email, reason, source, created_at)
                    VALUES ('$email', 'spam', 'sendgrid_webhook', '$currentDateTime')
                ");
            }
            break;
    }
}
http_response_code(200);
echo "OK";