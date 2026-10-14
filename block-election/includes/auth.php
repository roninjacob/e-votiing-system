<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

// ─── Output Helpers ───────────────────────────────────────────────────────────

function e(mixed $value): void
{
    echo htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ─── CSRF ─────────────────────────────────────────────────────────────────────

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function csrf_check(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        die('CSRF token mismatch.');
    }
}

// ─── Flash Messages ───────────────────────────────────────────────────────────

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function render_flash(): void
{
    if (empty($_SESSION['flash'])) return;
    foreach ($_SESSION['flash'] as $f) {
        $type = in_array($f['type'], ['success','danger','warning','info'], true) ? $f['type'] : 'info';
        echo '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert"
                   style="font-size:.85rem;border-radius:8px;border:none;">';
        e($f['message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
    unset($_SESSION['flash']);
}

// ─── Auth Guards ──────────────────────────────────────────────────────────────

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (current_user() === null) {
        flash('warning', 'Please log in to continue.');
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

/** @param string[] $roles */
function require_role(array $roles): void
{
    require_login();
    $user = current_user();
    if (!in_array($user['role'] ?? '', $roles, true)) {
        http_response_code(403);
        flash('danger', 'Access denied.');
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

function redirect(string $path): never
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

// ─── GSuite Email Validation ──────────────────────────────────────────────────

/**
 * Valid student GSuite format: YY-NNNNN@g.batstate-u.edu.ph
 * e.g. 23-01041@g.batstate-u.edu.ph
 */
function is_valid_gsuite(string $email): bool
{
    return (bool) preg_match('/^\d{2}-\d{5}@g\.batstate-u\.edu\.ph$/i', $email);
}

/**
 * Extract student_id from GSuite email (the local part before @).
 * e.g. 23-01041@g.batstate-u.edu.ph → 23-01041
 */
function gsuite_to_student_id(string $email): string
{
    return explode('@', strtolower(trim($email)))[0];
}

// ─── Section Format Helper ────────────────────────────────────────────────────

/**
 * Parse section code YSBB into readable label.
 * 1101 → Year 1 | Sem 1 | Block 01
 */
function parse_section(string $section): array
{
    if (strlen($section) !== 4 || !ctype_digit($section)) {
        return ['year' => '?', 'semester' => '?', 'block' => $section, 'label' => $section];
    }
    return [
        'year'     => $section[0],
        'semester' => $section[1],
        'block'    => substr($section, 2),
        'label'    => 'Year ' . $section[0] . ' · Sem ' . $section[1] . ' · Block ' . substr($section, 2),
    ];
}

function section_label(string $section, ?string $track = null): string
{
    $p    = parse_section($section);
    $base = $p['label'] ?? $section;
    return $base . match($track) {
        'BA'    => ' — Business Analytics',
        'NT'    => ' — Networking',
        default => '',
    };
}

function track_label(?string $track): string
{
    return match($track) {
        'BA'    => 'Business Analytics Track',
        'NT'    => 'Networking Track',
        default => 'General',
    };
}
