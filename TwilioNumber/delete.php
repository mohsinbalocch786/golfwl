<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

// Only managers/admins can delete Twilio numbers (shared resource)
if(!isSuperAdmin() && !isManager()){
    header("Location:list.php");
    exit;
}

$id=(int)$_GET['id'];

mysqli_query($conn,"DELETE FROM twilio_numbers WHERE id='$id'");

header("Location:list.php");
