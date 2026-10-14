<?php
declare(strict_types=1);

/**
 * Minimal test runner — no PHPUnit required.
 * Usage: cmd /c "C:\xampp\php\php.exe tests\TestRunner.php"
 */

define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_DIR',       BASE_PATH . '/uploads/candidates/');
define('UPLOAD_MAX_BYTES', 2 * 1024 * 1024);
define('UPLOAD_ALLOWED',   ['image/jpeg', 'image/png', 'image/webp']);
define('APP_DEBUG',        true);
define('APP_NAME',         'Test');
define('APP_VERSION',      '1.0.0');
define('BASE_URL',         '/E_VOTING/block-election');
define('SESSION_NAME',     'evoting_test');
define('GROUP4_VOTE_ENDPOINT', 'http://localhost');
define('GROUP4_API_KEY',       'test');
define('GROUP4_SIMULATE',      true);

// Start session before auth.php tries to
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    @session_start();
}

// ── Bootstrap DB (SQLite in-memory for isolation) ─────────────────────────────
function db(): PDO {
    static $pdo;
    if ($pdo) return $pdo;

    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec("
        CREATE TABLE blocks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            block_name TEXT NOT NULL,
            section    TEXT NOT NULL,
            year_level INTEGER NOT NULL,
            semester   INTEGER NOT NULL DEFAULT 1,
            track      TEXT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE users (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            student_id    TEXT NOT NULL UNIQUE,
            full_name     TEXT NOT NULL,
            email         TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role          TEXT NOT NULL DEFAULT 'voter',
            block_id      INTEGER NULL,
            is_verified   INTEGER NOT NULL DEFAULT 0,
            is_active     INTEGER NOT NULL DEFAULT 1,
            created_at    TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE block_elections (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            block_id    INTEGER NOT NULL,
            title       TEXT NOT NULL,
            description TEXT NULL,
            start_at    TEXT NOT NULL,
            end_at      TEXT NOT NULL,
            status      TEXT NOT NULL DEFAULT 'draft',
            created_by  INTEGER NULL,
            created_at  TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at  TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE block_positions (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            election_id   INTEGER NOT NULL,
            position_name TEXT NOT NULL,
            max_votes     INTEGER NOT NULL DEFAULT 1,
            is_required   INTEGER NOT NULL DEFAULT 1,
            sort_order    INTEGER NOT NULL DEFAULT 0
        );
        CREATE TABLE block_candidates (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            position_id INTEGER NOT NULL,
            full_name   TEXT NOT NULL,
            party       TEXT NULL,
            platform    TEXT NULL,
            photo_path  TEXT NULL,
            is_active   INTEGER NOT NULL DEFAULT 1
        );
        CREATE TABLE block_election_status_logs (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            election_id INTEGER NOT NULL,
            from_status TEXT NOT NULL,
            to_status   TEXT NOT NULL,
            changed_by  INTEGER NULL,
            changed_at  TEXT DEFAULT CURRENT_TIMESTAMP,
            note        TEXT NULL
        );
        CREATE TABLE block_eligibility_cache (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            election_id INTEGER NOT NULL,
            voter_id    INTEGER NOT NULL,
            block_id    INTEGER NOT NULL,
            cached_at   TEXT DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (voter_id, election_id)
        );
        CREATE TABLE block_votes (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            election_id  INTEGER NOT NULL,
            voter_id     INTEGER NOT NULL,
            block_id     INTEGER NOT NULL,
            vote_ref     TEXT NOT NULL,
            payload_hash TEXT NOT NULL,
            group4_ref   TEXT NULL,
            status       TEXT NOT NULL DEFAULT 'pending',
            submitted_at TEXT DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (voter_id, election_id)
        );
        INSERT INTO blocks (id, block_name, section, year_level, semester, track)
            VALUES (1, 'BSIT', '1101', 1, 1, NULL),
                   (2, 'BSIT', '1102', 1, 1, NULL);
        INSERT INTO users (id, student_id, full_name, email, password_hash, role, block_id, is_verified, is_active)
            VALUES (1, 'ADMIN001', 'Admin User', 'admin@test.com',  'hash', 'admin', NULL, 1, 1),
                   (2, '23-00001', 'Voter One',  'voter1@test.com', 'hash', 'voter', 1,    1, 1),
                   (3, '23-00002', 'Voter Two',  'voter2@test.com', 'hash', 'voter', 2,    1, 1),
                   (4, '23-00003', 'Unverified', 'voter3@test.com', 'hash', 'voter', 1,    0, 1),
                   (5, '23-00004', 'Inactive',   'voter4@test.com', 'hash', 'voter', 1,    1, 0);
    ");

    return $pdo;
}

// ── Autoload services (skip database.php and config.php — already defined) ───
foreach (glob(BASE_PATH . '/services/*.php') as $f) require_once $f;

// Load only the pure helper functions from auth.php (suppress session warnings)
// We manually include it after session is started
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require_once BASE_PATH . '/includes/auth.php';
error_reporting(E_ALL);

// ── Test framework helpers ────────────────────────────────────────────────────
$passed = 0;
$failed = 0;
$errors = [];

function test(string $name, callable $fn): void {
    global $passed, $failed, $errors;
    try {
        $fn();
        echo "\033[32m  \u2714 {$name}\033[0m\n";
        $passed++;
    } catch (Throwable $e) {
        echo "\033[31m  \u2718 {$name}\033[0m\n    \u2192 " . $e->getMessage() . "\n";
        $failed++;
        $errors[] = $name;
    }
}

function assert_true(bool $condition, string $msg = 'Expected true'): void {
    if (!$condition) throw new Exception($msg);
}

function assert_false(bool $condition, string $msg = 'Expected false'): void {
    if ($condition) throw new Exception($msg);
}

function assert_equals(mixed $expected, mixed $actual, string $msg = ''): void {
    if ($expected !== $actual)
        throw new Exception($msg ?: "Expected [{$expected}] got [{$actual}]");
}

function assert_throws(callable $fn, string $contains = ''): void {
    try {
        $fn();
        throw new Exception('Expected exception was not thrown.');
    } catch (Exception $e) {
        if ($e->getMessage() === 'Expected exception was not thrown.')
            throw $e;
        if ($contains && !str_contains(strtolower($e->getMessage()), strtolower($contains)))
            throw new Exception("Expected exception containing '{$contains}', got: {$e->getMessage()}");
    }
}

// ── Run all test modules ──────────────────────────────────────────────────────
$modules = glob(__DIR__ . '/Test*.php');
sort($modules);
foreach ($modules as $module) {
    if (basename($module) === 'TestRunner.php') continue;
    echo "\n\033[33m" . basename($module) . "\033[0m\n";
    require $module;
}

// ── Summary ───────────────────────────────────────────────────────────────────
echo "\n" . str_repeat('─', 50) . "\n";
echo "\033[32mPassed: {$passed}\033[0m  \033[31mFailed: {$failed}\033[0m\n";
if ($failed > 0) {
    echo "\nFailed tests:\n";
    foreach ($errors as $e) echo "  - {$e}\n";
    exit(1);
}
exit(0);
