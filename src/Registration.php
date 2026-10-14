<?php
declare(strict_types=1);

final class Registration {
    private static function s(array $in, string $key): string {
        return isset($in[$key]) && is_string($in[$key]) ? trim($in[$key]) : '';
    }

    public static function blocks(): array {
        return db()->query('SELECT id, block_name, section, year_level, semester, track FROM blocks ORDER BY year_level, semester, block_name, section')->fetchAll();
    }

    public static function basic(array $in): array {
        $name = preg_replace('/\s+/u', ' ', self::s($in, 'full_name'));
        if (!$name || mb_strlen($name) > 150 || !preg_match("/^[\p{L}\p{M}.' -]+$/u", $name))
            throw new ApiError('Enter a valid full name.', 'full_name');
        $email = strtolower(self::s($in, 'email'));
        if (strlen($email) > 150 || !preg_match(EMAIL_PATTERN, $email))
            throw new ApiError('Use your BatStateU email: name@g.batstate-u.edu.ph.', 'email');
        $studentId = self::s($in, 'student_id');
        if (!preg_match('/^\d{2}-\d{5}$/', $studentId))
            throw new ApiError('Enter your student ID in YY-NNNNN format (for example, 23-01041).', 'student_id');
        $pdo = db();
        $st = $pdo->prepare('SELECT 1 FROM users WHERE email = ? OR student_id = ? LIMIT 1');
        $st->execute([$email, $studentId]);
        if ($st->fetch()) throw new ApiError('That email or student ID is already registered.', null, 409);
        return ['full_name' => $name, 'email' => $email, 'student_id' => $studentId];
    }

    public static function department(array $in): array {
        $id = (int)($in['block_id'] ?? 0);
        $st = db()->prepare('SELECT id, block_name, section, year_level, semester, track FROM blocks WHERE id = ?');
        $st->execute([$id]);
        $block = $st->fetch();
        if (!$block) throw new ApiError('Select a valid block.', 'block_id');
        $track = $block['track'] ? ' · ' . ($block['track'] === 'BA' ? 'Business Analytics' : 'Networking') : '';
        return ['block_id' => (int)$block['id'], 'block_name' => $block['block_name'], 'section' => $block['section'],
                'year_level' => (int)$block['year_level'], 'semester' => (int)$block['semester'], 'track' => $block['track'],
                'label' => $block['block_name'] . ' ' . $block['section'] . ' · Year ' . $block['year_level'] . ' · Semester ' . $block['semester'] . $track];
    }

    public static function checkPassword(string $password, string $confirm): void {
        if (strlen($password) < 8 || strlen($password) > 72 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password))
            throw new ApiError('Password must be 8–72 characters and include letters and numbers.', 'password');
        if ($password !== $confirm) throw new ApiError('The passwords do not match.', 'confirm');
    }

    public static function review(array $registration): array {
        $basic = $registration['basic']; $block = $registration['dept'];
        $year = ['1' => '1st', '2' => '2nd', '3' => '3rd'][$block['year_level']] ?? ($block['year_level'] . 'th');
        $track = $block['track'] ? ($block['track'] === 'BA' ? 'Business Analytics' : 'Networking') : 'None';
        return ['Full Name' => $basic['full_name'], 'Email Address' => $basic['email'], 'Student ID' => $basic['student_id'],
                'Block' => $block['block_name'] . ' ' . $block['section'], 'Year Level' => $year . ' Year',
                'Semester' => (string)$block['semester'], 'Track' => $track];
    }

    public static function create(array $registration): array {
        $basic = $registration['basic']; $block = $registration['dept'];
        try {
            db()->prepare("INSERT INTO users (student_id, full_name, email, password_hash, role, block_id, is_verified, is_active) VALUES (?,?,?,?, 'voter', ?, 1, 1)")
                ->execute([$basic['student_id'], $basic['full_name'], $basic['email'], $registration['password_hash'], $block['block_id']]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') throw new ApiError('That email or student ID is already registered.', null, 409);
            throw $e;
        }
        return ['Full Name' => $basic['full_name'], 'Student ID' => $basic['student_id'], 'Email Address' => $basic['email'], 'Date Registered' => date('F j, Y g:i A')];
    }
}
