<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

if (current_user() !== null) redirect('/index.php');

$errors  = [];
$success = false;

// Load all BSIT blocks with track info
$blocks = db()->query(
    "SELECT id, section, year_level, semester, track
     FROM blocks WHERE block_name = 'BSIT'
     ORDER BY year_level, semester, track, section"
)->fetchAll();

// Derive available year levels from DB
$yearLevels = array_values(array_unique(array_column($blocks, 'year_level')));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $email     = strtolower(trim($_POST['email']     ?? ''));
    $fullName  = trim($_POST['full_name'] ?? '');
    $blockId   = (int) ($_POST['block_id']  ?? 0);
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (!is_valid_gsuite($email))      $errors[] = 'Email must be your GSuite account (e.g. 23-01041@g.batstate-u.edu.ph).';
    if ($fullName === '')               $errors[] = 'Full name is required.';
    if ($blockId === 0)                 $errors[] = 'Please select your block section.';
    if (strlen($password) < 8)         $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $password2)      $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) $errors[] = 'This email is already registered.';
    }

    if (empty($errors)) {
        $studentId = gsuite_to_student_id($email);
        $hash      = password_hash($password, PASSWORD_BCRYPT);
        db()->prepare(
            'INSERT INTO users (student_id, full_name, email, password_hash, role, block_id, is_verified, is_active)
             VALUES (?, ?, ?, ?, \'voter\', ?, 0, 1)'
        )->execute([$studentId, $fullName, $email, $hash, $blockId]);
        $success = true;
    }
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account — CICS E-Voting</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= filemtime(BASE_PATH . '/assets/css/style.css') ?>">
</head>
<body class="login-page">

<div class="login-banner">
    <div class="logos-group">
        <img src="<?= BASE_URL ?>/assets/img/cics-logo.png?v=<?= filemtime(BASE_PATH . '/assets/img/cics-logo.png') ?>"
             style="height:48px;width:auto;object-fit:contain;"
             alt="CICS" onerror="this.style.display='none'">
    </div>
    <div class="banner-title">CICS E-Voting System — Batangas State University Arasof Campus</div>
    <div class="logos-group">
        <img src="<?= BASE_URL ?>/assets/img/sc-logo.png?v=<?= filemtime(BASE_PATH . '/assets/img/sc-logo.png') ?>"
             style="height:48px;width:auto;object-fit:contain;"
             alt="CICS Student Council" onerror="this.style.display='none'">
    </div>
</div>

<div class="flex-grow-1 d-flex align-items-center justify-content-center py-5">
    <div class="login-card" style="max-width:500px;">

    <?php if ($success): ?>
        <div class="text-center py-3">
            <div style="width:72px;height:72px;border-radius:50%;background:rgba(46,204,113,.15);border:3px solid #2ecc71;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                <i class="bi bi-check-lg" style="font-size:2rem;color:#2ecc71;"></i>
            </div>
            <h3 style="color:#fff;margin-bottom:8px;">Account Created!</h3>
            <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:24px;">
                Your account is pending verification by your adviser.<br>
                You will be notified once approved.
            </p>
            <a href="<?= BASE_URL ?>/login.php" class="btn-gold" style="padding:11px 32px;">
                <i class="bi bi-box-arrow-in-right me-1"></i>Go to Login
            </a>
        </div>

    <?php else: ?>

        <div class="text-center mb-4">
            <div style="display:flex;align-items:center;justify-content:center;gap:16px;margin-bottom:14px;">
                <img src="<?= BASE_URL ?>/assets/img/cics-logo.png?v=<?= filemtime(BASE_PATH . '/assets/img/cics-logo.png') ?>"
                     style="width:64px;height:64px;object-fit:contain;"
                     alt="CICS" onerror="this.style.display='none'">
                <img src="<?= BASE_URL ?>/assets/img/sc-logo.png?v=<?= filemtime(BASE_PATH . '/assets/img/sc-logo.png') ?>"
                     style="width:64px;height:64px;object-fit:contain;"
                     alt="SC" onerror="this.style.display='none'">
            </div>
            <h3>Create Account</h3>
            <p style="font-size:.75rem;color:var(--text-muted);">Register with your BatStateU GSuite account</p>
        </div>

        <?php if (!empty($errors)): ?>
        <div style="background:rgba(192,57,43,.15);border:1px solid rgba(192,57,43,.4);border-radius:8px;padding:10px 14px;font-size:.83rem;color:#e74c3c;margin-bottom:16px;">
            <?php foreach ($errors as $err): ?>
            <div><i class="bi bi-exclamation-circle me-1"></i><?= h($err) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="post" autocomplete="off" id="regForm">
            <?= csrf_field() ?>

            <!-- GSuite Email -->
            <div class="mb-3">
                <label class="form-label-dark">GSuite Email <span style="color:#e74c3c;">*</span></label>
                <div style="position:relative;">
                    <i class="bi bi-envelope" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);"></i>
                    <input type="email" name="email" id="emailField" class="form-control-dark"
                           style="padding-left:36px;"
                           value="<?= h($_POST['email'] ?? '') ?>"
                           placeholder="23-01041@g.batstate-u.edu.ph"
                           required autofocus>
                </div>
                <div style="font-size:.7rem;color:var(--text-muted);margin-top:4px;">
                    Format: <code style="color:var(--gold);">YY-NNNNN@g.batstate-u.edu.ph</code>
                    — your student ID is extracted automatically.
                </div>
            </div>

            <!-- Full Name -->
            <div class="mb-3">
                <label class="form-label-dark">Full Name <span style="color:#e74c3c;">*</span></label>
                <div style="position:relative;">
                    <i class="bi bi-person" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);"></i>
                    <input type="text" name="full_name" class="form-control-dark"
                           style="padding-left:36px;"
                           value="<?= h($_POST['full_name'] ?? '') ?>"
                           placeholder="Last Name, First Name M.I."
                           required maxlength="150">
                </div>
            </div>

            <!-- Year Level (drives track visibility) -->
            <div class="mb-3">
                <label class="form-label-dark">Year Level <span style="color:#e74c3c;">*</span></label>
                <div style="position:relative;">
                    <i class="bi bi-mortarboard" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);z-index:1;"></i>
                    <select id="yearSelect" class="form-control-dark" style="padding-left:36px;">
                        <option value="">— Select year level —</option>
                        <?php foreach ($yearLevels as $yr): ?>
                        <option value="<?= $yr ?>"><?= 'Year ' . $yr ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Track (Year 3 only) -->
            <div class="mb-3" id="trackRow" style="display:none;">
                <label class="form-label-dark">Specialization Track <span style="color:#e74c3c;">*</span></label>
                <div style="display:flex;gap:10px;">
                    <label id="trackBA" class="track-option" onclick="selectTrack('BA')"
                           style="flex:1;padding:12px;border:2px solid var(--card-border);border-radius:8px;cursor:pointer;text-align:center;transition:all .15s;">
                        <i class="bi bi-graph-up-arrow" style="font-size:1.4rem;display:block;margin-bottom:4px;color:var(--gold);"></i>
                        <div style="font-size:.82rem;font-weight:700;color:#fff;">Business Analytics</div>
                        <div style="font-size:.7rem;color:var(--text-muted);">BA Track</div>
                    </label>
                    <label id="trackNT" class="track-option" onclick="selectTrack('NT')"
                           style="flex:1;padding:12px;border:2px solid var(--card-border);border-radius:8px;cursor:pointer;text-align:center;transition:all .15s;">
                        <i class="bi bi-hdd-network" style="font-size:1.4rem;display:block;margin-bottom:4px;color:var(--gold);"></i>
                        <div style="font-size:.82rem;font-weight:700;color:#fff;">Networking</div>
                        <div style="font-size:.7rem;color:var(--text-muted);">NT Track</div>
                    </label>
                </div>
                <input type="hidden" id="selectedTrack" value="">
            </div>

            <!-- Block Section -->
            <div class="mb-3" id="blockRow" style="display:none;">
                <label class="form-label-dark">Block Section <span style="color:#e74c3c;">*</span></label>
                <div style="position:relative;">
                    <i class="bi bi-grid" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);z-index:1;"></i>
                    <select name="block_id" id="blockSelect" class="form-control-dark" style="padding-left:36px;" required>
                        <option value="">— Select your block —</option>
                        <?php
                        // Track sequential block number per year+semester+track group
                        $blockCounters = [];
                        foreach ($blocks as $b):
                            $key = $b['year_level'] . '-' . $b['semester'] . '-' . ($b['track'] ?? '');
                            $blockCounters[$key] = ($blockCounters[$key] ?? 0) + 1;
                            $blockNum = $blockCounters[$key];
                        ?>
                        <option value="<?= $b['id'] ?>"
                                data-year="<?= $b['year_level'] ?>"
                                data-track="<?= h($b['track'] ?? '') ?>"
                                <?= ($_POST['block_id'] ?? '') == $b['id'] ? 'selected' : '' ?>>
                            Year <?= $b['year_level'] ?>, Sem <?= $b['semester'] ?> — Block <?= $blockNum ?>
                            <?php if ($b['track']): ?>(<?= h($b['track']) ?> Track)<?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="font-size:.7rem;color:var(--text-muted);margin-top:4px;">
                    Section code: <code style="color:var(--gold);">YSBB</code>
                    — Y=Year · S=Semester · BB=Block No.
                </div>
            </div>

            <!-- Password -->
            <div class="mb-3">
                <label class="form-label-dark">Password <span style="color:#e74c3c;">*</span></label>
                <div style="position:relative;">
                    <i class="bi bi-lock" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);"></i>
                    <input type="password" name="password" id="pwd1" class="form-control-dark"
                           style="padding-left:36px;padding-right:40px;"
                           placeholder="Minimum 8 characters" required minlength="8">
                    <button type="button" onclick="togglePwd('pwd1','eye1')"
                            style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;padding:0;">
                        <i class="bi bi-eye" id="eye1"></i>
                    </button>
                </div>
            </div>

            <!-- Confirm Password -->
            <div class="mb-4">
                <label class="form-label-dark">Confirm Password <span style="color:#e74c3c;">*</span></label>
                <div style="position:relative;">
                    <i class="bi bi-lock-fill" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);"></i>
                    <input type="password" name="password2" id="pwd2" class="form-control-dark"
                           style="padding-left:36px;padding-right:40px;"
                           placeholder="Re-enter your password" required>
                    <button type="button" onclick="togglePwd('pwd2','eye2')"
                            style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;padding:0;">
                        <i class="bi bi-eye" id="eye2"></i>
                    </button>
                </div>
                <div id="pwdMatch" style="font-size:.72rem;margin-top:4px;"></div>
            </div>

            <button type="submit" class="btn-gold w-100 justify-content-center" style="padding:12px;font-size:.9rem;">
                <i class="bi bi-person-plus me-1"></i>Create Account
            </button>
        </form>

        <div style="text-align:center;margin-top:20px;padding-top:16px;border-top:1px solid rgba(255,255,255,0.08);">
            <span style="font-size:.8rem;color:var(--text-muted);">Already have an account? </span>
            <a href="<?= BASE_URL ?>/login.php" style="font-size:.8rem;color:var(--gold);text-decoration:none;font-weight:700;">Log In</a>
        </div>

    <?php endif; ?>
    </div>
</div>

<footer style="background:rgba(0,0,0,0.5);border-top:1px solid rgba(255,255,255,0.07);padding:10px 24px;text-align:center;position:relative;z-index:1;">
    <span style="font-size:.72rem;color:#9aa0b0;">@batstateu &nbsp;|&nbsp; Leading Innovations, Transforming Lives, Building the Nation</span>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const yearSelect    = document.getElementById('yearSelect');
const trackRow      = document.getElementById('trackRow');
const blockRow      = document.getElementById('blockRow');
const blockSelect   = document.getElementById('blockSelect');
const selectedTrack = document.getElementById('selectedTrack');
let   activeTrack   = '';

// Show/hide track + block based on year selection
yearSelect.addEventListener('change', function () {
    const yr = this.value;
    activeTrack = '';
    selectedTrack.value = '';
    resetTrackUI();

    if (!yr) {
        trackRow.style.display = 'none';
        blockRow.style.display = 'none';
        return;
    }

    if (yr === '3' || yr === '4') {
        trackRow.style.display = 'block';
        blockRow.style.display = 'none';
    } else {
        trackRow.style.display = 'none';
        filterBlocks(yr, '');
        blockRow.style.display = 'block';
    }
});

function selectTrack(track) {
    activeTrack = track;
    selectedTrack.value = track;
    resetTrackUI();

    const yr = yearSelect.value;
    const ba = document.getElementById('trackBA');
    const nt = document.getElementById('trackNT');

    if (track === 'BA') {
        ba.style.borderColor = 'var(--teal-light)';
        ba.style.background  = 'rgba(61,191,184,.1)';
    } else {
        nt.style.borderColor = 'var(--teal-light)';
        nt.style.background  = 'rgba(61,191,184,.1)';
    }

    filterBlocks(yr, track);
    blockRow.style.display = 'block';
}

function resetTrackUI() {
    ['trackBA','trackNT'].forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.style.borderColor = 'var(--card-border)'; el.style.background = 'transparent'; }
    });
    blockSelect.value = '';
}

function filterBlocks(year, track) {
    const opts = blockSelect.querySelectorAll('option[data-year]');
    opts.forEach(opt => {
        const matchYear  = opt.dataset.year  === year;
        const matchTrack = track === '' ? opt.dataset.track === '' : opt.dataset.track === track;
        opt.style.display = (matchYear && matchTrack) ? '' : 'none';
    });
    blockSelect.value = '';
}

// Password helpers
function togglePwd(id, iconId) {
    const f = document.getElementById(id);
    const i = document.getElementById(iconId);
    f.type = f.type === 'password' ? 'text' : 'password';
    i.className = f.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}

document.getElementById('pwd2').addEventListener('input', function () {
    const msg = document.getElementById('pwdMatch');
    if (!this.value) { msg.textContent = ''; return; }
    msg.innerHTML = this.value === document.getElementById('pwd1').value
        ? '<span style="color:#2ecc71;"><i class="bi bi-check-circle me-1"></i>Passwords match</span>'
        : '<span style="color:#e74c3c;"><i class="bi bi-x-circle me-1"></i>Passwords do not match</span>';
});

// Restore state on validation error (POST back)
<?php if (!empty($_POST['block_id'])): ?>
(function () {
    const sel = blockSelect.querySelector('option[value="<?= (int)($_POST['block_id'] ?? 0) ?>"]');
    if (!sel) return;
    const yr    = sel.dataset.year;
    const track = sel.dataset.track;
    yearSelect.value = yr;
    if (yr === '3' || yr === '4') {
        trackRow.style.display = 'block';
        selectTrack(track);
    } else {
        filterBlocks(yr, '');
        blockRow.style.display = 'block';
    }
    blockSelect.value = '<?= (int)($_POST['block_id'] ?? 0) ?>';
})();
<?php endif; ?>
</script>
</body>
</html>
