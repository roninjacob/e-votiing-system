<?php
declare(strict_types=1);
/** Minimal SMTP client (STARTTLS on 587, implicit TLS on 465) so no Composer package is needed. */
final class Mailer {
    public static function configured(): bool {
        $v = [env('EVS_SMTP_HOST'), env('EVS_SMTP_USER'), env('EVS_SMTP_PASSWORD')];
        return !in_array(null, $v, true) && !str_contains(strtolower(implode('', $v)), 'your-');
    }

    public static function send(string $to, string $subject, string $body): void {
        $host = env('EVS_SMTP_HOST'); $port = (int)env('EVS_SMTP_PORT', '587');
        $user = env('EVS_SMTP_USER'); $pass = env('EVS_SMTP_PASSWORD');
        $from = preg_replace('/[\r\n]/', '', env('EVS_SMTP_FROM', $user));
        preg_match('/<([^>]+)>|(\S+@\S+)/', $from, $m);
        $fromAddr = $m[1] ?: $m[2];
        $fp = @stream_socket_client(($port === 465 ? 'ssl://' : 'tcp://') . "$host:$port", $en, $es, 20);
        if (!$fp) { error_log("SMTP connect failed: $es"); throw new RuntimeException('Could not connect to the mail server.'); }
        stream_set_timeout($fp, 20);
        try {
            self::read($fp, [220]);
            self::cmd($fp, 'EHLO localhost', [250]);
            if ($port !== 465) {
                self::cmd($fp, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('TLS failed.');
                self::cmd($fp, 'EHLO localhost', [250]);
            }
            self::cmd($fp, 'AUTH LOGIN', [334]);
            self::cmd($fp, base64_encode($user), [334]);
            self::cmd($fp, base64_encode($pass), [235]);
            self::cmd($fp, "MAIL FROM:<$fromAddr>", [250]);
            self::cmd($fp, "RCPT TO:<$to>", [250, 251]);
            self::cmd($fp, 'DATA', [354]);
            $msg = "From: $from\r\nTo: $to\r\nSubject: $subject\r\nDate: " . date('r') . "\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n"
                 . preg_replace('/^\./m', '..', str_replace("\n", "\r\n", $body));
            fwrite($fp, $msg . "\r\n.\r\n");
            self::read($fp, [250]);
            @fwrite($fp, "QUIT\r\n");
        } catch (RuntimeException $e) {
            error_log('SMTP error: ' . $e->getMessage());
            throw new RuntimeException('The verification email could not be sent. Check the SMTP settings in .env.');
        } finally { fclose($fp); }
    }

    private static function cmd($fp, string $c, array $ok): void { fwrite($fp, $c . "\r\n"); self::read($fp, $ok); }

    private static function read($fp, array $ok): void {
        do { $line = fgets($fp, 515); } while ($line !== false && isset($line[3]) && $line[3] === '-');
        if ($line === false || !in_array((int)substr($line, 0, 3), $ok, true)) throw new RuntimeException('SMTP replied: ' . trim((string)$line));
    }
}
