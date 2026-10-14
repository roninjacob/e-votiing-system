<?php
declare(strict_types=1);

// ─── Application Constants ────────────────────────────────────────────────────
define('APP_NAME',    'Block Election System');
define('APP_VERSION', '1.0.0');
define('BASE_PATH',   dirname(__DIR__));
define('BASE_URL',    '/E_VOTING/block-election');

// ─── Upload Settings ──────────────────────────────────────────────────────────
define('UPLOAD_DIR',      BASE_PATH . '/uploads/candidates/');
define('UPLOAD_MAX_BYTES', 2 * 1024 * 1024); // 2 MB
define('UPLOAD_ALLOWED',  ['image/jpeg', 'image/png', 'image/webp']);

// ─── Group 4 Integration ──────────────────────────────────────────────────────
define('GROUP4_VOTE_ENDPOINT', 'https://group4.internal/api/votes');
define('GROUP4_API_KEY',       'REPLACE_WITH_GROUP4_API_KEY');
// Set true during offline development — returns simulated success without calling Group 4
define('GROUP4_SIMULATE',      true);

// ─── Session ──────────────────────────────────────────────────────────────────
define('SESSION_NAME', 'evoting_session');

// ─── Error Reporting (set false in production) ────────────────────────────────
define('APP_DEBUG', false); // set to true only during local development

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// ─── Session Bootstrap ────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}
