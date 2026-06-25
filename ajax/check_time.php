<?php

include("../config/db.php");
include("../config/auth.php");
requireLogin();

$date=mysqli_real_escape_string($conn, $_POST['date']);
$time=mysqli_real_escape_string($conn, $_POST['time']);

// scope the "already booked" check to the current user's own campaigns
$ow = ownershipWhere('');

$r=mysqli_query($conn,"
SELECT id 
FROM campaigns
WHERE DATE(schedule_datetime)='$date'
AND send_time='$time'
AND status!='completed'
AND $ow
");

if(mysqli_num_rows($r)>0)
echo "booked";
else
echo "available";
