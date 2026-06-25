<?php
include("../config/db.php");
include("../config/auth.php");
requireLogin();
include("../layout/header.php");
include("../layout/sidebar.php");
?>

<style>
/* ── Knowledge Base Styles ──────────────────────────────────── */
.kb-wrap { display:flex; gap:0; min-height:80vh; }

/* Sticky left nav */
.kb-nav {
    width: 240px;
    flex-shrink: 0;
    position: sticky;
    top: 20px;
    align-self: flex-start;
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    overflow: hidden;
    max-height: calc(100vh - 80px);
    overflow-y: auto;
}
.kb-nav-section {
    padding: 8px 14px 4px;
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .8px;
    color: #adb5bd;
    margin-top: 6px;
}
.kb-nav a {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 7px 14px;
    font-size: .82rem;
    color: #495057;
    text-decoration: none;
    border-left: 3px solid transparent;
    transition: all .12s;
}
.kb-nav a:hover  { background: #f8f9fa; color: #007bff; }
.kb-nav a.active { background: #eef5ff; color: #007bff; border-left-color: #007bff; font-weight: 600; }
.kb-nav a .nav-icon { width: 18px; text-align: center; font-size: .85rem; }

/* Main content */
.kb-content { flex: 1; padding: 0 0 0 24px; min-width: 0; }

/* Article sections */
.kb-section {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    margin-bottom: 20px;
    overflow: hidden;
    scroll-margin-top: 20px;
}
.kb-section-header {
    padding: 14px 20px;
    background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
    border-bottom: 1px solid #dee2e6;
    display: flex;
    align-items: center;
    gap: 12px;
}
.kb-section-icon {
    width: 38px; height: 38px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; color: #fff; flex-shrink: 0;
}
.kb-section-header h2 { margin: 0; font-size: 1.1rem; font-weight: 700; color: #212529; }
.kb-section-header p  { margin: 0; font-size: .8rem; color: #6c757d; }
.kb-section-body { padding: 20px; }

/* Steps */
.kb-steps { list-style: none; padding: 0; margin: 0; counter-reset: step; }
.kb-steps li {
    counter-increment: step;
    display: flex; gap: 14px;
    margin-bottom: 16px;
    padding-bottom: 16px;
    border-bottom: 1px solid #f1f3f5;
}
.kb-steps li:last-child { border-bottom: 0; margin-bottom: 0; padding-bottom: 0; }
.kb-steps li::before {
    content: counter(step);
    flex-shrink: 0;
    width: 26px; height: 26px;
    border-radius: 50%;
    background: #007bff;
    color: #fff;
    font-size: .75rem; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    margin-top: 1px;
}
.kb-steps li .step-body { flex: 1; }
.kb-steps li strong { display: block; font-size: .88rem; color: #212529; margin-bottom: 3px; }
.kb-steps li p { font-size: .82rem; color: #6c757d; margin: 0; }

/* Tables */
.kb-table { width: 100%; border-collapse: collapse; font-size: .82rem; margin: 12px 0; }
.kb-table th { background: #f8f9fa; padding: 8px 12px; text-align: left; font-weight: 600; color: #495057; border: 1px solid #dee2e6; }
.kb-table td { padding: 8px 12px; border: 1px solid #dee2e6; color: #495057; vertical-align: top; }
.kb-table tr:hover td { background: #f8f9fa; }

/* Tips / warnings */
.kb-tip    { background: #eef5ff; border-left: 3px solid #007bff; padding: 10px 14px; border-radius: 0 6px 6px 0; margin: 12px 0; font-size: .82rem; color: #495057; }
.kb-warn   { background: #fff8e1; border-left: 3px solid #ffc107; padding: 10px 14px; border-radius: 0 6px 6px 0; margin: 12px 0; font-size: .82rem; color: #495057; }
.kb-danger { background: #fff0f0; border-left: 3px solid #dc3545; padding: 10px 14px; border-radius: 0 6px 6px 0; margin: 12px 0; font-size: .82rem; color: #495057; }
.kb-tip i, .kb-warn i, .kb-danger i { margin-right: 6px; }

/* Badges inline */
.kb-badge { display:inline-block; padding:2px 8px; border-radius:20px; font-size:.72rem; font-weight:600; }
.kb-badge-blue   { background:#dbeafe; color:#1e40af; }
.kb-badge-green  { background:#d1fae5; color:#065f46; }
.kb-badge-yellow { background:#fef9c3; color:#713f12; }
.kb-badge-red    { background:#fee2e2; color:#991b1b; }
.kb-badge-purple { background:#ede9fe; color:#5b21b6; }
.kb-badge-gray   { background:#f1f5f9; color:#475569; }

/* Code blocks */
.kb-code { background:#1e293b; color:#e2e8f0; padding:12px 16px; border-radius:6px; font-family:monospace; font-size:.8rem; overflow-x:auto; margin:10px 0; }
.kb-code .c { color:#94a3b8; } /* comment */
.kb-code .k { color:#7dd3fc; } /* keyword */
.kb-code .s { color:#86efac; } /* string */

/* Two column grid */
.kb-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.kb-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; }

/* FAQ accordion */
.kb-faq-q {
    background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px;
    padding: 10px 14px; cursor: pointer; font-size: .85rem; font-weight: 600;
    color: #495057; margin-bottom: 6px;
    display: flex; justify-content: space-between; align-items: center;
}
.kb-faq-q:hover { background: #eef5ff; color: #007bff; }
.kb-faq-a { display:none; padding: 10px 14px; font-size:.82rem; color:#6c757d; border: 1px solid #dee2e6; border-top:0; border-radius: 0 0 6px 6px; margin-bottom:10px; }

/* Search bar */
.kb-search-wrap { margin-bottom:20px; position:relative; }
.kb-search-wrap input { width:100%; padding:10px 16px 10px 40px; border:1px solid #dee2e6; border-radius:8px; font-size:.9rem; outline:none; }
.kb-search-wrap input:focus { border-color:#007bff; box-shadow:0 0 0 3px rgba(0,123,255,.1); }
.kb-search-wrap .search-icon { position:absolute; left:13px; top:50%; transform:translateY(-50%); color:#adb5bd; }
.kb-search-no-result { display:none; text-align:center; color:#adb5bd; padding:40px; font-size:.9rem; }

/* Role pill */
.role-admin   { background:#4f46e5; color:#fff; padding:2px 8px; border-radius:20px; font-size:.7rem; font-weight:600; }
.role-manager { background:#0369a1; color:#fff; padding:2px 8px; border-radius:20px; font-size:.7rem; font-weight:600; }
.role-user    { background:#64748b; color:#fff; padding:2px 8px; border-radius:20px; font-size:.7rem; font-weight:600; }

@media(max-width:768px){
    .kb-wrap { flex-direction:column; }
    .kb-nav { width:100%; position:static; max-height:none; }
    .kb-content { padding:0; margin-top:16px; }
    .kb-grid, .kb-grid-3 { grid-template-columns:1fr; }
}
</style>

<section class="content-header">
<div class="container-fluid">
    <div class="row mb-2">
        <div class="col-sm-8">
            <h1><i class="fas fa-book-open mr-2 text-primary"></i>Knowledge Base</h1>
            <p class="text-muted mb-0">Complete guide to using the Email &amp; SMS Blaster CRM system</p>
        </div>
        <div class="col-sm-4 text-right">
            <small class="text-muted">Last updated: <?php echo date('F j, Y'); ?></small>
        </div>
    </div>
</div>
</section>

<section class="content">
<div class="container-fluid">

<!-- Search -->
<div class="kb-search-wrap">
    <i class="fas fa-search search-icon"></i>
    <input type="text" id="kb-search" placeholder="Search the knowledge base… e.g. 'import contacts', 'create campaign', 'workflow'">
</div>
<div class="kb-search-no-result" id="kb-no-result">
    <i class="fas fa-search fa-2x mb-2"></i><br>No results found for your search.
</div>

<div class="kb-wrap">

<!-- ── Left Navigation ─────────────────────────────────── -->
<nav class="kb-nav" id="kb-nav">
    <div class="kb-nav-section">Getting Started</div>
    <a href="#gs-overview"    class="active"><span class="nav-icon">🏠</span> System Overview</a>
    <a href="#gs-roles">      <span class="nav-icon">👤</span> Roles &amp; Permissions</a>
    <a href="#gs-login">      <span class="nav-icon">🔑</span> Login &amp; Navigation</a>
    <a href="#gs-dashboard">  <span class="nav-icon">📊</span> Dashboard</a>

    <div class="kb-nav-section">Contacts &amp; Groups</div>
    <a href="#contacts">      <span class="nav-icon">👥</span> Contacts</a>
    <a href="#groups">        <span class="nav-icon">📁</span> Groups</a>

    <div class="kb-nav-section">Messaging</div>
    <a href="#templates">     <span class="nav-icon">📝</span> Templates</a>
    <a href="#campaigns">     <span class="nav-icon">📣</span> Campaigns</a>

    <div class="kb-nav-section">CRM</div>
    <a href="#leads">         <span class="nav-icon">🎯</span> Leads</a>
    <a href="#lead-view">     <span class="nav-icon">💬</span> Lead Detail &amp; SMS Chat</a>
    <a href="#opportunities"> <span class="nav-icon">💼</span> Pipeline</a>
    <a href="#tasks">         <span class="nav-icon">✅</span> Tasks</a>
    <a href="#workflow">      <span class="nav-icon">⚡</span> Workflow</a>

    <div class="kb-nav-section">Admin</div>
    <a href="#users">         <span class="nav-icon">🧑‍💼</span> Users</a>
    <a href="#twilio-numbers"><span class="nav-icon">📱</span> Twilio Numbers</a>
    <a href="#settings">      <span class="nav-icon">⚙️</span> Settings</a>
    <a href="#reports">       <span class="nav-icon">📈</span> Reports</a>

    <div class="kb-nav-section">Technical</div>
    <a href="#cron">          <span class="nav-icon">🕐</span> Cron &amp; Automation</a>
    <a href="#webhooks">      <span class="nav-icon">🔗</span> Webhooks</a>
    <a href="#faq">           <span class="nav-icon">❓</span> FAQ</a>
</nav>

<!-- ── Main Content ───────────────────────────────────── -->
<div class="kb-content" id="kb-articles">

<!-- ══════════════════════════════════════════════════════ -->
<!-- SYSTEM OVERVIEW                                         -->
<!-- ══════════════════════════════════════════════════════ -->
<div class="kb-section" id="gs-overview">
    <div class="kb-section-header">
        <div class="kb-section-icon" style="background:#4f46e5;"><i class="fas fa-home"></i></div>
        <div>
            <h2>System Overview</h2>
            <p>What this platform does and how the modules connect</p>
        </div>
    </div>
    <div class="kb-section-body">
        <p>The <strong>Email &amp; SMS Blaster CRM</strong> is an all-in-one platform for managing contacts, sending bulk email/SMS campaigns, nurturing leads through a sales pipeline, and automating follow-ups.</p>

        <div class="kb-grid mt-3">
            <div>
                <h5><i class="fas fa-envelope text-primary mr-1"></i> Email &amp; SMS Blasting</h5>
                <p class="small text-muted">Upload contacts, build groups, create templates, and fire campaigns — all scheduled and tracked automatically.</p>
            </div>
            <div>
                <h5><i class="fas fa-bullseye text-danger mr-1"></i> CRM Pipeline</h5>
                <p class="small text-muted">Track prospects from first contact to closed deal with Leads, Opportunities, and Tasks modules.</p>
            </div>
            <div>
                <h5><i class="fas fa-comments text-success mr-1"></i> Live SMS Chat</h5>
                <p class="small text-muted">Two-way SMS conversations on any lead's detail page. Inbound replies show in real-time with a notification bell.</p>
            </div>
            <div>
                <h5><i class="fas fa-bolt text-warning mr-1"></i> Workflow Automation</h5>
                <p class="small text-muted">Set up rules like "when a lead is created → create a follow-up task" or "when opportunity stage changes → send email".</p>
            </div>
        </div>

        <h5 class="mt-4">Module Flow</h5>
        <div class="kb-code">
<span class="c">// Typical usage flow:</span>
Settings (API keys) → Contacts (upload CSV) → Groups (segment contacts)
  → Templates (write email/SMS) → Campaigns (schedule &amp; send)
     → Leads (track prospects) → Opportunities (manage pipeline)
        → Tasks (follow-ups) → Workflow (automate)
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════ -->
<!-- ROLES & PERMISSIONS                                     -->
<!-- ══════════════════════════════════════════════════════ -->
<div class="kb-section" id="gs-roles">
    <div class="kb-section-header">
        <div class="kb-section-icon" style="background:#0369a1;"><i class="fas fa-user-shield"></i></div>
        <div>
            <h2>Roles &amp; Permissions</h2>
            <p>Three role levels control what each person can see and do</p>
        </div>
    </div>
    <div class="kb-section-body">

        <table class="kb-table">
            <thead>
                <tr>
                    <th>Feature</th>
                    <th><span class="role-admin">Super Admin</span></th>
                    <th><span class="role-manager">Manager</span></th>
                    <th><span class="role-user">User</span></th>
                </tr>
            </thead>
            <tbody>
                <tr><td>View own records</td><td>✅</td><td>✅</td><td>✅</td></tr>
                <tr><td>View team's records</td><td>✅ All</td><td>✅ Team only</td><td>❌ (unless flagged)</td></tr>
                <tr><td>Create/edit contacts, leads, campaigns</td><td>✅</td><td>✅</td><td>✅</td></tr>
                <tr><td>Create/manage users</td><td>✅</td><td>✅ (own team)</td><td>❌</td></tr>
                <tr><td>Add/edit Twilio Numbers</td><td>✅</td><td>✅</td><td>❌ (view only)</td></tr>
                <tr><td>Change system Settings</td><td>✅</td><td>❌</td><td>❌</td></tr>
                <tr><td>Template visibility: Global</td><td>✅</td><td>✅</td><td>❌ (private only)</td></tr>
                <tr><td>View Team Reports</td><td>✅</td><td>✅</td><td>Only if flagged</td></tr>
                <tr><td>SMS notification bell</td><td>All users' SMS</td><td>Team's SMS</td><td>Own SMS only</td></tr>
            </tbody>
        </table>

        <div class="kb-tip"><i class="fas fa-info-circle text-primary"></i> <strong>Can View Team</strong> — A regular user can be granted read-only access to their team's data by a Manager checking "Can View Team" when creating/editing the user account.</div>

        <h5 class="mt-3">Data Ownership Rules</h5>
        <p class="small">Every record (contact, lead, campaign, template, task, opportunity) stores <code>user_id</code> and <code>manager_id</code>. Queries automatically filter based on your role:</p>
        <ul class="small text-muted">
            <li><strong>User:</strong> sees only records where <code>user_id = me</code></li>
            <li><strong>Manager:</strong> sees records where <code>user_id = me</code> OR <code>manager_id = me</code> (own + team)</li>
            <li><strong>Super Admin:</strong> sees everything — no filter applied</li>
        </ul>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════ -->
<!-- LOGIN & NAVIGATION                                      -->
<!-- ══════════════════════════════════════════════════════ -->
<div class="kb-section" id="gs-login">
    <div class="kb-section-header">
        <div class="kb-section-icon" style="background:#059669;"><i class="fas fa-sign-in-alt"></i></div>
        <div>
            <h2>Login &amp; Navigation</h2>
            <p>Signing in and finding your way around</p>
        </div>
    </div>
    <div class="kb-section-body">
        <div class="kb-grid">
            <div>
                <h5>Login</h5>
                <ol class="kb-steps">
                    <li><strong>Go to the login page</strong><p>Navigate to <code>/admin/login.php</code></p></li>
                    <li><strong>Enter credentials</strong><p>Use the email and password provided by your admin. Both super admins (from the <code>admins</code> table) and regular users (from the <code>users</code> table) use the same login page.</p></li>
                    <li><strong>You're in</strong><p>You'll land on the Dashboard. Your role badge shows in the top-right navbar.</p></li>
                </ol>
            </div>
            <div>
                <h5>Top Navbar</h5>
                <table class="kb-table">
                    <tr><th>Element</th><th>What it does</th></tr>
                    <tr><td><i class="fas fa-bars"></i> Hamburger</td><td>Collapse/expand the left sidebar</td></tr>
                    <tr><td><i class="fas fa-bell"></i> Bell icon</td><td>SMS notification centre — shows unread inbound messages. Count updates every 30 s</td></tr>
                    <tr><td>Role badge + Name</td><td>Your current role and display name</td></tr>
                    <tr><td>Logout</td><td>Ends your session securely</td></tr>
                </table>

                <h5 class="mt-3">Sidebar</h5>
                <p class="small text-muted">The sidebar shows only the sections available to your role. Managers see an additional Team Reports link. Super admins see Settings.</p>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════ -->
<!-- DASHBOARD                                               -->
<!-- ══════════════════════════════════════════════════════ -->
<div class="kb-section" id="gs-dashboard">
    <div class="kb-section-header">
        <div class="kb-section-icon" style="background:#7c3aed;"><i class="fas fa-tachometer-alt"></i></div>
        <div>
            <h2>Dashboard</h2>
            <p>Your live performance overview — all data is scoped to your role</p>
        </div>
    </div>
    <div class="kb-section-body">
        <div class="kb-grid-3">
            <div><span class="kb-badge kb-badge-blue">KPI Cards</span><p class="small mt-1 text-muted">Contacts, Leads, Pipeline Value, Campaigns, Open Tasks, Unread SMS. Click any card to go directly to that module.</p></div>
            <div><span class="kb-badge kb-badge-green">Charts</span><p class="small mt-1 text-muted">Email open/click/bounce doughnut, SMS delivery doughnut, Lead status pie, 12-month sends line chart, Pipeline value by stage bar chart.</p></div>
            <div><span class="kb-badge kb-badge-yellow">Activity Panels</span><p class="small mt-1 text-muted">Recent campaigns (with sent/opened counts), Recent leads, and Overdue tasks — all at a glance.</p></div>
        </div>

        <div class="kb-tip mt-3"><i class="fas fa-question-circle text-primary"></i> <strong>Take a Tour</strong> — Click the "Take a Tour" button (top right of dashboard) to get a guided walkthrough of every section. It auto-starts on your first visit.</div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════ -->
<!-- CONTACTS                                                -->
<!-- ══════════════════════════════════════════════════════ -->
<div class="kb-section" id="contacts">
    <div class="kb-section-header">
        <div class="kb-section-icon" style="background:#0891b2;"><i class="fas fa-users"></i></div>
        <div>
            <h2>Contacts</h2>
            <p>Your contact database — add individually or import in bulk</p>
        </div>
    </div>
    <div class="kb-section-body">

        <div class="kb-grid">
            <div>
                <h5>Add a Single Contact</h5>
                <ol class="kb-steps">
                    <li><strong>Contacts → Add Contact</strong><p>Click the "+ Add Contact" button on the contacts list page.</p></li>
                    <li><strong>Fill in details</strong><p>Name, Email, Phone are the key fields. Phone must be in a format Twilio can dial (e.g. <code>+19998887777</code> or <code>9998887777</code>).</p></li>
                    <li><strong>Save</strong><p>The contact is automatically owned by you.</p></li>
                </ol>
            </div>
            <div>
                <h5>Import from CSV</h5>
                <ol class="kb-steps">
                    <li><strong>Contacts → Import CSV</strong><p>Click the "Import CSV" button on the contacts list.</p></li>
                    <li><strong>Prepare your file</strong><p>CSV must have a header row. Columns: <code>name, email, phone, group</code></p></li>
                    <li><strong>Upload</strong><p>The group column auto-creates the group if it doesn't exist. All imported contacts are owned by you.</p></li>
                </ol>
                <div class="kb-code"># Example CSV
name,email,phone,group
John Doe,john@example.com,9998887777,Newsletter
Jane Smith,jane@example.com,8887776666,VIP</div>
                <div class="kb-warn"><i class="fas fa-exclamation-triangle text-warning"></i> Row 1 is treated as a header and skipped. Start data from row 2.</div>
            </div>
        </div>

        <h5 class="mt-3">Searching &amp; Filtering</h5>
        <p class="small">Use the search bar to find contacts by name, email, or phone. Managers can filter by owner. Results export to CSV/Excel via the DataTable buttons.</p>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════ -->
<!-- GROUPS                                                  -->
<!-- ══════════════════════════════════════════════════════ -->
<div class="kb-section" id="groups">
    <div class="kb-section-header">
        <div class="kb-section-icon" style="background:#0284c7;"><i class="fas fa-layer-group"></i></div>
        <div>
            <h2>Groups</h2>
            <p>Segment contacts into lists for targeted campaigns</p>
        </div>
    </div>
    <div class="kb-section-body">
        <ol class="kb-steps">
            <li><strong>Create a Group</strong><p>Groups → Add Group. Give it a name (e.g. "Golf Club Members", "Newsletter Subscribers").</p></li>
            <li><strong>Add Contacts to Group</strong><p>On the group list page, click the <kbd>Contacts</kbd> button to view members. Contacts are added via the CSV import's "group" column, or manually from the group contacts page.</p></li>
            <li><strong>Use in Campaigns</strong><p>When creating a campaign, select one or more target groups. The campaign queue is built from all contacts in those groups.</p></li>
        </ol>
        <div class="kb-tip"><i class="fas fa-info-circle text-primary"></i> A contact can belong to multiple groups. If the same contact appears in two selected groups, they receive the message only once (DISTINCT query).</div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════ -->
<!-- TEMPLATES                                               -->
<!-- ══════════════════════════════════════════════════════ -->
<div class="kb-section" id="templates">
    <div class="kb-section-header">
        <div class="kb-section-icon" style="background:#dc2626;"><i class="fas fa-file-alt"></i></div>
        <div>
            <h2>Templates</h2>
            <p>Reusable Email and SMS message templates with merge tags</p>
        </div>
    </div>
    <div class="kb-section-body">

        <div class="kb-grid">
            <div>
                <h5>Creating a Template</h5>
                <ol class="kb-steps">
                    <li><strong>Templates → Add Template</strong><p>Choose type: <strong>Email</strong> (rich HTML editor) or <strong>SMS</strong> (plain text, max 160 chars per segment).</p></li>
                    <li><strong>Set visibility</strong><p>Managers/Admins can choose: <span class="kb-badge kb-badge-gray">Private</span> (only you), <span class="kb-badge kb-badge-blue">Team</span> (your team), <span class="kb-badge kb-badge-green">Global</span> (everyone). Regular users are always Private.</p></li>
                    <li><strong>Write content with merge tags</strong><p>Use <code>{{ NAME }}</code>, <code>{{ EMAIL }}</code>, <code>{{ PHONE }}</code> — replaced at send time with each contact's data.</p></li>
                    <li><strong>SMS Image (MMS)</strong><p>Upload an image for SMS templates. Must be JPG/PNG/GIF, under 600 KB. Adding an image converts SMS to MMS.</p></li>
                </ol>
            </div>
            <div>
                <h5>Merge Tags Reference</h5>
                <table class="kb-table">
                    <tr><th>Tag</th><th>Replaced with</th></tr>
                    <tr><td><code>{{ NAME }}</code></td><td>Contact's full name</td></tr>
                    <tr><td><code>{{ EMAIL }}</code></td><td>Contact's email</td></tr>
                    <tr><td><code>{{ PHONE }}</code></td><td>Contact's phone number</td></tr>
                    <tr><td><code>{{ FIRST_NAME }}</code></td><td>Lead's first name (lead context)</td></tr>
                    <tr><td><code>{{ LAST_NAME }}</code></td><td>Lead's last name (lead context)</td></tr>
                    <tr><td><code>{{ COMPANY }}</code></td><td>Lead's company</td></tr>
                    <tr><td><code>{{ STATUS }}</code></td><td>Lead's current status</td></tr>
                </table>

                <h5 class="mt-3">Template Status</h5>
                <table class="kb-table">
                    <tr><td><span class="kb-badge kb-badge-green">Active</span></td><td>Available for use in campaigns</td></tr>
                    <tr><td><span class="kb-badge kb-badge-gray">Inactive</span></td><td>Hidden from campaign dropdowns</td></tr>
                    <tr><td><span class="kb-badge kb-badge-red">Archived</span></td><td>Soft-deleted, not shown anywhere</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════ -->
<!-- CAMPAIGNS                                               -->
<!-- ══════════════════════════════════════════════════════ -->
<div class="kb-section" id="campaigns">
    <div class="kb-section-header">
        <div class="kb-section-icon" style="background:#d97706;"><i class="fas fa-bullhorn"></i></div>
        <div>
            <h2>Campaigns</h2>
            <p>Schedule and send bulk email or SMS to your contact groups</p>
        </div>
    </div>
    <div class="kb-section-body">

        <h5>Creating a Campaign</h5>
        <ol class="kb-steps">
            <li><strong>Campaigns → Create Campaign</strong><p>Click the blue "+ Create Campaign" button.</p></li>
            <li><strong>Name &amp; Type</strong><p>Give your campaign a name. Select <strong>Email</strong> or <strong>SMS</strong>. The template dropdown filters automatically to the correct type.</p></li>
            <li><strong>Choose Template</strong><p>Select from active templates visible to you. Template content is used verbatim — merge tags are resolved per-contact at send time.</p></li>
            <li><strong>Select Target Groups</strong><p>Pick one or more contact groups. The campaign will send to all contacts in those groups.</p></li>
            <li><strong>Set Start Date</strong><p>Choose the date the campaign should first run.</p></li>
            <li><strong>Pick Send Time</strong><p>Click a time slot button (4:00 AM – 10:00 PM in 15-min intervals). If a time slot is already taken by one of your other campaigns on that date, you'll get an "already booked" warning.</p></li>
            <li><strong>Repeat Days</strong><p>Tick MON–FRI to repeat on those days. Leave all ticked for a Mon–Fri recurring campaign.</p></li>
            <li><strong>Send Per Day</strong><p>Throttle: 1,000 / 2,000 / 3,000 / 5,000 / 10,000 per day.</p></li>
            <li><strong>Create Campaign</strong><p>Campaign is saved with status <span class="kb-badge kb-badge-yellow">Pending</span>. The cron job picks it up at the scheduled time.</p></li>
        </ol>

        <div class="kb-grid mt-3">
            <div>
                <h5>Campaign Statuses</h5>
                <table class="kb-table">
                    <tr><td><span class="kb-badge kb-badge-gray">Pending</span></td><td>Waiting for scheduled time</td></tr>
                    <tr><td><span class="kb-badge kb-badge-yellow">In Progress</span></td><td>Currently sending</td></tr>
                    <tr><td><span class="kb-badge kb-badge-green">Completed</span></td><td>All messages sent</td></tr>
                </table>
            </div>
            <div>
                <h5>Filtering Campaigns</h5>
                <p class="small text-muted">Filter by <strong>Type</strong>, <strong>Status</strong>, <strong>Group</strong>, or <strong>Schedule Date Range</strong>. Use the DataTable export buttons for CSV/Excel.</p>
                <div class="kb-tip"><i class="fas fa-info-circle text-primary"></i> Managers see an "Owner" column showing which team member created each campaign.</div>
            </div>
        </div>

        <div class="kb-warn mt-2"><i class="fas fa-exclamation-triangle text-warning"></i> <strong>Important:</strong> Campaigns require the cron job to be running. See the <a href="#cron">Cron &amp; Automation</a> section. Campaigns will not send if the cron is not set up.</div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════ -->
<!-- LEADS                                                   -->
<!-- ══════════════════════════════════════════════════════ -->
<div class="kb-section" id="leads">
    <div class="kb-section-header">
        <div class="kb-section-icon" style="background:#be123c;"><i class="fas fa-bullseye"></i></div>
        <div>
            <h2>Leads</h2>
            <p>Track sales prospects through a 6-stage pipeline</p>
        </div>
    </div>
    <div class="kb-section-body">

        <div class="kb-grid">
            <div>
                <h5>Lead Statuses</h5>
                <table class="kb-table">
                    <tr><td><span class="kb-badge kb-badge-gray">New</span></td><td>Just added, not yet contacted</td></tr>
                    <tr><td><span class="kb-badge kb-badge-blue">Contacted</span></td><td>Initial outreach made</td></tr>
                    <tr><td><span class="kb-badge kb-badge-blue">Qualified</span></td><td>Confirmed interest/fit</td></tr>
                    <tr><td><span class="kb-badge kb-badge-yellow">Proposal</span></td><td>Proposal/quote sent</td></tr>
                    <tr><td><span class="kb-badge kb-badge-green">Won</span></td><td>Deal closed successfully</td></tr>
                    <tr><td><span class="kb-badge kb-badge-red">Lost</span></td><td>Deal lost or contact declined</td></tr>
                </table>
            </div>
            <div>
                <h5>Adding a Lead</h5>
                <ol class="kb-steps">
                    <li><strong>Leads → Add Lead</strong><p>Fill in first name, email, phone, company, source, and initial status.</p></li>
                    <li><strong>Link a Contact (optional)</strong><p>Link to an existing contact from the contacts database. This lets you reuse contact details and see the full communication history.</p></li>
                    <li><strong>Save</strong><p>Lead appears on the list with a status badge. Click <kbd>View</kbd> for the full detail page.</p></li>
                </ol>
            </div>
        </div>

        <h5 class="mt-3">Convert Lead → Opportunity</h5>
        <p class="small">When a lead is qualified and ready for deal-tracking, click the green <kbd><i class="fas fa-exchange-alt"></i> Convert</kbd> button on the list or detail page. This:</p>
        <ul class="small text-muted">
            <li>Creates a new Opportunity linked to this lead</li>
            <li>Sets the lead's status to <span class="kb-badge kb-badge-blue">Qualified</span></li>
            <li>Redirects you to the Pipeline Kanban view</li>
        </ul>
        <div class="kb-tip"><i class="fas fa-info-circle text-primary"></i> Status summary cards at the top of the leads list show counts per stage. Click a card to filter the list by that status.</div>

        <h5 class="mt-3">Sources</h5>
        <p class="small text-muted">Website · Referral · Cold Call · Email · Social Media · Event · Other</p>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════ -->
<!-- LEAD DETAIL & SMS CHAT                                  -->
<!-- ══════════════════════════════════════════════════════ -->
<div class="kb-section" id="lead-view">
    <div class="kb-section-header">
        <div class="kb-section-icon" style="background:#0891b2;"><i class="fas fa-comments"></i></div>
        <div>
            <h2>Lead Detail &amp; SMS Chat</h2>
            <p>The full lead view — pipeline bar, tabs, live chat, and interactions timeline</p>
        </div>
    </div>
    <div class="kb-section-body">

        <h5>Pipeline Progress Bar</h5>
        <p class="small">The coloured button bar at the top shows the 6 stages. Click any stage button to instantly update the lead's status — no save required.</p>

        <h5 class="mt-3">Tabs</h5>
        <table class="kb-table">
            <tr>
                <th>Tab</th><th>What you can do</th>
            </tr>
            <tr>
                <td><i class="fas fa-sticky-note text-warning mr-1"></i><strong>Notes</strong></td>
                <td>Write internal notes about the lead. Notes appear in the Interactions timeline below.</td>
            </tr>
            <tr>
                <td><i class="fas fa-envelope text-primary mr-1"></i><strong>Email</strong></td>
                <td>Send a one-off email directly to the lead. Load an email template to auto-fill subject/body with merge tags resolved. Sent emails appear in the timeline.</td>
            </tr>
            <tr>
                <td><i class="fas fa-sms text-success mr-1"></i><strong>SMS</strong></td>
                <td>Live two-way SMS chat window. Select a from-number, optionally load an SMS template, type and send. Inbound replies appear automatically (polled every 5 s). See below.</td>
            </tr>
            <tr>
                <td><i class="fas fa-tasks text-warning mr-1"></i><strong>Tasks</strong></td>
                <td>View all tasks linked to this lead. Quick "Mark Done" and Edit buttons. "+ Add Task" creates a new task pre-linked to this lead.</td>
            </tr>
        </table>

        <h5 class="mt-3">SMS Live Chat</h5>
        <ol class="kb-steps">
            <li><strong>Select From Number</strong><p>Choose which Twilio number to send from. Defaults to the number in Settings if none selected.</p></li>
            <li><strong>Load a Template (optional)</strong><p>Pick from active SMS templates. Merge tags ({{ NAME }}, {{ PHONE }}, etc.) are resolved against this lead's data.</p></li>
            <li><strong>Type &amp; Send</strong><p>Press Enter or click the send button. Message appears as a blue outbound bubble immediately.</p></li>
            <li><strong>Inbound replies arrive automatically</strong><p>The chat polls every 5 seconds. New inbound messages appear as white bubbles on the left. A toast notification pops up in the corner.</p></li>
        </ol>
        <div class="kb-warn"><i class="fas fa-exclamation-triangle text-warning"></i> For inbound SMS to appear, the Twilio webhook must be configured. See <a href="#webhooks">Webhooks</a>.</div>

        <h5 class="mt-3">Call Lead Widget</h5>
        <p class="small">The yellow "Call Lead" card on the left column lets you initiate an outbound call via Twilio's REST API. Select a from-number, confirm the phone, click Start Call. The call is logged to the Interactions timeline automatically. After hanging up, you can save call notes.</p>

        <h5 class="mt-3">Interactions Timeline</h5>
        <p class="small">Every action — notes, emails, SMS messages (inbound and outbound), and calls — is logged in chronological order at the bottom. Filter by type using the buttons. Email bodies can be expanded in a full modal. Each entry shows who performed the action and when.</p>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════ -->
<!-- OPPORTUNITIES / PIPELINE                                -->
<!-- ══════════════════════════════════════════════════════ -->
<div class="kb-section" id="opportunities">
    <div class="kb-section-header">
        <div class="kb-section-icon" style="background:#7c3aed;"><i class="fas fa-stream"></i></div>
        <div>
            <h2>Pipeline (Opportunities)</h2>
            <p>Visual Kanban board for tracking deals in progress</p>
        </div>
    </div>
    <div class="kb-section-body">

        <div class="kb-grid">
            <div>
                <h5>Pipeline Stages</h5>
                <table class="kb-table">
                    <tr><td><span class="kb-badge kb-badge-gray">New</span></td><td>10% probability</td></tr>
                    <tr><td><span class="kb-badge kb-badge-blue">Qualified</span></td><td>25% probability</td></tr>
                    <tr><td><span class="kb-badge kb-badge-yellow">Proposal</span></td><td>50% probability</td></tr>
                    <tr><td><span class="kb-badge kb-badge-purple">Negotiation</span></td><td>75% probability</td></tr>
                    <tr><td><span class="kb-badge kb-badge-green">Won</span></td><td>100% — updates linked lead to Won</td></tr>
                    <tr><td><span class="kb-badge kb-badge-red">Lost</span></td><td>0% — updates linked lead to Lost</td></tr>
                </table>
            </div>
            <div>
                <h5>Drag &amp; Drop</h5>
                <p class="small">Drag any deal card from one column to another to update its stage. The probability percentage is updated automatically. If the deal is linked to a lead and you move it to Won or Lost, the lead's status updates too.</p>

                <h5 class="mt-3">Pipeline Value</h5>
                <p class="small">The total dollar value of all open opportunities (excluding Lost) is shown in the header. On the Dashboard, a bar chart breaks this down by stage.</p>

                <div class="kb-tip"><i class="fas fa-info-circle text-primary"></i> Managers can filter the pipeline by team member using the Owner dropdown.</div>
            </div>
        </div>

        <h5 class="mt-3">Adding an Opportunity Manually</h5>
        <p class="small">Click "+ New Opportunity". Fill in the title, stage, amount, expected close date, and optionally link to a lead. Opportunities can also be created automatically via the "Convert" button on a Lead.</p>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════ -->
<!-- TASKS                                                   -->
<!-- ══════════════════════════════════════════════════════ -->
<div class="kb-section" id="tasks">
    <div class="kb-section-header">
        <div class="kb-section-icon" style="background:#b45309;"><i class="fas fa-tasks"></i></div>
        <div>
            <h2>Tasks &amp; Follow-ups</h2>
            <p>Keep on top of action items — linked to leads and opportunities</p>
        </div>
    </div>
    <div class="kb-section-body">

        <div class="kb-grid">
            <div>
                <h5>Creating a Task</h5>
                <ol class="kb-steps">
                    <li><strong>Tasks → New Task</strong><p>Or click "+ Add Task" from within a Lead or Opportunity.</p></li>
                    <li><strong>Set title, due date/time &amp; priority</strong><p>Priorities: <span class="kb-badge kb-badge-red">High</span> <span class="kb-badge kb-badge-yellow">Medium</span> <span class="kb-badge kb-badge-green">Low</span></p></li>
                    <li><strong>Link to a Lead or Opportunity (optional)</strong><p>Linked tasks show under the Tasks tab on the Lead detail page.</p></li>
                    <li><strong>Save</strong><p>Task appears in the list sorted by due date, pending first.</p></li>
                </ol>
            </div>
            <div>
                <h5>Task Actions</h5>
                <table class="kb-table">
                    <tr><th>Action</th><th>How</th></tr>
                    <tr><td>Mark Done</td><td>Click the green <kbd><i class="fas fa-check"></i></kbd> button. Task stays visible as Completed.</td></tr>
                    <tr><td>Edit</td><td>Click the blue <kbd><i class="fas fa-edit"></i></kbd> button to change any field, including status.</td></tr>
                    <tr><td>Delete</td><td>Click the red <kbd><i class="fas fa-trash"></i></kbd> button.</td></tr>
                </table>

                <div class="kb-danger mt-3"><i class="fas fa-exclamation-circle text-danger"></i> <strong>Overdue tasks</strong> appear highlighted in red with an "Overdue" badge. An alert banner shows at the top of the tasks list when you have overdue items. They also appear on the Dashboard.</div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════ -->
<!-- WORKFLOW AUTOMATION                                     -->
<!-- ══════════════════════════════════════════════════════ -->
<div class="kb-section" id="workflow">
    <div class="kb-section-header">
        <div class="kb-section-icon" style="background:#ea580c;"><i class="fas fa-bolt"></i></div>
        <div>
            <h2>Workflow Automation</h2>
            <p>Create rules that trigger automatic actions — no coding required</p>
        </div>
    </div>
    <div class="kb-section-body">

        <h5>How Rules Work</h5>
        <p class="small">Each rule has three parts: <strong>Trigger</strong> (what event fires it), <strong>Conditions</strong> (optional filters), and <strong>Actions</strong> (what happens).</p>

        <div class="kb-grid mt-3">
            <div>
                <h5>Triggers</h5>
                <table class="kb-table">
                    <tr><td><code>lead_created</code></td><td>Fires when a new lead is created (within the last 15 min on each engine run)</td></tr>
                    <tr><td><code>lead_status_changed</code></td><td>Fires when a lead's status changes (updated_at within 15 min)</td></tr>
                    <tr><td><code>task_overdue</code></td><td>Fires for every pending task past its due_date</td></tr>
                    <tr><td><code>opportunity_stage_changed</code></td><td>Fires when an opportunity's stage changes (updated_at within 15 min)</td></tr>
                </table>
            </div>
            <div>
                <h5>Actions</h5>
                <table class="kb-table">
                    <tr>
                        <td><span class="kb-badge kb-badge-blue">Create Task</span></td>
                        <td>Auto-creates a follow-up task. Set title, priority, and "due in N days".</td>
                    </tr>
                    <tr>
                        <td><span class="kb-badge kb-badge-green">Send Email</span></td>
                        <td>Sends an email via SendGrid. "To" field supports <code>{{LEAD_EMAIL}}</code>. Subject/body support all merge tags.</td>
                    </tr>
                    <tr>
                        <td><span class="kb-badge kb-badge-yellow">Update Field</span></td>
                        <td>Updates a field on the triggering record. E.g. set <code>status</code> = <code>contacted</code> when a task fires.</td>
                    </tr>
                </table>
            </div>
        </div>

        <h5 class="mt-3">Conditions</h5>
        <p class="small">Optional row-level filters. Each condition is field / operator / value. Operators: <code>=</code> <code>!=</code> <code>&gt;</code> <code>&lt;</code> <code>contains</code>. All conditions must match (AND logic). Example: only fire on leads where <code>source = Referral</code>.</p>

        <h5 class="mt-3">Example Rules</h5>
        <table class="kb-table">
            <tr><th>Trigger</th><th>Condition</th><th>Action</th><th>Result</th></tr>
            <tr>
                <td>lead_created</td>
                <td><em>none</em></td>
                <td>Create Task: "Follow up call" (due in 1 day, High)</td>
                <td>Every new lead gets an automatic next-day follow-up task</td>
            </tr>
            <tr>
                <td>lead_status_changed</td>
                <td>status = won</td>
                <td>Send Email to {{LEAD_EMAIL}}: "Thanks for choosing us!"</td>
                <td>Auto-sends a win congratulation email</td>
            </tr>
            <tr>
                <td>task_overdue</td>
                <td><em>none</em></td>
                <td>Update Field: status = contacted</td>
                <td>Marks overdue task's lead as contacted</td>
            </tr>
            <tr>
                <td>opportunity_stage_changed</td>
                <td>stage = proposal</td>
                <td>Create Task: "Send follow-up" (due in 2 days)</td>
                <td>Auto-follow-up when a deal reaches Proposal stage</td>
            </tr>
        </table>

        <h5 class="mt-3">Running the Engine</h5>
        <p class="small">The engine processes all active rules. It can be triggered two ways:</p>
        <ul class="small text-muted">
            <li><strong>Cron job (recommended):</strong> Runs automatically every 5 minutes — see <a href="#cron">Cron &amp; Automation</a></li>
            <li><strong>Manual:</strong> Click "▶ Run Engine Now" on the Workflow list page to run immediately (processes your/your team's rules only)</li>
        </ul>
        <div class="kb-tip"><i class="fas fa-info-circle text-primary"></i> The engine uses a deduplication log — the same rule won't fire twice for the same record+state combination.</div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════ -->
<!-- USERS                                                   -->
<!-- ══════════════════════════════════════════════════════ -->
<div class="kb-section" id="users">
    <div class="kb-section-header">
        <div class="kb-section-icon" style="background:#1d4ed8;"><i class="fas fa-user-cog"></i></div>
        <div>
            <h2>Users</h2>
            <p>Manage team members and their roles — Managers and Super Admin only</p>
        </div>
    </div>
    <div class="kb-section-body">

        <div class="kb-grid">
            <div>
                <h5>Adding a User</h5>
                <ol class="kb-steps">
                    <li><strong>Users → Add User</strong><p>Available to Managers and Super Admins.</p></li>
                    <li><strong>Enter details</strong><p>Name, Email (used for login), Phone, Password.</p></li>
                    <li><strong>Set Role</strong><p><span class="role-manager">Manager</span> — can see team data, manage sub-users<br><span class="role-user">User</span> — sees only their own data</p></li>
                    <li><strong>Assign Manager</strong><p>Super Admins can assign any manager. A Manager creating a user automatically assigns themselves.</p></li>
                    <li><strong>Can View Team (optional)</strong><p>Tick to let a regular User see their whole team's records (read-only view access).</p></li>
                </ol>
            </div>
            <div>
                <h5>Editing &amp; Deleting</h5>
                <p class="small">Managers can edit and delete users on their own team. They cannot delete themselves or users belonging to other managers. Super Admins can manage any user.</p>

                <div class="kb-warn mt-3"><i class="fas fa-exclamation-triangle text-warning"></i> Deleting a user does <strong>not</strong> delete their records (contacts, leads, campaigns). Those records remain visible to the user's manager.</div>

                <h5 class="mt-3">Passwords</h5>
                <p class="small">Passwords are encrypted using AES-256-CBC before storage. There is no plain-text password recovery — reset by editing the user and setting a new password.</p>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════ -->
<!-- TWILIO NUMBERS                                          -->
<!-- ══════════════════════════════════════════════════════ -->
<div class="kb-section" id="twilio-numbers">
    <div class="kb-section-header">
        <div class="kb-section-icon" style="background:#0f766e;"><i class="fas fa-phone"></i></div>
        <div>
            <h2>Twilio Numbers</h2>
            <p>Manage the phone numbers used for sending SMS and making calls</p>
        </div>
    </div>
    <div class="kb-section-body">
        <p class="small">These are the sender phone numbers that appear as "From" when sending SMS or initiating calls. They must be Twilio-provisioned numbers on your Twilio account.</p>

        <ol class="kb-steps">
            <li><strong>Purchase a number on Twilio</strong><p>Log into <a href="https://www.twilio.com" target="_blank">twilio.com</a> → Phone Numbers → Buy a Number. Choose a number with SMS capability.</p></li>
            <li><strong>Add it here</strong><p>Twilio Numbers → Add. Enter the number in E.164 format: <code>+18317049625</code></p></li>
            <li><strong>Set status to Active</strong><p>Only Active numbers appear in the SMS send dropdowns on Lead pages and Campaigns.</p></li>
            <li><strong>Configure the webhook</strong><p>On Twilio, set the incoming message webhook for this number to your <code>sms_inbound.php</code> URL. See <a href="#webhooks">Webhooks</a>.</p></li>
        </ol>

        <div class="kb-tip"><i class="fas fa-info-circle text-primary"></i> Only Managers and Super Admins can add/edit/delete Twilio numbers. All users can see the list and use the numbers.</div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════ -->
<!-- SETTINGS                                                -->
<!-- ══════════════════════════════════════════════════════ -->
<div class="kb-section" id="settings">
    <div class="kb-section-header">
        <div class="kb-section-icon" style="background:#374151;"><i class="fas fa-cog"></i></div>
        <div>
            <h2>Settings</h2>
            <p>System-wide API credentials — Super Admin only</p>
        </div>
    </div>
    <div class="kb-section-body">
        <table class="kb-table">
            <tr><th>Field</th><th>Where to find it</th><th>Used for</th></tr>
            <tr>
                <td>From Name</td>
                <td>Your choice (e.g. "Golf Club Team")</td>
                <td>Display name on outbound emails</td>
            </tr>
            <tr>
                <td>From Email</td>
                <td>Must match a verified SendGrid sender</td>
                <td>From address on all system emails</td>
            </tr>
            <tr>
                <td>SendGrid API Key</td>
                <td>SendGrid → Settings → API Keys</td>
                <td>Sending all email campaigns and lead emails</td>
            </tr>
            <tr>
                <td>Twilio Account SID</td>
                <td>Twilio Console → Account Info</td>
                <td>Authenticating all Twilio API calls</td>
            </tr>
            <tr>
                <td>Twilio Auth Token</td>
                <td>Twilio Console → Account Info (reveal)</td>
                <td>Authenticating all Twilio API calls</td>
            </tr>
            <tr>
                <td>Twilio From Number</td>
                <td>A number you own in E.164 format</td>
                <td>Default sender for SMS/calls when no specific number chosen</td>
            </tr>
        </table>
        <div class="kb-danger mt-3"><i class="fas fa-exclamation-circle text-danger"></i> Never share your Twilio Auth Token or SendGrid API Key. Treat them like passwords. If compromised, rotate them immediately in the respective dashboards, then update here.</div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════ -->
<!-- REPORTS                                                 -->
<!-- ══════════════════════════════════════════════════════ -->
<div class="kb-section" id="reports">
    <div class="kb-section-header">
        <div class="kb-section-icon" style="background:#0369a1;"><i class="fas fa-chart-line"></i></div>
        <div>
            <h2>Team Reports</h2>
            <p>Performance breakdown per team member — Managers &amp; Admin only</p>
        </div>
    </div>
    <div class="kb-section-body">
        <p class="small">The Team Reports page provides a date-range-filterable breakdown of each team member's activity. Use the date picker to set a reporting period.</p>
        <table class="kb-table">
            <tr><th>Section</th><th>What it shows</th></tr>
            <tr><td>Team Members</td><td>List of all team members, their role, and Can View Team flag</td></tr>
            <tr><td>Leads by Member</td><td>Count per lead status (New, Contacted, Qualified, Proposal, Won, Lost) for each user in the period</td></tr>
            <tr><td>Opportunity Pipeline</td><td>Total pipeline value, won value, and win rate percentage per member</td></tr>
            <tr><td>Task Completion</td><td>Pending vs completed tasks with a visual completion rate progress bar per member</td></tr>
        </table>
        <div class="kb-tip"><i class="fas fa-info-circle text-primary"></i> Export the table to CSV or Excel using the buttons that appear when the report loads.</div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════ -->
<!-- CRON & AUTOMATION                                       -->
<!-- ══════════════════════════════════════════════════════ -->
<div class="kb-section" id="cron">
    <div class="kb-section-header">
        <div class="kb-section-icon" style="background:#1e40af;"><i class="fas fa-clock"></i></div>
        <div>
            <h2>Cron &amp; Automation</h2>
            <p>Server-side scheduled jobs that power campaign sending and workflow automation</p>
        </div>
    </div>
    <div class="kb-section-body">

        <h5>Required Cron Jobs</h5>
        <p class="small">Add these two lines to your server's crontab (<code>crontab -e</code>). Replace the paths with your actual server paths.</p>

        <div class="kb-code"><span class="c"># Send email/SMS campaigns (runs every 5 minutes)</span>
*/5 * * * * wget --no-check-certificate --quiet -O - <?php echo getWebhookUrl(); ?>/golfwl/cron/send_campaigns.php

<span class="c"># Workflow automation engine (runs every 5 minutes)</span>
*/5 * * * * wget --no-check-certificate --quiet -O - <?php echo getWebhookUrl(); ?>/golfwl/workflow/engine.php</div>

        <h5 class="mt-3">What send_campaigns.php does</h5>
        <ol class="kb-steps">
            <li><strong>Finds active campaigns</strong><p>Looks for campaigns where <code>schedule_datetime &lt;= NOW()</code>, status = pending/in-progress, and today is a repeat day.</p></li>
            <li><strong>Generates the queue</strong><p>If not already queued, inserts all contacts from the campaign's groups into <code>campaign_queue</code>.</p></li>
            <li><strong>Sends messages</strong><p>Processes up to <code>send_per_day</code> records. Emails go via SendGrid API. SMS goes via Twilio REST API. Respects daily send limit.</p></li>
            <li><strong>Updates status</strong><p>Each queue row is marked sent/failed. Campaign status updates to in-progress/completed.</p></li>
        </ol>

        <div class="kb-warn"><i class="fas fa-exclamation-triangle text-warning"></i> SendGrid and Twilio SDKs are installed via Composer (<code>vendor/autoload.php</code>) and loaded with a path relative to <code>cron/send_campaigns.php</code>, so no per-server path changes are needed. Run <code>composer install</code> after deploying to a new server.</div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════ -->
<!-- WEBHOOKS                                                -->
<!-- ══════════════════════════════════════════════════════ -->
<div class="kb-section" id="webhooks">
    <div class="kb-section-header">
        <div class="kb-section-icon" style="background:#065f46;"><i class="fas fa-link"></i></div>
        <div>
            <h2>Webhooks &amp; External Integrations</h2>
            <p>How Twilio and SendGrid post data back to the system</p>
        </div>
    </div>
    <div class="kb-section-body">

        <h5>Twilio Inbound SMS (required for Live Chat)</h5>
        <p class="small">When a lead replies to an SMS, Twilio POSTs to your webhook URL. The system matches the reply's from-number to a lead and stores it for the live chat widget.</p>
        <div class="kb-code"><span class="c"># Twilio Console → Phone Numbers → Your Number → Messaging</span>
Webhook URL: <?php echo getWebhookUrl(); ?>/golfwl/tracking/sms_inbound.php
Method: HTTP POST</div>

        <h5 class="mt-3">Twilio Delivery Status</h5>
        <div class="kb-code"><span class="c"># Twilio Console → Phone Numbers → Your Number → Messaging → Status Callback</span>
Webhook URL: <?php echo getWebhookUrl(); ?>/golfwl/tracking/twilio_webhook.php
Method: HTTP POST</div>
        <p class="small text-muted mt-1">Updates <code>campaign_queue</code> with delivery status (queued/sent/delivered/failed) per message SID.</p>

        <h5 class="mt-3">SendGrid Event Webhook</h5>
        <div class="kb-code"><span class="c"># SendGrid → Settings → Mail Settings → Event Webhook</span>
URL: <?php echo getWebhookUrl(); ?>/golfwl/tracking/sendgrid_webhook.php
Events: delivered, opened, clicked, bounced, unsubscribed, spam_report</div>
        <p class="small text-muted mt-1">Updates <code>campaign_queue</code> with email engagement stats (opens, clicks, bounces, etc.).</p>

        <h5 class="mt-3">Email Open Tracking</h5>
        <div class="kb-code"><span class="c"># Automatically embedded in email templates</span>
Tracking pixel: <?php echo getWebhookUrl(); ?>/golfwl/tracking/open.php?id={queue_id}</div>

        <div class="kb-tip"><i class="fas fa-info-circle text-primary"></i> Webhooks must be publicly accessible URLs. They will not work on localhost. Use a service like ngrok for local testing.</div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════ -->
<!-- FAQ                                                     -->
<!-- ══════════════════════════════════════════════════════ -->
<div class="kb-section" id="faq">
    <div class="kb-section-header">
        <div class="kb-section-icon" style="background:#6d28d9;"><i class="fas fa-question-circle"></i></div>
        <div>
            <h2>Frequently Asked Questions</h2>
            <p>Quick answers to common questions</p>
        </div>
    </div>
    <div class="kb-section-body">

        <?php
        $faqs = [
            ["Why is my campaign not sending?",
             "Check three things: (1) Is the cron job running? SSH in and check crontab -e. (2) Is the campaign status 'Pending' and the schedule_datetime in the past? (3) Are your SendGrid/Twilio API keys correct in Settings?"],
            ["Inbound SMS replies are not appearing in the chat window.",
             "The Twilio webhook must be configured on your Twilio phone number. Go to Twilio Console → Phone Numbers → your number → Messaging → set the webhook to " . getWebhookUrl() . "/golfwl/tracking/sms_inbound.php (HTTP POST). Also confirm the lead's phone number matches the 'From' phone of the incoming message."],
            ["Why can I not see my team member's leads/contacts?",
             "Make sure you are logged in as a Manager or have 'Can View Team' enabled on your user account. Also verify the team member's user account has manager_id set to your user ID."],
            ["I imported contacts but they didn't appear in my list.",
             "Contacts are owned by the user who imports them. If you are a Manager viewing a list, check that the Owner filter is not set to a specific person. Also verify the CSV has the correct header row (name,email,phone,group) and that row 1 is the header, not data."],
            ["How do I reset a user's password?",
             "Go to Users → Edit the user → enter a new password and save. Passwords are encrypted — there is no plain-text recovery or 'forgot password' flow in this system."],
            ["Can the same contact be in multiple groups?",
             "Yes. A contact can be in any number of groups. If they belong to two groups selected in a campaign, the DISTINCT query ensures they only receive one message."],
            ["What is the SMS character limit?",
             "Standard SMS is 160 characters per segment. The chat window shows a character counter. Messages over 160 characters are automatically split into multiple segments by Twilio, which may increase costs."],
            ["How does the notification bell work?",
             "The bell polls the server every 30 seconds (speeding up to every 8 seconds when there are unread messages). It shows unread inbound SMS replies scoped to your leads (or your team's for Managers/Admins). Clicking the bell opens a dropdown with the 5 most recent unread messages. Clicking anywhere in the panel marks all as read."],
            ["The workflow engine fired a rule twice.",
             "The engine uses a workflow_logs deduplication table. If you see duplicate actions, check the workflow_logs table — the record_type column stores a dedupe key. If the key is not unique (e.g. for task_overdue which fires every run while the task is still open), this is expected behaviour — add a condition on the rule to restrict when it fires."],
            ["How do I add a menu link to the Knowledge Base?",
             "In layout/sidebar.php, add a new nav-item with href pointing to ../knowledge/index.php. Use the book icon: fas fa-book-open."],
        ];
        foreach($faqs as $faq){ ?>
        <div class="kb-faq-q" onclick="this.nextElementSibling.style.display=this.nextElementSibling.style.display==='block'?'none':'block';this.querySelector('.faq-chevron').style.transform=this.nextElementSibling.style.display==='block'?'rotate(90deg)':'rotate(0deg)';">
            <span><?php echo htmlspecialchars($faq[0]); ?></span>
            <i class="fas fa-chevron-right faq-chevron" style="transition:transform .2s;font-size:.75rem;"></i>
        </div>
        <div class="kb-faq-a"><?php echo htmlspecialchars($faq[1]); ?></div>
        <?php } ?>
    </div>
</div>

</div><!-- /kb-content -->
</div><!-- /kb-wrap -->

</div>
</section>

<?php include("../layout/footer.php"); ?>

<script>
// ── Sidebar active highlighting on scroll ────────────────────────────────────
$(function(){
    var $navLinks = $('#kb-nav a');

    function updateActive(){
        var scrollY = $(window).scrollTop() + 80;
        var current = '';
        $('.kb-section').each(function(){
            if($(this).offset().top <= scrollY){
                current = '#' + $(this).attr('id');
            }
        });
        $navLinks.removeClass('active');
        if(current) $navLinks.filter('[href="'+current+'"]').addClass('active');
    }

    $(window).on('scroll', updateActive);
    updateActive();

    // Smooth scroll
    $navLinks.on('click', function(e){
        var target = $(this).attr('href');
        if($(target).length){
            e.preventDefault();
            $('html,body').animate({ scrollTop: $(target).offset().top - 20 }, 350);
        }
    });

    // ── Live search ──────────────────────────────────────────────────────────
    $('#kb-search').on('input', function(){
        var q = $(this).val().trim().toLowerCase();

        if(!q){
            $('.kb-section').show();
            $('#kb-no-result').hide();
            return;
        }

        var found = 0;
        $('.kb-section').each(function(){
            var text = $(this).text().toLowerCase();
            if(text.indexOf(q) !== -1){
                $(this).show();
                found++;
            } else {
                $(this).hide();
            }
        });

        $('#kb-no-result').toggle(found === 0);
    });
});
</script>
