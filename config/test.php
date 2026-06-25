<?php
date_default_timezone_set('America/Los_Angeles');
include("db.php");

$q = mysqli_query($conn,"SELECT *
FROM campaign_queue");

if($q){
echo "Campaign queue retrieved successfully\n";
// print results
while($row = mysqli_fetch_assoc($q)){
    echo "<pre>"; print_r($row);
}
die;


} else { 
echo "Error retrieving campaign queue: " . mysqli_error($conn) . "\n";
}exit;

?>