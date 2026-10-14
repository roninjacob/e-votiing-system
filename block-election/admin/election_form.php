<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/services/BlockElectionService.php';

require_role(['admin', 'adviser', 'committee']);

$svc      = new BlockElectionService();
$editId   = isset($_GET['id']) ? (int) $_GET['id'] : null;
$election = $editId ? $svc->getById($editId) : null;

if ($editId && !$election) { flash('danger', 'Election not found.'); redirect('/admin/elections.php'); }
if ($election && $election['status'] !== 'draft') {
    flash('warning', 'Only draft elections can be edited.');
    redirect('/admin/elections.php');
}

// Pull blocks from DB — same source as Manage Blocks
$blocks = db()->query(
    "SELECT id, section, year_level, semester, track FROM blocks WHERE block_name = 'BSIT' ORDER BY year_level, semester, track, section"
)->fetchAll();

$pageTitle = $editId ? 'Edit Election' : 'New Election';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header">
    <h2><?= $editId ? 'Edit Election' : 'New Election' ?></h2>
</div>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="dark-card">
            <div class="card-head">
                <i class="bi bi-<?= $editId ? 'pencil' : 'plus-lg' ?> me-1"></i>
                <?= $editId ? 'Edit Election Details' : 'Create Block Election' ?>
            </div>
            <div class="card-body-inner">
                <form method="post" action="<?= BASE_URL ?>/admin/election_action.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= $editId ? 'update' : 'create' ?>">
                    <?php if ($editId): ?>
                    <input type="hidden" name="id" value="<?= $editId ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label-dark">BSIT Block <span style="color:#e74c3c;">*</span></label>
                        <select name="block_id" class="form-control-dark" required>
                            <option value="">— Select Block —</option>
                            <?php
                            $counters = [];
                            foreach ($blocks as $b):
                                $key = $b['year_level'] . '-' . $b['semester'] . '-' . ($b['track'] ?? '');
                                $counters[$key] = ($counters[$key] ?? 0) + 1;
                                $num   = $counters[$key];
                                $label = 'Year ' . $b['year_level'] . ', Sem ' . $b['semester'] . ' — Block ' . $num;
                                if ($b['track']) $label .= ' (' . $b['track'] . ' Track)';
                            ?>
                            <option value="<?= $b['id'] ?>"
                                <?= ($election['block_id'] ?? '') == $b['id'] ? 'selected' : '' ?>>
                                BSIT <?= h($b['section']) ?> — <?= h($label) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label-dark">Title <span style="color:#e74c3c;">*</span></label>
                        <input type="text" name="title" class="form-control-dark" required maxlength="200"
                               value="<?= h($election['title'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label-dark">Description</label>
                        <textarea name="description" class="form-control-dark" rows="3"><?= h($election['description'] ?? '') ?></textarea>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label-dark">Start Date/Time <span style="color:#e74c3c;">*</span></label>
                            <input type="datetime-local" name="start_at" class="form-control-dark" required
                                   value="<?= h(isset($election['start_at']) ? date('Y-m-d\TH:i', strtotime($election['start_at'])) : '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-dark">End Date/Time <span style="color:#e74c3c;">*</span></label>
                            <input type="datetime-local" name="end_at" class="form-control-dark" required
                                   value="<?= h(isset($election['end_at']) ? date('Y-m-d\TH:i', strtotime($election['end_at'])) : '') ?>">
                        </div>
                    </div>

                    <div style="display:flex;gap:10px;">
                        <button type="submit" class="btn-gold">
                            <i class="bi bi-save"></i> <?= $editId ? 'Save Changes' : 'Create Election' ?>
                        </button>
                        <a href="<?= BASE_URL ?>/admin/elections.php" class="btn-ghost">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
