<?php

include("../config/db.php");
include("../config/send_email.php");
include("../config/send_sms.php");

$q=mysqli_query($conn,"
SELECT q.*,c.type,t.subject,t.content
FROM campaign_queue q
JOIN campaigns c ON c.id=q.campaign_id
JOIN templates t ON t.id=c.template_id
WHERE q.status='pending'
LIMIT 50
");

while($row=mysqli_fetch_assoc($q)){

$id=$row['id'];

try{

if($row['type']=="email"){

sendEmail(
$row['email'],
$row['subject'],
$row['content']
);

}else{

sendSMS(
$row['phone'],
$row['content']
);

}

mysqli_query($conn,"
UPDATE campaign_queue
SET status='sent',sent_at='$currentTime'
WHERE id='$id'
");

}catch(Exception $e){

mysqli_query($conn,"
UPDATE campaign_queue
SET status='failed'
WHERE id='$id'
");

}

}

?>