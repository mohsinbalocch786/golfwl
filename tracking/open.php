<?php

include("../config/db.php");

$id=$_GET['id'];

mysqli_query($conn,"
UPDATE campaign_queue
SET open_count=open_count+1
WHERE id='$id'
");

header("Content-Type:image/gif");

echo base64_decode("R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==");

?>