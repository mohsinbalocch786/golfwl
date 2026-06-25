<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

$id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);

$r = mysqli_query($conn, "SELECT * FROM workflow_rules WHERE id='$id'");
$rule = mysqli_fetch_assoc($r);

if($rule){
    assertOwnership($rule);
    $newVal = $rule['is_active'] ? 0 : 1;
    mysqli_query($conn, "UPDATE workflow_rules SET is_active='$newVal' WHERE id='$id'");
}

header("Location:list.php");
