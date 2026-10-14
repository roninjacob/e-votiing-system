<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/services/BlockElectionService.php';
require_once dirname(__DIR__) . '/services/BlockEligibilityService.php';
require_once dirname(__DIR__) . '/services/BlockBallotService.php';

require_login();

$user        = current_user();
$electionId  = (int) ($_GET['election_id'] ?? 0);
$electionSvc = new BlockElectionService();
$eligSvc     = new BlockEligibilityService();
$ballotSvc   = new BlockBallotService();

$election = $electionSvc->getById($electionId);

if (!$election) { flash('danger', 'Election not found.'); redirect('/voter/elections.php'); }
if (!$eligSvc->isEligible((int) $user['id'], $electionId)) {
    flash('danger', 'You are not eligible to vote in this election.');
    redirect('/voter/elections.php');
}
if ($ballotSvc->hasVoted($electionId, (int) $user['id'])) {
    flash('info', 'You have already submitted your vote.');
    redirect('/voter/elections.php');
}

$ballot    = $ballotSvc->buildBallot($electionId, randomize: true);
$pageTitle = 'My Ballot';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2>My Ballot</h2>
        <div class="election-status-bar">
            <div class="status-dot"></div>
            <span class="status-label"><?= h($election['title']) ?></span>
        </div>
    </div>
    <button type="button" id="btnConfirm" class="btn-gold">
        <i class="bi bi-send"></i> Submit Ballot
    </button>
</div>

<form id="ballotForm" method="post" action="<?= BASE_URL ?>/voter/submit_vote.php">
    <?= csrf_field() ?>
    <input type="hidden" name="election_id" value="<?= $electionId ?>">

    <?php foreach ($ballot as $item):
        $pos   = $item['position'];
        $cands = $item['candidates'];
    ?>
    <div class="dark-card mb-4">
        <div class="card-head">
            <?= h($pos['position_name']) ?>
            <span style="font-size:.72rem;color:var(--text-muted);font-weight:400;text-transform:none;">
                Select up to <?= $pos['max_votes'] ?>
                <?= $pos['is_required'] ? '<span style="color:#e74c3c;">*</span>' : '' ?>
            </span>
        </div>
        <div class="card-body-inner">
            <?php if (empty($cands)): ?>
                <p style="color:var(--text-muted);">No candidates for this position.</p>
            <?php else: ?>
            <div class="row g-3 candidate-group"
                 data-max="<?= $pos['max_votes'] ?>"
                 data-required="<?= $pos['is_required'] ? '1' : '0' ?>"
                 data-position="<?= h($pos['position_name']) ?>">
                <?php foreach ($cands as $c): ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <label class="candidate-card w-100 h-100 text-center p-3 d-block" data-id="<?= $c['id'] ?>">
                        <input type="checkbox"
                               name="votes[<?= $pos['id'] ?>][]"
                               value="<?= $c['id'] ?>"
                               class="visually-hidden candidate-checkbox">
                        <?php if ($c['photo_path']): ?>
                        <img src="<?= BASE_URL ?>/<?= h($c['photo_path']) ?>"
                             class="rounded-circle mb-2 candidate-photo-sm" alt="<?= h($c['full_name']) ?>">
                        <?php else: ?>
                        <div class="mb-2">
                            <i class="bi bi-person-circle" style="font-size:2.5rem;color:var(--text-muted);"></i>
                        </div>
                        <?php endif; ?>
                        <div style="font-size:.85rem;font-weight:700;color:#fff;" class="fw-semibold"><?= h($c['full_name']) ?></div>
                        <?php if ($c['party']): ?>
                        <div style="font-size:.72rem;color:var(--gold);"><?= h($c['party']) ?></div>
                        <?php endif; ?>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="text-end mb-4">
        <button type="button" id="btnConfirmBottom" class="btn-gold" style="padding:12px 32px;font-size:.95rem;">
            <i class="bi bi-send"></i> Submit Ballot
        </button>
    </div>
</form>

<!-- Confirm Modal — matches reference UI -->
<div class="modal fade modal-dark" id="confirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Your Votes!</h5>
            </div>
            <div class="modal-body">
                <p style="font-size:.82rem;color:var(--text-muted);">
                    Are you sure you want to finalize your selections?<br>
                    Changes cannot be made after submission.
                </p>
                <div id="confirmSummary"></div>
            </div>
            <div class="modal-footer flex-column gap-2">
                <button type="button" id="btnSubmit" class="btn-red w-100 justify-content-center" style="padding:12px;">
                    Yes, Submit Votes
                </button>
                <button type="button" class="btn-ghost w-100 justify-content-center" data-bs-dismiss="modal" style="padding:11px;">
                    Back to Ballot
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Wire both submit buttons to the same confirm action
document.getElementById('btnConfirmBottom')?.addEventListener('click', () =>
    document.getElementById('btnConfirm').click()
);
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
