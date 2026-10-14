<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role(['admin', 'adviser', 'committee']);

// ── ACTIONS ───────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id     = (int) ($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($id && $action === 'verify') {
        db()->prepare('UPDATE users SET is_verified = 1 WHERE id = ? AND role = ?')
           ->execute([$id, 'voter']);
        flash('success', 'Student verified successfully.');
    } elseif ($id && $action === 'reject') {
        db()->prepare('DELETE FROM users WHERE id = ? AND role = ? AND is_verified = 0')
           ->execute([$id, 'voter']);
        flash('success', 'Student registration rejected and removed.');
    } elseif ($id && $action === 'remove') {
        db()->prepare('DELETE FROM users WHERE id = ? AND role = ?')
           ->execute([$id, 'voter']);
        flash('success', 'Student removed.');
    }
    $back = $_POST['section'] ?? '';
    redirect('/admin/students.php' . ($back ? '?section=' . urlencode($back) : ''));
}

// ── LOAD ──────────────────────────────────────────────────────────────────────
$pending = db()->query(
    'SELECT u.id, u.student_id, u.full_name, u.email, u.created_at, b.section, b.track
     FROM users u
     LEFT JOIN blocks b ON b.id = u.block_id
     WHERE u.role = \'voter\' AND u.is_verified = 0
     ORDER BY b.section, u.created_at ASC'
)->fetchAll();

// Group pending by section
$pendingBySection = [];
foreach ($pending as $s) {
    $key = $s['section'] ?? 'Unassigned';
    $pendingBySection[$key][] = $s;
}
ksort($pendingBySection);

$verified = db()->query(
    'SELECT u.id, u.student_id, u.full_name, u.email, u.is_active, u.created_at, b.section, b.track
     FROM users u
     LEFT JOIN blocks b ON b.id = u.block_id
     WHERE u.role = \'voter\' AND u.is_verified = 1
     ORDER BY b.section, u.full_name'
)->fetchAll();

// Group verified by section
$verifiedBySection = [];
foreach ($verified as $s) {
    $key = $s['section'] ?? 'Unassigned';
    $verifiedBySection[$key][] = $s;
}
ksort($verifiedBySection);

// Load all blocks for tabs (always show all sections regardless of student count)
$allBlocks = db()->query(
    'SELECT id, section, track FROM blocks ORDER BY section'
)->fetchAll();

// Active section filter
$activeSection = $_GET['section'] ?? null;

$pageTitle = 'Manage Students';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2>Manage Students</h2>
        <div class="election-status-bar">
            <?php if (count($pending) > 0): ?>
            <div class="status-dot"></div>
            <span class="status-label"><?= count($pending) ?> pending verification</span>
            <?php else: ?>
            <span style="color:var(--text-muted);">No pending registrations</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Section Dropdown -->
<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
    <label style="color:var(--text-muted);font-size:.82rem;white-space:nowrap;">Filter by Section:</label>
    <select onchange="window.location=this.value" class="form-control-dark" style="max-width:260px;">
        <option value="<?= BASE_URL ?>/admin/students.php" <?= $activeSection === null ? 'selected' : '' ?>>All Sections</option>
        <?php foreach ($allBlocks as $blk):
            $sec        = $blk['section'];
            $hasPending = isset($pendingBySection[$sec]);
            $label      = 'BSIT ' . $sec . ($blk['track'] ? ' (' . $blk['track'] . ')' : '');
            if ($hasPending) $label .= ' — ' . count($pendingBySection[$sec]) . ' pending';
        ?>
        <option value="<?= BASE_URL ?>/admin/students.php?section=<?= urlencode($sec) ?>"
                <?= $activeSection === $sec ? 'selected' : '' ?>>
            <?= h($label) ?>
        </option>
        <?php endforeach; ?>
    </select>
</div>

<?php
// Filter by active section
$showPending  = $activeSection ? ($pendingBySection[$activeSection]  ?? []) : $pending;
$showVerified = $activeSection ? ($verifiedBySection[$activeSection] ?? []) : [];
$showVerifiedBySection = $activeSection
    ? ($activeSection && isset($verifiedBySection[$activeSection]) ? [$activeSection => $verifiedBySection[$activeSection]] : [])
    : $verifiedBySection;
?>

<!-- Pending Verification -->
<?php if (!empty($showPending)): ?>
<div class="dark-card mb-4">
    <div class="card-head" style="color:#f0a500;">
        <i class="bi bi-hourglass-split me-2"></i>Pending Verification (<?= count($showPending) ?>)
    </div>
    <?php
    $showPendingGrouped = $activeSection
        ? [$activeSection => $showPending]
        : $pendingBySection;
    foreach ($showPendingGrouped as $section => $students):
    ?>
    <?php if (!$activeSection): ?>
    <div style="padding:10px 18px 4px;border-top:1px solid var(--card-border);">
        <span style="font-size:.75rem;font-weight:700;color:var(--gold);">
            BSIT <?= h($section) ?>
            <?php if ($students[0]['track']): ?>
            <span style="color:var(--text-muted);font-weight:400;">(<?= h($students[0]['track']) ?> Track)</span>
            <?php endif; ?>
        </span>
    </div>
    <?php endif; ?>
    <div style="overflow-x:auto;">
        <table class="dark-table">
            <thead><tr>
                <th>Student ID</th><th>Full Name</th><th>Email</th><th>Registered</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($students as $s): ?>
            <tr>
                <td style="color:var(--gold);font-weight:700;"><?= h($s['student_id']) ?></td>
                <td><?= h($s['full_name']) ?></td>
                <td style="color:var(--text-muted);font-size:.8rem;"><?= h($s['email']) ?></td>
                <td style="color:var(--text-muted);font-size:.78rem;"><?= h(date('M d, Y', strtotime($s['created_at']))) ?></td>
                <td>
                    <div style="display:flex;gap:6px;">
                        <form method="post" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="verify">
                            <input type="hidden" name="user_id" value="<?= $s['id'] ?>">
                            <input type="hidden" name="section" value="<?= h($activeSection ?? '') ?>">
                            <button class="btn-sm-icon" style="color:#2ecc71;border-color:#2ecc71;" title="Approve">
                                <i class="bi bi-check-lg"></i>
                            </button>
                        </form>
                        <form method="post" style="display:inline;"
                              onsubmit="return confirm('Reject and remove this registration?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="user_id" value="<?= $s['id'] ?>">
                            <input type="hidden" name="section" value="<?= h($activeSection ?? '') ?>">
                            <button class="btn-sm-icon danger" title="Reject"><i class="bi bi-x-lg"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Verified Students -->
<div class="dark-card">
    <div class="card-head">
        <i class="bi bi-people me-2"></i>
        <?= $activeSection ? 'BSIT ' . h($activeSection) . ' — Verified Students' : 'Verified Students (' . count($verified) . ')' ?>
    </div>
    <?php if (empty($showVerifiedBySection)): ?>
    <div style="text-align:center;color:var(--text-muted);padding:32px;">
        <?= $activeSection ? 'No verified students in this section.' : 'No verified students yet.' ?>
    </div>
    <?php else: foreach ($showVerifiedBySection as $section => $students): ?>
    <?php if (!$activeSection): ?>
    <div style="padding:10px 18px 4px;border-top:1px solid var(--card-border);">
        <span style="font-size:.75rem;font-weight:700;color:var(--gold);">
            BSIT <?= h($section) ?>
            <?php if ($students[0]['track']): ?>
            <span style="color:var(--text-muted);font-weight:400;">(<?= h($students[0]['track']) ?> Track)</span>
            <?php endif; ?>
            <span style="color:var(--text-muted);font-weight:400;font-size:.7rem;"> — <?= count($students) ?> student<?= count($students) !== 1 ? 's' : '' ?></span>
        </span>
    </div>
    <?php endif; ?>
    <div style="overflow-x:auto;">
        <table class="dark-table">
            <thead><tr>
                <th>Student ID</th><th>Full Name</th><th>Email</th><th>Status</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($students as $s): ?>
            <tr>
                <td style="color:var(--gold);font-weight:700;"><?= h($s['student_id']) ?></td>
                <td><?= h($s['full_name']) ?></td>
                <td style="color:var(--text-muted);font-size:.8rem;"><?= h($s['email']) ?></td>
                <td>
                    <?php if ($s['is_active']): ?>
                    <span style="color:#2ecc71;font-size:.78rem;font-weight:700;"><i class="bi bi-check-circle me-1"></i>Active</span>
                    <?php else: ?>
                    <span style="color:#e74c3c;font-size:.78rem;font-weight:700;"><i class="bi bi-x-circle me-1"></i>Inactive</span>
                    <?php endif; ?>
                </td>
                <td>
                    <form method="post" style="display:inline;"
                          onsubmit="return confirm('Remove <?= h(addslashes($s['full_name'])) ?> from the system?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="user_id" value="<?= $s['id'] ?>">
                        <input type="hidden" name="section" value="<?= h($activeSection ?? '') ?>">
                        <button class="btn-sm-icon danger" title="Remove Student">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; endif; ?>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
