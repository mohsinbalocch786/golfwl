<?php
/**
 * auth.php — Role + ownership helpers. PHP 5.6 compatible.
 *
 * Session shape after login:
 *   $_SESSION['admin']      = true            (super-admins from `admins` table)
 *   $_SESSION['user_id']    = users.id
 *   $_SESSION['role']       = 'manager' | 'user'
 *   $_SESSION['manager_id'] = manager scope id
 */
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function isSuperAdmin() {
    return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
}

function isManager() {
    return isSuperAdmin() || ((isset($_SESSION['role']) ? $_SESSION['role'] : '') === 'manager');
}

function isLoggedIn() {
    return isSuperAdmin() || isset($_SESSION['user_id']);
}

function requireLogin($loginPath = "../admin/login.php") {
    secureSession();
    if (!isLoggedIn()) {
        header("Location: $loginPath");
        exit;
    }
}

function currentUserId() {
    return (int)(isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0);
}

function currentManagerId() {
    return (int)(isset($_SESSION['manager_id']) ? $_SESSION['manager_id'] : 0);
}

function canViewTeam() {
    return isManager() || !empty($_SESSION['can_view_team']);
}

function ownershipWhere($alias = '') {
    $col = $alias ? "$alias." : '';
    if (isSuperAdmin()) return "1=1";
    $uid = currentUserId();
    if (isManager()) return "({$col}user_id = $uid OR {$col}manager_id = $uid)";
    return "{$col}user_id = $uid";
}

function ownershipStamp() {
    if (isSuperAdmin()) return array(0, 0);
    $uid = currentUserId();
    $mid = currentManagerId();
    return array($uid, $mid);
}

function assertOwnership($row) {
    if (isSuperAdmin()) return;
    $uid = currentUserId();
    if ((int)(isset($row['user_id']) ? $row['user_id'] : -1) === $uid) return;
    if (isManager() && (int)(isset($row['manager_id']) ? $row['manager_id'] : -1) === $uid) return;
    http_response_code(403);
    die("Access denied: you do not have permission to access this record.");
}

function teamMembers($conn) {
    $members = array();
    if (isSuperAdmin()) {
        $r = mysqli_query($conn, "SELECT id, name, email, role FROM users ORDER BY name");
        while ($row = mysqli_fetch_assoc($r)) $members[] = $row;
        return $members;
    }
    $uid = currentUserId();
    if (isManager()) {
        $r = mysqli_query($conn, "SELECT id, name, email, role FROM users WHERE id=$uid OR manager_id=$uid ORDER BY name");
        while ($row = mysqli_fetch_assoc($r)) $members[] = $row;
        return $members;
    }
    $r = mysqli_query($conn, "SELECT id, name, email, role FROM users WHERE id=$uid");
    while ($row = mysqli_fetch_assoc($r)) $members[] = $row;
    return $members;
}

// ── CSRF helpers ──────────────────────────────────────────────

function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrfToken()) . '">';
}

function verifyCsrf() {
    $token    = isset($_POST['_csrf'])        ? $_POST['_csrf']        : '';
    $expected = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
    if (!$expected || !hash_equals($expected, $token)) {
        http_response_code(403);
        die('Invalid CSRF token. Please go back and try again.');
    }
    // Rotate token after use
    $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
}

// ── Session security ──────────────────────────────────────────

function secureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $maxLifetime = 8 * 3600;  // 8 hours absolute
    $idleTimeout = 2 * 3600;  // 2 hours idle

    if (isset($_SESSION['_last_activity']) && (time() - (int)$_SESSION['_last_activity']) > $maxLifetime) {
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
        }
        session_destroy();
        session_start();
        return;
    }
    $_SESSION['_last_activity'] = time();

    if (isset($_SESSION['_last_action']) && (time() - (int)$_SESSION['_last_action']) > $idleTimeout) {
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
        }
        session_destroy();
        session_start();
        return;
    }
    $_SESSION['_last_action'] = time();
}

// ── Secure file upload ────────────────────────────────────────

function secureImageUpload($file, $uploadDir, &$error) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload error code: ' . $file['error'];
        return '';
    }
    if ($file['size'] > 614400) {
        $error = 'File too large. Maximum size is 600 KB.';
        return '';
    }
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    $allowed  = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif');
    if (!array_key_exists($mimeType, $allowed)) {
        $error = 'Invalid file type. Only JPG, PNG and GIF are allowed.';
        return '';
    }
    if (!getimagesize($file['tmp_name'])) {
        $error = 'Uploaded file is not a valid image.';
        return '';
    }
    $ext      = $allowed[$mimeType];
    $newName  = uniqid('img_', true) . '.' . $ext;
    $destPath = rtrim($uploadDir, '/') . '/' . $newName;
    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        $error = 'Failed to save uploaded file.';
        return '';
    }
    return $newName;
}

// ── Audit logging ─────────────────────────────────────────────

function auditLog($conn, $action, $module, $recordId = 0, $detail = '') {
    $userId   = currentUserId();
    $userType = isSuperAdmin() ? 'admin' : 'user';
    $ip       = mysqli_real_escape_string($conn, isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
    $action   = mysqli_real_escape_string($conn, $action);
    $module   = mysqli_real_escape_string($conn, $module);
    $detail   = mysqli_real_escape_string($conn, mb_substr($detail, 0, 500));
    $now      = date('Y-m-d H:i:s');
    mysqli_query($conn, "
        INSERT INTO audit_log (user_id, user_type, action, module, record_id, detail, ip_address, created_at)
        VALUES ('$userId', '$userType', '$action', '$module', '$recordId', '$detail', '$ip', '$now')
    ");
}
function getWebhookUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    return $protocol . $host;
}