<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/Registration.php';
start_session();
try {
    $blocks = Registration::blocks();
} catch (Throwable $e) {
    error_log((string)$e);
    http_response_code(503);
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Database setup required</title><link rel="stylesheet" href="assets/css/style.css"><main class="card"><h2>Database setup required</h2><p class="sub">The app needs its MySQL database before registration can load. Create the database, import <code>database/mysql_schema.sql</code>, and check the EVS_DB_* settings in <code>.env</code>.</p><p><a href="login.php">Back to login</a></p></main></html>';
    exit;
}
$boot = ['csrf' => $_SESSION['csrf'], 'blocks' => $blocks,
         'loginUrl' => LOGIN_URL, 'homeUrl' => HOME_URL, 'expiry' => OTP_EXPIRY_MINUTES];
$steps = ['Basic Info', 'Department', 'Verification', 'Password', 'Review', 'Complete'];
function field(string $id, string $label, string $type = 'text', string $ph = '', string $extra = ''): void {
    echo "<div class=\"field\"><label for=\"$id\">$label</label><input id=\"$id\" name=\"$id\" type=\"$type\" placeholder=\"$ph\" $extra><p class=\"err\" data-err=\"$id\"></p></div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Create Your Account · E-Voting System</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<main class="shell">
  <aside class="hero">
    <h1>BATANGAS STATE<br>UNIVERSITY</h1>
    <p class="tag">The National Engineering University</p>
    <p>Official voting platform of the College of Informatics and Computing Sciences Student Council (CICS-SC). Open to CICS students only.</p>
    <ul>
      <li><b>Secure</b> Your vote is encrypted and protected.</li>
      <li><b>Transparent</b> Every vote is counted fairly and accurately.</li>
      <li><b>Accessible</b> Vote anytime, anywhere with your account.</li>
    </ul>
  </aside>

  <section class="panel">
    <ol class="stepper" aria-label="Registration progress">
      <?php foreach ($steps as $i => $s): ?><li data-dot="<?= $i + 1 ?>"><span><?= $i + 1 ?></span><em><?= $s ?></em></li><?php endforeach; ?>
    </ol>

    <!-- 1 · Basic info -->
    <form class="step" data-step="1" novalidate>
      <h2 tabindex="-1">Create Your Account</h2><p class="sub">Fill in your basic information to get started.</p>
      <div class="alert" role="alert" hidden></div>
      <?php field('full_name', 'Full Name', 'text', 'Enter your full name', 'autocomplete="name" maxlength="100" required');
            field('email', 'Email Address', 'email', 'name@g.batstate-u.edu.ph', 'autocomplete="email" maxlength="254" required');
            field('student_id', 'Student ID Number', 'text', 'e.g., 23-01041', 'inputmode="numeric" maxlength="8" required'); ?>
      <div class="actions"><a class="btn ghost" href="<?= htmlspecialchars(LOGIN_URL) ?>">← Cancel</a><button class="btn" type="submit">Next →</button></div>
    </form>

    <!-- 2 · Department -->
    <form class="step" data-step="2" novalidate hidden>
      <h2 tabindex="-1">Choose Your Block</h2><p class="sub">Your block determines which election you can vote in.</p>
      <div class="alert" role="alert" hidden></div>
      <div class="field"><label for="block_id">Block / Section</label><select id="block_id" required></select><p class="err" data-err="block_id"></p></div>
      <p class="note">You can vote only in the election assigned to your registered block.</p>
      <div class="actions"><button class="btn ghost" type="button" data-back>← Back</button><button class="btn" type="submit">Next →</button></div>
    </form>

    <!-- 3 · Verification -->
    <form class="step" data-step="3" novalidate hidden>
      <h2 tabindex="-1">Create Your Account</h2><p class="sub">We need to verify that you are a real person.</p>
      <div class="alert" role="alert" hidden></div>
      <div class="box" id="otpBox">
        <p>We have sent a 6-digit verification code to:</p><p class="strong" id="otpEmail"></p>
        <p class="muted">It expires in <?= OTP_EXPIRY_MINUTES ?> minutes.</p>
        <div class="otp" id="otp"><?php for ($i = 0; $i < 6; $i++): ?><input inputmode="numeric" maxlength="1" aria-label="Digit <?= $i + 1 ?>" <?= $i === 0 ? 'autocomplete="one-time-code"' : '' ?>><?php endfor; ?></div>
        <p class="err" data-err="otp"></p>
        <p class="dev" id="devCode" hidden></p>
        <p class="muted">Didn't receive the code? <button class="link" type="button" id="resend" disabled>Resend Code</button> <span id="timer"></span></p>
      </div>
      <div class="box ok" id="verifiedBox" hidden>✓ Your email has been verified.</div>
      <div class="actions"><button class="btn ghost" type="button" data-back>← Back</button><button class="btn" type="submit">Next →</button></div>
    </form>

    <!-- 4 · Password -->
    <form class="step" data-step="4" novalidate hidden>
      <h2 tabindex="-1">Create Your Account</h2><p class="sub">Set a secure password for your account.</p>
      <div class="alert" role="alert" hidden></div>
      <?php foreach (['password' => 'Password', 'confirm' => 'Confirm Password'] as $id => $l): ?>
      <div class="field"><label for="<?= $id ?>"><?= $l ?></label>
        <div class="pw"><input id="<?= $id ?>" type="password" autocomplete="new-password" maxlength="72" placeholder="<?= $id === 'confirm' ? 'Confirm your password' : 'Enter your password' ?>" required>
        <button class="link" type="button" data-toggle="<?= $id ?>" aria-pressed="false">Show</button></div><p class="err" data-err="<?= $id ?>"></p></div>
      <?php endforeach; ?>
      <p class="note">Must be at least 8 characters long and include letters and numbers. Adding symbols makes it stronger.</p>
      <div class="actions"><button class="btn ghost" type="button" data-back>← Back</button><button class="btn" type="submit">Next →</button></div>
    </form>

    <!-- 5 · Review -->
    <form class="step" data-step="5" novalidate hidden>
      <h2 tabindex="-1">Review Your Information</h2><p class="sub">Please review all details carefully. You can go back to edit if needed.</p>
      <div class="alert" role="alert" hidden></div>
      <dl class="details" id="review"></dl>
      <div class="actions"><button class="btn ghost" type="button" data-back>← Back</button><button class="btn" type="submit">✓ Complete Registration</button></div>
    </form>

    <!-- 6 · Success -->
    <div class="step done" data-step="6" hidden>
      <div class="check">✓</div>
      <h2 tabindex="-1">Registration Successful!</h2><p class="sub">Your account has been created successfully.</p>
      <dl class="details" id="success"></dl>
      <div class="actions"><a class="btn" href="<?= htmlspecialchars(LOGIN_URL) ?>">Go to Login</a><a class="btn ghost" href="<?= htmlspecialchars(HOME_URL) ?>">Back to Homepage</a></div>
    </div>
  </section>
</main>
<script id="boot" type="application/json"><?= json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<script src="assets/js/app.js"></script>
</body>
</html>
