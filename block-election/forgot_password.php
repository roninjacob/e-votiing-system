<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/mail.php';
require_once __DIR__ . '/vendor/phpmailer/Exception.php';
require_once __DIR__ . '/vendor/phpmailer/PHPMailer.php';
require_once __DIR__ . '/vendor/phpmailer/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

if (current_user() !== null) redirect('/index.php');

$isAdmin = isset($_GET['admin']);
$sent    = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $email = strtolower(trim($_POST['email'] ?? ''));

    if ($email === '') {
        $error = 'Please enter your email address.';
    } elseif (!$isAdmin && !is_valid_gsuite($email)) {
        $error = 'Enter your GSuite email (e.g. 23-01041@g.batstate-u.edu.ph).';
    } else {
        $roleClause = $isAdmin ? "role IN ('admin','adviser','committee')" : "role = 'voter'";
        $stmt = db()->prepare("SELECT id, full_name FROM users WHERE email = ? AND {$roleClause} LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Invalidate any existing tokens for this user
            db()->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?')
               ->execute([$user['id']]);

            $token     = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);

            db()->prepare(
                'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))'
            )->execute([$user['id'], $tokenHash]);

            $resetLink = 'http://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/reset_password.php?token=' . urlencode($token);

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = MAIL_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = MAIL_USERNAME;
                $mail->Password   = MAIL_PASSWORD;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = MAIL_PORT;
                $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
                $mail->addAddress($email, $user['full_name']);
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset — CICS E-Voting';
                $mail->Body    = '
                    <div style="font-family:Arial,sans-serif;max-width:480px;margin:auto;background:#1a1a2e;color:#eee;border-radius:10px;padding:32px;">
                        <h2 style="color:#f0a500;margin-bottom:8px;">Password Reset</h2>
                        <p>Hi ' . htmlspecialchars($user['full_name']) . ',</p>
                        <p>Click the button below to reset your password. This link expires in <strong>1 hour</strong>.</p>
                        <div style="text-align:center;margin:28px 0;">
                            <a href="' . $resetLink . '"
                               style="background:#f0a500;color:#1a1a2e;padding:12px 32px;border-radius:6px;text-decoration:none;font-weight:bold;font-size:15px;">
                                Reset Password
                            </a>
                        </div>
                        <p style="font-size:12px;color:#888;">If you did not request this, ignore this email.</p>
                        <hr style="border-color:#333;">
                        <p style="font-size:11px;color:#666;">CICS E-Voting System — Batangas State University</p>
                    </div>';
                $mail->send();
            } catch (MailException $e) {
                error_log('Mailer error: ' . $mail->ErrorInfo);
            }
        }

        // Always show success to prevent email enumeration
        $sent = true;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — CICS E-Voting</title>
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

        <div class="text-center mb-4">
            <div style="width:72px;height:72px;border-radius:50%;background:rgba(61,191,184,.15);border:3px solid var(--gold);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                <i class="bi bi-key-fill" style="font-size:2rem;color:var(--gold);"></i>
            </div>
            <h3>Forgot Password</h3>
            <p style="font-size:.78rem;color:var(--text-muted);">
                <?= $isAdmin ? 'Enter your admin email address.' : 'Enter your GSuite email address.' ?>
            </p>
        </div>

        <?php if ($sent): ?>
        <div style="background:rgba(46,204,113,.12);border:1px solid rgba(46,204,113,.3);border-radius:8px;padding:14px;font-size:.84rem;color:#2ecc71;margin-bottom:16px;text-align:center;">
            <i class="bi bi-check-circle me-1"></i>
            If that email is registered, a reset link has been sent.
        </div>



        <a href="<?= BASE_URL ?>/<?= $isAdmin ? 'admin/login.php' : 'login.php' ?>"
           class="btn-gold w-100 justify-content-center" style="padding:11px;">
            <i class="bi bi-arrow-left me-1"></i>Back to Login
        </a>

        <?php else: ?>

        <?php if ($error): ?>
        <div style="background:rgba(192,57,43,.15);border:1px solid rgba(192,57,43,.4);border-radius:8px;padding:10px 14px;font-size:.83rem;color:#e74c3c;margin-bottom:16px;">
            <i class="bi bi-exclamation-circle me-1"></i><?= h($error) ?>
        </div>
        <?php endif; ?>

        <form method="post">
            <?= csrf_field() ?>
            <?php if ($isAdmin): ?>
            <input type="hidden" name="admin" value="1">
            <?php endif; ?>

            <div class="mb-4">
                <label class="form-label-dark">Email Address</label>
                <div style="position:relative;">
                    <i class="bi bi-envelope" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);"></i>
                    <input type="email" name="email" class="form-control-dark"
                           style="padding-left:36px;"
                           value="<?= h($_POST['email'] ?? '') ?>"
                           placeholder="<?= $isAdmin ? 'admin@cics.batstate-u.edu.ph' : '23-01041@g.batstate-u.edu.ph' ?>"
                           required autofocus>
                </div>
            </div>

            <button type="submit" class="btn-gold w-100 justify-content-center" style="padding:12px;">
                <i class="bi bi-send me-1"></i>Send Reset Link
            </button>
        </form>

        <div style="text-align:center;margin-top:16px;">
            <a href="<?= BASE_URL ?>/<?= $isAdmin ? 'admin/login.php' : 'login.php' ?>"
               style="font-size:.8rem;color:var(--text-muted);text-decoration:none;">
                <i class="bi bi-arrow-left me-1"></i>Back to Login
            </a>
        </div>

        <?php endif; ?>
    </div>
</div>

<footer style="background:rgba(0,0,0,0.5);border-top:1px solid rgba(255,255,255,0.07);padding:10px 24px;text-align:center;position:relative;z-index:1;">
    <span style="font-size:.72rem;color:#9aa0b0;">@batstateu &nbsp;|&nbsp; Leading Innovations, Transforming Lives, Building the Nation</span>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
