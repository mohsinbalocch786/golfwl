<?php

include("../config/db.php");
include("../config/auth.php");
requireLogin();

$type = mysqli_real_escape_string($conn, $_GET['type']);

// Visibility-aware scoping (same rule as templates/list.php)
if(isSuperAdmin()){
    $tWhere = "1=1";
} else {
    $uid = currentUserId();
    $mid = currentManagerId();
    $visParts = [];
    $visParts[] = "visibility='global'";
    $visParts[] = "user_id=$uid";
    if(canViewTeam()){
        $visParts[] = "(visibility='team' AND manager_id=$mid)";
    }
    $tWhere = "(" . implode(" OR ", $visParts) . ")";
}

$r=mysqli_query($conn,"
SELECT id,name 
FROM templates 
WHERE type='$type' 
AND status='active'
AND $tWhere
ORDER BY name
");

echo '<option value="">Select Template</option>';

while($row=mysqli_fetch_assoc($r)){

echo '<option value="'.$row['id'].'">'.htmlspecialchars($row['name']).'</option>';

}
