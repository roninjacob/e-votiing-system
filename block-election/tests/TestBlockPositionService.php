<?php
// ── Module: BlockPositionService ─────────────────────────────────────────────

$posSvc = new BlockPositionService(db());
$elSvc  = new BlockElectionService(db());

// Create a fresh draft election for position tests
$posElId = $elSvc->create([
    'block_id' => 1,
    'title'    => 'Position Test Election',
    'start_at' => '2025-01-01 08:00:00',
    'end_at'   => '2025-12-31 23:59:59',
], 1);

$validPos = ['position_name' => 'President', 'max_votes' => 1, 'is_required' => 1, 'sort_order' => 1];

// ── create() ─────────────────────────────────────────────────────────────────

test('Position: create() returns new ID', function () use ($posSvc, $posElId, $validPos) {
    $id = $posSvc->create($posElId, $validPos);
    assert_true($id > 0);
});

test('Position: create() rejects empty position_name', function () use ($posSvc, $posElId) {
    assert_throws(
        fn() => $posSvc->create($posElId, ['position_name' => '', 'max_votes' => 1]),
        'required'
    );
});

test('Position: create() rejects position_name over 100 chars', function () use ($posSvc, $posElId) {
    assert_throws(
        fn() => $posSvc->create($posElId, ['position_name' => str_repeat('x', 101), 'max_votes' => 1]),
        '100 characters'
    );
});

test('Position: create() rejects max_votes = 0', function () use ($posSvc, $posElId) {
    assert_throws(
        fn() => $posSvc->create($posElId, ['position_name' => 'VP', 'max_votes' => 0]),
        'between 1 and 10'
    );
});

test('Position: create() rejects max_votes > 10', function () use ($posSvc, $posElId) {
    assert_throws(
        fn() => $posSvc->create($posElId, ['position_name' => 'VP', 'max_votes' => 11]),
        'between 1 and 10'
    );
});

// ── getByElection() ──────────────────────────────────────────────────────────

test('Position: getByElection() returns created positions', function () use ($posSvc, $posElId, $validPos) {
    $posSvc->create($posElId, $validPos);
    $positions = $posSvc->getByElection($posElId);
    assert_true(count($positions) >= 1);
});

test('Position: getByElection() returns empty for unknown election', function () use ($posSvc) {
    $positions = $posSvc->getByElection(99999);
    assert_equals(0, count($positions));
});

// ── getById() ────────────────────────────────────────────────────────────────

test('Position: getById() returns null for missing ID', function () use ($posSvc) {
    assert_true($posSvc->getById(99999) === null);
});

// ── update() ─────────────────────────────────────────────────────────────────

test('Position: update() modifies position_name', function () use ($posSvc, $posElId, $validPos) {
    $id = $posSvc->create($posElId, $validPos);
    $posSvc->update($id, array_merge($validPos, ['position_name' => 'Vice President']));
    assert_equals('Vice President', $posSvc->getById($id)['position_name']);
});

test('Position: update() rejects missing position', function () use ($posSvc, $validPos) {
    assert_throws(fn() => $posSvc->update(99999, $validPos), 'not found');
});

// ── delete() ─────────────────────────────────────────────────────────────────

test('Position: delete() removes position on draft election', function () use ($posSvc, $posElId, $validPos) {
    $id = $posSvc->create($posElId, $validPos);
    $posSvc->delete($id);
    assert_true($posSvc->getById($id) === null);
});

test('Position: delete() rejects missing position', function () use ($posSvc) {
    assert_throws(fn() => $posSvc->delete(99999), 'not found');
});

test('Position: delete() rejects position on open election', function () use ($posSvc, $posElId, $validPos) {
    $id = $posSvc->create($posElId, $validPos);
    db()->exec("UPDATE block_elections SET status='open' WHERE id={$posElId}");
    assert_throws(fn() => $posSvc->delete($id), 'draft or configured');
    db()->exec("UPDATE block_elections SET status='draft' WHERE id={$posElId}"); // restore
});
