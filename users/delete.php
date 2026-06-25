<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

if(!isSuperAdmin() && !isManager()){
    auditLog($conn, "delete_user", "users", $id);
header("Location:list.php");
    exit;
}

$id=(int)$_GET['id'];

// ownership check for managers
if(!isSuperAdmin()){
    $chk = mysqli_query($conn, "SELECT * FROM users WHERE id='$id'");
    $target = mysqli_fetch_assoc($chk);
    $uid = currentUserId();
    if(!$target || ((int)$target['id'] !== $uid && (int)(isset($target['manager_id']) ? $target['manager_id'] : -1) !== $uid)){
        header("Location:list.php");
        exit;
    }
    // prevent deleting yourself
    if((int)$target['id'] === $uid){
        header("Location:list.php");
        exit;
    }
}

mysqli_query($conn,"DELETE FROM users WHERE id='$id'");

header("Location:list.php");
