<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

$id=(int)$_GET['id'];

$chk=mysqli_query($conn,"SELECT * FROM campaigns WHERE id='$id'");
$campaign=mysqli_fetch_assoc($chk);

if($campaign){
    assertOwnership($campaign);

    mysqli_query($conn,"DELETE FROM campaigns WHERE id='$id'");
    mysqli_query($conn,"DELETE FROM campaign_groups WHERE campaign_id='$id'");
    mysqli_query($conn,"DELETE FROM campaign_queue WHERE campaign_id='$id'");
}

header("Location:list.php");
