<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();

require_once "engine.php";

// Scope to rules the current user can see (own + team if manager/admin)
$scopeWhere = ownershipWhere('');

$log = runWorkflowEngine($conn, $scopeWhere);

$_SESSION['flash_success'] = "Engine ran: " . count($log) . " trigger(s) processed.";
if(!empty($log)){
    $_SESSION['flash_log'] = $log;
}

header("Location:list.php");
