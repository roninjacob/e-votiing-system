<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_role(['admin']);

$errors  = [];
$success = '';

// ── DELETE ────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_check();
    $id = (int) ($_POST['block_id'] ?? 0);
    try {
        db()->prepare('DELETE FROM blocks WHERE id = ?')->execute([$id]);
        flash('success', 'Block removed.');
        redirect('/admin/blocks.php');
    } catch (PDOException $e) {
        flash('danger', 'Cannot delete — block is in use by an election or user.');
        redirect('/admin/blocks.php');
    }
}

// ── ADD ───────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    csrf_check();
    $yearLevel = (int) ($_POST['year_level'] ?? 0);
    $semester  = (int) ($_POST['semester']   ?? 0);
    $track     = $_POST['track'] ?? '';
    $track     = in_array($track, ['BA','NT'], true) ? $track : null;

    if ($yearLevel < 1 || $yearLevel > 4) $errors[] = 'Invalid year level.';
    if ($semester  < 1 || $semester  > 2) $errors[] = 'Invalid semester.';
    if (in_array($yearLevel, [3,4]) && $track === null) $errors[] = 'Year 3 and Year 4 require a track (BA or NT).';
    if (!in_array($yearLevel, [3,4])) $track = null;

    if (empty($errors)) {
        // Find next block number for this year+semester+track group
        $existing = db()->prepare(
            'SELECT section FROM blocks WHERE year_level = ? AND semester = ? AND (track = ? OR (track IS NULL AND ? IS NULL)) ORDER BY section'
        );
        $existing->execute([$yearLevel, $semester, $track, $track]);
        $sections = $existing->fetchAll(PDO::FETCH_COLUMN);

        // Find lowest available BB (fills gaps left by deleted blocks)
        $usedBBs = array_map(fn($s) => (int) substr($s, 2), $sections);
        $nextBB  = 1;
        while (in_array($nextBB, $usedBBs)) $nextBB++;
        $nextBB  = str_pad((string)$nextBB, 2, '0', STR_PAD_LEFT);
        $section = $yearLevel . $semester . $nextBB;

        try {
            db()->prepare(
                'INSERT INTO blocks (block_name, section, year_level, semester, track) VALUES (?, ?, ?, ?, ?)'
            )->execute(['BSIT', $section, $yearLevel, $semester, $track]);
            flash('success', "Block BSIT {$section} added.");
            redirect('/admin/blocks.php');
        } catch (PDOException $e) {
            $errors[] = 'Failed to add block: ' . $e->getMessage();
        }
    }
}

// ── LOAD ──────────────────────────────────────────────────────────────────────
$blocks = db()->query(
    'SELECT b.id, b.section, b.year_level, b.semester, b.track,
            COUNT(DISTINCT u.id) AS student_count,
            COUNT(DISTINCT be.id) AS election_count
     FROM blocks b
     LEFT JOIN users u  ON u.block_id = b.id AND u.role = \'voter\'
     LEFT JOIN block_elections be ON be.block_id = b.id
     GROUP BY b.id
     ORDER BY b.year_level, b.semester, b.track, b.section'
)->fetchAll();

$pageTitle = 'Manage Blocks';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h2>Manage Blocks</h2>
        <div class="election-status-bar">
            <span style="color:var(--text-muted);"><?= count($blocks) ?> blocks total</span>
        </div>
    </div>
    <button class="btn-gold" onclick="document.getElementById('addModal').style.display='flex'">
        <i class="bi bi-plus-lg"></i> Add Block
    </button>
</div>

<?php if (!empty($errors)): ?>
<div style="background:rgba(192,57,43,.15);border:1px solid rgba(192,57,43,.4);border-radius:8px;padding:10px 14px;font-size:.83rem;color:#e74c3c;margin-bottom:16px;">
    <?php foreach ($errors as $e): ?><div><i class="bi bi-exclamation-circle me-1"></i><?= h($e) ?></div><?php endforeach; ?>
</div>
<?php endif; ?>

<div class="dark-card">
    <div style="overflow-x:auto;">
        <table class="dark-table">
            <thead>
                <tr>
                    <th>Section</th>
                    <th>Year</th>
                    <th>Semester</th>
                    <th>Track</th>
                    <th>Students</th>
                    <th>Elections</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($blocks)): ?>
                <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:32px;">No blocks found.</td></tr>
            <?php else: foreach ($blocks as $b): ?>
                <tr>
                    <td style="font-weight:700;color:var(--gold);">BSIT <?= h($b['section']) ?></td>
                    <td>Year <?= $b['year_level'] ?></td>
                    <td>Sem <?= $b['semester'] ?></td>
                    <td>
                        <?php if ($b['track']): ?>
                        <span style="background:rgba(240,165,0,.15);color:var(--gold);padding:2px 8px;border-radius:4px;font-size:.75rem;font-weight:700;">
                            <?= h($b['track']) ?>
                        </span>
                        <?php else: ?>
                        <span style="color:var(--text-muted);font-size:.78rem;">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="color:var(--text-muted);"><?= $b['student_count'] ?></td>
                    <td style="color:var(--text-muted);"><?= $b['election_count'] ?></td>
                    <td>
                        <button class="btn-sm-icon danger" title="Remove Block"
                                onclick="confirmDelete(<?= $b['id'] ?>, 'BSIT <?= h($b['section']) ?>', <?= $b['student_count'] ?>, <?= $b['election_count'] ?>)">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Add Block Modal ─────────────────────────────────────────────────────── -->
<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:12px;padding:28px;width:100%;max-width:400px;">
        <h5 style="color:#fff;margin-bottom:20px;"><i class="bi bi-plus-circle me-2" style="color:var(--gold);"></i>Add New Block</h5>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">

            <div class="mb-3">
                <label class="form-label-dark">Year Level</label>
                <select name="year_level" id="addYear" class="form-control-dark" onchange="toggleTrack()" required>
                    <option value="">— Select —</option>
                    <option value="1">Year 1</option>
                    <option value="2">Year 2</option>
                    <option value="3">Year 3</option>
                    <option value="4">Year 4</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label-dark">Semester</label>
                <select name="semester" class="form-control-dark" required>
                    <option value="">— Select —</option>
                    <option value="1">Semester 1</option>
                    <option value="2">Semester 2</option>
                </select>
            </div>

            <div class="mb-4" id="trackField" style="display:none;">
                <label class="form-label-dark">Track <span style="color:#e74c3c;">*</span></label>
                <select name="track" id="addTrack" class="form-control-dark">
                    <option value="">— Select Track —</option>
                    <option value="BA">Business Analytics (BA)</option>
                    <option value="NT">Networking (NT)</option>
                </select>
            </div>

            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn-gold" style="flex:1;justify-content:center;padding:10px;">
                    <i class="bi bi-plus-lg me-1"></i>Add Block
                </button>
                <button type="button" onclick="document.getElementById('addModal').style.display='none'"
                        style="flex:1;padding:10px;background:transparent;border:1px solid var(--card-border);border-radius:6px;color:var(--text-muted);cursor:pointer;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── Delete Confirm Modal ────────────────────────────────────────────────── -->
<div id="delModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:12px;padding:28px;width:100%;max-width:380px;text-align:center;">
        <div style="width:60px;height:60px;border-radius:50%;background:rgba(192,57,43,.15);border:2px solid #e74c3c;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
            <i class="bi bi-trash" style="font-size:1.5rem;color:#e74c3c;"></i>
        </div>
        <h5 style="color:#fff;margin-bottom:6px;">Remove Block?</h5>
        <p id="delMsg" style="color:var(--text-muted);font-size:.83rem;margin-bottom:20px;"></p>
        <form method="post" id="delForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="block_id" id="delBlockId">
            <div style="display:flex;gap:10px;">
                <button type="submit" style="flex:1;padding:10px;background:#c0392b;border:none;border-radius:6px;color:#fff;font-weight:700;cursor:pointer;">
                    Remove
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
function toggleTrack() {
    const yr = document.getElementById('addYear').value;
    const tf = document.getElementById('trackField');
    const at = document.getElementById('addTrack');
    tf.style.display = (yr === '3' || yr === '4') ? 'block' : 'none';
    if (yr !== '3' && yr !== '4') at.value = '';
}

function confirmDelete(id, name, students, elections) {
    document.getElementById('delBlockId').value = id;
    let msg = `You are about to remove <strong style="color:#fff;">${name}</strong>.`;
    if (students > 0) msg += `<br><span style="color:#e74c3c;">⚠️ ${students} student(s) are assigned to this block.</span>`;
    if (elections > 0) msg += `<br><span style="color:#e74c3c;">⚠️ ${elections} election(s) linked to this block.</span>`;
    document.getElementById('delMsg').innerHTML = msg;
    document.getElementById('delModal').style.display = 'flex';
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
