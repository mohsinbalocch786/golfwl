<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

$id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);

$r = mysqli_query($conn, "SELECT * FROM workflow_rules WHERE id='$id'");
$rule = mysqli_fetch_assoc($r);

if($rule){
    assertOwnership($rule);
    mysqli_query($conn, "DELETE FROM workflow_rules WHERE id='$id'");
    mysqli_query($conn, "DELETE FROM workflow_logs WHERE rule_id='$id'");
}

header("Location:list.php");
