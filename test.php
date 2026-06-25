<?php
/**
 * Dry-run check for the SendGrid / Twilio setup used by cron/send_campaigns.php.
 * Verifies PHP extensions, Composer autoload, SDK classes, and that
 * credentials are configured in the settings table — without sending
 * any real email or SMS.
 *
 * Usage: php test.php   (CLI)   or visit test.php in a browser.
 */

$results = [];

function check($label, $pass, $detail = '') {
    global $results;
    $results[] = ['label' => $label, 'pass' => $pass, 'detail' => $detail];
}

// 1. PHP extensions required by the SDKs
foreach (['curl', 'json', 'mbstring', 'openssl'] as $ext) {
    check("ext-$ext loaded", extension_loaded($ext));
}

// 2. Composer autoload
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
    check('vendor/autoload.php found', true);
} else {
    check('vendor/autoload.php found', false, 'Run "composer install" in ' . __DIR__);
}

// 3. SDK classes available
check('Class Twilio\\Rest\\Client exists', class_exists('Twilio\\Rest\\Client'));
check('Class SendGrid\\Mail\\Mail exists', class_exists('SendGrid\\Mail\\Mail'));
check('Class SendGrid exists', class_exists('SendGrid'));

// 4. DB connection + settings table
$conn = null;
try {
    require_once __DIR__ . '/config/db.php';
    check('Database connection', (bool)$conn, $conn ? '' : mysqli_connect_error());
} catch (\Throwable $e) {
    check('Database connection', false, $e->getMessage());
}

$settings = null;
if ($conn) {
    $q = mysqli_query($conn, "SELECT * FROM settings LIMIT 1");
    $settings = $q ? mysqli_fetch_assoc($q) : null;
    check('settings row found', (bool)$settings);
}

// 5. Required settings fields are present and non-empty
$fields = ['from_name', 'from_email', 'sendgrid_api_key', 'twilio_from', 'twilio_sid', 'twilio_token'];
foreach ($fields as $field) {
    $value = $settings[$field] ?? null;
    check("settings.$field is set", !empty($value));
}

// 6. Instantiate the SDK clients (no network calls / no sends)
if (class_exists('SendGrid') && !empty($settings['sendgrid_api_key'])) {
    try {
        new \SendGrid($settings['sendgrid_api_key']);
        check('SendGrid client instantiates', true);
    } catch (\Throwable $e) {
        check('SendGrid client instantiates', false, $e->getMessage());
    }
}

if (class_exists('Twilio\\Rest\\Client') && !empty($settings['twilio_sid']) && !empty($settings['twilio_token'])) {
    try {
        new \Twilio\Rest\Client($settings['twilio_sid'], $settings['twilio_token']);
        check('Twilio client instantiates', true);
    } catch (\Throwable $e) {
        check('Twilio client instantiates', false, $e->getMessage());
    }
}

// ── Output ──────────────────────────────────────────────────────────
$isCli = (PHP_SAPI === 'cli');
$failures = 0;

if (!$isCli) {
    header('Content-Type: text/plain');
}

foreach ($results as $r) {
    $mark = $r['pass'] ? 'PASS' : 'FAIL';
    if (!$r['pass']) $failures++;
    echo "[$mark] {$r['label']}";
    if ($r['detail']) echo " — {$r['detail']}";
    echo "\n";
}

echo "\n" . ($failures === 0 ? "All checks passed." : "$failures check(s) failed.") . "\n";

if (isset($conn) && $conn) {
    mysqli_close($conn);
}
