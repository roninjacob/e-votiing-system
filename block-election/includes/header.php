<?php
declare(strict_types=1);
$pageTitle = $pageTitle ?? APP_NAME;
$user = current_user();
$currentFile = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));
$isDashboard = ($currentDir === 'admin' && $currentFile === 'dashboard.php')
    || ($currentDir === 'voter' && $currentFile === 'elections.php');
$isAdmin = $user && in_array($user['role'], ['admin', 'adviser', 'committee'], true);

function nav_active(string $file, string $dir = ''): string {
    global $currentFile, $currentDir;
    if ($dir && $currentDir !== $dir) return '';
    return $currentFile === $file ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> — CICS E-Voting</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= filemtime(BASE_PATH . '/assets/css/style.css') ?>">
</head>
<body class="app-page<?= $isDashboard ? ' app-dashboard' : '' ?>">
<a class="app-skip-link" href="#main-content">Skip to content</a>

<!-- ── Top Banner ── -->
<header class="top-banner app-banner">
    <div class="app-brand">
      <div class="logos-group">
        <img src="<?= BASE_URL ?>/assets/img/batstate-logo-main.png?v=<?= filemtime(BASE_PATH . '/assets/img/batstate-logo-main.png') ?>" class="logo" alt="BatStateU" onerror="this.style.display='none'">
        <img src="<?= BASE_URL ?>/assets/img/cics-logo.png?v=<?= filemtime(BASE_PATH . '/assets/img/cics-logo.png') ?>" class="logo" alt="CICS" onerror="this.style.display='none'">
      </div>
      <div class="app-brand-title"><strong>CICS E-Voting</strong><span>Batangas State University &middot; ARASOF Campus</span></div>
    </div>
    <div class="app-header-actions">
        <span class="app-role-pill"><span aria-hidden="true"></span><?= $isAdmin ? h(ucfirst($user['role'])) . ' workspace' : 'Student workspace' ?></span>
        <img src="<?= BASE_URL ?>/assets/img/student-council-logo.png?v=<?= filemtime(BASE_PATH . '/assets/img/student-council-logo.png') ?>" class="logo" alt="CICS Student Council">
        <button type="button" class="app-nav-toggle" id="navToggle" aria-controls="appSidebar" aria-expanded="false" aria-label="Open navigation"><i class="bi bi-list" aria-hidden="true"></i></button>
    </div>
</header>

<!-- ── App Layout ── -->
<div class="app-layout">

    <!-- ── Sidebar ── -->
    <aside class="sidebar" id="appSidebar" aria-label="Workspace navigation">
        <?php if ($user): ?>
        <div class="sidebar-user">
            <div class="cics-badge">
                <span class="app-user-avatar" aria-hidden="true"><?= h(strtoupper(substr(trim($user['full_name']), 0, 1))) ?></span>
                <div>
                    <div class="welcome-name"><?= h($user['full_name']) ?></div>
                    <?php if (in_array($user['role'], ['admin','adviser','committee'], true)): ?>
                    <div class="student-id"><?= h(ucfirst($user['role'])) ?> Account</div>
                    <?php else: ?>
                    <div class="student-id">Student ID: <?= h($user['student_id']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <nav class="sidebar-nav" aria-label="Main navigation">
            <?php if ($user && in_array($user['role'], ['admin','adviser','committee'], true)): ?>
            <div class="nav-section">Election management</div>
            <a href="<?= BASE_URL ?>/admin/dashboard.php" class="<?= nav_active('dashboard.php','admin') ?>">
                <i class="bi bi-speedometer2"></i> Election Overview
            </a>
            <a href="<?= BASE_URL ?>/admin/elections.php" class="<?= nav_active('elections.php','admin') ?>">
                <i class="bi bi-list-check"></i> Manage Elections
            </a>
            <a href="<?= BASE_URL ?>/admin/blocks.php" class="<?= nav_active('blocks.php','admin') ?>">
                <i class="bi bi-grid-3x3-gap"></i> Manage Blocks
            </a>
            <a href="<?= BASE_URL ?>/admin/students.php" class="<?= nav_active('students.php','admin') ?>">
                <i class="bi bi-people"></i> Manage Students
                <?php
                $pendingCount = db()->query("SELECT COUNT(*) FROM users WHERE role='voter' AND is_verified=0")->fetchColumn();
                if ($pendingCount > 0): ?>
                <span class="app-nav-count" title="Students awaiting verification"><?= $pendingCount ?></span>
                <?php endif; ?>
            </a>
            <div class="nav-section">Voter</div>
            <?php endif; ?>

            <?php if ($user): ?>
            <a href="<?= BASE_URL ?>/voter/elections.php" class="<?= nav_active('elections.php','voter') ?>">
                <i class="bi bi-clipboard2-check"></i> <?= $isAdmin ? 'Student elections' : 'My dashboard' ?>
            </a>
            <a href="<?= BASE_URL ?>/voter/elections.php#student-election-list">
                <i class="bi bi-check2-square"></i> My Ballot
            </a>



            <div class="nav-section">Account</div>
            <a href="<?= BASE_URL ?>/logout.php">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
            <?php endif; ?>
        </nav>
        <div class="app-sidebar-note"><img src="<?= BASE_URL ?>/assets/img/student-council-logo.png" alt=""><strong>Your campus.<br>Your voice.</strong><span>Building our community,<br>one vote at a time.</span></div>
    </aside>

    <!-- ── Main Content ── -->
    <main class="main-content" id="main-content">
        <div class="flash-wrap"><?php render_flash(); ?></div>
