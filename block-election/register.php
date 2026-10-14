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
<body class="login-page login-page--portal">

<?php require __DIR__ . '/includes/login_brand.php'; ?>

<main class="portal-main portal-registration">
    <?php require __DIR__ . '/includes/login_intro.php'; ?>
    <section class="login-card portal-card" aria-labelledby="registration-heading">

    <?php if ($success): ?>
        <div class="portal-registration-success" role="status">
            <div class="portal-account-icon" aria-hidden="true"><i class="bi bi-check-lg"></i></div>
            <span class="portal-card-kicker">YOU'RE PART OF THE COMMUNITY</span>
            <h2 id="registration-heading">Account created.</h2>
            <p>Your account is pending verification by your adviser. Once approved, you can sign in and take part in your block elections.</p>
            <div class="portal-registration-notice"><i class="bi bi-person-check" aria-hidden="true"></i><span>Need help getting approved? Contact your adviser.</span></div>
            <a href="<?= BASE_URL ?>/login.php" class="portal-submit">Back to Sign In <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
        </div>

    <?php else: ?>

        <a href="<?= BASE_URL ?>/login.php" class="portal-back-link"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to sign in</a>
        <div class="portal-card-heading">
            <div class="portal-account-icon" aria-hidden="true"><i class="bi bi-person-plus"></i></div>
            <span class="portal-card-kicker">JOIN THE CICS COMMUNITY</span>
            <h2 id="registration-heading">Your voice belongs here.</h2>
            <p>Create your student account to get started.</p>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="portal-error" role="alert">
            <?php foreach ($errors as $err): ?>
            <div><i class="bi bi-exclamation-circle me-1"></i><?= h($err) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="post" id="regForm">
            <?= csrf_field() ?>
            <div class="portal-form-section"><span>Student details</span><small>All fields are required</small></div>

            <!-- GSuite Email -->
            <div class="mb-3">
                <label class="form-label-dark" for="emailField">GSuite email</label>
                <div style="position:relative;">
                    <i class="bi bi-envelope" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);"></i>
                    <input type="email" name="email" id="emailField" class="form-control-dark" autocomplete="username" aria-describedby="emailHint"
                           style="padding-left:36px;"
                           value="<?= h($_POST['email'] ?? '') ?>"
                           placeholder="23-01041@g.batstate-u.edu.ph"
                           required>
                </div>
                <p class="portal-field-hint" id="emailHint">Use the GSuite email issued by BatStateU.</p>
            </div>

            <!-- Full Name -->
            <div class="mb-3">
                <label class="form-label-dark" for="fullNameField">Full name</label>
                <div style="position:relative;">
                    <i class="bi bi-person" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);"></i>
                    <input type="text" name="full_name" id="fullNameField" class="form-control-dark" autocomplete="name"
                           style="padding-left:36px;"
                           value="<?= h($_POST['full_name'] ?? '') ?>"
                           placeholder="Last Name, First Name M.I."
                           required maxlength="150">
                </div>
            </div>

            <!-- Year Level (drives track visibility) -->
            <div class="mb-3">
                <label class="form-label-dark" for="yearSelect">Year level</label>
                <div style="position:relative;">
                    <i class="bi bi-mortarboard" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);z-index:1;"></i>
                    <select id="yearSelect" class="form-control-dark" style="padding-left:36px;" required>
                        <option value="">— Select year level —</option>
                        <?php foreach ($yearLevels as $yr): ?>
                        <option value="<?= $yr ?>"><?= 'Year ' . $yr ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Specialization for Years 3 and 4 -->
            <div class="mb-3" id="trackRow" style="display:none;">
                <span class="form-label-dark" id="trackLabel">Specialization track</span>
                <div class="portal-track-options" role="group" aria-labelledby="trackLabel">
                    <button type="button" id="trackBA" class="track-option" onclick="selectTrack('BA')" aria-pressed="false">
                        <i class="bi bi-graph-up-arrow" aria-hidden="true"></i><strong>Business Analytics</strong><small>BA Track</small>
                    </button>
                    <button type="button" id="trackNT" class="track-option" onclick="selectTrack('NT')" aria-pressed="false">
                        <i class="bi bi-hdd-network" aria-hidden="true"></i><strong>Networking</strong><small>NT Track</small>
                    </button>
                </div>
                <input type="hidden" id="selectedTrack" value="">
            </div>

            <!-- Block Section -->
            <div class="mb-3" id="blockRow" style="display:none;">
                <label class="form-label-dark" for="blockSelect">Block section</label>
                <div style="position:relative;">
                    <i class="bi bi-grid" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);z-index:1;"></i>
                    <select name="block_id" id="blockSelect" class="form-control-dark" style="padding-left:36px;" required disabled>
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
                <p class="portal-field-hint">Choose the section you are currently enrolled in.</p>
            </div>

            <div class="portal-form-section portal-form-section--security"><span>Account security</span><i class="bi bi-lock" aria-hidden="true"></i></div>
            <!-- Password -->
            <div class="mb-3">
                <label class="form-label-dark" for="pwd1">Password</label>
                <div style="position:relative;">
                    <i class="bi bi-lock" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);"></i>
                    <input type="password" name="password" id="pwd1" class="form-control-dark" autocomplete="new-password"
                           style="padding-left:36px;padding-right:40px;"
                           placeholder="Minimum 8 characters" required minlength="8">
                    <button type="button" onclick="togglePwd('pwd1','eye1',this)" aria-label="Show password" aria-pressed="false"
                            style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;padding:0;">
                        <i class="bi bi-eye" id="eye1"></i>
                    </button>
                </div>
            </div>

            <!-- Confirm Password -->
            <div class="mb-4">
                <label class="form-label-dark" for="pwd2">Confirm password</label>
                <div style="position:relative;">
                    <i class="bi bi-lock-fill" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);"></i>
                    <input type="password" name="password2" id="pwd2" class="form-control-dark" autocomplete="new-password" aria-describedby="pwdMatch"
                           style="padding-left:36px;padding-right:40px;"
                           placeholder="Re-enter your password" required>
                    <button type="button" onclick="togglePwd('pwd2','eye2',this)" aria-label="Show confirm password" aria-pressed="false"
                            style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;padding:0;">
                        <i class="bi bi-eye" id="eye2"></i>
                    </button>
                </div>
                <div id="pwdMatch" class="portal-field-hint" aria-live="polite"></div>
            </div>

            <button type="submit" class="portal-submit">Create Account <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </button>
        </form>

        <div class="portal-registration-notice"><i class="bi bi-person-check" aria-hidden="true"></i><span>Your adviser will verify your account before you can vote.</span></div>
        <div class="portal-card-note">
            <span>Already have an account? </span>
            <a href="<?= BASE_URL ?>/login.php" class="portal-link">Sign In</a>
        </div>

    <?php endif; ?>
    </section>
</main>

<footer class="portal-footer">
    <span>CICS &middot; BatStateU ARASOF</span>
    <span>Leading Innovations. Transforming Lives. Building the Nation.</span>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
<?php if (!$success): ?>
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
    blockSelect.disabled = true;

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
        blockSelect.disabled = false;
    }
});

function selectTrack(track) {
    activeTrack = track;
    selectedTrack.value = track;
    resetTrackUI();

    const yr = yearSelect.value;
    const chosen = document.getElementById('track' + track);
    if (chosen) {
        chosen.classList.add('is-selected');
        chosen.setAttribute('aria-pressed', 'true');
    }

    filterBlocks(yr, track);
    blockRow.style.display = 'block';
    blockSelect.disabled = false;
}

function resetTrackUI() {
    ['trackBA','trackNT'].forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.classList.remove('is-selected'); el.setAttribute('aria-pressed', 'false'); }
    });
    blockSelect.value = '';
}

function filterBlocks(year, track) {
    const opts = blockSelect.querySelectorAll('option[data-year]');
    opts.forEach(opt => {
        const matchYear  = opt.dataset.year  === year;
        const matchTrack = track === '' ? opt.dataset.track === '' : opt.dataset.track === track;
        opt.style.display = (matchYear && matchTrack) ? '' : 'none';
        opt.disabled = !(matchYear && matchTrack);
    });
    blockSelect.value = '';
}

// Password helpers
function togglePwd(id, iconId, button) {
    const f = document.getElementById(id);
    const i = document.getElementById(iconId);
    f.type = f.type === 'password' ? 'text' : 'password';
    i.className = f.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
    const field = id === 'pwd2' ? 'confirm password' : 'password';
    button.setAttribute('aria-label', (f.type === 'password' ? 'Show ' : 'Hide ') + field);
    button.setAttribute('aria-pressed', f.type === 'text' ? 'true' : 'false');
}

function updatePasswordMatch() {
    const confirmPassword = document.getElementById('pwd2');
    const msg = document.getElementById('pwdMatch');
    if (!confirmPassword.value) { msg.textContent = ''; msg.classList.remove('is-match', 'is-mismatch'); confirmPassword.setCustomValidity(''); return; }
    const matches = confirmPassword.value === document.getElementById('pwd1').value;
    msg.textContent = matches ? 'Passwords match' : 'Passwords do not match';
    msg.classList.toggle('is-match', matches);
    msg.classList.toggle('is-mismatch', !matches);
    confirmPassword.setCustomValidity(matches ? '' : 'Passwords do not match.');
}
document.getElementById('pwd1').addEventListener('input', updatePasswordMatch);
document.getElementById('pwd2').addEventListener('input', updatePasswordMatch);

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
        blockSelect.disabled = false;
    }
    blockSelect.value = '<?= (int)($_POST['block_id'] ?? 0) ?>';
})();
<?php endif; ?>
<?php endif; ?>
</script>
</body>
</html>
