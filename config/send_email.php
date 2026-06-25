<?php

require '../vendor/autoload.php';

function sendEmail($to,$subject,$content){

$email = new \SendGrid\Mail\Mail();

$email->setFrom("marketing@yourdomain.com","Marketing");

$email->setSubject($subject);

$email->addTo($to);

$email->addContent("text/html",$content);

$sendgrid = new \SendGrid("SENDGRID_API_KEY");

$response = $sendgrid->send($email);

return $response;

}

?>