<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/services/BlockElectionService.php';
require_once dirname(__DIR__) . '/services/BlockPositionService.php';
require_once dirname(__DIR__) . '/services/BlockCandidateService.php';

require_role(['admin', 'adviser', 'committee']);

$positionId  = (int) ($_GET['position_id'] ?? 0);
$electionId  = (int) ($_GET['election_id'] ?? 0);
$posSvc      = new BlockPositionService();
$candSvc     = new BlockCandidateService();
$electionSvc = new BlockElectionService();

$position = $posSvc->getById($positionId);
$election = $electionSvc->getById($electionId);

if (!$position || !$election) { flash('danger', 'Not found.'); redirect('/admin/elections.php'); }

$candidates = $candSvc->getByPosition($positionId);
$isDraft    = $election['status'] === 'draft';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $candAction = $_POST['cand_action'] ?? '';
    try {
        if ($candAction === 'toggle') {
            $candSvc->toggleActive((int) ($_POST['candidate_id'] ?? 0));
            flash('success', 'Candidate status toggled.');
        } elseif ($candAction === 'delete' && $isDraft) {
            $candSvc->delete((int) ($_POST['candidate_id'] ?? 0));
            flash('success', 'Candidate deleted.');
        }
    } catch (RuntimeException $e) {
        flash('danger', $e->getMessage());
    }
    redirect('/admin/candidates.php?position_id=' . $positionId . '&election_id=' . $electionId);
}

$pageTitle = 'Candidates — ' . $position['position_name'];
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header d-flex align-items-center gap-3">
    <a href="<?= BASE_URL ?>/admin/positions.php?election_id=<?= $electionId ?>" class="btn-ghost" style="padding:7px 12px;">
        <i class="bi bi-arrow-left"></i>
    </a>
    <div>
        <h2 style="margin-bottom:4px;"><?= h($position['position_name']) ?></h2>
        <div class="election-status-bar">
            <span style="color:var(--text-muted);"><?= h($election['title']) ?></span>
        </div>
    </div>
    <a href="<?= BASE_URL ?>/admin/candidate_form.php?position_id=<?= $positionId ?>&election_id=<?= $electionId ?>"
       class="btn-gold ms-auto">
        <i class="bi bi-person-plus"></i> Add Candidate
    </a>
</div>

<div class="row g-3">
<?php if (empty($candidates)): ?>
    <div class="col-12">
        <div class="dark-card">
            <div class="card-body-inner" style="text-align:center;padding:40px;color:var(--text-muted);">
                <i class="bi bi-people" style="font-size:2.5rem;display:block;margin-bottom:10px;"></i>
                No candidates yet. Add one above.
            </div>
        </div>
    </div>
<?php else: foreach ($candidates as $c): ?>
    <div class="col-md-4 col-lg-3">
        <div class="dark-card h-100" style="<?= $c['is_active'] ? '' : 'opacity:.5;' ?>">
            <?php if ($c['photo_path']): ?>
            <img src="<?= BASE_URL ?>/<?= h($c['photo_path']) ?>"
                 style="width:100%;height:140px;object-fit:cover;" alt="<?= h($c['full_name']) ?>">
            <?php else: ?>
            <div style="height:140px;background:rgba(255,255,255,0.05);display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-person-circle" style="font-size:3rem;color:var(--text-muted);"></i>
            </div>
            <?php endif; ?>
            <div style="padding:12px;">
                <div style="font-weight:700;font-size:.9rem;color:#fff;"><?= h($c['full_name']) ?></div>
                <?php if ($c['party']): ?>
                <div style="font-size:.75rem;color:var(--gold);"><?= h($c['party']) ?></div>
                <?php endif; ?>
                <span class="badge-status <?= $c['is_active'] ? 'badge-open' : 'badge-draft' ?>" style="margin-top:6px;display:inline-block;">
                    <?= $c['is_active'] ? 'Active' : 'Inactive' ?>
                </span>
            </div>
            <div style="padding:8px 12px;border-top:1px solid var(--card-border);display:flex;gap:6px;">
                <form method="post" style="flex:1;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="cand_action" value="toggle">
                    <input type="hidden" name="candidate_id" value="<?= $c['id'] ?>">
                    <button class="btn-ghost w-100" style="padding:6px;font-size:.75rem;justify-content:center;">
                        <?= $c['is_active'] ? 'Deactivate' : 'Activate' ?>
                    </button>
                </form>
                <?php if ($isDraft): ?>
                <a href="<?= BASE_URL ?>/admin/candidate_form.php?id=<?= $c['id'] ?>&position_id=<?= $positionId ?>&election_id=<?= $electionId ?>"
                   class="btn-sm-icon"><i class="bi bi-pencil"></i></a>
                <form method="post" style="display:inline;" onsubmit="return confirm('Delete candidate?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="cand_action" value="delete">
                    <input type="hidden" name="candidate_id" value="<?= $c['id'] ?>">
                    <button class="btn-sm-icon danger"><i class="bi bi-trash"></i></button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endforeach; endif; ?>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
