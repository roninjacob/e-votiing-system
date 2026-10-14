<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/services/BlockElectionService.php';

require_role(['admin', 'adviser', 'committee']);

$svc       = new BlockElectionService();
$elections = $svc->getAll();

function status_badge(string $status): string {
    $cls = ['draft'=>'badge-draft','configured'=>'badge-configured','open'=>'badge-open','closed'=>'badge-closed','published'=>'badge-published'];
    return '<span class="badge-status ' . ($cls[$status] ?? 'badge-draft') . '">' . h(ucfirst($status)) . '</span>';
}

$pageTitle = 'Manage Elections';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2>Manage Elections</h2>
        <div class="election-status-bar">
            <span style="color:var(--text-muted);">All block elections</span>
        </div>
    </div>
    <a href="<?= BASE_URL ?>/admin/election_form.php" class="btn-gold">
        <i class="bi bi-plus-lg"></i> New Election
    </a>
</div>

<div class="dark-card">
    <div style="overflow-x:auto;">
        <table class="dark-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Title</th>
                    <th>Block</th>
                    <th>Status</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($elections)): ?>
                <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:32px;">No elections found.</td></tr>
            <?php else: foreach ($elections as $el): ?>
                <tr>
                    <td style="color:var(--text-muted);"><?= $el['id'] ?></td>
                    <td style="font-weight:600;"><?= h($el['title']) ?></td>
                    <td style="color:var(--text-muted);">BSIT <?= h($el['section'] ?? '') ?></td>
                    <td><?= status_badge($el['status']) ?></td>
                    <td style="color:var(--text-muted);font-size:.78rem;"><?= h(date('M d, Y', strtotime($el['start_at']))) ?></td>
                    <td style="color:var(--text-muted);font-size:.78rem;"><?= h(date('M d, Y', strtotime($el['end_at']))) ?></td>
                    <td>
                        <div style="display:flex;gap:6px;align-items:center;">
                            <a href="<?= BASE_URL ?>/admin/positions.php?election_id=<?= $el['id'] ?>"
                               class="btn-sm-icon" title="Manage Positions"><i class="bi bi-diagram-3"></i></a>

                            <?php if ($el['status'] === 'draft'): ?>
                            <a href="<?= BASE_URL ?>/admin/election_form.php?id=<?= $el['id'] ?>"
                               class="btn-sm-icon" title="Edit"><i class="bi bi-pencil"></i></a>
                            <?php endif; ?>

                            <button class="btn-sm-icon danger" title="Delete"
                                    onclick="openDelModal(<?= $el['id'] ?>, '<?= h(addslashes($el['title'])) ?>')"><i class="bi bi-trash"></i></button>

                            <?php if (in_array($el['status'], ['draft','configured','open','closed'], true)): ?>
                            <form method="post" action="<?= BASE_URL ?>/admin/election_action.php"
                                  style="display:inline;" onsubmit="return confirm('Advance election status?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="transition">
                                <input type="hidden" name="id" value="<?= $el['id'] ?>">
                                <button class="btn-sm-icon" title="Advance Status" style="color:#2ecc71;border-color:#2ecc71;">
                                    <i class="bi bi-arrow-right-circle"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Delete Election Modal ─────────────────────────────────────────────── -->
<div id="delModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:12px;padding:28px;width:100%;max-width:400px;">
        <div style="text-align:center;margin-bottom:20px;">
            <div style="width:60px;height:60px;border-radius:50%;background:rgba(192,57,43,.15);border:2px solid #e74c3c;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                <i class="bi bi-trash" style="font-size:1.5rem;color:#e74c3c;"></i>
            </div>
            <h5 style="color:#fff;margin-bottom:4px;">Delete Election?</h5>
            <p id="delElName" style="color:var(--text-muted);font-size:.83rem;"></p>
        </div>
        <form method="post" action="<?= BASE_URL ?>/admin/election_action.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="delElId">
            <div class="mb-4">
                <label class="form-label-dark">Enter your admin password to confirm</label>
                <div style="position:relative;">
                    <i class="bi bi-lock" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);"></i>
                    <input type="password" name="admin_password" class="form-control-dark"
                           style="padding-left:36px;" placeholder="Your password" required autofocus>
                </div>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="submit" style="flex:1;padding:10px;background:#c0392b;border:none;border-radius:6px;color:#fff;font-weight:700;cursor:pointer;">
                    <i class="bi bi-trash me-1"></i>Delete
                </button>
                <button type="button" onclick="document.getElementById('delModal').style.display='none'"
                        style="flex:1;padding:10px;background:transparent;border:1px solid var(--card-border);border-radius:6px;color:var(--text-muted);cursor:pointer;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openDelModal(id, title) {
    document.getElementById('delElId').value   = id;
    document.getElementById('delElName').textContent = title;
    document.getElementById('delModal').style.display = 'flex';
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
