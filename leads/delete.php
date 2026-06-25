<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

$id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);

$r = mysqli_query($conn, "SELECT * FROM leads WHERE id='$id'");
$lead = mysqli_fetch_assoc($r);

if($lead){
    assertOwnership($lead);
    mysqli_query($conn, "DELETE FROM leads WHERE id='$id'");
}

header("Location:list.php");