<?php
/**
 * Workflow Engine
 *
 * Processes active workflow_rules and fires their actions when the
 * trigger conditions are met. Can run via cron (CLI) or via
 * run_engine.php (logged-in user, scoped to their own + team rules).
 *
 * Usage (CLI / cron - processes ALL users' rules):
 *   php /path/to/golfwl/workflow/engine.php
 *
 * Usage (web, scoped to a single user's visible rules):
 *   require this file and call runWorkflowEngine($conn, $scopeWhere);
 */

require_once __DIR__ . '/../config/db.php';

// ── Cron heartbeat ────────────────────────────────────────────────────────
$_cronNow = date('Y-m-d H:i:s');
mysqli_query($conn, "
    INSERT INTO cron_heartbeat (job_name, last_run, status, message)
    VALUES ('workflow_engine', '$_cronNow', 'running', '')
    ON DUPLICATE KEY UPDATE last_run='$_cronNow', status='running', message=''
");

if(!function_exists('mysqli_real_escape_string')){
    die("mysqli extension required.\n");
}

/**
 * Run the workflow engine.
 *
 * @param mysqli $conn
 * @param $scopeWhere  SQL WHERE fragment to scope which rules run
 *                             (e.g. "1=1" for cron / all users, or an
 *                             ownershipWhere() fragment for a web run).
 * @return array  Log lines describing what fired.
 */
function runWorkflowEngine($conn, $scopeWhere = "1=1") {

    $log = [];

    $rules = [];
    $r = mysqli_query($conn, "SELECT * FROM workflow_rules WHERE is_active=1 AND ($scopeWhere)");
    while($row = mysqli_fetch_assoc($r)) $rules[] = $row;

    foreach($rules as $rule){

        $conditions = json_decode(isset($rule['conditions']) ? $rule['conditions'] : '[]', true) ?: [];
        $actions    = json_decode(isset($rule['actions']) ? $rule['actions'] : '[]', true) ?: [];
        $ownerUid   = (int)(isset($rule['user_id']) ? $rule['user_id'] : 0);

        switch($rule['trigger_event']){

            case 'task_overdue':
                $tasks = [];
                $tq = mysqli_query($conn, "
                    SELECT * FROM tasks
                    WHERE user_id='$ownerUid'
                    AND status='pending'
                    AND due_date IS NOT NULL
                    AND due_date < '$currentTime'
                ");
                while($t = mysqli_fetch_assoc($tq)) $tasks[] = $t;

                foreach($tasks as $task){
                    if(conditionsMatch($conditions, $task)){
                        executeActions($conn, $actions, $rule, $task, 'task');
                        $log[] = "Rule [{$rule['name']}]: overdue task #{$task['id']} processed.";
                    }
                }
                break;

            case 'lead_created':
                $leads = [];
                $lq = mysqli_query($conn, "
                    SELECT * FROM leads
                    WHERE user_id='$ownerUid'
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                ");
                while($l = mysqli_fetch_assoc($lq)) $leads[] = $l;

                foreach($leads as $lead){
                    if(conditionsMatch($conditions, $lead) && !workflowAlreadyRan($conn, $rule['id'], 'lead', $lead['id'])){
                        executeActions($conn, $actions, $rule, $lead, 'lead');
                        logWorkflow($conn, $rule['id'], 'lead', $lead['id'], 'trigger', 'success', 'lead_created');
                        $log[] = "Rule [{$rule['name']}]: new lead #{$lead['id']} processed.";
                    }
                }
                break;

            case 'lead_status_changed':
                $leads = [];
                $lq = mysqli_query($conn, "
                    SELECT * FROM leads
                    WHERE user_id='$ownerUid'
                    AND updated_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                ");
                while($l = mysqli_fetch_assoc($lq)) $leads[] = $l;

                foreach($leads as $lead){
                    if(conditionsMatch($conditions, $lead) && !workflowAlreadyRan($conn, $rule['id'], 'lead_status', $lead['id'].'_'.$lead['status'])){
                        executeActions($conn, $actions, $rule, $lead, 'lead');
                        logWorkflow($conn, $rule['id'], 'lead_status', null, 'trigger', 'success', "lead #{$lead['id']} -> {$lead['status']}");
                        $log[] = "Rule [{$rule['name']}]: lead #{$lead['id']} status change ({$lead['status']}) processed.";
                    }
                }
                break;

            case 'opportunity_stage_changed':
                $opps = [];
                $oq = mysqli_query($conn, "
                    SELECT * FROM opportunities
                    WHERE user_id='$ownerUid'
                    AND updated_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                ");
                while($o = mysqli_fetch_assoc($oq)) $opps[] = $o;

                foreach($opps as $opp){
                    if(conditionsMatch($conditions, $opp) && !workflowAlreadyRan($conn, $rule['id'], 'opp_stage', $opp['id'].'_'.$opp['stage'])){
                        executeActions($conn, $actions, $rule, $opp, 'opportunity');
                        logWorkflow($conn, $rule['id'], 'opp_stage', null, 'trigger', 'success', "opp #{$opp['id']} -> {$opp['stage']}");
                        $log[] = "Rule [{$rule['name']}]: opportunity #{$opp['id']} stage change ({$opp['stage']}) processed.";
                    }
                }
                break;
        }
    }

    return $log;
}

/**
 * Avoid re-firing the same rule for the same record+key repeatedly.
 * Uses a synthetic record_type key combining the dedupe key.
 */
function workflowAlreadyRan($conn, $ruleId, $dedupeType, $dedupeKey) {
    $key = mysqli_real_escape_string($conn, $dedupeType . ':' . $dedupeKey);
    $r = mysqli_query($conn, "
        SELECT id FROM workflow_logs
        WHERE rule_id='$ruleId' AND record_type='$key'
        LIMIT 1
    ");
    return $r && mysqli_num_rows($r) > 0;
}

function logWorkflow($conn, $ruleId, $recordType, $recordId, $actionType, $result, $message = '') {
    $recordType = mysqli_real_escape_string($conn, $recordType);
    $recordId   = $recordId === null ? 'NULL' : (int)$recordId;
    $actionType = mysqli_real_escape_string($conn, $actionType);
    $result     = mysqli_real_escape_string($conn, $result);
    $message    = mysqli_real_escape_string($conn, $message);

    mysqli_query($conn,"INSERT INTO workflow_logs
        (rule_id,record_type,record_id,action_type,result,message,created_at)
        VALUES
        ('$ruleId','$recordType',$recordId,'$actionType','$result','$message','$currentTime')");
}

function conditionsMatch(array $conditions, array $row) {
    foreach($conditions as $cond){
        $field    = isset($cond['field']) ? $cond['field'] : '';
        $operator = isset($cond['operator']) ? $cond['operator'] : '=';
        $value    = isset($cond['value']) ? $cond['value'] : '';
        $actual   = isset($row[$field]) ? $row[$field] : null;

        switch($operator){
            case '=':        $match = ((string)$actual == (string)$value); break;
            case '!=':       $match = ((string)$actual != (string)$value); break;
            case '>':        $match = is_numeric($actual) && is_numeric($value) ? ($actual > $value) : ((string)$actual > (string)$value); break;
            case '<':        $match = is_numeric($actual) && is_numeric($value) ? ($actual < $value) : ((string)$actual < (string)$value); break;
            case 'contains': $match = (strpos((string)$actual, (string)$value) !== false); break;
            default:         $match = true;
        }

        if(!$match) return false;
    }
    return true;
}

function executeActions($conn, array $actions, array $rule, array $record, $recordType) {
    foreach($actions as $action){
        $type = isset($action['type']) ? $action['type'] : '';

        switch($type){

            case 'create_task':
                $dueDays  = (int)(isset($action['due_days']) ? $action['due_days'] : 1);
                $dueDate  = date('Y-m-d H:i:s', strtotime("+{$dueDays} days"));
                $title    = mysqli_real_escape_string($conn, isset($action['task_title']) ? $action['task_title'] : 'Follow up');
                $priority = in_array((isset($action['task_priority']) ? $action['task_priority'] : ''), ['low','medium','high']) ? (isset($action['task_priority']) ? $action['task_priority'] : 'medium') : 'medium';


                $leadId = $recordType === 'lead' ? (int)(isset($record['id']) ? $record['id'] : 0) : ($recordType === 'opportunity' ? (int)(isset($record['lead_id']) ? $record['lead_id'] : 0) : 0);
                $oppId  = $recordType === 'opportunity' ? (int)(isset($record['id']) ? $record['id'] : 0) : 0;

                $leadIdSql = $leadId ? $leadId : 'NULL';
                $oppIdSql  = $oppId ? $oppId : 'NULL';

                mysqli_query($conn,"INSERT INTO tasks
                    (user_id,manager_id,lead_id,opportunity_id,title,due_date,priority,status,created_at)
                    VALUES
                    ('".(int)$rule['user_id']."','".(int)(isset($rule['manager_id']) ? $rule['manager_id'] : 0)."',$leadIdSql,$oppIdSql,'$title','$dueDate','$priority','pending','$currentTime')");

                logWorkflow($conn, $rule['id'], $recordType, isset($record['id']) ? $record['id'] : null, 'create_task', 'success', $title);
                break;

            case 'send_email':
                $to      = resolveTemplate(isset($action['to']) ? $action['to'] : '', $record);
                $subject = resolveTemplate(isset($action['subject']) ? $action['subject'] : 'CRM Notification', $record);
                $body    = resolveTemplate(isset($action['body']) ? $action['body'] : '', $record);

                if(filter_var($to, FILTER_VALIDATE_EMAIL)){
                    $sent = sendWorkflowEmail($conn, $to, $subject, $body);
                    logWorkflow($conn, $rule['id'], $recordType, isset($record['id']) ? $record['id'] : null, 'send_email', $sent ? 'success' : 'failed', "to=$to subject=$subject");
                } else {
                    logWorkflow($conn, $rule['id'], $recordType, isset($record['id']) ? $record['id'] : null, 'send_email', 'failed', "invalid email: $to");
                }
                break;

            case 'update_field':
                $field = preg_replace('/[^a-zA-Z0-9_]/', '', isset($action['field']) ? $action['field'] : '');
                $value = mysqli_real_escape_string($conn, isset($action['value']) ? $action['value'] : '');

                if($field === '') break;

                if($recordType === 'lead'){
                    mysqli_query($conn,"UPDATE leads SET `$field`='$value', updated_at='$currentTime' WHERE id='".(int)$record['id']."'");
                } elseif($recordType === 'opportunity'){
                    mysqli_query($conn,"UPDATE opportunities SET `$field`='$value', updated_at='$currentTime' WHERE id='".(int)$record['id']."'");
                } elseif($recordType === 'task'){
                    mysqli_query($conn,"UPDATE tasks SET `$field`='$value' WHERE id='".(int)$record['id']."'");
                }

                logWorkflow($conn, $rule['id'], $recordType, isset($record['id']) ? $record['id'] : null, 'update_field', 'success', "$field=$value");
                break;
        }
    }
}

/**
 * Replace {{TOKEN}} placeholders with values from the triggering record.
 */
function resolveTemplate($text, array $record) {

    $replacements = [
        'NAME'         => trim((isset($record['first_name']) ? $record['first_name'] : '') . ' ' . (isset($record['last_name']) ? $record['last_name'] : '')) ?: (isset($record['title']) ? $record['title'] : ''),
        'EMAIL'        => isset($record['email']) ? $record['email'] : '',
        'PHONE'        => isset($record['phone']) ? $record['phone'] : '',
        'COMPANY'      => isset($record['company']) ? $record['company'] : '',
        'LEAD_EMAIL'   => isset($record['email']) ? $record['email'] : '',
        'CONTACT_EMAIL'=> isset($record['email']) ? $record['email'] : '',
        'STATUS'       => isset($record['status']) ? $record['status'] : (isset($record['stage']) ? $record['stage'] : ''),
        'TITLE'        => isset($record['title']) ? $record['title'] : '',
    ];

    return preg_replace_callback('/{{\s*(\w+)\s*}}/', function($matches) use ($replacements) {
        $key = strtoupper($matches[1]);
        return isset($replacements[$key]) ? $replacements[$key] : $matches[0];
    }, $text);
}

/**
 * Send an email via SendGrid using golfwl's existing settings.
 * Returns true on apparent success, false on failure.
 */
function sendWorkflowEmail($conn, $to, $subject, $body) {

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if(!file_exists($autoload)) return false;
    require_once $autoload;

    if(!class_exists('\SendGrid\Mail\Mail')) return false;

    $s = mysqli_query($conn, "SELECT * FROM settings LIMIT 1");
    $settings = $s ? mysqli_fetch_assoc($s) : null;

    if(empty($settings['sendgrid_api_key'])) return false;

    $fromEmail = $settings['from_email'] ?: 'no-reply@example.com';
    $fromName  = $settings['from_name'] ?: 'CRM';

    try {
        $mail = new \SendGrid\Mail\Mail();
        $mail->setFrom($fromEmail, $fromName);
        $mail->setSubject($subject);
        $mail->addTo($to);
        $mail->addContent("text/html", nl2br(htmlspecialchars($body)));

        $sendgrid = new \SendGrid($settings['sendgrid_api_key']);
        $response = $sendgrid->send($mail);

        return $response->statusCode() >= 200 && $response->statusCode() < 300;
    } catch(\Throwable $e){
        return false;
    }
}

// ── CLI entrypoint (cron) ─────────────────────────────────────
if(php_sapi_name() === 'cli'){
    $log = runWorkflowEngine($conn, "1=1");
    foreach($log as $line) echo $line . PHP_EOL;
    echo "Engine run complete. " . count($log) . " trigger(s) processed.\n";
}
