<?php
date_default_timezone_set('America/Los_Angeles');
include("../config/db.php");

// ── Cron heartbeat — update so dashboard can detect if cron stopped ───────
$_cronNow = date('Y-m-d H:i:s');
mysqli_query($conn, "
    INSERT INTO cron_heartbeat (job_name, last_run, status, message)
    VALUES ('send_campaigns', '$_cronNow', 'running', '')
    ON DUPLICATE KEY UPDATE last_run='$_cronNow', status='running', message=''
");

require_once ('/home/victor17/portal2.aitrans.co/bhavin/vendor/autoload.php');
require_once('/home/victor17/public_html/api/twilio/Twilio/autoload.php');

$config = include("../config/services.php");


$q = mysqli_query($conn, "SELECT * FROM settings LIMIT 1");
$settings = mysqli_fetch_assoc($q);

$fromName = $settings['from_name'];
$fromEmail = $settings['from_email'];
$sendgridApiKey = $settings['sendgrid_api_key'];
$twilio_from = $settings['twilio_from'];
$twilio_sid = $settings['twilio_sid'];
$twilio_token = $settings['twilio_token'];

use Twilio\Rest\Client;

$today_day = strtoupper(date("D")); // MON TUE WED
$current_time = date("H:i");
$now = date("Y-m-d H:i:s");


/* find active campaigns */
$q = mysqli_query($conn,"
SELECT *
FROM campaigns
WHERE status IN ('pending','in-progress')
AND schedule_datetime <= '$now'
");


while($campaign = mysqli_fetch_assoc($q)){

$campaign_id = $campaign['id'];
$type = $campaign['type'];
$template_id = $campaign['template_id'];
$send_per_day = $campaign['send_per_day'];
$repeat_days = $campaign['repeat_days'];
$send_time = $campaign['send_time'];
$sendDate = $campaign['schedule_datetime'];
/* check if queue exists */

$qcheck = mysqli_query($conn,"
SELECT COUNT(*) as total
FROM campaign_queue
WHERE campaign_id='$campaign_id'
");

$total = mysqli_fetch_assoc($qcheck)['total'];

if($total == 0){

echo "Generating queue for campaign $campaign_id\n";

generateCampaignQueue($conn,$campaign_id, $sendDate);

}

/* repeat day check */

if($repeat_days){

$days = explode(",",$repeat_days);

if(!in_array($today_day,$days))
continue;

}

// echo $current_time ."<". $send_time;die;
/* time check */
if($current_time < $send_time)
continue;


/* load template */

$t = mysqli_query($conn,"SELECT * FROM templates WHERE id='$template_id'");
$template = mysqli_fetch_assoc($t);

$content = $template['content'];
$subject = $template['subject'];
$media = trim($template['image']);

if (!empty($media)) {

    $path1 = "https://aitrans.co/golfwl/templates/smsimage/";

    $images = explode(',', $media);

    foreach ($images as $image) {

        $image = trim($image);

        if ($image == '') {
            continue;
        }

        $url1 = $path1 . $image;
        

        if (urlExists($url1)) {
            $validMediaUrls[] = $url1;
        } 
    }
}




/* remaining limit today */

$sent_today = mysqli_query($conn,"
SELECT COUNT(*) as total
FROM campaign_queue
WHERE campaign_id='$campaign_id'
AND DATE(sent_at)=CURDATE()
");


$sent_today = mysqli_fetch_assoc($sent_today)['total'];

$remaining = $send_per_day - $sent_today;

if($remaining <= 0)
continue;


/* get pending queue */
$q2 = mysqli_query($conn,"
SELECT cq.*
FROM campaign_queue cq
WHERE cq.campaign_id='$campaign_id'
AND cq.status='pending'
AND cq.unsubscribed = 0
AND cq.bounced = 0
AND NOT EXISTS (
    SELECT 1 FROM do_not_contact dnc
    WHERE (dnc.email IS NOT NULL AND dnc.email = cq.email)
    OR (dnc.phone IS NOT NULL AND dnc.phone = cq.phone)
)
LIMIT $remaining
");


while($row = mysqli_fetch_assoc($q2)){

$queue_id = $row['id'];
// $email = $row['email'];
// $phone = $row['phone'];
$contact_id = $row['contact_id'];
$q = mysqli_query($conn, "SELECT * FROM contacts WHERE id = '$contact_id' LIMIT 1");
$contact = mysqli_fetch_assoc($q);

$name  = $contact['name'];
$email = $contact['email'];
$phone = $contact['phone'];

$message_id = "";


$replacements = [
    'NAME'  => $name,
    'EMAIL' => $email,
    'PHONE' => $phone
];


$content = preg_replace_callback('/{{\s*(\w+)\s*}}/', function($matches) use ($replacements) {
    $key = strtoupper($matches[1]);
    return isset($replacements[$key]) ? $replacements[$key] : $matches[0];
}, $content);

// echo "Sending to $email / $phone\n";
// echo "Content: $content\n";

try{

if($type == "email" && $email){

$mail = new \SendGrid\Mail\Mail(); 
$mail->setFrom($fromEmail, $fromName);
$mail->setSubject($subject);
$mail->addTo($email);
$mail->addContent("text/html",$content);

$sendgrid = new \SendGrid($sendgridApiKey);
$response = $sendgrid->send($mail);

    $headers = $response->headers();
    $message_id = "";
    foreach ($headers as $header) {
        if(strpos($header, 'Message-Id') !== false) {
            $msgExp = explode(":",$header);
            $message_id = trim(end($msgExp));        
        }
    }
}

$smsParams = array(
    "from" => $twilio_from,
    "body" => strip_tags($content),
    "statusCallback" => "https://aitrans.co/golfwl/tracking/sms-status.php"
);

if (!empty($validMediaUrls)) {
    $smsParams["mediaUrl"] = $validMediaUrls;
}

if ($type == "sms" && $phone) {

    $client = new Client($twilio_sid, $twilio_token);

    $msg = $client->messages->create(
        $phone,
        $smsParams
    );

    $message_id = $msg->sid;
}


/* update queue */

mysqli_query($conn,"
UPDATE campaign_queue
SET status='sent',
sent_at='$currentTime',
message_id='$message_id',
delivered=1
WHERE id='$queue_id'
");

}catch(Exception $e){

mysqli_query($conn,"
UPDATE campaign_queue
SET status='failed'
WHERE id='$queue_id'
");

}

}


/* campaign progress check */

$pending = mysqli_query($conn,"
SELECT COUNT(*) as total
FROM campaign_queue
WHERE campaign_id='$campaign_id'
AND status='pending'
");

$pending = mysqli_fetch_assoc($pending)['total'];

if($pending == 0){

mysqli_query($conn,"
UPDATE campaigns
SET status='completed'
WHERE id='$campaign_id'
");

}else{

mysqli_query($conn,"
UPDATE campaigns
SET status='in-progress'
WHERE id='$campaign_id'
");

}

}

function generateCampaignQueue($conn,$campaign_id, $sendDate){

$q = mysqli_query($conn,"
SELECT DISTINCT
c.id as contact_id,
c.email,
c.phone
FROM campaign_groups cg
JOIN contact_group_map cgm
ON cg.group_id = cgm.group_id
JOIN contacts c
ON c.id = cgm.contact_id
WHERE cg.campaign_id='$campaign_id'
");

while($row = mysqli_fetch_assoc($q)){

$contact_id = $row['contact_id'];
$email = $row['email'];
$phone = $row['phone'];

mysqli_query($conn,"
INSERT IGNORE INTO campaign_queue
(campaign_id,contact_id,email,phone,status)
VALUES
('$campaign_id','$contact_id','$email','$phone','pending')
");

}

}


if (!function_exists('urlExists')) {
    function urlExists($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true); // only check headers
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($status >= 200 && $status < 400); // allow 2xx & 3xx
    }
}