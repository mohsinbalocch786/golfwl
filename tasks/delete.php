<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

$id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);

$r = mysqli_query($conn, "SELECT * FROM tasks WHERE id='$id'");
$task = mysqli_fetch_assoc($r);

if($task){
    assertOwnership($task);
    mysqli_query($conn, "DELETE FROM tasks WHERE id='$id'");
}

header("Location:list.php");