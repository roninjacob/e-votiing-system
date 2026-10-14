<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/services/BlockEligibilityService.php';
require_once dirname(__DIR__) . '/services/BlockBallotService.php';

require_login();

$user      = current_user();
$eligSvc   = new BlockEligibilityService();
$ballotSvc = new BlockBallotService();
$elections = $eligSvc->getVisibleElections((int) $user['block_id']);

foreach ($elections as &$el) {
    $el['has_voted'] = $ballotSvc->hasVoted((int) $el['id'], (int) $user['id']);
}
unset($el);

function status_badge(string $status): string {
    $cls = ['draft'=>'badge-draft','configured'=>'badge-configured','open'=>'badge-open','closed'=>'badge-closed','published'=>'badge-published'];
    return '<span class="badge-status ' . ($cls[$status] ?? 'badge-draft') . '">' . h(ucfirst($status)) . '</span>';
}

$pageTitle = 'My Ballot';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h2>My Block Elections</h2>
    <div class="election-status-bar">
        <span style="color:var(--text-muted);">BSIT <?= h($user['block_id'] ? '' : '') ?> — Elections available to your block</span>
    </div>
</div>

<?php if (empty($elections)): ?>
<div class="dark-card">
    <div class="card-body-inner" style="text-align:center;padding:40px;color:var(--text-muted);">
        <i class="bi bi-inbox" style="font-size:2.5rem;display:block;margin-bottom:10px;"></i>
        No elections are currently available for your block.
    </div>
</div>
<?php else: ?>
<div class="row g-3">
    <?php foreach ($elections as $el): ?>
    <div class="col-md-6 col-lg-4">
        <div class="dark-card h-100">
            <div class="card-head">
                <?= h($el['title']) ?>
                <?= status_badge($el['status']) ?>
            </div>
            <div class="card-body-inner">
                <?php if ($el['description']): ?>
                <p style="font-size:.82rem;color:var(--text-muted);margin-bottom:12px;"><?= h($el['description']) ?></p>
                <?php endif; ?>
                <div style="font-size:.78rem;color:var(--text-muted);margin-bottom:16px;">
                    <i class="bi bi-calendar me-1"></i>
                    <?= h(date('M d', strtotime($el['start_at']))) ?> –
                    <?= h(date('M d, Y', strtotime($el['end_at']))) ?>
                </div>

                <?php if ($el['has_voted']): ?>
                    <div style="display:flex;align-items:center;gap:8px;color:#2ecc71;font-weight:700;font-size:.85rem;">
                        <i class="bi bi-check-circle-fill"></i> Vote Submitted
                    </div>
                <?php elseif ($el['status'] === 'open'): ?>
                    <a href="<?= BASE_URL ?>/voter/ballot.php?election_id=<?= $el['id'] ?>"
                       class="btn-gold w-100 justify-content-center" style="padding:10px;">
                        <i class="bi bi-ballot"></i> Vote Now
                    </a>
                <?php else: ?>
                    <span style="color:var(--text-muted);font-size:.82rem;">Voting not open.</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
