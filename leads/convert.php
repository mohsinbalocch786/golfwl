<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

$id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);

$r = mysqli_query($conn, "SELECT * FROM leads WHERE id='$id'");
$lead = mysqli_fetch_assoc($r);

if(!$lead){
    header("Location:list.php");
    exit;
}

assertOwnership($lead);

$title = mysqli_real_escape_string($conn, trim($lead['first_name'].' '.$lead['last_name']).' - Opportunity');
$close_date = date('Y-m-d', strtotime('+30 days'));

// keep ownership consistent with the lead's own owner (not the converting user,
// in case a manager converts a team member's lead)
$owner_uid = (int)$lead['user_id'];
$owner_mid = (int)$lead['manager_id'];

mysqli_query($conn,"INSERT INTO opportunities
    (lead_id,user_id,manager_id,title,amount,stage,probability,expected_close_date,created_at,updated_at)
    VALUES
    ('$id','$owner_uid','$owner_mid','$title',0,'new',10,'$close_date','$currentTime','$currentTime')");

// mark lead as qualified
mysqli_query($conn,"UPDATE leads SET status='qualified', updated_at='$currentTime' WHERE id='$id'");

header("Location:../opportunities/pipeline.php");
