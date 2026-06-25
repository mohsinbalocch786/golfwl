<?php

include("../config/db.php");
include("../config/auth.php");
requireLogin();

$id=(int)$_GET['id'];

$chk=mysqli_query($conn,"SELECT * FROM templates WHERE id='$id'");
$row=mysqli_fetch_assoc($chk);

if($row){
    assertOwnership($row);

    // remove uploaded image file if present
    if(!empty($row['image'])){
        $filePath = "smsimage/" . $row['image'];
        if(file_exists($filePath)){
            unlink($filePath);
        }
    }

    mysqli_query($conn,"DELETE FROM templates WHERE id='$id'");
}

header("Location:list.php");
