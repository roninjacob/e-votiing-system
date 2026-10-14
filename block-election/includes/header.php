<?php
declare(strict_types=1);
$pageTitle = $pageTitle ?? APP_NAME;
$user = current_user();
$currentFile = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));

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
<body>

<!-- ── Top Banner ── -->
<div class="top-banner">
    <div class="logos-group">
        <img src="<?= BASE_URL ?>/assets/img/batstate-logo-main.png?v=<?= filemtime(BASE_PATH . '/assets/img/batstate-logo-main.png') ?>" class="logo" alt="BatStateU" onerror="this.style.display='none'">
        <img src="<?= BASE_URL ?>/assets/img/cics-logo.png?v=<?= filemtime(BASE_PATH . '/assets/img/cics-logo.png') ?>" class="logo" alt="CICS" onerror="this.style.display='none'">
    </div>
    <div class="banner-title">CICS E-Voting System — Batangas State University Arasof Campus</div>
    <div class="logos-group">
        <img src="<?= BASE_URL ?>/assets/img/sc-logo.png?v=<?= filemtime(BASE_PATH . '/assets/img/sc-logo.png') ?>" class="logo" alt="CICS Student Council" onerror="this.style.display='none'">
    </div>
</div>

<!-- ── App Layout ── -->
<div class="app-layout">

    <!-- ── Sidebar ── -->
    <aside class="sidebar">
        <?php if ($user): ?>
        <div class="sidebar-user">
            <div class="cics-badge">
                <img src="<?= BASE_URL ?>/assets/img/sc-logo.png?v=<?= filemtime(BASE_PATH . '/assets/img/sc-logo.png') ?>"
                     style="width:42px;height:42px;object-fit:contain;"
                     alt="SC" onerror="this.outerHTML='<div class=cics-icon>SC</div>'">
                <div>
                    <div class="welcome-name">Welcome, <?= h(explode(' ', $user['full_name'])[0]) ?></div>
                    <?php if (in_array($user['role'], ['admin','adviser','committee'], true)): ?>
                    <div class="student-id"><?= h(ucfirst($user['role'])) ?> Account</div>
                    <?php else: ?>
                    <div class="student-id">Student ID: <?= h($user['student_id']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <nav class="sidebar-nav">
            <?php if ($user && in_array($user['role'], ['admin','adviser','committee'], true)): ?>
            <div class="nav-section">Admin</div>
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
                <span style="background:#e74c3c;color:#fff;border-radius:10px;padding:1px 7px;font-size:.68rem;font-weight:700;margin-left:auto;"><?= $pendingCount ?></span>
                <?php endif; ?>
            </a>
            <div class="nav-section">Voter</div>
            <?php endif; ?>

            <?php if ($user): ?>
            <a href="<?= BASE_URL ?>/voter/elections.php" class="<?= nav_active('elections.php','voter') ?>">
                <i class="bi bi-clipboard2-check"></i> My Ballot
            </a>
            <a href="<?= BASE_URL ?>/voter/elections.php">
                <i class="bi bi-bar-chart-line"></i> Live Turnout
            </a>



            <div class="nav-section">Account</div>
            <a href="<?= BASE_URL ?>/logout.php">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
            <?php endif; ?>
        </nav>
    </aside>

    <!-- ── Main Content ── -->
    <div class="main-content">
        <div class="flash-wrap"><?php render_flash(); ?></div>
