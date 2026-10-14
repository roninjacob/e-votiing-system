<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

if (current_user() !== null) redirect('/index.php');

$token    = trim($_GET['token'] ?? '');
$error    = '';
$success  = false;
$validToken = null;

if ($token === '') {
    flash('danger', 'Invalid or missing reset token.');
    redirect('/login.php');
}

// Look up the token
$tokenHash = hash('sha256', $token);
$stmt = db()->prepare(
    'SELECT t.*, u.email, u.role FROM password_reset_tokens t
     JOIN users u ON u.id = t.user_id
     WHERE t.token_hash = ? AND t.used = 0 AND t.expires_at > NOW()
     LIMIT 1'
);
$stmt->execute([$tokenHash]);
$validToken = $stmt->fetch();

if (!$validToken) {
    $error = 'This reset link is invalid or has expired. Please request a new one.';
}

if ($validToken && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $password2) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);

        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
           ->execute([$hash, $validToken['user_id']]);

        db()->prepare('UPDATE password_reset_tokens SET used = 1 WHERE id = ?')
           ->execute([$validToken['id']]);

        $success = true;
    }
}

$isAdmin = $validToken && in_array($validToken['role'], ['admin','adviser','committee'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — CICS E-Voting</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= filemtime(BASE_PATH . '/assets/css/style.css') ?>">
</head>
<body class="login-page">

<div class="login-banner">
    <div class="logos-group">
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
    <div class="login-card" style="max-width:420px;">

        <?php if ($success): ?>
        <div class="text-center py-3">
            <div style="width:72px;height:72px;border-radius:50%;background:rgba(46,204,113,.15);border:3px solid #2ecc71;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                <i class="bi bi-check-lg" style="font-size:2rem;color:#2ecc71;"></i>
            </div>
            <h3>Password Reset!</h3>
            <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:24px;">
                Your password has been updated successfully.
            </p>
            <a href="<?= BASE_URL ?>/<?= $isAdmin ? 'admin/login.php' : 'login.php' ?>"
               class="btn-gold" style="padding:11px 32px;">
                <i class="bi bi-box-arrow-in-right me-1"></i>Go to Login
            </a>
        </div>

        <?php elseif ($error && !$validToken): ?>
        <div class="text-center py-3">
            <div style="width:72px;height:72px;border-radius:50%;background:rgba(192,57,43,.15);border:3px solid #e74c3c;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                <i class="bi bi-x-lg" style="font-size:2rem;color:#e74c3c;"></i>
            </div>
            <h3>Link Expired</h3>
            <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:24px;"><?= h($error) ?></p>
            <a href="<?= BASE_URL ?>/forgot_password.php" class="btn-gold" style="padding:11px 32px;">
                Request New Link
            </a>
        </div>

        <?php else: ?>

        <div class="text-center mb-4">
            <div style="width:72px;height:72px;border-radius:50%;background:rgba(61,191,184,.15);border:3px solid var(--teal-light);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                <i class="bi bi-shield-lock-fill" style="font-size:2rem;color:var(--teal-light);"></i>
            </div>
            <h3>Set New Password</h3>
            <p style="font-size:.75rem;color:var(--text-muted);"><?= h($validToken['email']) ?></p>
        </div>

        <?php if ($error): ?>
        <div style="background:rgba(192,57,43,.15);border:1px solid rgba(192,57,43,.4);border-radius:8px;padding:10px 14px;font-size:.83rem;color:#e74c3c;margin-bottom:16px;">
            <i class="bi bi-exclamation-circle me-1"></i><?= h($error) ?>
        </div>
        <?php endif; ?>

        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= h($token) ?>">

            <div class="mb-3">
                <label class="form-label-dark">New Password</label>
                <div style="position:relative;">
                    <i class="bi bi-lock" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);"></i>
                    <input type="password" name="password" id="pwd1" class="form-control-dark"
                           style="padding-left:36px;padding-right:40px;"
                           placeholder="Minimum 8 characters" required minlength="8" autofocus>
                    <button type="button" onclick="togglePwd('pwd1','eye1')"
                            style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;padding:0;">
                        <i class="bi bi-eye" id="eye1"></i>
                    </button>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label-dark">Confirm New Password</label>
                <div style="position:relative;">
                    <i class="bi bi-lock-fill" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);"></i>
                    <input type="password" name="password2" id="pwd2" class="form-control-dark"
                           style="padding-left:36px;padding-right:40px;"
                           placeholder="Re-enter new password" required>
                    <button type="button" onclick="togglePwd('pwd2','eye2')"
                            style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;padding:0;">
                        <i class="bi bi-eye" id="eye2"></i>
                    </button>
                </div>
                <div id="pwdMatch" style="font-size:.72rem;margin-top:4px;"></div>
            </div>

            <button type="submit" class="btn-gold w-100 justify-content-center" style="padding:12px;">
                <i class="bi bi-check2 me-1"></i>Reset Password
            </button>
        </form>

        <?php endif; ?>
    </div>
</div>

<footer style="background:rgba(0,0,0,0.5);border-top:1px solid rgba(255,255,255,0.07);padding:10px 24px;text-align:center;position:relative;z-index:1;">
    <span style="font-size:.72rem;color:#9aa0b0;">@batstateu &nbsp;|&nbsp; Leading Innovations, Transforming Lives, Building the Nation</span>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePwd(id, iconId) {
    const f = document.getElementById(id);
    const i = document.getElementById(iconId);
    if (f.type === 'password') { f.type = 'text';     i.className = 'bi bi-eye-slash'; }
    else                       { f.type = 'password'; i.className = 'bi bi-eye'; }
}
document.getElementById('pwd2')?.addEventListener('input', function() {
    const p1  = document.getElementById('pwd1').value;
    const msg = document.getElementById('pwdMatch');
    if (!this.value) { msg.textContent = ''; return; }
    msg.innerHTML = this.value === p1
        ? '<span style="color:#2ecc71;"><i class="bi bi-check-circle me-1"></i>Passwords match</span>'
        : '<span style="color:#e74c3c;"><i class="bi bi-x-circle me-1"></i>Passwords do not match</span>';
});
</script>
</body>
</html>
