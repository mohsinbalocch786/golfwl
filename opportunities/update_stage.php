<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

header('Content-Type: application/json');

// CSRF check
if(empty($_POST['csrf']) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])){
    echo json_encode(['ok'=>false,'error'=>'Invalid CSRF token']);
    exit;
}

$id    = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
$stage = isset($_POST['stage']) ? $_POST['stage'] : '';

$valid = ['new','qualified','proposal','negotiation','won','lost'];
if(!$id || !in_array($stage, $valid)){
    echo json_encode(['ok'=>false,'error'=>'Invalid input']);
    exit;
}

$r = mysqli_query($conn, "SELECT * FROM opportunities WHERE id='$id'");
$opp = mysqli_fetch_assoc($r);

if(!$opp){
    echo json_encode(['ok'=>false,'error'=>'Not found']);
    exit;
}

assertOwnership($opp);

// auto-update probability based on stage
$probMap = ['new'=>10,'qualified'=>25,'proposal'=>50,'negotiation'=>75,'won'=>100,'lost'=>0];
$probability = $probMap[$stage];

mysqli_query($conn,"UPDATE opportunities SET stage='$stage', probability='$probability', updated_at='$currentTime' WHERE id='$id'");

// reflect won/lost back onto the linked lead
if(in_array($stage, ['won','lost']) && !empty($opp['lead_id'])){
    $lead_id = (int)$opp['lead_id'];
    mysqli_query($conn,"UPDATE leads SET status='$stage', updated_at='$currentTime' WHERE id='$lead_id'");
}

echo json_encode(['ok'=>true]);