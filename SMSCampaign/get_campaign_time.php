<?php

include("../config/db.php");


$timeArray = [];

$date      = mysqli_real_escape_string($conn, $_GET['date']);
$id        = (int)$_GET['id'];
$c_type    = mysqli_real_escape_string($conn, $_GET['c_type']);
$type      = mysqli_real_escape_string($conn, $_GET['type']);
$limitTime = isset($_GET['limitTime']) ? $_GET['limitTime'] : '';

// Selected Campaign
$selected_campaign = array();

$sql = "SELECT scheduled
        FROM email_campaign
        WHERE id = $id";

$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    $selected_campaign = mysqli_fetch_assoc($result);
}

// Existing Campaigns
$email_campaigns = array();

$sql = "SELECT scheduled
        FROM email_campaign
        WHERE type = '$c_type'
        AND DATE(scheduled) = '$date'
        AND status IN ('Pending', 'InProgress')
        AND id != $id";

$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $email_campaigns[] = $row;
    }
}

$timeArray = array();

if (!empty($email_campaigns)) {

    foreach ($email_campaigns as $item) {

        if (!empty($selected_campaign)) {
            continue;
        }

        $timeArray[] = date('H:i', strtotime($item['scheduled']));
    }
}

$nearest15Min = date('H');

$timestring = date("Y-m-d H:i:s");

$minutes = date('i', strtotime($timestring));
$minutes = $minutes - ($minutes % 15);

$nearest15Min .= ':' . str_pad($minutes, 2, '0', STR_PAD_LEFT);

$start = strtotime('04:00');
$end   = strtotime('16:00');

if ($limitTime == "Y") {

    $start = strtotime('05:00');
    $end   = strtotime('07:00');

    for ($i = $start; $i <= $end; $i += (15 * 60)) {

        $currentTime  = date('H:i', $i);
        $selectedClass = 'btn-outline-secondary';
        $displayTime  = date('h:i A', $i);

        if (!in_array($currentTime, $timeArray)) {

            if (!empty($selected_campaign)) {

                if (date("H:i", strtotime($selected_campaign['scheduled'])) == $currentTime) {
                    $selectedClass = 'btn-secondary';
                }

            } else {

                if ($nearest15Min == $currentTime) {
                    $selectedClass = 'btn-secondary';
                }
            }

            echo "<button type='button' class='btn m-1 btn-sm $selectedClass' onclick='selectTime(this, \"$currentTime\")'>{$displayTime}</button> ";
        }
    }

    $start = strtotime('09:00');
    $end   = strtotime('11:00');

    for ($i = $start; $i <= $end; $i += (15 * 60)) {

        $currentTime  = date('H:i', $i);
        $selectedClass = 'btn-outline-secondary';
        $displayTime  = date('h:i A', $i);

        if (!in_array($currentTime, $timeArray)) {

            if (!empty($selected_campaign)) {

                if (date("H:i", strtotime($selected_campaign['scheduled'])) == $currentTime) {
                    $selectedClass = 'btn-secondary';
                }

            } else {

                if ($nearest15Min == $currentTime) {
                    $selectedClass = 'btn-secondary';
                }
            }

            echo "<button type='button' class='btn m-1 btn-sm $selectedClass' onclick='selectTime(this, \"$currentTime\")'>{$displayTime}</button> ";
        }
    }

} else {

    for ($i = $start; $i <= $end; $i += (60 * 60)) {

        $currentTime   = date('H:i', $i);
        $selectedClass = 'btn-outline-secondary';
        $displayTime   = date('h:i A', $i);

        if (!in_array($currentTime, $timeArray)) {

            if (!empty($selected_campaign)) {

                if (date("H:i", strtotime($selected_campaign['scheduled'])) == $currentTime) {
                    $selectedClass = 'btn-secondary';
                }

            } else {

                if ($nearest15Min == $currentTime) {
                    $selectedClass = 'btn-secondary';
                }
            }

            echo "<button type='button' class='btn m-1 btn-sm $selectedClass' onclick='selectTime(this, \"$currentTime\")'>{$displayTime}</button> ";
        }
    }
}
?>