<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

$id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);

$r = mysqli_query($conn, "SELECT * FROM opportunities WHERE id='$id'");
$opp = mysqli_fetch_assoc($r);

if($opp){
    assertOwnership($opp);
    mysqli_query($conn, "DELETE FROM opportunities WHERE id='$id'");
}

header("Location:pipeline.php");