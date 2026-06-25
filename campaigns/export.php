<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

$id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
if (!$id) exit;

$r = mysqli_query($conn, "SELECT * FROM campaigns WHERE id='$id'");
$camp = mysqli_fetch_assoc($r);
if (!$camp) exit;
assertOwnership($camp);

$statusFilter = isset($_GET['filter']) ? $_GET['filter'] : '';
$qWhere = "q.campaign_id='$id'";
if ($statusFilter === 'opened')       $qWhere .= " AND q.opened=1";
elseif ($statusFilter === 'bounced')  $qWhere .= " AND q.bounced=1";
elseif ($statusFilter === 'unsubscribed') $qWhere .= " AND q.unsubscribed=1";
elseif (in_array($statusFilter, ['sent','failed','pending'])) $qWhere .= " AND q.status='$statusFilter'";

$dq = mysqli_query($conn, "
    SELECT c.name, c.email, c.phone, q.status, q.sent_at,
           q.opened, q.clicked, q.bounced, q.unsubscribed,
           q.smsstatus, q.sms_delivered, q.sms_failed, q.bounce_reason
    FROM campaign_queue q
    LEFT JOIN contacts c ON c.id=q.contact_id
    WHERE $qWhere
    ORDER BY q.id DESC
");

$filename = 'campaign_'.$id.'_'.date('Ymd_His').'.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="'.$filename.'"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Name','Email','Phone','Status','Sent At','Opened','Clicked','Bounced','Unsubscribed','SMS Status','SMS Delivered','SMS Failed','Bounce Reason']);

while ($row = mysqli_fetch_assoc($dq)) {
    fputcsv($out, [
        $row['name'], $row['email'], $row['phone'],
        $row['status'], $row['sent_at'],
        $row['opened'] ? 'Yes' : 'No',
        $row['clicked'] ? 'Yes' : 'No',
        $row['bounced'] ? 'Yes' : 'No',
        $row['unsubscribed'] ? 'Yes' : 'No',
        $row['smsstatus'], $row['sms_delivered'] ? 'Yes' : 'No',
        $row['sms_failed'] ? 'Yes' : 'No',
        $row['bounce_reason'],
    ]);
}
fclose($out);
