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
<body class="login-page login-page--portal">

<?php require __DIR__ . '/../includes/login_brand.php'; ?>

<main class="portal-main">
    <?php require __DIR__ . '/../includes/login_intro.php'; ?>
    <section class="login-card portal-card" aria-labelledby="login-heading">

        <!-- Tab Toggle -->
        <nav class="portal-tabs" aria-label="Choose your account type">
            <a href="<?= BASE_URL ?>/login.php">
                <i class="bi bi-person-fill me-1"></i>Student
            </a>
            <a href="<?= BASE_URL ?>/admin/login.php" class="active" aria-current="page">
                <i class="bi bi-shield-lock me-1"></i>Admin
            </a>
        </nav>

        <!-- Logo -->
        <div class="portal-card-heading">
            <div class="portal-account-icon" aria-hidden="true"><i class="bi bi-shield-lock"></i></div>
            <span class="portal-card-kicker">ADMINISTRATOR ACCESS</span>
            <h2 id="login-heading">Welcome back.</h2>
            <p>Sign in to manage your campus elections.</p>
        </div>

        <?php if ($error): ?>
        <div class="portal-error" role="alert">
            <i class="bi bi-exclamation-circle me-1"></i><?= h($error) ?>
        </div>
        <?php endif; ?>

        <form method="post">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label-dark" for="emailField">Admin email</label>
                <div style="position:relative;">
                    <i class="bi bi-envelope" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);"></i>
                    <input type="email" name="email" id="emailField" class="form-control-dark" autocomplete="username"
                           style="padding-left:36px;"
                           value="<?= h($_POST['email'] ?? '') ?>"
                           placeholder="admin@cics.batstate-u.edu.ph"
                           required>
                </div>
            </div>

            <div class="mb-2">
                <label class="form-label-dark" for="pwdField">Password</label>
                <div style="position:relative;">
                    <i class="bi bi-lock" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);"></i>
                    <input type="password" name="password" id="pwdField" class="form-control-dark" autocomplete="current-password"
                           style="padding-left:36px;padding-right:40px;"
                           placeholder="Enter your password" required>
                    <button type="button" onclick="togglePwd(this)" aria-label="Show password" aria-pressed="false"
                            style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;padding:0;">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <div class="text-end mb-4">
                <a href="<?= BASE_URL ?>/forgot_password.php?admin=1" class="portal-link">
                    Forgot Password?
                </a>
            </div>

            <button type="submit" class="portal-submit">
                Admin Sign In <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </button>
        </form>

        <div class="portal-card-note">
            <i class="bi bi-lock" aria-hidden="true"></i>
            For authorized administrators, advisers, and committee members.
        </div>

    </section>
</main>

<footer class="portal-footer">
    <span>CICS &middot; BatStateU ARASOF</span>
    <span>Leading Innovations. Transforming Lives. Building the Nation.</span>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePwd(button) {
    const f = document.getElementById('pwdField');
    const i = document.getElementById('eyeIcon');
    if (f.type === 'password') { f.type = 'text';     i.className = 'bi bi-eye-slash'; }
    else                       { f.type = 'password'; i.className = 'bi bi-eye'; }
    button.setAttribute('aria-label', f.type === 'text' ? 'Hide password' : 'Show password');
    button.setAttribute('aria-pressed', f.type === 'text' ? 'true' : 'false');
}
</script>
</body>
</html>
