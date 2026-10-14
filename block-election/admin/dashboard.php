<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/services/BlockElectionService.php';

require_role(['admin', 'adviser', 'committee']);

function status_badge(string $status): string {
    $cls = ['draft'=>'badge-draft','configured'=>'badge-configured','open'=>'badge-open','closed'=>'badge-closed','published'=>'badge-published'];
    return '<span class="badge-status ' . ($cls[$status] ?? 'badge-draft') . '">' . h(ucfirst($status)) . '</span>';
}

$svc       = new BlockElectionService();
$stats     = $svc->getStats();
$elections = $svc->getAll();

// Block turnout — BSIT only
$turnoutRows = db()->query(
    'SELECT b.id, b.section, b.year_level, b.semester, b.track,
            COUNT(DISTINCT bv.voter_id) AS voted,
            (SELECT COUNT(*) FROM users u WHERE u.block_id = b.id AND u.role = \'voter\' AND u.is_active = 1) AS total
     FROM blocks b
     LEFT JOIN block_elections be ON be.block_id = b.id AND be.status = \'open\'
     LEFT JOIN block_votes bv ON bv.election_id = be.id
     GROUP BY b.id
     ORDER BY b.year_level, b.semester, b.track, b.section'
)->fetchAll();

// Build sequential block numbers per year+semester+track group for labels
$blockCounters = [];
foreach ($turnoutRows as &$row) {
    $key = $row['year_level'] . '-' . $row['semester'] . '-' . ($row['track'] ?? '');
    $blockCounters[$key] = ($blockCounters[$key] ?? 0) + 1;
    $row['block_num'] = $blockCounters[$key];
}
unset($row);

$auditLog = db()->query(
    'SELECT l.*, be.title AS election_title, u.full_name AS changed_by_name
     FROM block_election_status_logs l
     JOIN block_elections be ON be.id = l.election_id
     LEFT JOIN users u ON u.id = l.changed_by
     ORDER BY l.changed_at DESC LIMIT 8'
)->fetchAll();

// Overall turnout
$totalVoters = db()->query("SELECT COUNT(*) FROM users WHERE role='voter' AND is_active=1")->fetchColumn();
$totalVoted  = db()->query("SELECT COUNT(DISTINCT voter_id) FROM block_votes WHERE status='confirmed'")->fetchColumn();
$turnoutPct  = $totalVoters > 0 ? round(($totalVoted / $totalVoters) * 100) : 0;
$ringPct = max(0, min(100, $turnoutPct));
$recentElections = array_slice($elections, 0, 6);
$dashboardDate = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));

$pageTitle = 'Election Overview';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="dashboard-topline"><span><i class="bi bi-grid" aria-hidden="true"></i> Workspace <span>/</span> Election overview</span><time datetime="<?= $dashboardDate->format('Y-m-d') ?>"><?= $dashboardDate->format('M d, Y') ?></time></div>

<section class="dashboard-hero">
    <div class="dashboard-hero-content">
        <span class="dashboard-eyebrow">ELECTION MANAGEMENT</span>
        <h1>Your campus, <em>at a glance.</em></h1>
        <p>Welcome back, <?= h(explode(' ', trim($user['full_name']))[0]) ?>. Here is what is happening across your elections.</p>
        <span class="dashboard-live-pill"><span class="<?= $stats['open'] > 0 ? 'is-live' : '' ?>" aria-hidden="true"></span><?= $stats['open'] > 0 ? $stats['open'] . ' election' . ($stats['open'] > 1 ? 's' : '') . ' open for voting' : 'No elections currently open' ?></span>
    </div>
    <div class="dashboard-hero-actions">
        <a class="dashboard-button" href="<?= BASE_URL ?>/admin/election_form.php"><i class="bi bi-plus-lg" aria-hidden="true"></i> New election</a>
        <a class="dashboard-button dashboard-button--outline" href="<?= BASE_URL ?>/admin/elections.php">Manage elections <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
    </div>
</section>

<div class="dashboard-stats">
    <?php $cards = [
        ['label'=>'Total elections', 'value'=>$stats['total'], 'icon'=>'bi-stack', 'tone'=>'teal', 'note'=>'Across all blocks'],
        ['label'=>'Open for voting', 'value'=>$stats['open'], 'icon'=>'bi-broadcast', 'tone'=>'green', 'note'=>'Accepting ballots'],
        ['label'=>'In draft', 'value'=>$stats['draft'], 'icon'=>'bi-pencil-square', 'tone'=>'amber', 'note'=>'Being prepared'],
        ['label'=>'Published', 'value'=>$stats['published'], 'icon'=>'bi-patch-check', 'tone'=>'blue', 'note'=>'Results released'],
    ]; foreach ($cards as $card): ?>
    <article class="dashboard-stat">
        <div class="dashboard-stat-top"><span><?= $card['label'] ?></span><span class="dashboard-icon dashboard-icon--<?= $card['tone'] ?>"><i class="bi <?= $card['icon'] ?>" aria-hidden="true"></i></span></div>
        <strong><?= number_format((int) $card['value']) ?></strong><small><?= $card['note'] ?></small>
    </article>
    <?php endforeach; ?>
</div>

<div class="dashboard-columns">
    <div class="dashboard-stack">
        <section class="dashboard-panel" aria-labelledby="recent-elections-heading">
            <div class="dashboard-panel-head"><div><h2 id="recent-elections-heading">Recent elections</h2><p>A quick look at your latest election activity.</p></div><a class="dashboard-text-link" href="<?= BASE_URL ?>/admin/elections.php">View all <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></div>
            <?php if (empty($elections)): ?>
            <div class="dashboard-empty"><span class="dashboard-empty-icon"><i class="bi bi-check2-square" aria-hidden="true"></i></span><h3>Your first election starts here.</h3><p>Create an election to bring your campus community together.</p><a class="dashboard-button" href="<?= BASE_URL ?>/admin/election_form.php">Create an election <i class="bi bi-plus-lg" aria-hidden="true"></i></a></div>
            <?php else: ?>
            <div class="dashboard-table-scroll"><table class="dashboard-table"><caption class="visually-hidden">The six most recently created elections</caption>
                <thead><tr><th scope="col">Election</th><th scope="col">Block</th><th scope="col">Status</th><th scope="col">Period</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody><?php foreach ($recentElections as $el): ?><tr>
                    <td><div class="dashboard-election-title"><span class="dashboard-table-icon"><i class="bi bi-check2-square" aria-hidden="true"></i></span><div><strong><?= h($el['title']) ?></strong><small><?= h($el['created_by_name'] ?? 'Campus election') ?></small></div></div></td>
                    <td><span class="dashboard-block-pill">BSIT <?= h($el['section'] ?? '') ?></span></td>
                    <td><?= status_badge($el['status']) ?></td>
                    <td class="dashboard-table-date"><?= h(date('M d', strtotime($el['start_at']))) ?> &ndash; <?= h(date('M d, Y', strtotime($el['end_at']))) ?></td>
                    <td><a href="<?= BASE_URL ?>/admin/positions.php?election_id=<?= (int) $el['id'] ?>" class="dashboard-icon-link" aria-label="Manage <?= h($el['title']) ?>"><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></td>
                </tr><?php endforeach; ?></tbody>
            </table></div>
            <?php endif; ?>
        </section>

        <section class="dashboard-panel" aria-labelledby="activity-heading">
            <div class="dashboard-panel-head"><div><h2 id="activity-heading">Latest activity</h2><p>Recent updates from your election workspace.</p></div><span class="dashboard-soft-label"><i class="bi bi-clock-history" aria-hidden="true"></i> Status history</span></div>
            <div class="dashboard-panel-body">
                <?php if (empty($auditLog)): ?>
                <div class="dashboard-inline-empty"><i class="bi bi-clock-history" aria-hidden="true"></i><div><strong>A fresh start.</strong><p>Election status updates will appear here.</p></div></div>
                <?php else: ?>
                <ol class="dashboard-timeline"><?php foreach ($auditLog as $log): ?><li>
                    <span class="dashboard-timeline-dot<?= $log['to_status'] === 'open' ? ' is-live' : '' ?>" aria-hidden="true"></span>
                    <div><p><strong><?= h($log['changed_by_name'] ?? 'System') ?></strong> moved <strong><?= h($log['election_title']) ?></strong> to <?= status_badge($log['to_status']) ?>.</p><time datetime="<?= h(date('c', strtotime($log['changed_at']))) ?>"><?= h(date('M d, Y · g:i A', strtotime($log['changed_at']))) ?></time></div>
                </li><?php endforeach; ?></ol>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <div class="dashboard-stack">
        <section class="dashboard-panel dashboard-participation" aria-labelledby="participation-heading">
            <div class="dashboard-panel-head"><div><h2 id="participation-heading">Every voice counts.</h2><p>Overall student participation</p></div><i class="bi bi-people dashboard-heading-icon" aria-hidden="true"></i></div>
            <div class="dashboard-panel-body">
                <div class="dashboard-turnout-ring" style="--turnout:<?= $ringPct ?>%;" role="img" aria-label="<?= $turnoutPct ?> percent student participation"><div><strong><?= $turnoutPct ?><span>%</span></strong><small>Participation</small></div></div>
                <div class="dashboard-turnout-totals"><div><span class="dashboard-legend-dot" aria-hidden="true"></span><strong><?= number_format((int) $totalVoted) ?></strong><small>Students who voted</small></div><div><strong><?= number_format((int) $totalVoters) ?></strong><small>Active students</small></div></div>
                <p class="dashboard-footnote">Based on students with a confirmed vote across all elections.</p>
            </div>
        </section>

        <section class="dashboard-panel" aria-labelledby="block-turnout-heading">
            <div class="dashboard-panel-head"><div><h2 id="block-turnout-heading">Participation by block</h2><p>Turnout in open elections</p></div></div>
            <div class="dashboard-panel-body dashboard-block-list">
                <?php foreach ($turnoutRows as $row):
                    $pct = $row['total'] > 0 ? round(($row['voted'] / $row['total']) * 100) : 0;
                    $label = 'BSIT ' . $row['section'] . ($row['track'] ? ' · ' . $row['track'] : '');
                ?>
                <div class="dashboard-block-progress"><div><strong><?= h($label) ?></strong><span><?= $pct ?>%</span></div><div class="dashboard-progress-track" role="progressbar" aria-label="<?= h($label) ?> turnout" aria-valuenow="<?= max(0, min(100, $pct)) ?>" aria-valuemin="0" aria-valuemax="100"><span style="width:<?= max(0, min(100, $pct)) ?>%;"></span></div><small><?= (int) $row['voted'] ?> of <?= (int) $row['total'] ?> students voted</small></div>
                <?php endforeach; ?>
                <?php if (empty($turnoutRows)): ?><p class="dashboard-muted mb-0">Add blocks to see student participation here.</p><?php endif; ?>
            </div>
        </section>

        <section class="dashboard-quick-links" aria-label="Workspace shortcuts"><span class="dashboard-eyebrow">KEEP THINGS MOVING</span><a href="<?= BASE_URL ?>/admin/students.php"><i class="bi bi-people" aria-hidden="true"></i><span>Manage students<small><?= $pendingCount > 0 ? $pendingCount . ' awaiting verification' : 'Your campus community' ?></small></span><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a><a href="<?= BASE_URL ?>/admin/blocks.php"><i class="bi bi-grid" aria-hidden="true"></i><span>Manage blocks<small>Organize your student groups</small></span><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></section>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
