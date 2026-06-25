<?php
session_start();
include("../config/db.php");
include("../config/auth.php");

// ── Brute-force protection ────────────────────────────────────────────────
// Max 5 failed attempts per IP per 15 minutes stored in session
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['login_lockout_until'] = 0;
}

$now = time();
$isLocked = $now < (int)$_SESSION['login_lockout_until'];

$error = "";

if (isset($_POST['login'])) {

    if ($isLocked) {
        $wait = ceil(((int)$_SESSION['login_lockout_until'] - $now) / 60);
        $error = "Too many failed attempts. Please wait {$wait} minute(s).";
    } else {

        // CSRF check
        if (empty($_POST['_csrf']) || !hash_equals(isset($_SESSION['csrf_login']) ? $_SESSION['csrf_login'] : '', $_POST['_csrf'])) {
            $error = "Invalid request. Please try again.";
        } else {

            $email       = mysqli_real_escape_string($conn, trim($_POST['email']));
            $password_raw = $_POST['password'];

            $logged_in = false;

            // ── 1. Super-admin table (admins) ─────────────────────────
            $q = mysqli_query($conn, "SELECT * FROM admins WHERE email='$email' LIMIT 1");
            if ($q && mysqli_num_rows($q) > 0) {
                $admin = mysqli_fetch_assoc($q);
                $stored = $admin['password'];

                // Support both bcrypt (new) and md5 (legacy, auto-upgrade on login)
                $valid = false;
                if (strlen($stored) === 60 && substr($stored, 0, 4) === '$2y$') {
                    // bcrypt
                    $valid = password_verify($password_raw, $stored);
                } else {
                    // legacy md5 — accept then immediately upgrade
                    $valid = hash_equals($stored, md5($password_raw));
                    if ($valid) {
                        $newHash = password_hash($password_raw, PASSWORD_BCRYPT);
                        $newHash = mysqli_real_escape_string($conn, $newHash);
                        mysqli_query($conn, "UPDATE admins SET password='$newHash' WHERE id='{$admin['id']}'");
                    }
                }

                if ($valid) {
                    session_regenerate_id(true);
                    $_SESSION['admin']      = true;
                    $_SESSION['admin_id']   = $admin['id'];
                    $_SESSION['admin_name'] = $admin['name'];
                    $_SESSION['login_attempts'] = 0;
                    header("Location:dashboard.php");
                    exit;
                }
            }

            // ── 2. Users table (role-based) ───────────────────────────
            if (!$logged_in) {
                $q = mysqli_query($conn, "SELECT * FROM users WHERE email='$email' AND status='active' LIMIT 1");
                if ($q && mysqli_num_rows($q) > 0) {
                    $user   = mysqli_fetch_assoc($q);
                    $stored = $user['password'];
                    $valid  = false;

                    if (strlen($stored) >= 60 && substr($stored, 0, 4) === '$2y$') {
                        // bcrypt
                        $valid = password_verify($password_raw, $stored);
                    } else {
                        // legacy AES — accept then immediately upgrade
                        $decrypted = decryptData($stored, $secret_key, $secret_iv);
                        $valid = ($decrypted !== false && $decrypted === $password_raw);
                        if ($valid) {
                            $newHash = mysqli_real_escape_string($conn, password_hash($password_raw, PASSWORD_BCRYPT));
                            mysqli_query($conn, "UPDATE users SET password='$newHash' WHERE id='{$user['id']}'");
                        }
                    }

                    if ($valid) {
                        session_regenerate_id(true);
                        $_SESSION['user']          = $user;
                        $_SESSION['user_id']       = $user['id'];
                        $_SESSION['role']          = $user['role'];
                        $_SESSION['can_view_team'] = (int)$user['can_view_team'];
                        $_SESSION['manager_id']    = ($user['role'] === 'manager')
                            ? (int)$user['id']
                            : (int)(isset($user['manager_id']) ? $user['manager_id'] : 0);
                        $_SESSION['user_name']     = $user['name'];
                        $_SESSION['login_attempts'] = 0;
                        header("Location:../admin/dashboard.php");
                        exit;
                    }
                }
            }

            // ── Failed attempt tracking ───────────────────────────────
            $_SESSION['login_attempts']++;
            if ((int)$_SESSION['login_attempts'] >= 5) {
                $_SESSION['login_lockout_until'] = $now + 900; // 15 min
                $_SESSION['login_attempts'] = 0;
                $error = "Too many failed attempts. Account locked for 15 minutes.";
            } else {
                $remaining = 5 - (int)$_SESSION['login_attempts'];
                $error = "Invalid Email or Password. {$remaining} attempt(s) remaining.";
            }
        }
    }
}

// ── Generate CSRF token for login form ────────────────────────────────────
if (empty($_SESSION['csrf_login'])) {
    $_SESSION['csrf_login'] = bin2hex(openssl_random_pseudo_bytes(32));
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body class="hold-transition login-page">
<div class="login-box">
<div class="login-logo"><b>Email</b>Blaster</div>
<div class="card">
<div class="card-body login-card-body">
<p class="login-box-msg">Sign in to start session</p>

<?php if ($error !== ""): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<?php if ($isLocked): ?>
<div class="alert alert-warning">
    Account temporarily locked. Try again in <?php echo ceil(((int)$_SESSION['login_lockout_until'] - $now) / 60); ?> minute(s).
</div>
<?php endif; ?>

<form method="post">
<input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($_SESSION['csrf_login']); ?>">

<div class="input-group mb-3">
<input type="email" name="email" class="form-control" placeholder="Email" required
       value="<?php echo htmlspecialchars(isset($_POST['email']) ? $_POST['email'] : ''); ?>">
<div class="input-group-append"><div class="input-group-text"><span class="fas fa-envelope"></span></div></div>
</div>

<div class="input-group mb-3">
<input type="password" name="password" class="form-control" placeholder="Password" required autocomplete="current-password">
<div class="input-group-append"><div class="input-group-text"><span class="fas fa-lock"></span></div></div>
</div>

<div class="row">
<div class="col-8"></div>
<div class="col-4">
<button type="submit" name="login" class="btn btn-primary btn-block" <?php echo $isLocked ? 'disabled' : ''; ?>>Sign In</button>
</div>
</div>
</form>

</div>
</div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>
