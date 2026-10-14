<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/services/BlockPositionService.php';
require_once dirname(__DIR__) . '/services/BlockCandidateService.php';

require_role(['admin', 'adviser', 'committee']);

$positionId = (int) ($_GET['position_id'] ?? 0);
$electionId = (int) ($_GET['election_id'] ?? 0);
$editId     = isset($_GET['id']) ? (int) $_GET['id'] : null;

$posSvc    = new BlockPositionService();
$candSvc   = new BlockCandidateService();
$position  = $posSvc->getById($positionId);
$candidate = $editId ? $candSvc->getById($editId) : null;

if (!$position) { flash('danger', 'Position not found.'); redirect('/admin/elections.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $data = [
        'full_name' => trim($_POST['full_name'] ?? ''),
        'party'     => trim($_POST['party']     ?? ''),
        'platform'  => trim($_POST['platform']  ?? ''),
        'is_active' => $_POST['is_active'] ?? null,
    ];
    $file = ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE ? $_FILES['photo'] : null;

    try {
        if ($editId) {
            $candSvc->update($editId, $data, $file);
            flash('success', 'Candidate updated.');
        } else {
            $candSvc->create($positionId, $data, $file);
            flash('success', 'Candidate added.');
        }
        redirect('/admin/candidates.php?position_id=' . $positionId . '&election_id=' . $electionId);
    } catch (RuntimeException $e) {
        flash('danger', $e->getMessage());
    }
}

$pageTitle = ($editId ? 'Edit' : 'Add') . ' Candidate';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header d-flex align-items-center gap-3">
    <a href="<?= BASE_URL ?>/admin/candidates.php?position_id=<?= $positionId ?>&election_id=<?= $electionId ?>"
       class="btn-ghost" style="padding:7px 12px;"><i class="bi bi-arrow-left"></i></a>
    <div>
        <h2><?= $editId ? 'Edit Candidate' : 'Add Candidate' ?></h2>
        <div class="election-status-bar">
            <span style="color:var(--text-muted);"><?= h($position['position_name']) ?></span>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="dark-card">
            <div class="card-head">
                <i class="bi bi-person-<?= $editId ? 'gear' : 'plus' ?> me-1"></i>
                <?= $editId ? 'Edit Candidate Details' : 'New Candidate' ?>
            </div>
            <div class="card-body-inner">
                <form method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label-dark">Full Name <span style="color:#e74c3c;">*</span></label>
                        <input type="text" name="full_name" class="form-control-dark" required maxlength="150"
                               value="<?= h($candidate['full_name'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label-dark">Party / Affiliation</label>
                        <input type="text" name="party" class="form-control-dark" maxlength="100"
                               value="<?= h($candidate['party'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label-dark">Platform</label>
                        <textarea name="platform" class="form-control-dark" rows="3"><?= h($candidate['platform'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label-dark">Photo <span style="color:var(--text-muted);font-weight:400;text-transform:none;">(JPG/PNG/WebP, max 2MB)</span></label>
                        <?php if (!empty($candidate['photo_path'])): ?>
                        <div style="margin-bottom:10px;">
                            <img src="<?= BASE_URL ?>/<?= h($candidate['photo_path']) ?>"
                                 style="width:72px;height:72px;object-fit:cover;border-radius:50%;border:2px solid var(--gold);"
                                 alt="Current photo">
                        </div>
                        <?php endif; ?>
                        <input type="file" name="photo" class="form-control-dark"
                               accept="image/jpeg,image/png,image/webp"
                               style="padding:7px;">
                    </div>

                    <div class="mb-4 d-flex align-items-center gap-2">
                        <input type="checkbox" name="is_active" id="isActive"
                               <?= ($candidate['is_active'] ?? 1) ? 'checked' : '' ?>
                               style="accent-color:var(--gold);width:16px;height:16px;">
                        <label for="isActive" style="color:var(--text-muted);font-size:.85rem;">Active (visible on ballot)</label>
                    </div>

                    <div style="display:flex;gap:10px;">
                        <button type="submit" class="btn-gold">
                            <i class="bi bi-save"></i> <?= $editId ? 'Save Changes' : 'Add Candidate' ?>
                        </button>
                        <a href="<?= BASE_URL ?>/admin/candidates.php?position_id=<?= $positionId ?>&election_id=<?= $electionId ?>"
                           class="btn-ghost">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
