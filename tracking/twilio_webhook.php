<?php

include("../config/db.php");

date_default_timezone_set('America/Los_Angeles');

$currentDateTime = date('Y-m-d H:i:s');

$messageSid = mysqli_real_escape_string(
    $conn,
    $_POST['MessageSid']
);

$from = mysqli_real_escape_string(
    $conn,
    $_POST['From']
);

$to = mysqli_real_escape_string(
    $conn,
    $_POST['To']
);

$body = trim($_POST['Body']);

$bodySafe = mysqli_real_escape_string(
    $conn,
    $body
);

$keyword = strtoupper(trim($body));
$now        = date('Y-m-d H:i:s');
$subj       = "SMS from $from";
/*
|--------------------------------------------------------------------------
| Normalize Phone
|--------------------------------------------------------------------------
*/

$phone = preg_replace('/[^0-9]/', '', $from);

$lead_id = 0;
$contact_id = 0;

/*
|--------------------------------------------------------------------------
| Find Lead
|--------------------------------------------------------------------------
*/

$qLead = mysqli_query($conn,"
SELECT id
FROM leads
WHERE REPLACE(REPLACE(REPLACE(REPLACE(phone,'-',''),'(',''),')',''),' ','')
LIKE '%$phone%'
LIMIT 1
");

if(mysqli_num_rows($qLead))
{
    $rowLead = mysqli_fetch_assoc($qLead);

    $lead_id = $rowLead['id'];
}

/*
|--------------------------------------------------------------------------
| Find Contact
|--------------------------------------------------------------------------
*/

$qContact = mysqli_query($conn,"
SELECT id
FROM contacts
WHERE REPLACE(REPLACE(REPLACE(REPLACE(phone,'-',''),'(',''),')',''),' ','')
LIKE '%$phone%'
LIMIT 1
");

if(mysqli_num_rows($qContact))
{
    $rowContact = mysqli_fetch_assoc($qContact);

    $contact_id = $rowContact['id'];
}

/*
|--------------------------------------------------------------------------
| Save Reply
|--------------------------------------------------------------------------
*/

mysqli_query($conn,"
INSERT INTO sms_replies
(
    lead_id,
    contact_id,
    message_sid,
    from_number,
    to_number,
    message,
    created_at
)
VALUES
(
    '$lead_id',
    '$contact_id',
    '$messageSid',
    '$from',
    '$to',
    '$bodySafe',
    '$currentDateTime'
)
");

/*
|--------------------------------------------------------------------------
| YES = Interested
|--------------------------------------------------------------------------
*/

if($keyword == 'YES')
{
    if($lead_id > 0)
    {
        mysqli_query($conn,"
        UPDATE leads
        SET callback_requested=1,
            callback_requested_at='$currentDateTime'
        WHERE id='$lead_id'
        ");
    } else if($contact_id > 0) {
        $r = mysqli_query($conn, "SELECT * FROM contacts WHERE id='$contact_id'");
        $contact = mysqli_fetch_assoc($r);
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
        $lead_id = mysqli_insert_id($conn);
        // mark contact as converted
        mysqli_query($conn,"UPDATE contacts SET converted=1, updated_at='$currentTime' WHERE id='$contact_id'");
    }
}

/*
|--------------------------------------------------------------------------
| STOP = Opt Out
|--------------------------------------------------------------------------
*/

if(
    $keyword == 'STOP' ||
    $keyword == 'STOPALL' ||
    $keyword == 'UNSUBSCRIBE' ||
    $keyword == 'CANCEL'
)
{
    if($lead_id > 0)
    {
        mysqli_query($conn,"
        UPDATE leads
        SET sms_opt_out=1,
            sms_opt_out_at='$currentDateTime'
        WHERE id='$lead_id'
        ");
    }

    if($contact_id > 0)
    {
        mysqli_query($conn,"
        UPDATE contacts
        SET sms_opt_out=1,
            sms_opt_out_at='$currentDateTime'
        WHERE id='$contact_id'
        ");
    }
}

/*
|--------------------------------------------------------------------------
| Auto Task For Sales Rep
|--------------------------------------------------------------------------
*/

if($lead_id > 0)
{
    mysqli_query($conn,"
    INSERT INTO lead_sms
    (
        lead_id,
        direction,
        phone,
        message,
        twilio_sid,
        status,
        created_at
    )
    VALUES
    (
        '$lead_id',
        'inbound',
        '".mysqli_real_escape_string($conn,$from)."',
        '".mysqli_real_escape_string($conn,$bodySafe)."',
        '".mysqli_real_escape_string($conn,$messageSid)."',
        'received',
        '$currentTime'
    )
    ");
    mysqli_query($conn,
        "INSERT INTO lead_interactions
         (lead_id, user_id, type, direction, subject, body, status, ext_id, created_at)
         VALUES
         ($lead_id, NULL, 'sms', 'inbound', '$subj', '$bodySafe', 'received', '$messageSid', '$now')"
    );
}

/*
|--------------------------------------------------------------------------
| Twilio Response
|--------------------------------------------------------------------------
*/

header("Content-Type: text/xml");

echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<Response></Response>';

exit;