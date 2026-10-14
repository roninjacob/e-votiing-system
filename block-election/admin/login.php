<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';

if (current_user() !== null) redirect('/index.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = db()->prepare(
            'SELECT id, student_id, full_name, email, password_hash, role, block_id, is_verified, is_active
             FROM users WHERE email = ? AND role IN (\'admin\',\'adviser\',\'committee\') LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if (!$user['is_active']) {
                $error = 'This account has been deactivated.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id'          => (int)  $user['id'],
                    'student_id'  =>        $user['student_id'],
                    'full_name'   =>        $user['full_name'],
                    'email'       =>        $user['email'],
                    'role'        =>        $user['role'],
                    'block_id'    => (int)  $user['block_id'],
                    'is_verified' => (bool) $user['is_verified'],
                    'is_active'   => (bool) $user['is_active'],
                ];
                flash('success', 'Welcome, ' . $user['full_name'] . '!');
                redirect('/admin/dashboard.php');
            }
        } else {
            // Generic message — don't reveal whether email exists
            $error = 'Invalid credentials.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — CICS E-Voting</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= filemtime(BASE_PATH . '/assets/css/style.css') ?>">
</head>
<body class="login-page">

<!-- Banner -->
<div class="login-banner">
    <div class="logos-group">
        <img src="<?= BASE_URL ?>/assets/img/batstate-logo-main.png?v=<?= filemtime(BASE_PATH . '/assets/img/batstate-logo-main.png') ?>"
             style="height:48px;width:auto;object-fit:contain;"
             alt="BatStateU" onerror="this.style.display='none'">
        <img src="<?= BASE_URL ?>/assets/img/cics-logo.png?v=<?= filemtime(BASE_PATH . '/assets/img/cics-logo.png') ?>"
             style="height:48px;width:auto;object-fit:contain;"
             alt="CICS" onerror="this.style.display='none'">
    </div>
    <div class="banner-title">CICS E-Voting System — Batangas State University Arasof Campus</div>
    <div class="logos-group">
        <img src="<?= BASE_URL ?>/assets/img/sc-logo.png?v=<?= filemtime(BASE_PATH . '/assets/img/sc-logo.png') ?>"
             style="height:48px;width:auto;object-fit:contain;"
             alt="CICS Student Council" onerror="this.style.display='none'">
    </div>
</div>

<div class="flex-grow-1 d-flex align-items-center justify-content-center py-5">
    <div class="login-card" style="max-width:440px;">

        <!-- Tab Toggle -->
        <div style="display:flex;gap:0;margin-bottom:28px;border:1px solid var(--card-border);border-radius:8px;overflow:hidden;">
            <a href="<?= BASE_URL ?>/login.php"
               style="flex:1;padding:10px;text-align:center;font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;background:transparent;color:var(--text-muted);text-decoration:none;">
                <i class="bi bi-person-fill me-1"></i>Student
            </a>
            <a href="<?= BASE_URL ?>/admin/login.php"
               style="flex:1;padding:10px;text-align:center;font-size:.82rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;background:var(--gold);color:#fff;text-decoration:none;">
                <i class="bi bi-shield-lock me-1"></i>Admin
            </a>
        </div>

        <!-- Logo -->
        <div class="text-center mb-4">
            <div style="width:80px;height:80px;border-radius:50%;background:rgba(192,57,43,.2);border:3px solid #c0392b;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                <i class="bi bi-shield-lock-fill" style="font-size:2rem;color:#e74c3c;"></i>
            </div>
            <h3>Admin Login</h3>
            <p style="font-size:.75rem;color:var(--text-muted);">Restricted — Authorized Personnel Only</p>
        </div>

        <?php if ($error): ?>
        <div style="background:rgba(192,57,43,.15);border:1px solid rgba(192,57,43,.4);border-radius:8px;padding:10px 14px;font-size:.83rem;color:#e74c3c;margin-bottom:16px;">
            <i class="bi bi-exclamation-circle me-1"></i><?= h($error) ?>
        </div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label-dark">Admin Email</label>
                <div style="position:relative;">
                    <i class="bi bi-envelope" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);"></i>
                    <input type="email" name="email" class="form-control-dark"
                           style="padding-left:36px;"
                           value="<?= h($_POST['email'] ?? '') ?>"
                           placeholder="admin@cics.batstate-u.edu.ph"
                           required autofocus>
                </div>
            </div>

            <div class="mb-2">
                <label class="form-label-dark">Password</label>
                <div style="position:relative;">
                    <i class="bi bi-lock" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);"></i>
                    <input type="password" name="password" id="pwdField" class="form-control-dark"
                           style="padding-left:36px;padding-right:40px;"
                           placeholder="Enter your password" required>
                    <button type="button" onclick="togglePwd()"
                            style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;padding:0;">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <div class="text-end mb-4">
                <a href="<?= BASE_URL ?>/forgot_password.php?admin=1"
                   style="font-size:.75rem;color:var(--gold);text-decoration:none;">
                    Forgot Password?
                </a>
            </div>

            <button type="submit" class="btn-red w-100 justify-content-center" style="padding:12px;font-size:.9rem;">
                <i class="bi bi-shield-lock me-1"></i>Admin Sign In
            </button>
        </form>

        <div style="margin-top:20px;padding-top:16px;border-top:1px solid rgba(255,255,255,0.08);font-size:.75rem;color:var(--text-muted);text-align:center;">
            <i class="bi bi-info-circle me-1"></i>
            Admin accounts are created by the system administrator only.
        </div>

    </div>
</div>

<footer style="background:rgba(0,0,0,0.5);border-top:1px solid rgba(255,255,255,0.07);padding:10px 24px;text-align:center;position:relative;z-index:1;">
    <span style="font-size:.72rem;color:#9aa0b0;">
        @batstateu &nbsp;|&nbsp; Leading Innovations, Transforming Lives, Building the Nation
    </span>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePwd() {
    const f = document.getElementById('pwdField');
    const i = document.getElementById('eyeIcon');
    if (f.type === 'password') { f.type = 'text';     i.className = 'bi bi-eye-slash'; }
    else                       { f.type = 'password'; i.className = 'bi bi-eye'; }
}
</script>
</body>
</html>
