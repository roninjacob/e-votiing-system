<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/services/BlockEligibilityService.php';
require_once dirname(__DIR__) . '/services/BlockBallotService.php';

require_login();

$user      = current_user();
$eligSvc   = new BlockEligibilityService();
$ballotSvc = new BlockBallotService();
$elections = $eligSvc->getVisibleElections((int) $user['block_id']);

foreach ($elections as &$el) {
    $el['has_voted'] = $ballotSvc->hasVoted((int) $el['id'], (int) $user['id']);
}
unset($el);
$readyCount = count(array_filter($elections, static fn(array $el): bool => $el['status'] === 'open' && !$el['has_voted']));
$submittedCount = count(array_filter($elections, static fn(array $el): bool => $el['has_voted']));
$blockStmt = db()->prepare('SELECT section, year_level, track FROM blocks WHERE id = ?');
$blockStmt->execute([(int) $user['block_id']]);
$studentBlock = $blockStmt->fetch();
$dashboardDate = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));

function status_badge(string $status): string {
    $cls = ['draft'=>'badge-draft','configured'=>'badge-configured','open'=>'badge-open','closed'=>'badge-closed','published'=>'badge-published'];
    return '<span class="badge-status ' . ($cls[$status] ?? 'badge-draft') . '">' . h(ucfirst($status)) . '</span>';
}

$pageTitle = 'Student Dashboard';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="dashboard-topline"><span><i class="bi bi-grid" aria-hidden="true"></i> Student workspace <span>/</span> My dashboard</span><time datetime="<?= $dashboardDate->format('Y-m-d') ?>"><?= $dashboardDate->format('M d, Y') ?></time></div>

<section class="dashboard-hero dashboard-hero--student">
    <div class="dashboard-hero-content">
        <span class="dashboard-eyebrow">HELLO, <?= h(strtoupper(explode(' ', trim($user['full_name']))[0])) ?>.</span>
        <h1>Your voice <em>makes a difference.</em></h1>
        <p>A stronger campus starts with you. Meet your future leaders and take part in shaping our community.</p>
        <a class="dashboard-button" href="#student-election-list"><?= $readyCount > 0 ? 'Explore your ballots' : 'Explore your elections' ?> <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
    </div>
    <div class="student-hero-art" aria-hidden="true"><div class="student-hero-orbit"></div><span class="student-hero-ballot"><i class="bi bi-check2-square"></i></span><span class="student-hero-spark"><i class="bi bi-stars"></i></span><span class="student-hero-art-label">ONE VOTE. A SHARED FUTURE.</span></div>
</section>

<div class="dashboard-stats dashboard-stats--student">
    <?php $cards = [
        ['label'=>'Ready to vote', 'value'=>$readyCount, 'icon'=>'bi-check2-square', 'tone'=>'teal', 'note'=>'Open ballots waiting for you'],
        ['label'=>'Votes submitted', 'value'=>$submittedCount, 'icon'=>'bi-check-circle', 'tone'=>'green', 'note'=>'Your participation so far'],
        ['label'=>'Available elections', 'value'=>count($elections), 'icon'=>'bi-stack', 'tone'=>'blue', 'note'=>'Elections for your block'],
    ]; foreach ($cards as $card): ?>
    <article class="dashboard-stat"><div class="dashboard-stat-top"><span><?= $card['label'] ?></span><span class="dashboard-icon dashboard-icon--<?= $card['tone'] ?>"><i class="bi <?= $card['icon'] ?>" aria-hidden="true"></i></span></div><strong><?= $card['value'] ?></strong><small><?= $card['note'] ?></small></article>
    <?php endforeach; ?>
</div>

<div class="dashboard-columns dashboard-columns--student">
    <section id="student-election-list" aria-labelledby="student-elections-heading">
        <div class="dashboard-section-head"><div><span class="dashboard-eyebrow">YOUR NEXT CHAPTER</span><h2 id="student-elections-heading">My block elections</h2><p>Find your ballot and make your choice count.</p></div><span class="dashboard-soft-label"><?= count($elections) ?> election<?= count($elections) !== 1 ? 's' : '' ?></span></div>
        <?php if (empty($elections)): ?>
        <div class="dashboard-panel dashboard-empty"><span class="dashboard-empty-icon"><i class="bi bi-inbox" aria-hidden="true"></i></span><h3>Something good is on the way.</h3><p>No elections are available for your block yet. Check back here when your campus is ready to vote.</p><span class="dashboard-soft-label"><i class="bi bi-clock" aria-hidden="true"></i> Stay tuned</span></div>
        <?php else: ?>
        <div class="student-election-grid">
            <?php foreach ($elections as $el): ?>
            <article class="student-election-card<?= $el['status'] === 'open' && !$el['has_voted'] ? ' is-open' : '' ?>">
                <div class="student-election-top"><span class="dashboard-icon dashboard-icon--<?= $el['has_voted'] ? 'green' : 'teal' ?>"><i class="bi <?= $el['has_voted'] ? 'bi-check-circle' : 'bi-check2-square' ?>" aria-hidden="true"></i></span><?= status_badge($el['status']) ?></div>
                <span class="student-election-block">BSIT <?= h($el['section']) ?> &middot; Year <?= (int) $el['year_level'] ?></span>
                <h3><?= h($el['title']) ?></h3>
                <p class="student-election-description"><?= h($el['description'] ?: 'Help choose the representatives who will lead your block and serve your community.') ?></p>
                <div class="student-election-dates"><div><span><i class="bi bi-calendar2" aria-hidden="true"></i> Opens</span><strong><?= h(date('M d, Y', strtotime($el['start_at']))) ?></strong><small><?= h(date('g:i A', strtotime($el['start_at']))) ?></small></div><div><span><i class="bi bi-calendar2-check" aria-hidden="true"></i> Closes</span><strong><?= h(date('M d, Y', strtotime($el['end_at']))) ?></strong><small><?= h(date('g:i A', strtotime($el['end_at']))) ?></small></div></div>
                <div class="student-election-action">
                    <?php if ($el['has_voted']): ?>
                    <div class="student-vote-submitted"><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span>Vote submitted<small>Thank you for taking part.</small></span></div>
                    <?php elseif ($el['status'] === 'open'): ?>
                    <a href="<?= BASE_URL ?>/voter/ballot.php?election_id=<?= (int) $el['id'] ?>" class="dashboard-button">Vote now <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                    <?php else: ?>
                    <span class="student-voting-closed"><i class="bi bi-lock" aria-hidden="true"></i> <?= $el['status'] === 'published' ? 'Results have been published' : 'Voting has closed' ?></span>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <aside class="dashboard-stack" aria-label="Student voting guide">
        <section class="dashboard-panel student-guide"><div class="dashboard-panel-head"><div><span class="dashboard-eyebrow">A LITTLE GUIDANCE</span><h2>Make your vote count.</h2></div></div><div class="dashboard-panel-body"><ol><li><span>01</span><div><strong>Get to know the candidates</strong><p>Read their platforms and see what they stand for.</p></div></li><li><span>02</span><div><strong>Choose your representatives</strong><p>Select the people you want to lead your block.</p></div></li><li><span>03</span><div><strong>Review, then submit</strong><p>Check your choices before sending your ballot.</p></div></li></ol><div class="student-guide-tip"><i class="bi bi-lightbulb" aria-hidden="true"></i><p>Take your time. Your choices help shape the CICS community.</p></div></div></section>
        <section class="student-community-card"><img src="<?= BASE_URL ?>/assets/img/cics-logo.png?v=<?= filemtime(BASE_PATH . '/assets/img/cics-logo.png') ?>" alt="CICS"><span class="dashboard-eyebrow">YOUR COMMUNITY</span><h2><?= $studentBlock ? 'BSIT ' . h($studentBlock['section']) : 'CICS student community' ?></h2><p><?= $studentBlock ? h(section_label($studentBlock['section'], $studentBlock['track'])) : 'Your block elections will appear here once a block is assigned.' ?></p><span class="student-community-id"><i class="bi bi-person-badge" aria-hidden="true"></i> <?= h($user['student_id']) ?></span></section>
    </aside>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
