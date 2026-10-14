<?php
// ── Module: BlockElectionService ─────────────────────────────────────────────

$svc = new BlockElectionService(db());

$validData = [
    'block_id'    => 1,
    'title'       => 'Test Election',
    'description' => 'A test election.',
    'start_at'    => '2025-01-01 08:00:00',
    'end_at'      => '2025-12-31 23:59:59',
];

// ── create() ─────────────────────────────────────────────────────────────────

test('Election: create() returns new ID', function () use ($svc, $validData) {
    $id = $svc->create($validData, 1);
    assert_true($id > 0, 'ID should be positive integer');
});

test('Election: create() sets status to draft', function () use ($svc, $validData) {
    $id = $svc->create($validData, 1);
    $el = $svc->getById($id);
    assert_equals('draft', $el['status']);
});

test('Election: create() rejects empty title', function () use ($svc, $validData) {
    assert_throws(fn() => $svc->create(array_merge($validData, ['title' => '']), 1), 'title is required');
});

test('Election: create() rejects title over 200 chars', function () use ($svc, $validData) {
    assert_throws(fn() => $svc->create(array_merge($validData, ['title' => str_repeat('x', 201)]), 1), '200 characters');
});

test('Election: create() rejects invalid block_id', function () use ($svc, $validData) {
    assert_throws(fn() => $svc->create(array_merge($validData, ['block_id' => 0]), 1), 'valid block');
});

test('Election: create() rejects non-existent block', function () use ($svc, $validData) {
    assert_throws(fn() => $svc->create(array_merge($validData, ['block_id' => 9999]), 1), 'does not exist');
});

test('Election: create() rejects end_at before start_at', function () use ($svc, $validData) {
    assert_throws(fn() => $svc->create(array_merge($validData, [
        'start_at' => '2025-12-31 23:59:59',
        'end_at'   => '2025-01-01 08:00:00',
    ]), 1), 'after start');
});

test('Election: create() rejects equal start and end', function () use ($svc, $validData) {
    assert_throws(fn() => $svc->create(array_merge($validData, [
        'start_at' => '2025-06-01 08:00:00',
        'end_at'   => '2025-06-01 08:00:00',
    ]), 1), 'after start');
});

// ── getById() ────────────────────────────────────────────────────────────────

test('Election: getById() returns null for missing ID', function () use ($svc) {
    assert_true($svc->getById(99999) === null);
});

test('Election: getById() returns correct election', function () use ($svc, $validData) {
    $id = $svc->create($validData, 1);
    $el = $svc->getById($id);
    assert_equals('Test Election', $el['title']);
});

// ── update() ─────────────────────────────────────────────────────────────────

test('Election: update() modifies title on draft', function () use ($svc, $validData) {
    $id = $svc->create($validData, 1);
    $svc->update($id, array_merge($validData, ['title' => 'Updated Title']));
    assert_equals('Updated Title', $svc->getById($id)['title']);
});

test('Election: update() rejects non-draft election', function () use ($svc, $validData) {
    $id = $svc->create($validData, 1);
    db()->exec("UPDATE block_elections SET status='open' WHERE id={$id}");
    assert_throws(fn() => $svc->update($id, $validData), 'Only draft');
});

test('Election: update() rejects missing election', function () use ($svc, $validData) {
    assert_throws(fn() => $svc->update(99999, $validData), 'not found');
});

// ── delete() ─────────────────────────────────────────────────────────────────

test('Election: delete() removes draft election', function () use ($svc, $validData) {
    $id = $svc->create($validData, 1);
    $svc->delete($id);
    assert_true($svc->getById($id) === null);
});

test('Election: delete() rejects open election', function () use ($svc, $validData) {
    $id = $svc->create($validData, 1);
    db()->exec("UPDATE block_elections SET status='open' WHERE id={$id}");
    assert_throws(fn() => $svc->delete($id), 'Only draft or configured');
});

test('Election: delete() rejects missing election', function () use ($svc) {
    assert_throws(fn() => $svc->delete(99999), 'not found');
});

// ── transition() ─────────────────────────────────────────────────────────────

test('Election: transition() draft → configured', function () use ($svc, $validData) {
    $id   = $svc->create($validData, 1);
    $next = $svc->transition($id, 1);
    assert_equals('configured', $next);
});

test('Election: transition() configured → open requires candidate', function () use ($svc, $validData) {
    $id = $svc->create($validData, 1);
    $svc->transition($id, 1); // → configured
    assert_throws(fn() => $svc->transition($id, 1), 'at least 1 position');
});

test('Election: transition() rejects published (terminal)', function () use ($svc, $validData) {
    $id = $svc->create($validData, 1);
    db()->exec("UPDATE block_elections SET status='published' WHERE id={$id}");
    assert_throws(fn() => $svc->transition($id, 1), 'terminal status');
});

// ── getStats() ───────────────────────────────────────────────────────────────

test('Election: getStats() returns all status keys', function () use ($svc) {
    $stats = $svc->getStats();
    foreach (['total', 'open', 'draft', 'closed', 'published', 'configured'] as $key) {
        assert_true(array_key_exists($key, $stats), "Missing key: {$key}");
    }
});

test('Election: getStats() total equals sum of statuses', function () use ($svc) {
    $stats = $svc->getStats();
    $sum   = $stats['open'] + $stats['draft'] + $stats['closed'] + $stats['published'] + $stats['configured'];
    assert_equals($stats['total'], $sum);
});
