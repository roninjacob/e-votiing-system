<?php
// ── Module: BlockBallotService ───────────────────────────────────────────────

$ballotSvc = new BlockBallotService(db());
$elSvc3    = new BlockElectionService(db());
$posSvc3   = new BlockPositionService(db());
$candSvc3  = new BlockCandidateService(db());

$ballotElId  = $elSvc3->create([
    'block_id' => 1, 'title' => 'Ballot Test Election',
    'start_at' => '2025-01-01 08:00:00', 'end_at' => '2025-12-31 23:59:59',
], 1);
$ballotPosId = $posSvc3->create($ballotElId, ['position_name' => 'President', 'max_votes' => 1]);
$candSvc3->create($ballotPosId, ['full_name' => 'Candidate A', 'is_active' => 1]);
$candSvc3->create($ballotPosId, ['full_name' => 'Candidate B', 'is_active' => 1]);
$candSvc3->create($ballotPosId, ['full_name' => 'Inactive C',  'is_active' => 0]);

// ── buildBallot() ────────────────────────────────────────────────────────────

test('Ballot: buildBallot() returns positions array', function () use ($ballotSvc, $ballotElId) {
    $ballot = $ballotSvc->buildBallot($ballotElId);
    assert_true(count($ballot) >= 1);
});

test('Ballot: buildBallot() each item has position and candidates keys', function () use ($ballotSvc, $ballotElId) {
    $ballot = $ballotSvc->buildBallot($ballotElId);
    foreach ($ballot as $item) {
        assert_true(isset($item['position']),   'Missing position key');
        assert_true(isset($item['candidates']), 'Missing candidates key');
    }
});

test('Ballot: buildBallot() only includes active candidates', function () use ($ballotSvc, $ballotElId) {
    $ballot = $ballotSvc->buildBallot($ballotElId);
    foreach ($ballot as $item) {
        foreach ($item['candidates'] as $c) {
            assert_equals(1, (int) $c['is_active'], 'Inactive candidate in ballot');
        }
    }
});

test('Ballot: buildBallot() returns 2 active candidates for position', function () use ($ballotSvc, $ballotElId) {
    $ballot = $ballotSvc->buildBallot($ballotElId);
    // Count only active candidates across all positions
    $activeCounts = array_map(fn($item) => count($item['candidates']), $ballot);
    assert_true(array_sum($activeCounts) >= 2, 'Expected at least 2 active candidates');
});

test('Ballot: buildBallot() returns empty for unknown election', function () use ($ballotSvc) {
    $ballot = $ballotSvc->buildBallot(99999);
    assert_equals(0, count($ballot));
});

// ── hasVoted() ───────────────────────────────────────────────────────────────

test('Ballot: hasVoted() returns false when no vote exists', function () use ($ballotSvc, $ballotElId) {
    assert_false($ballotSvc->hasVoted($ballotElId, 2));
});

test('Ballot: hasVoted() returns true after confirmed vote inserted', function () use ($ballotSvc, $ballotElId) {
    db()->exec("INSERT INTO block_votes (election_id, voter_id, block_id, vote_ref, payload_hash, status)
                VALUES ({$ballotElId}, 2, 1, 'REF001', 'hash001', 'confirmed')");
    assert_true($ballotSvc->hasVoted($ballotElId, 2));
});

test('Ballot: hasVoted() returns true for pending vote', function () use ($ballotSvc, $ballotElId) {
    db()->exec("INSERT OR IGNORE INTO block_votes (election_id, voter_id, block_id, vote_ref, payload_hash, status)
                VALUES ({$ballotElId}, 3, 2, 'REF002', 'hash002', 'pending')");
    assert_true($ballotSvc->hasVoted($ballotElId, 3));
});

test('Ballot: hasVoted() returns false for failed vote status', function () use ($ballotSvc) {
    $elId = 9001;
    db()->exec("INSERT INTO block_elections (id, block_id, title, start_at, end_at, status, created_by)
                VALUES ({$elId}, 1, 'Temp', '2025-01-01', '2025-12-31', 'open', 1)");
    db()->exec("INSERT INTO block_votes (election_id, voter_id, block_id, vote_ref, payload_hash, status)
                VALUES ({$elId}, 2, 1, 'REF003', 'hash003', 'failed')");
    assert_false($ballotSvc->hasVoted($elId, 2));
});
