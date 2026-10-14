<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
start_session();
if (!empty($_SESSION['block_uid'])) { header('Location: vote.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= htmlspecialchars($_SESSION['csrf']) ?>"><title>Log In · CICS-SC Election</title>
<link rel="stylesheet" href="assets/css/style.css"></head>
<body data-page="login"><main class="card">
  <h2>Welcome Back!</h2><p class="sub">Log in to vote in the CICS Student Council election.</p>
  <div class="alert" role="alert" hidden></div>
  <form id="loginForm" novalidate>
    <div class="field"><label for="student_id">Student ID</label><input id="student_id" inputmode="numeric" autocomplete="username" placeholder="23-01041" required></div>
    <div class="field"><label for="password">Password</label><input id="password" type="password" autocomplete="current-password" required></div>
    <button class="btn" type="submit" style="width:100%">Log In</button>
  </form>
  <p class="note">No account yet? <a href="index.php">Create an account</a></p>
</main><script src="assets/js/vote.js"></script></body></html>
