<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

// Legacy endpoint (not linked in UI) - kept for backward compatibility.
// Use contacts/import_csv.php for the supported import flow.

list($owner_uid, $owner_mid) = ownershipStamp();

$file=fopen($_FILES['file']['tmp_name'],"r");

while(($data=fgetcsv($file,1000,","))!==FALSE){

$name=mysqli_real_escape_string($conn, isset($data[0]) ? $data[0] : '');
$email=mysqli_real_escape_string($conn, isset($data[1]) ? $data[1] : '');
$phone=mysqli_real_escape_string($conn, isset($data[2]) ? $data[2] : '');

mysqli_query($conn,"
INSERT INTO contacts(name,email,phone,created_at,user_id,manager_id)
VALUES('$name','$email','$phone','$currentTime','$owner_uid','$owner_mid')
");

}

echo "Import Complete";

?>
