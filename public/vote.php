<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
start_session();
if (empty($_SESSION['block_uid'])) { header('Location: login.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= htmlspecialchars($_SESSION['csrf']) ?>"><title>CICS-SC Election</title>
<link rel="stylesheet" href="assets/css/style.css"></head>
<body data-page="vote">
<header class="topbar"><div class="brand-logos" aria-label="Batangas State University, College of Informatics and Computing Sciences, and CICS Student Council"><img src="assets/img/batstateu-logo.png" alt="Batangas State University logo"><img src="assets/img/cics-logo.png" alt="College of Informatics and Computing Sciences logo"><img src="assets/img/cics-student-council-logo.png" alt="CICS Student Council logo"></div><strong>Per-Block Election</strong><span id="who"></span><a class="btn ghost" href="cics-vote.php">CICS-SC Election</a><button class="btn ghost" id="logout" type="button">Log out</button></header>
<main class="wrap">
  <h2 id="title">Loading…</h2><p class="sub" id="window"></p>
  <p class="dashboard-eyebrow">PER-BLOCK STUDENT VOTING DASHBOARD</p>
  <div class="dashboard-stats" id="dashboardStats" aria-label="Election details"></div>
  <p class="dashboard-hint">Review each candidate’s profile, credentials, and platform before making your selection.</p>
  <div class="alert" role="alert" hidden></div>
  <div id="ballot"></div>
</main>
<dialog id="confirm"><h3>Confirm your ballot</h3><dl class="details" id="summary"></dl>
  <p class="note">Your vote cannot be changed after you submit it.</p>
  <div class="actions"><button class="btn ghost" id="cancel" type="button">Back</button><button class="btn" id="cast" type="button">Submit Vote</button></div></dialog>
<script src="assets/js/vote.js"></script></body></html>
