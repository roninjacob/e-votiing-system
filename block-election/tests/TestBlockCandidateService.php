<?php
// ── Module: BlockCandidateService ────────────────────────────────────────────

$candSvc = new BlockCandidateService(db());
$elSvc2  = new BlockElectionService(db());
$posSvc2 = new BlockPositionService(db());

$candElId  = $elSvc2->create([
    'block_id' => 1, 'title' => 'Candidate Test Election',
    'start_at' => '2025-01-01 08:00:00', 'end_at' => '2025-12-31 23:59:59',
], 1);
$candPosId = $posSvc2->create($candElId, ['position_name' => 'President', 'max_votes' => 1]);

$validCand = ['full_name' => 'Juan dela Cruz', 'party' => 'Unity', 'platform' => 'For the students.', 'is_active' => 1];

// ── create() ─────────────────────────────────────────────────────────────────

test('Candidate: create() returns new ID', function () use ($candSvc, $candPosId, $validCand) {
    $id = $candSvc->create($candPosId, $validCand);
    assert_true($id > 0);
});

test('Candidate: create() stores full_name correctly', function () use ($candSvc, $candPosId, $validCand) {
    $id   = $candSvc->create($candPosId, $validCand);
    $cand = $candSvc->getById($id);
    assert_equals('Juan dela Cruz', $cand['full_name']);
});

test('Candidate: create() with no photo sets photo_path null', function () use ($candSvc, $candPosId, $validCand) {
    $id   = $candSvc->create($candPosId, $validCand, null);
    $cand = $candSvc->getById($id);
    assert_true($cand['photo_path'] === null);
});

// ── getByPosition() ──────────────────────────────────────────────────────────

test('Candidate: getByPosition() returns candidates', function () use ($candSvc, $candPosId, $validCand) {
    $candSvc->create($candPosId, $validCand);
    $list = $candSvc->getByPosition($candPosId);
    assert_true(count($list) >= 1);
});

test('Candidate: getActiveByPosition() excludes inactive', function () use ($candSvc, $candPosId) {
    $id = $candSvc->create($candPosId, ['full_name' => 'Inactive Cand 2', 'is_active' => 0]);
    $active = $candSvc->getActiveByPosition($candPosId);
    foreach ($active as $c) {
        assert_equals(1, (int) $c['is_active'], 'Inactive candidate should not appear');
    }
});

// ── getById() ────────────────────────────────────────────────────────────────

test('Candidate: getById() returns null for missing ID', function () use ($candSvc) {
    assert_true($candSvc->getById(99999) === null);
});

// ── update() ─────────────────────────────────────────────────────────────────

test('Candidate: update() modifies full_name', function () use ($candSvc, $candPosId, $validCand) {
    $id = $candSvc->create($candPosId, $validCand);
    $candSvc->update($id, array_merge($validCand, ['full_name' => 'Maria Santos']));
    assert_equals('Maria Santos', $candSvc->getById($id)['full_name']);
});

test('Candidate: update() rejects missing candidate', function () use ($candSvc, $validCand) {
    assert_throws(fn() => $candSvc->update(99999, $validCand), 'not found');
});

test('Candidate: update() keeps existing photo when no new upload', function () use ($candSvc, $candPosId, $validCand) {
    $id = $candSvc->create($candPosId, $validCand);
    db()->exec("UPDATE block_candidates SET photo_path='uploads/candidates/test.jpg' WHERE id={$id}");
    $candSvc->update($id, $validCand, null);
    assert_equals('uploads/candidates/test.jpg', $candSvc->getById($id)['photo_path']);
});

// ── toggleActive() ───────────────────────────────────────────────────────────

test('Candidate: toggleActive() flips is_active from 1 to 0', function () use ($candSvc, $candPosId, $validCand) {
    $id = $candSvc->create($candPosId, array_merge($validCand, ['is_active' => 1]));
    $candSvc->toggleActive($id);
    assert_equals(0, (int) $candSvc->getById($id)['is_active']);
});

test('Candidate: toggleActive() flips is_active from 0 to 1', function () use ($candSvc, $candPosId) {
    $id = $candSvc->create($candPosId, ['full_name' => 'Toggle Test Zero Unique', 'is_active' => 0]);
    $before = (int) $candSvc->getById($id)['is_active'];
    $candSvc->toggleActive($id);
    $after = (int) $candSvc->getById($id)['is_active'];
    assert_true($after !== $before, 'is_active should have changed');
    assert_equals(1, $after, 'Should be 1 after toggling from 0');
});

// ── delete() ─────────────────────────────────────────────────────────────────

test('Candidate: delete() removes candidate', function () use ($candSvc, $candPosId, $validCand) {
    $id = $candSvc->create($candPosId, $validCand);
    $candSvc->delete($id);
    assert_true($candSvc->getById($id) === null);
});
