<?php
// ── Module: Auth Helpers (includes/auth.php) ─────────────────────────────────
// Tests pure functions only — no HTTP/session side effects

require_once BASE_PATH . '/includes/auth.php';

// ── is_valid_gsuite() ────────────────────────────────────────────────────────

test('Auth: valid GSuite email passes', function () {
    assert_true(is_valid_gsuite('23-01041@g.batstate-u.edu.ph'));
});

test('Auth: valid GSuite with different year passes', function () {
    assert_true(is_valid_gsuite('21-99999@g.batstate-u.edu.ph'));
});

test('Auth: wrong domain fails', function () {
    assert_false(is_valid_gsuite('23-01041@gmail.com'));
});

test('Auth: missing student number fails', function () {
    assert_false(is_valid_gsuite('@g.batstate-u.edu.ph'));
});

test('Auth: wrong format (no dash) fails', function () {
    assert_false(is_valid_gsuite('2301041@g.batstate-u.edu.ph'));
});

test('Auth: too short student number fails', function () {
    assert_false(is_valid_gsuite('23-0104@g.batstate-u.edu.ph'));
});

test('Auth: too long student number fails', function () {
    assert_false(is_valid_gsuite('23-010410@g.batstate-u.edu.ph'));
});

test('Auth: uppercase domain passes (case-insensitive)', function () {
    assert_true(is_valid_gsuite('23-01041@G.BATSTATE-U.EDU.PH'));
});

// ── gsuite_to_student_id() ───────────────────────────────────────────────────

test('Auth: gsuite_to_student_id() extracts local part', function () {
    assert_equals('23-01041', gsuite_to_student_id('23-01041@g.batstate-u.edu.ph'));
});

test('Auth: gsuite_to_student_id() lowercases result', function () {
    assert_equals('23-01041', gsuite_to_student_id('23-01041@G.BATSTATE-U.EDU.PH'));
});

// ── h() ──────────────────────────────────────────────────────────────────────

test('Auth: h() escapes HTML special chars', function () {
    assert_equals('&lt;script&gt;', h('<script>'));
});

test('Auth: h() escapes double quotes', function () {
    assert_equals('&quot;', h('"'));
});

test('Auth: h() escapes single quotes', function () {
    assert_equals('&#039;', h("'"));
});

test('Auth: h() returns plain string unchanged', function () {
    assert_equals('Hello World', h('Hello World'));
});

// ── csrf_token() ─────────────────────────────────────────────────────────────

test('Auth: csrf_token() returns non-empty string', function () {
    $token = csrf_token();
    assert_true(strlen($token) > 0);
});

test('Auth: csrf_token() returns same token on repeated calls', function () {
    $t1 = csrf_token();
    $t2 = csrf_token();
    assert_equals($t1, $t2);
});

// ── parse_section() ──────────────────────────────────────────────────────────

test('Auth: parse_section() parses 1101 correctly', function () {
    $p = parse_section('1101');
    assert_equals('1', $p['year']);
    assert_equals('1', $p['semester']);
    assert_equals('01', $p['block']);
});

test('Auth: parse_section() handles invalid input gracefully', function () {
    $p = parse_section('XYZ');
    assert_equals('?', $p['year']);
});
