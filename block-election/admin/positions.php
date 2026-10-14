<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/services/BlockElectionService.php';
require_once dirname(__DIR__) . '/services/BlockPositionService.php';
require_once dirname(__DIR__) . '/services/BlockCandidateService.php';

require_role(['admin', 'adviser', 'committee']);

$electionId  = (int) ($_GET['election_id'] ?? 0);
$electionSvc = new BlockElectionService();
$posSvc      = new BlockPositionService();

$election = $electionSvc->getById($electionId);
if (!$election) { flash('danger', 'Election not found.'); redirect('/admin/elections.php'); }

$positions = $posSvc->getByElection($electionId);
$statusLog = $electionSvc->getStatusLog($electionId);
$user      = current_user();
$isDraft   = $election['status'] === 'draft';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $posAction = $_POST['pos_action'] ?? '';
    try {
        if ($posAction === 'add' && $isDraft) {
            $posSvc->create($electionId, [
                'position_name' => trim($_POST['position_name'] ?? ''),
                'max_votes'     => (int) ($_POST['max_votes'] ?? 1),
                'is_required'   => $_POST['is_required'] ?? null,
                'sort_order'    => (int) ($_POST['sort_order'] ?? 0),
            ]);
            flash('success', 'Position added.');
        } elseif ($posAction === 'delete' && $isDraft) {
            $posSvc->delete((int) ($_POST['position_id'] ?? 0));
            flash('success', 'Position deleted.');
        } elseif ($posAction === 'transition') {
            $next = $electionSvc->transition($electionId, $user['id'], trim($_POST['note'] ?? ''));
            flash('success', 'Status advanced to: ' . ucfirst($next));
        }
    } catch (RuntimeException $e) {
        flash('danger', $e->getMessage());
    }
    redirect('/admin/positions.php?election_id=' . $electionId);
}

function status_badge(string $status): string {
    $cls = ['draft'=>'badge-draft','configured'=>'badge-configured','open'=>'badge-open','closed'=>'badge-closed','published'=>'badge-published'];
    return '<span class="badge-status ' . ($cls[$status] ?? 'badge-draft') . '">' . h(ucfirst($status)) . '</span>';
}

$pageTitle = 'Positions — ' . $election['title'];
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header d-flex align-items-center gap-3">
    <a href="<?= BASE_URL ?>/admin/elections.php" class="btn-ghost" style="padding:7px 12px;">
        <i class="bi bi-arrow-left"></i>
    </a>
    <div>
        <h2 style="margin-bottom:4px;"><?= h($election['title']) ?></h2>
        <div class="election-status-bar">
            <?= status_badge($election['status']) ?>
            <span style="color:var(--text-muted);">BSIT <?= h($election['section'] ?? '') ?></span>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">

        <!-- Status Advance -->
        <?php if (in_array($election['status'], ['draft','configured','open','closed'], true)): ?>
        <div class="dark-card mb-3">
            <div class="card-body-inner d-flex align-items-center gap-3">
                <span style="color:var(--text-muted);font-size:.82rem;">Advance status:</span>
                <form method="post" style="display:flex;gap:8px;align-items:center;" onsubmit="return confirm('Advance election status?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="pos_action" value="transition">
                    <input type="text" name="note" class="form-control-dark" style="width:200px;"
                           placeholder="Optional note">
                    <button class="btn-gold" style="padding:7px 16px;">
                        <i class="bi bi-arrow-right-circle"></i> Advance
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Positions Table -->
        <div class="dark-card mb-3">
            <div class="card-head">Positions</div>
            <div style="overflow-x:auto;">
                <table class="dark-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Position</th>
                            <th>Required</th>
                            <th>Candidates</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($positions)): ?>
                        <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:24px;">No positions yet.</td></tr>
                    <?php else: foreach ($positions as $pos): ?>
                        <tr>
                            <td style="color:var(--text-muted);"><?= $pos['sort_order'] ?></td>
                            <td style="font-weight:600;"><?= h($pos['position_name']) ?></td>
                            <td><?= $pos['is_required'] ? '<i class="bi bi-check-circle-fill" style="color:#2ecc71;"></i>' : '<i class="bi bi-dash" style="color:var(--text-muted);"></i>' ?></td>
                            <td>
                                <a href="<?= BASE_URL ?>/admin/candidates.php?position_id=<?= $pos['id'] ?>&election_id=<?= $electionId ?>"
                                   class="badge-status badge-open" style="text-decoration:none;">
                                    <?= $pos['active_candidates'] ?> active
                                </a>
                            </td>
                            <td style="display:flex;gap:6px;">
                                <a href="<?= BASE_URL ?>/admin/candidates.php?position_id=<?= $pos['id'] ?>&election_id=<?= $electionId ?>"
                                   class="btn-sm-icon" title="Candidates"><i class="bi bi-people"></i></a>
                                <?php if ($isDraft): ?>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete position and all candidates?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="pos_action" value="delete">
                                    <input type="hidden" name="position_id" value="<?= $pos['id'] ?>">
                                    <button class="btn-sm-icon danger"><i class="bi bi-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add Position -->
        <?php if ($isDraft): ?>
        <div class="dark-card">
            <div class="card-head"><i class="bi bi-plus-lg me-1"></i>Add Position</div>
            <div class="card-body-inner">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="pos_action" value="add">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label-dark">Position Name <span style="color:#e74c3c;">*</span></label>
                            <input type="text" name="position_name" class="form-control-dark"
                                   placeholder="e.g. President, Secretary" required maxlength="100">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-dark">Ballot Order
                                <span style="color:var(--text-muted);font-size:.7rem;font-weight:400;"> (1=first on ballot)</span>
                            </label>
                            <input type="number" name="sort_order" class="form-control-dark"
                                   placeholder="1" value="<?= count($positions) + 1 ?>" min="1">
                            <input type="hidden" name="max_votes" value="1">
                        </div>
                        <div class="col-md-2 d-flex align-items-end gap-2 pb-1">
                            <input type="checkbox" name="is_required" id="isReq" checked
                                   style="accent-color:var(--gold);width:16px;height:16px;">
                            <label for="isReq" style="color:var(--text-muted);font-size:.82rem;">Required</label>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button class="btn-gold w-100" style="padding:9px;">Add</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Status History Sidebar -->
    <div class="col-lg-4">
        <div class="dark-card">
            <div class="card-head"><i class="bi bi-clock-history me-1"></i>Status History</div>
            <div class="card-body-inner" style="padding:0;">
                <?php if (empty($statusLog)): ?>
                    <p style="color:var(--text-muted);font-size:.82rem;padding:16px;">No transitions yet.</p>
                <?php else: foreach ($statusLog as $log): ?>
                    <div class="feed-item" style="padding:12px 16px;">
                        <div class="feed-dot gold"></div>
                        <div>
                            <div style="font-size:.82rem;">
                                <span class="badge-status badge-draft"><?= h($log['from_status']) ?></span>
                                <i class="bi bi-arrow-right mx-1" style="color:var(--text-muted);"></i>
                                <span class="badge-status badge-open"><?= h($log['to_status']) ?></span>
                            </div>
                            <div class="feed-time mt-1">
                                <?= h($log['changed_by_name'] ?? 'System') ?> —
                                <?= h(date('M d, Y H:i', strtotime($log['changed_at']))) ?>
                            </div>
                            <?php if ($log['note']): ?>
                            <div style="font-size:.75rem;color:var(--text-muted);font-style:italic;"><?= h($log['note']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
