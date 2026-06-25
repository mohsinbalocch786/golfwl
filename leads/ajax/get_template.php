<?php
/**
 * AJAX: fetch a single email_template and resolve {{placeholders}}
 * against the current lead.
 *
 * GET params:
 *   template_id  INT   – templates.id
 *   lead_id      INT   – leads.id
 */
include("../../config/db.php");
include("../../config/auth.php");
requireLogin();

header('Content-Type: application/json');

$template_id = (int)(isset($_GET['template_id']) ? $_GET['template_id'] : 0);
$lead_id     = (int)(isset($_GET['lead_id']) ? $_GET['lead_id'] : 0);

if (!$template_id || !$lead_id) {
    echo json_encode(['ok' => false, 'error' => 'Missing params']);
    exit;
}

// Fetch template
$tr = mysqli_query($conn, "SELECT * FROM templates WHERE id='$template_id' AND status='active' LIMIT 1");
$tpl = mysqli_fetch_assoc($tr);
if (!$tpl) {
    echo json_encode(['ok' => false, 'error' => 'Template not found']);
    exit;
}

// Fetch lead + linked contact
$lr = mysqli_query($conn, "
    SELECT l.*,
           c.name  AS contact_name,
           c.email AS contact_email,
           c.phone AS contact_phone
    FROM leads l
    LEFT JOIN contacts c ON c.id = l.contact_id
    WHERE l.id = '$lead_id' LIMIT 1
");
$lead = mysqli_fetch_assoc($lr);
if (!$lead) {
    echo json_encode(['ok' => false, 'error' => 'Lead not found']);
    exit;
}

// Build token map
$name  = trim((isset($lead['first_name']) ? $lead['first_name'] : '') . ' ' . (isset($lead['last_name']) ? $lead['last_name'] : ''));
$tokens = [
    'name'          => $name ? $name : (isset($lead['contact_name']) ? $lead['contact_name'] : ''),
    'NAME'          => $name ? $name : (isset($lead['contact_name']) ? $lead['contact_name'] : ''),
    'first_name'    => isset($lead['first_name']) ? $lead['first_name'] : '',
    'FIRST_NAME'    => isset($lead['first_name']) ? $lead['first_name'] : '',
    'last_name'     => isset($lead['last_name']) ? $lead['last_name'] : '',
    'LAST_NAME'     => isset($lead['last_name']) ? $lead['last_name'] : '',
    'email'         => isset($lead['email']) ? $lead['email'] : (isset($lead['contact_email']) ? $lead['contact_email'] : ''),
    'EMAIL'         => isset($lead['email']) ? $lead['email'] : (isset($lead['contact_email']) ? $lead['contact_email'] : ''),
    'phone'         => isset($lead['phone']) ? $lead['phone'] : (isset($lead['contact_phone']) ? $lead['contact_phone'] : ''),
    'PHONE'         => isset($lead['phone']) ? $lead['phone'] : (isset($lead['contact_phone']) ? $lead['contact_phone'] : ''),
    'company'       => isset($lead['company']) ? $lead['company'] : '',
    'COMPANY'       => isset($lead['company']) ? $lead['company'] : '',
    'source'        => isset($lead['source']) ? $lead['source'] : '',
    'SOURCE'        => isset($lead['source']) ? $lead['source'] : '',
    'status'        => isset($lead['status']) ? $lead['status'] : '',
    'STATUS'        => isset($lead['status']) ? $lead['status'] : '',
];

function resolvePlaceholders($text, $tokens) {
    return preg_replace_callback(
        '/\{\{\s*(\w+)\s*\}\}/',
        function($m) use ($tokens) {
            return isset($tokens[$m[1]]) ? $tokens[$m[1]] : $m[0];
        },
        $text
    );
}

$subject  = resolvePlaceholders(isset($tpl['subject']) ? $tpl['subject'] : '', $tokens);
$body     = resolvePlaceholders(isset($tpl['content']) ? $tpl['content'] : '', $tokens);
$from_name  = isset($tpl['from_name']) ? $tpl['from_name'] : '';
$from_email = isset($tpl['from_email']) ? $tpl['from_email'] : '';

echo json_encode([
    'ok'         => true,
    'subject'    => $subject,
    'body'       => $body,
    'from_name'  => $from_name,
    'from_email' => $from_email,
    'type'       => $tpl['type'],
    'note'       => isset($tpl['note']) ? $tpl['note'] : '',
]);