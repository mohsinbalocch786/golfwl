<?php 
$host="localhost";
$user="root";
$pass='h.S0b0Y6Rok@';
$db="victor17_golfwls";

$conn=mysqli_connect($host,$user,$pass,$db);

if(!$conn){
die("Database Error");
}
$currentTime = date('Y-m-d H:i:s');
$currentDate = date('Y-m-d');

$secret_key = '8Fj#92kLm@Pq7Xz!A5sD1vBnR6tYwE3u';
$secret_iv = 'R9xT4mK2pQ8zV1cN';

function encryptData($string, $secret_key, $secret_iv) {
    $key = hash('sha256', $secret_key);
    $iv  = substr(hash('sha256', $secret_iv), 0, 16);

    return base64_encode(
        openssl_encrypt($string, 'AES-256-CBC', $key, 0, $iv)
    );
}

function decryptData($string, $secret_key, $secret_iv) {
    $key = hash('sha256', $secret_key);
    $iv  = substr(hash('sha256', $secret_iv), 0, 16);

    return openssl_decrypt(
        base64_decode($string),
        'AES-256-CBC',
        $key,
        0,
        $iv
    );
}
@date_default_timezone_set('America/Los_Angeles');
?>
