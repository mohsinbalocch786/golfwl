<?php
if(session_status()==PHP_SESSION_NONE){
session_start();
}

require_once __DIR__ . "/../config/auth.php";

if(!isLoggedIn()){
header("Location:../admin/login.php");
exit;
}
// ── Build relative path prefix to root ──────────────────────────────────────
// Figures out how many ../ are needed to reach the app root from any page.
$scriptPath = str_replace('\\','/', $_SERVER['SCRIPT_FILENAME']);
$rootPath   = str_replace('\\','/', realpath(__DIR__.'/..'));
$rel        = '';
$diff       = str_replace($rootPath.'/', '', $scriptPath);
$depth      = substr_count(rtrim($diff,'/'), '/');
for($i = 0; $i < $depth; $i++) $rel .= '../';
// e.g. from leads/view.php → $rel = '../'
//      from leads/partials/x.php → $rel = '../../'
//      from admin/dashboard.php  → $rel = '../'
?>

<!DOCTYPE html>
<html>

<head>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Email Blaster Admin</title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">  

<!-- Date Range Picker css -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />

<!-- <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css" rel="stylesheet" /> -->

<!--  Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/shepherd.js@10.0.1/dist/css/shepherd.css">
<style>
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: #007bff;
        border-color: #007bff;
        color: white;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: red;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
        color: red;
    }
    .badge-role-manager { background:#4f46e5; color:#fff; }
    .badge-role-user    { background:#94a3b8; color:#fff; }
</style>

<style>
/* ── Select2 styling ─────────────────────────────────────── */
.select2-container--default .select2-selection--multiple .select2-selection__choice {
    background-color:#007bff; border-color:#007bff; color:#fff;
}
.select2-container--default .select2-selection--multiple .select2-selection__choice__remove { color:red; }
.select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover { color:darkred; }

/* ── Role badges ─────────────────────────────────────────── */
.badge-role-manager { background:#4f46e5; color:#fff; }
.badge-role-user    { background:#94a3b8; color:#fff; }

/* ── Notification bell ───────────────────────────────────── */
.notif-bell-wrap {
    position: relative;
}
.notif-bell-wrap .notif-count-badge {
    position: absolute;
    top: 4px;
    right: 4px;
    min-width: 16px;
    height: 16px;
    padding: 0 4px;
    font-size: 10px;
    font-weight: 700;
    line-height: 16px;
    border-radius: 8px;
    background: #dc3545;
    color: #fff;
    text-align: center;
    display: none;
    pointer-events: none;
}
/* ── Notification dropdown ───────────────────────────────── */
.notif-dropdown {
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    width: 340px;
    max-height: 480px;
    overflow-y: auto;
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    box-shadow: 0 8px 24px rgba(0,0,0,.15);
    display: none;
    z-index: 9999;
}
.notif-dropdown.open { display: block; }
.notif-dropdown-header {
    padding: 10px 14px;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    border-radius: 8px 8px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: .8rem;
    font-weight: 600;
    color: #495057;
}
.notif-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px 14px;
    border-bottom: 1px solid #f1f3f5;
    text-decoration: none;
    color: #212529;
    transition: background .12s;
}
.notif-item:hover { background: #f8f9fa; text-decoration: none; color: #212529; }
.notif-item.unread { background: #eef5ff; }
.notif-item .notif-icon {
    flex-shrink: 0;
    width: 34px; height: 34px;
    border-radius: 50%;
    background: #28a745;
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: .85rem;
}
.notif-item .notif-body { flex: 1; min-width: 0; }
.notif-item .notif-name { font-size: .8rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.notif-item .notif-msg  { font-size: .75rem; color: #6c757d; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.notif-item .notif-time { font-size: .7rem; color: #adb5bd; white-space: nowrap; }
.notif-empty {
    padding: 24px;
    text-align: center;
    color: #adb5bd;
    font-size: .8rem;
}
.notif-footer {
    padding: 8px 14px;
    text-align: center;
    border-top: 1px solid #dee2e6;
    font-size: .78rem;
}
/* ── Bell animation when new message ─────────────────────── */
@keyframes bellShake {
    0%,100%{ transform:rotate(0); }
    15%    { transform:rotate(15deg); }
    30%    { transform:rotate(-12deg); }
    45%    { transform:rotate(8deg); }
    60%    { transform:rotate(-5deg); }
    75%    { transform:rotate(3deg); }
}
.bell-shake { animation: bellShake .6s ease; }
</style>

</head>

<body class="hold-transition sidebar-mini">

<div class="wrapper">

<!-- Navbar -->
<nav class="main-header navbar navbar-expand navbar-white navbar-light">

<ul class="navbar-nav">

<li class="nav-item">
<a class="nav-link" data-widget="pushmenu" href="#">
<i class="fas fa-bars"></i>
</a>
</li>

</ul>

<ul class="navbar-nav ml-auto">
<!-- ── SMS Notification Bell ──────────────────────────────────── -->
        <li class="nav-item mr-2" style="position:relative;">
            <button id="notif-bell-btn" class="btn btn-link nav-link px-2 notif-bell-wrap"
                    style="position:relative; background:none; border:none; cursor:pointer;"
                    title="SMS Notifications" aria-label="SMS notifications">
                <i id="notif-bell-icon" class="fas fa-bell" style="font-size:1.1rem; color:#555;"></i>
                <span id="notif-count-badge" class="notif-count-badge"></span>
            </button>

            <!-- Dropdown panel -->
            <div id="notif-dropdown" class="notif-dropdown">
                <div class="notif-dropdown-header">
                    <span><i class="fas fa-sms mr-1 text-success"></i> Unread SMS Messages</span>
                    <button id="notif-mark-all" class="btn btn-xs btn-outline-secondary py-0"
                            style="font-size:.7rem; display:none;">Mark all read</button>
                </div>
                <div id="notif-list">
                    <div class="notif-empty" id="notif-empty">
                        <i class="fas fa-check-circle text-success fa-lg mb-1"></i><br>
                        No unread messages
                    </div>
                </div>
                <div class="notif-footer">
                    <a href="<?php echo $rel; ?>leads/list.php" class="text-primary">
                        View all leads &rarr;
                    </a>
                </div>
            </div>
        </li>
<li class="nav-item">
<span class="nav-link">
<?php
    if (isSuperAdmin()) {
        echo '<span class="badge badge-role-manager">ADMIN</span> ' . htmlspecialchars(isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : 'Admin');
    } else {
        $roleLabel = (isset($_SESSION['role']) ? $_SESSION['role'] : '') === 'manager' ? 'MANAGER' : 'USER';
        $badgeCls  = (isset($_SESSION['role']) ? $_SESSION['role'] : '') === 'manager' ? 'badge-role-manager' : 'badge-role-user';
        echo '<span class="badge '.$badgeCls.'">'.$roleLabel.'</span> ' . htmlspecialchars(isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User');
    }
?>
</span>
</li>

<li class="nav-item">
<a href="../admin/logout.php" class="nav-link">
Logout
</a>
</li>

</ul>

</nav>