<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

$id=(int)$_GET['id'];

$chk=mysqli_query($conn,"SELECT * FROM contact_groups WHERE id='$id'");
$group=mysqli_fetch_assoc($chk);

if($group){
    assertOwnership($group);

    mysqli_query($conn,"DELETE FROM contact_groups WHERE id='$id'");
    mysqli_query($conn,"DELETE FROM contact_group_map WHERE group_id='$id'");
}

header("Location:list.php");
