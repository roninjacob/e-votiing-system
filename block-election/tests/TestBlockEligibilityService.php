<?php
// ── Module: BlockEligibilityService ─────────────────────────────────────────

$eligSvc = new BlockEligibilityService(db());
$elSvc4  = new BlockElectionService(db());

// Election for block 1 (voter ID 2 is in block 1, verified, active)
$eligElId = $elSvc4->create([
    'block_id' => 1, 'title' => 'Eligibility Test Election',
    'start_at' => '2025-01-01 08:00:00', 'end_at' => '2025-12-31 23:59:59',
], 1);

// Force status to 'open' for eligibility tests
db()->exec("UPDATE block_elections SET status='open' WHERE id={$eligElId}");

// ── isEligible() ─────────────────────────────────────────────────────────────

test('Eligibility: eligible voter in correct block returns true', function () use ($eligSvc, $eligElId) {
    // voter ID 2: block_id=1, is_verified=1, is_active=1
    assert_true($eligSvc->isEligible(2, $eligElId));
});

test('Eligibility: voter in wrong block returns false', function () use ($eligSvc, $eligElId) {
    // voter ID 3: block_id=2, election is for block 1
    assert_false($eligSvc->isEligible(3, $eligElId));
});

test('Eligibility: unverified voter returns false', function () use ($eligSvc, $eligElId) {
    // voter ID 4: is_verified=0
    assert_false($eligSvc->isEligible(4, $eligElId));
});

test('Eligibility: inactive voter returns false', function () use ($eligSvc, $eligElId) {
    // voter ID 5: is_active=0
    assert_false($eligSvc->isEligible(5, $eligElId));
});

test('Eligibility: returns false for non-open election', function () use ($eligSvc, $elSvc4) {
    $draftElId = $elSvc4->create([
        'block_id' => 1, 'title' => 'Draft Elig Election',
        'start_at' => '2025-01-01 08:00:00', 'end_at' => '2025-12-31 23:59:59',
    ], 1); // stays draft
    assert_false($eligSvc->isEligible(2, $draftElId));
});

test('Eligibility: returns false for non-existent voter', function () use ($eligSvc, $eligElId) {
    assert_false($eligSvc->isEligible(99999, $eligElId));
});

test('Eligibility: returns false for non-existent election', function () use ($eligSvc) {
    assert_false($eligSvc->isEligible(2, 99999));
});

// ── getVisibleElections() ────────────────────────────────────────────────────

test('Eligibility: getVisibleElections() returns open election for correct block', function () use ($eligSvc, $eligElId) {
    $elections = $eligSvc->getVisibleElections(1);
    $ids = array_column($elections, 'id');
    assert_true(in_array($eligElId, $ids), 'Open election should be visible');
});

test('Eligibility: getVisibleElections() excludes draft elections', function () use ($eligSvc, $elSvc4) {
    $draftId = $elSvc4->create([
        'block_id' => 1, 'title' => 'Hidden Draft',
        'start_at' => '2025-01-01 08:00:00', 'end_at' => '2025-12-31 23:59:59',
    ], 1);
    $elections = $eligSvc->getVisibleElections(1);
    $ids = array_column($elections, 'id');
    assert_false(in_array($draftId, $ids), 'Draft election should not be visible');
});

test('Eligibility: getVisibleElections() returns empty for unknown block', function () use ($eligSvc) {
    $elections = $eligSvc->getVisibleElections(99999);
    assert_equals(0, count($elections));
});

// ── Cache behaviour ──────────────────────────────────────────────────────────

test('Eligibility: second isEligible() call uses cache (no exception)', function () use ($eligSvc, $eligElId) {
    // First call populates cache, second should hit it silently
    $eligSvc->isEligible(2, $eligElId);
    $result = $eligSvc->isEligible(2, $eligElId);
    assert_true($result);
});
