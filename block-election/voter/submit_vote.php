<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/services/BlockElectionService.php';
require_once dirname(__DIR__) . '/services/BlockEligibilityService.php';
require_once dirname(__DIR__) . '/services/BlockBallotService.php';
require_once dirname(__DIR__) . '/services/BlockPositionService.php';
require_once dirname(__DIR__) . '/services/BlockCandidateService.php';
require_once dirname(__DIR__) . '/services/Group4Client.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/voter/elections.php');
}

csrf_check();

$user        = current_user();
$electionId  = (int) ($_POST['election_id'] ?? 0);
$electionSvc = new BlockElectionService();
$eligSvc     = new BlockEligibilityService();
$ballotSvc   = new BlockBallotService();
$posSvc      = new BlockPositionService();
$candSvc     = new BlockCandidateService();
$g4          = new Group4Client();

$election = $electionSvc->getById($electionId);

// ─── Guards ───────────────────────────────────────────────────────────────────
if (!$election || $election['status'] !== 'open') {
    flash('danger', 'This election is not open for voting.');
    redirect('/voter/elections.php');
}

if (!$eligSvc->isEligible((int) $user['id'], $electionId)) {
    flash('danger', 'You are not eligible to vote in this election.');
    redirect('/voter/elections.php');
}

if ($ballotSvc->hasVoted($electionId, (int) $user['id'])) {
    flash('warning', 'You have already voted in this election.');
    redirect('/voter/elections.php');
}

// ─── Validate Selections ──────────────────────────────────────────────────────
$rawVotes  = $_POST['votes'] ?? [];
$positions = $posSvc->getByElection($electionId);
$errors    = [];
$selections = []; // position_id => [candidate_id, ...]

foreach ($positions as $pos) {
    $posId    = (int) $pos['id'];
    $chosen   = array_map('intval', (array) ($rawVotes[$posId] ?? []));
    $maxVotes = (int) $pos['max_votes'];

    if ($pos['is_required'] && empty($chosen)) {
        $errors[] = $pos['position_name'] . ' is required.';
        continue;
    }

    if (count($chosen) > $maxVotes) {
        $errors[] = $pos['position_name'] . ': you selected too many candidates (max ' . $maxVotes . ').';
        continue;
    }

    // Verify each candidate ID actually belongs to this position and is active
    foreach ($chosen as $candId) {
        $cand = $candSvc->getById($candId);
        if (!$cand || (int) $cand['position_id'] !== $posId || !$cand['is_active']) {
            $errors[] = 'Invalid candidate selection for ' . $pos['position_name'] . '.';
            break;
        }
    }

    $selections[$posId] = $chosen;
}

if (!empty($errors)) {
    foreach ($errors as $err) flash('danger', $err);
    redirect('/voter/ballot.php?election_id=' . $electionId);
}

// ─── Build payload for Group 4 ────────────────────────────────────────────────
// Group 3 never stores vote content — only the reference and a hash
$payload = [
    'election_id' => $electionId,
    'voter_id'    => $user['id'],
    'block_id'    => $user['block_id'],
    'selections'  => $selections,
    'submitted_at'=> date('c'),
];

$payloadHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

// ─── Submit to Group 4 ────────────────────────────────────────────────────────
$result = $g4->submitVote($payload);

if (!($result['success'] ?? false)) {
    flash('danger', 'Vote submission failed: ' . ($result['message'] ?? 'Unknown error.'));
    redirect('/voter/ballot.php?election_id=' . $electionId);
}

// ─── Record vote reference in Group 3's tracking table ───────────────────────
// INSERT IGNORE protects against race condition on double-submit
$stmt = db()->prepare(
    'INSERT IGNORE INTO block_votes
     (election_id, voter_id, block_id, vote_ref, payload_hash, group4_ref, status)
     VALUES (?, ?, ?, ?, ?, ?, \'confirmed\')'
);
$stmt->execute([
    $electionId,
    $user['id'],
    $user['block_id'],
    $result['vote_ref'],
    $payloadHash,
    $result['group4_ref'] ?? null,
]);

if ($stmt->rowCount() === 0) {
    flash('warning', 'Your vote was already recorded.');
    redirect('/voter/elections.php');
}

flash('success', 'Your vote has been submitted successfully! Reference: ' . $result['vote_ref']);
redirect('/voter/elections.php');
