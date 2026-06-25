<!-- Sidebar -->

<?php
require_once __DIR__ . "/../config/auth.php";

// detect active menu by folder
function isActive($folder){
    return (strpos($_SERVER['REQUEST_URI'], $folder) !== false) ? 'active' : '';
}
?>

<aside class="main-sidebar sidebar-dark-primary elevation-4">

<a href="../admin/dashboard.php" class="brand-link">
    <span class="brand-text font-weight-light">Email Blaster</span>
</a>

<div class="sidebar">

<nav class="mt-2">

<ul class="nav nav-pills nav-sidebar flex-column">

    <!-- Dashboard -->
    <li class="nav-item">
        <a href="../admin/dashboard.php" class="nav-link <?= isActive('admin') ?>">
            <i class="nav-icon fas fa-tachometer-alt"></i>
            <p>Dashboard</p>
        </a>
    </li>

    <?php if(isSuperAdmin() || isManager()){ ?>
    <!-- User -->
    <li class="nav-item">
        <a href="../users/list.php" class="nav-link <?= isActive('users') ?>">
            <i class="nav-icon fas fa-users"></i>
            <p>Users</p>
        </a>
    </li>
    <?php } ?>

    <!-- Twilio Numbers -->
    <li class="nav-item">
        <a href="../TwilioNumber/list.php" class="nav-link <?= isActive('TwilioNumber') ?>">
            <i class="nav-icon fas fa-phone"></i>
            <p>Twilio Numbers</p>
        </a>
    </li>

    <!-- Contacts -->
    <li class="nav-item">
        <a href="../contacts/list.php" class="nav-link <?= isActive('contacts') ?>">
            <i class="nav-icon fas fa-users"></i>
            <p>Contacts</p>
        </a>
    </li>

    <!-- Groups -->
    <li class="nav-item">
        <a href="../groups/list.php" class="nav-link <?= isActive('groups') ?>">
            <i class="nav-icon fas fa-layer-group"></i>
            <p>Groups</p>
        </a>
    </li>

    <!-- Templates -->
    <li class="nav-item">
        <a href="../templates/list.php" class="nav-link <?= isActive('templates') ?>">
            <i class="nav-icon fas fa-envelope"></i>
            <p>Templates</p>
        </a>
    </li>

    <!-- Campaigns -->
    <li class="nav-item">
        <a href="../campaigns/list.php" class="nav-link <?= isActive('campaigns') ?>">
            <i class="nav-icon fas fa-bullhorn"></i>
            <p>Campaigns</p>
        </a>
    </li>

    <!-- Leads -->
    <li class="nav-item">
        <a href="../leads/list.php" class="nav-link <?= isActive('leads') ?>">
            <i class="nav-icon fas fa-bullseye"></i>
            <p>Leads <span id="sms-unread-badge" class="badge badge-danger badge-pill float-right" style="display:none;"></span></p>
        </a>
    </li>

    <!-- Opportunities / Pipeline -->
    <li class="nav-item">
        <a href="../opportunities/pipeline.php" class="nav-link <?= isActive('opportunities') ?>">
            <i class="nav-icon fas fa-stream"></i>
            <p>Pipeline</p>
        </a>
    </li>

    <!-- Tasks & Follow-ups -->
    <li class="nav-item">
        <a href="../tasks/list.php" class="nav-link <?= isActive('tasks') ?>">
            <i class="nav-icon fas fa-tasks"></i>
            <p>Tasks</p>
        </a>
    </li>

    <!-- Workflow Automation -->
    <li class="nav-item">
        <a href="../workflow/list.php" class="nav-link <?= isActive('workflow') ?>">
            <i class="nav-icon fas fa-bolt"></i>
            <p>Workflow</p>
        </a>
    </li>


    <?php if(isSuperAdmin() || canViewTeam()){ ?>
    <!-- Team Reports (manager / can_view_team users) -->
    <li class="nav-item">
        <a href="../reports/team.php" class="nav-link <?= isActive('reports') ?>">
            <i class="nav-icon fas fa-chart-line"></i>
            <p>Team Reports</p>
        </a>
    </li>
    <?php } ?>

    <?php if(isSuperAdmin()){ ?>
    <!-- Settings -->
    <li class="nav-item">
        <a href="../settings/setting.php" class="nav-link <?= isActive('settings') ?>">
            <i class="nav-icon fas fa-cog"></i>
            <p>Settings</p>
        </a>
    </li>
    <!-- Do-Not-Contact -->
    <li class="nav-item">
        <a href="../dnc/list.php" class="nav-link <?= isActive('dnc') ?>">
            <i class="nav-icon fas fa-ban"></i>
            <p>Do-Not-Contact</p>
        </a>
    </li>
    <!-- Audit Log -->
    <li class="nav-item">
        <a href="../audit/log.php" class="nav-link <?= isActive('audit') ?>">
            <i class="nav-icon fas fa-history"></i>
            <p>Audit Log</p>
        </a>
    </li>
    <?php } ?>

        <!-- Knowledge Base -->
    <li class="nav-item">
        <a href="../knowledge/index.php" class="nav-link <?= isActive('knowledge') ?>">
            <i class="nav-icon fas fa-book-open"></i>
            <p>Knowledge Base</p>
        </a>
    </li>

</ul>

</nav>

</div>

</aside>

<div class="content-wrapper">

<section class="content pt-3">

<div class="container-fluid">

<!-- Global SMS notification popup (injected by JS) -->
<div id="sms-notif-popup" style="position:fixed;bottom:24px;right:24px;z-index:9999;display:none;min-width:280px;max-width:320px;"></div>
