<?php

require '../vendor/autoload.php';

use Twilio\Rest\Client;

function sendSMS($phone,$msg){

$sid="TWILIO_SID";
$token="TWILIO_TOKEN";

$client=new Client($sid,$token);

$message=$client->messages->create(
$phone,
[
'from'=>"+123456789",
'body'=>$msg
]
);

return $message->sid;

}

?>