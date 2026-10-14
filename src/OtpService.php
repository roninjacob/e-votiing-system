<?php
declare(strict_types=1);
require_once __DIR__ . '/Mailer.php';

final class OtpService {
    private static function key(): string {
        $f = APP_ROOT . '/data/.appkey';
        if (!is_file($f)) { @mkdir(dirname($f), 0750, true); file_put_contents($f, bin2hex(random_bytes(32))); }
        return trim((string)file_get_contents($f));
    }
    private static function hash(string $code): string { return hash_hmac('sha256', $code, self::key()); }

    /** @return array{sent:bool,cooldown:int,dev_code:?string} */
    public static function send(string $email, string $purpose, bool $strict): array {
        $pdo = db(); $email = strtolower($email); $now = time();
        $st = $pdo->prepare('SELECT MAX(created_at) FROM web_otp_codes WHERE email = ? AND purpose = ?');
        $st->execute([$email, $purpose]);
        $wait = OTP_RESEND_SECONDS - ($now - (int)$st->fetchColumn());
        if ($wait > 0) {
            if ($strict) throw new ApiError("Please wait $wait seconds before requesting another code.", 'otp', 429);
            return ['sent' => false, 'cooldown' => $wait, 'dev_code' => null];
        }
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $pdo->prepare('UPDATE web_otp_codes SET used_at = ? WHERE email = ? AND purpose = ? AND used_at IS NULL')->execute([$now, $email, $purpose]);
        $pdo->prepare('INSERT INTO web_otp_codes (email, purpose, code_hash, expires_at, created_at) VALUES (?,?,?,?,?)')
            ->execute([$email, $purpose, self::hash($code), $now + OTP_EXPIRY_MINUTES * 60, $now]);
        $id = (int)$pdo->lastInsertId();
        try {
            if (Mailer::configured()) {
                Mailer::send($email, 'E-Voting System verification code',
                    "Your E-Voting System OTP is: $code\n\nUse it to complete your account registration. It expires in " . OTP_EXPIRY_MINUTES . " minutes. Do not share this code.");
                $dev = null;
            } elseif (env('EVS_DEV_MODE') === '1') {
                $dev = $code;
            } else {
                throw new RuntimeException('Email is not configured. Set the EVS_SMTP_* values in .env.');
            }
        } catch (RuntimeException $e) {
            $pdo->prepare('DELETE FROM web_otp_codes WHERE otp_id = ?')->execute([$id]); // never leave an undelivered code valid
            throw new ApiError($e->getMessage(), null, 503);
        }
        return ['sent' => true, 'cooldown' => OTP_RESEND_SECONDS, 'dev_code' => $dev];
    }

    /** @return array{0:bool,1:string} */
    public static function verify(string $email, string $purpose, string $code): array {
        $pdo = db(); $now = time();
        $st = $pdo->prepare('SELECT * FROM web_otp_codes WHERE email = ? AND purpose = ? AND used_at IS NULL ORDER BY otp_id DESC LIMIT 1');
        $st->execute([strtolower($email), $purpose]);
        $row = $st->fetch();
        if (!$row) return [false, 'No active OTP was found. Request a new code.'];
        $close = fn() => $pdo->prepare('UPDATE web_otp_codes SET used_at = ? WHERE otp_id = ?')->execute([$now, $row['otp_id']]);
        if ($now > (int)$row['expires_at']) { $close(); return [false, 'This OTP expired after ' . OTP_EXPIRY_MINUTES . ' minutes. Request a new code.']; }
        if (hash_equals($row['code_hash'], self::hash($code))) { $close(); return [true, 'OTP verified.']; }
        $left = OTP_MAX_ATTEMPTS - ((int)$row['attempts'] + 1);
        $pdo->prepare('UPDATE web_otp_codes SET attempts = attempts + 1 WHERE otp_id = ?')->execute([$row['otp_id']]);
        if ($left <= 0) { $close(); return [false, 'Too many incorrect attempts. Request a new code.']; }
        return [false, "Incorrect OTP. $left attempt(s) left."];
    }
}
