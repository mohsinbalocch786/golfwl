<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

$id=(int)$_GET['id'];

$chk=mysqli_query($conn,"SELECT * FROM contacts WHERE id='$id'");
$row=mysqli_fetch_assoc($chk);
if($row){
    assertOwnership($row);

    // 1. Delete from mapping table first
    mysqli_query($conn, "DELETE FROM contact_group_map WHERE contact_id = '$id'");
    // 2. Delete from contacts table
    mysqli_query($conn,"DELETE FROM contacts WHERE id='$id'");
}

header("Location:list.php");
