<?php

    include "../config/db.php";
    include "../config/auth.php";
    requireLogin();

    // Settings page is admin-only (shared SendGrid/Twilio credentials)
    if(!isSuperAdmin()){
        header("Location:../admin/dashboard.php");
        exit;
    }

    include "../layout/header.php";
    include "../layout/sidebar.php";
    $msg = "";

    if(isset($_POST['from_name'])){
        verifyCsrf();

        $from_name = mysqli_real_escape_string($conn, $_POST['from_name']);
        $from_email = mysqli_real_escape_string($conn, $_POST['from_email']);
        $sendgrid_api_key = mysqli_real_escape_string($conn, $_POST['sendgrid_api_key']);
        $twilio_from = mysqli_real_escape_string($conn, $_POST['twilio_from']);
        $twilio_sid = mysqli_real_escape_string($conn, $_POST['twilio_sid']);
        $twilio_token = mysqli_real_escape_string($conn, $_POST['twilio_token']);

        // validation
        if($from_name == '' || $from_email == '' || $sendgrid_api_key == '' || $twilio_from == '' || $twilio_sid == '' || $twilio_token == ''){
            $msg = "All fields are required!";
             $msgError = '1';
        } elseif(!filter_var($from_email, FILTER_VALIDATE_EMAIL)){
            $msg = "Invalid email!";
            $msgError = '1';
        } else {

            // check if row exists
            $check = mysqli_query($conn, "SELECT id FROM settings LIMIT 1");

            if(mysqli_num_rows($check) > 0){
                // UPDATE
                mysqli_query($conn, "                    
                    UPDATE settings SET 
                        from_name = '$from_name',
                        from_email = '$from_email',
                        sendgrid_api_key = '$sendgrid_api_key',
                        twilio_from = '$twilio_from',
                        twilio_sid = '$twilio_sid',
                        twilio_token = '$twilio_token'
                ");
                auditLog($conn, "update_settings", "settings", 0, "Settings updated");
            } else {
                // INSERT
                mysqli_query($conn, "
                    INSERT INTO settings (from_name, from_email, sendgrid_api_key, twilio_from, twilio_sid, twilio_token)
                    VALUES ('$from_name', '$from_email', '$sendgrid_api_key', '$twilio_from', '$twilio_sid', '$twilio_token')
                ");
            }

            $msg = "Settings saved successfully!";
            $msgError = '0';
        }
    }

    // FETCH DATA AGAIN (important after save)
    $q = mysqli_query($conn, "SELECT * FROM settings LIMIT 1");
    $settings = mysqli_fetch_assoc($q);

    $masked_key = '';

    if(!empty($settings['sendgrid_api_key'])){
        $key = $settings['sendgrid_api_key'];
        $len = strlen($key);

        // show last 6 chars
        $visible = substr($key, -6);

        $masked_key = str_repeat('*', $len - 6) . $visible;
    }
?>

<div class="card">
    <div class="card-header">
        <h3>Settings</h3>
    </div>

    <div class="card-body">
        <?php if ($msg != "" && $msgError == '0') {?>
        <div class="alert alert-success"><?php echo $msg; ?></div>
        <?php }else if($msg != "" && $msgError == '1'){?>
        <div class="alert alert-danger"><?php echo $msg; ?></div>
        <?php }?>

        <!-- setting form with FROM NAME AND FROM EMAIL AND SENDGRID SETTINGS -->
        <form method="post" id="settingForm">
<?php echo csrfField(); ?>



            <div class="form-group">
                <label>From Name</label>
                <input type="text" name="from_name" class="form-control"
                    value="<?php echo isset($settings['from_name']) ? $settings['from_name'] : ''; ?>">
            </div>

            <div class="form-group">
                <label>From Email</label>
                <input type="email" name="from_email" class="form-control"
                    value="<?php echo isset($settings['from_email']) ? $settings['from_email'] : ''; ?>">
            </div>

            <div class="form-group">
                <label>SendGrid API Key</label>
                <input type="text" name="sendgrid_api_key" class="form-control"
                    value="<?php echo isset($settings['sendgrid_api_key']) ? $settings['sendgrid_api_key'] : ''; ?>">
                <?php
                    /* if($masked_key != ''){ ?>
                <small class="text-muted">
                    Saved Key: <?php echo $masked_key; ?>
                </small>
                <?php }*/ ?>
            </div>
            <!-- twilio_from -->
            <div class="form-group">
                <label>Twilio From</label>
                <input type="text" name="twilio_from" class="form-control"
                    value="<?php echo isset($settings['twilio_from']) ? $settings['twilio_from'] : ''; ?>">
            </div>

            <div class="form-group">
                <label>Twilio SID</label>
                <input type="text" name="twilio_sid" class="form-control"
                    value="<?php echo isset($settings['twilio_sid']) ? $settings['twilio_sid'] : ''; ?>">
            </div>
            <div class="form-group">
                <label>Twilio Token</label>
                <input type="text" name="twilio_token" class="form-control"
                    value="<?php echo isset($settings['twilio_token']) ? $settings['twilio_token'] : ''; ?>">
            </div>
            <div class="form-group">
                <label>Twilio Webhook URL</label><br>

                <span class="text-muted">
                    https://aitrans.co/golfwl/tracking/twilio_webhook.php
                </span>

                <div class="hint mt-3 p-3 border rounded bg-light">
                    <strong>For Incoming SMS</strong>

                    <ol class="mt-2 mb-0 pl-3">
                        <li>
                            Login to Twilio Console:
                            <br>
                            <a href="https://console.twilio.com/" target="_blank">
                                Twilio Console
                            </a>
                        </li>

                        <li class="mt-2">
                            Go to:
                            <br>
                            <strong>Phone Numbers → Manage → Active Numbers</strong>
                        </li>

                        <li class="mt-2">
                            Click your Twilio phone number.
                        </li>

                        <li class="mt-2">
                            Under <strong>Messaging</strong>:
                            <ul class="mb-0 mt-2">
                                <li><strong>A MESSAGE COMES IN</strong></li>
                                <li>Select <strong>Webhook</strong></li>
                                <li>HTTP Method: <strong>POST</strong></li>
                                <li>URL:</li>
                            </ul>

                            <div class="alert alert-dark mt-2 mb-2">
                                https://aitrans.co/golfwl/tracking/twilio_webhook.php
                            </div>
                        </li>

                        <li>
                            Save the configuration.
                        </li>
                    </ol>
                </div>
            </div>
            <div class="form-group">
                <label>SendGrid Webhook URL</label><br>

                <span class="text-muted">
                    https://aitrans.co/golfwl/tracking/sendgrid_webhook.php
                </span>

                <div class="hint mt-3 p-3 border rounded bg-light">
                    <strong>Setup Steps</strong>

                    <ol class="mt-2 mb-0 pl-3">
                        <li>
                            Login to SendGrid:
                            <br>
                            <a href="https://app.sendgrid.com/" target="_blank">
                                SendGrid Login
                            </a>
                        </li>

                        <li class="mt-2">
                            Navigate to:
                            <br>
                            <strong>Settings → Mail Settings</strong>
                        </li>

                        <li class="mt-2">
                            Open:
                            <br>
                            <strong>Event Webhook</strong>
                        </li>

                        <li class="mt-2">
                            Enable the Event Webhook.
                        </li>

                        <li class="mt-2">
                            In HTTP POST URL enter:
                            <div class="alert alert-dark mt-2 mb-2">
                                https://aitrans.co/golfwl/tracking/sendgrid_webhook.php
                            </div>
                        </li>

                        <li>
                            Select the events you want:
                            <ul class="mb-0">
                                <li>Processed</li>
                                <li>Delivered</li>
                                <li>Opened</li>
                                <li>Clicked</li>
                                <li>Bounced</li>
                                <li>Dropped</li>
                                <li>Spam Reports</li>
                                <li>Unsubscribes</li>
                            </ul>
                        </li>

                        <li class="mt-2">
                            Save the configuration. SendGrid will send event data as HTTP POST requests to your URL.
                        </li>
                    </ol>
                </div>
            </div>



            <button type="submit" class="btn btn-primary">Save Settings</button>

        </form>
    </div>
</div>