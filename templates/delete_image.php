<?php

include("../config/db.php");
include("../config/auth.php");
requireLogin();

$id = (int)$_POST['id'];

$sql = "SELECT * FROM templates WHERE id = $id";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);

if (!$row) {
    echo "error";
    exit;
}

// ownership check (no redirect - this is an AJAX endpoint)
$canEdit = isSuperAdmin()
    || (int)$row['user_id'] === currentUserId()
    || (isManager() && (int)$row['manager_id'] === currentManagerId());

if (!$canEdit) {
    echo "error";
    exit;
}

if (!empty($row['image'])) {

    $filePath = "smsimage/" . $row['image'];

    if (file_exists($filePath)) {
        unlink($filePath);
    }

    $sql = "UPDATE templates SET image = '' WHERE id = $id";
    mysqli_query($conn, $sql);

    echo "success";

} else {

    echo "error";
}
