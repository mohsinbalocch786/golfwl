<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

$id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);

$r = mysqli_query($conn, "SELECT * FROM contacts WHERE id='$id'");
$contact = mysqli_fetch_assoc($r);

if(!$contact){
    header("Location:list.php");
    exit;
}

assertOwnership($contact);

$title = mysqli_real_escape_string($conn, trim($contact['first_name'].' '.$contact['last_name']).' - Opportunity');
$close_date = date('Y-m-d', strtotime('+30 days'));

// keep ownership consistent with the lead's own owner (not the converting user,
// in case a manager converts a team member's lead)
$owner_uid = (int)$contact['user_id'];
$owner_mid = (int)$contact['manager_id'];
$contact_id = (int)$contact['id'];
$first_name = mysqli_real_escape_string($conn, $contact['first_name']);
$last_name = mysqli_real_escape_string($conn, $contact['last_name']);
$email = mysqli_real_escape_string($conn, $contact['email']);
$phone = mysqli_real_escape_string($conn, $contact['phone']);
$company = '';
$source = 'contact conversion';
$status = 'new';
$notes = 'Converted from contact: '.$contact['name'];

 mysqli_query($conn,"INSERT INTO leads
                (user_id,manager_id,contact_id,first_name,last_name,email,phone,company,source,status,notes,created_at,updated_at)
                VALUES
                ('$owner_uid','$owner_mid',$contact_id,'$first_name','$last_name','$email','$phone','$company','$source','$status','$notes','$currentTime','$currentTime')");


// mark contact as converted
mysqli_query($conn,"UPDATE contacts SET converted=1, updated_at='$currentTime' WHERE id='$id'");

header("Location:../leads/list.php");
