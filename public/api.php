<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/OtpService.php';
require __DIR__ . '/../src/Registration.php';
start_session();
require __DIR__ . '/../src/Voting.php';
require __DIR__ . '/../src/CicsVoting.php';

function current_user(): array {
    $st = db()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([(int)($_SESSION['block_uid'] ?? 0)]);
    $u = $st->fetch();
    if (!$u) { unset($_SESSION['uid']); throw new ApiError('Please log in.', null, 401); }
    return $u;
}

function need(array $reg, string ...$keys): void {
    foreach ($keys as $k) if (empty($reg[$k])) throw new ApiError('Your registration session expired. Please start again.', null, 409);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new ApiError('Method not allowed.', null, 405);
    if (!hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) throw new ApiError('Security check failed. Reload the page.', null, 403);
    $raw = file_get_contents('php://input');
    $in = strlen($raw) < 20000 ? json_decode($raw, true) : null;
    if (!is_array($in)) $in = [];
    $reg = $_SESSION['reg'] ?? [];

    switch ($_GET['action'] ?? '') {
        case 'basic':
            $b = Registration::basic($in);
            if (($reg['basic']['email'] ?? '') !== $b['email']) $reg = [];   // new email => verification starts over
            $reg['basic'] = $b; $_SESSION['reg'] = $reg;
            json_out(['ok' => true]);

        case 'department':
            need($reg, 'basic');
            $reg['dept'] = Registration::department($in); $_SESSION['reg'] = $reg;
            if (!empty($reg['otp_verified'])) json_out(['ok' => true, 'verified' => true, 'email' => $reg['basic']['email']]);
            $r = OtpService::send($reg['basic']['email'], 'signup', false);
            json_out(['ok' => true, 'verified' => false, 'email' => $reg['basic']['email'], 'cooldown' => $r['cooldown'], 'dev_code' => $r['dev_code']]);

        case 'resend':
            need($reg, 'basic', 'dept');
            if (!empty($reg['otp_verified'])) throw new ApiError('Your email is already verified.');
            $r = OtpService::send($reg['basic']['email'], 'signup', true);
            json_out(['ok' => true, 'cooldown' => $r['cooldown'], 'dev_code' => $r['dev_code']]);

        case 'verify':
            need($reg, 'basic', 'dept');
            $code = is_string($in['code'] ?? null) ? $in['code'] : '';
            if (!preg_match('/^\d{6}$/', $code)) throw new ApiError('Enter all six digits of the verification code.', 'otp');
            [$ok, $msg] = OtpService::verify($reg['basic']['email'], 'signup', $code);
            if (!$ok) throw new ApiError($msg, 'otp');
            $reg['otp_verified'] = true; $_SESSION['reg'] = $reg;
            json_out(['ok' => true]);

        case 'password':
            need($reg, 'basic', 'dept', 'otp_verified');
            Registration::checkPassword((string)($in['password'] ?? ''), (string)($in['confirm'] ?? ''));
            $reg['password_hash'] = password_hash((string)$in['password'], PASSWORD_DEFAULT); $_SESSION['reg'] = $reg;
            json_out(['ok' => true, 'review' => Registration::review($reg)]);

        case 'complete':
            need($reg, 'basic', 'dept', 'otp_verified', 'password_hash');
            $details = Registration::create($reg);
            unset($_SESSION['reg']);
            json_out(['ok' => true, 'details' => $details]);

        case 'login':
            if (($_SESSION['lock'] ?? 0) > time()) throw new ApiError('Too many attempts. Try again in a minute.', null, 429);
            $st = db()->prepare('SELECT id, password_hash, is_active, is_verified FROM users WHERE student_id = ?');
            $st->execute([trim((string)($in['student_id'] ?? ''))]);
            $u = $st->fetch();
            if (!$u || !password_verify((string)($in['password'] ?? ''), $u['password_hash'])) {
                $_SESSION['fails'] = ($_SESSION['fails'] ?? 0) + 1;
                if ($_SESSION['fails'] >= 5) { $_SESSION['lock'] = time() + 60; $_SESSION['fails'] = 0; }
                throw new ApiError('Invalid student ID or password.');
            }
            if (!$u['is_active']) throw new ApiError('This account is inactive. Please contact an administrator.', null, 403);
            if (!$u['is_verified']) throw new ApiError('Verify your email before signing in.', null, 403);
            session_regenerate_id(true); $_SESSION['block_uid'] = (int)$u['id']; $_SESSION['fails'] = 0;
            json_out(['ok' => true]);

        case 'logout':
            unset($_SESSION['uid'], $_SESSION['block_uid']); json_out(['ok' => true]);

        case 'ballot':
            json_out(['ok' => true] + Voting::ballot(current_user()));

        case 'vote':
            json_out(['ok' => true, 'reference' => Voting::submit(current_user(), is_array($in['choices'] ?? null) ? $in['choices'] : [])]);

        case 'cics-ballot':
            json_out(['ok' => true] + CicsVoting::ballot(current_user()));

        case 'cics-vote':
            json_out(['ok' => true, 'reference' => CicsVoting::submit(current_user(), is_array($in['choices'] ?? null) ? $in['choices'] : [])]);

        default:
            throw new ApiError('Unknown action.', null, 404);
    }
} catch (ApiError $e) {
    json_out(['ok' => false, 'error' => $e->getMessage(), 'field' => $e->field], $e->status);
} catch (Throwable $e) {
    error_log((string)$e);
    if ($e instanceof RuntimeException && (str_starts_with($e->getMessage(), 'Cannot connect to MySQL.') || str_contains($e->getMessage(), 'Import database/mysql_schema.sql'))) {
        json_out(['ok' => false, 'error' => $e->getMessage()], 503);
    }
    json_out(['ok' => false, 'error' => 'Something went wrong on the server. Please try again.'], 500);
}
