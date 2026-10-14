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

$pageTitle = 'Election Overview';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <h2>Live Election Overview</h2>
    <div class="election-status-bar">
        <?php if ($stats['open'] > 0): ?>
        <div class="status-dot"></div>
        <span class="status-label">Election in Progress</span>
        <span class="status-timer">| <?= $stats['open'] ?> active election<?= $stats['open'] > 1 ? 's' : '' ?></span>
        <?php else: ?>
        <span class="status-label" style="color:var(--text-muted);">No Active Elections</span>
        <?php endif; ?>
    </div>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['label'=>'Total Elections', 'value'=>$stats['total'],     'icon'=>'bi-ballot',        'bg'=>'rgba(240,165,0,.15)',   'color'=>'#f0a500'],
        ['label'=>'Open',            'value'=>$stats['open'],      'icon'=>'bi-unlock-fill',   'bg'=>'rgba(46,204,113,.15)', 'color'=>'#2ecc71'],
        ['label'=>'Draft',           'value'=>$stats['draft'],     'icon'=>'bi-pencil-square', 'bg'=>'rgba(150,150,150,.15)','color'=>'#aaa'],
        ['label'=>'Published',       'value'=>$stats['published'], 'icon'=>'bi-check-circle',  'bg'=>'rgba(100,200,255,.15)','color'=>'#7dd3fc'],
    ];
    foreach ($cards as $c): ?>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:<?= $c['bg'] ?>;color:<?= $c['color'] ?>;">
                <i class="bi <?= $c['icon'] ?>"></i>
            </div>
            <div>
                <div class="stat-value"><?= $c['value'] ?></div>
                <div class="stat-label"><?= $c['label'] ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <!-- Left: Voter Turnout + Block Breakdown -->
    <div class="col-lg-5">
        <div class="dark-card mb-4">
            <div class="card-head">Voter Turnout</div>
            <div class="card-body-inner">
                <div class="turnout-wrap mb-3">
                    <div class="turnout-pct"><?= $turnoutPct ?>%</div>
                    <div class="turnout-sub"><?= number_format((int)$totalVoted) ?> of <?= number_format((int)$totalVoters) ?> votes cast</div>
                </div>
                <!-- SVG arc gauge -->
                <div class="text-center mb-2">
                    <svg width="160" height="90" viewBox="0 0 160 90">
                        <path d="M10,80 A70,70 0 0,1 150,80" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="14" stroke-linecap="round"/>
                        <?php
                        $angle = min($turnoutPct / 100, 1);
                        $rad   = M_PI * $angle;
                        $ex    = 80 - 70 * cos($rad);
                        $ey    = 80 - 70 * sin($rad);
                        $large = $angle > 0.5 ? 1 : 0;
                        if ($turnoutPct > 0):
                        ?>
                        <path d="M10,80 A70,70 0 <?= $large ?>,1 <?= round($ex,2) ?>,<?= round($ey,2) ?>"
                              fill="none" stroke="#f0a500" stroke-width="14" stroke-linecap="round"/>
                        <?php endif; ?>
                    </svg>
                </div>
            </div>
        </div>

        <div class="dark-card">
            <div class="card-head">
                BSIT Student Council Turnout
            </div>
            <div class="card-body-inner">
                <?php foreach ($turnoutRows as $row):
                    $pct   = $row['total'] > 0 ? round(($row['voted'] / $row['total']) * 100) : 0;
                    $label = $row['section'];
                    if ($row['track']) $label .= ' ' . $row['track'];
                    $sub   = $row['voted'] . '/' . $row['total'] . ' voted';
                ?>
                <div class="prog-row">
                    <div class="prog-label">
                        <span><?= h($label) ?></span>
                        <span style="color:var(--text-muted);font-size:.72rem;"><?= $sub ?> &nbsp;<strong style="color:#fff;"><?= $pct ?>%</strong></span>
                    </div>
                    <div class="prog-bar-track">
                        <div class="prog-bar-fill" style="width:<?= $pct ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($turnoutRows)): ?>
                <p style="color:var(--text-muted);font-size:.82rem;">No block data available.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right: Elections Table + Activity Feed -->
    <div class="col-lg-7">
        <div class="dark-card mb-4">
            <div class="card-head">
                Elections
                <a href="<?= BASE_URL ?>/admin/election_form.php" class="btn-gold" style="padding:5px 14px;font-size:.78rem;">
                    <i class="bi bi-plus-lg"></i> New
                </a>
            </div>
            <div style="overflow-x:auto;">
                <table class="dark-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Block</th>
                            <th>Status</th>
                            <th>Period</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($elections)): ?>
                        <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:24px;">No elections yet.</td></tr>
                    <?php else: foreach ($elections as $el): ?>
                        <tr>
                            <td style="font-weight:600;"><?= h($el['title']) ?></td>
                            <td style="color:var(--text-muted);">BSIT <?= h($el['section'] ?? '') ?></td>
                            <td><?= status_badge($el['status']) ?></td>
                            <td style="color:var(--text-muted);font-size:.78rem;">
                                <?= h(date('M d', strtotime($el['start_at']))) ?> –
                                <?= h(date('M d', strtotime($el['end_at']))) ?>
                            </td>
                            <td>
                                <a href="<?= BASE_URL ?>/admin/positions.php?election_id=<?= $el['id'] ?>"
                                   class="btn-sm-icon" title="Manage"><i class="bi bi-gear"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Real-Time Activity Feed -->
        <div class="dark-card">
            <div class="card-head">Real-Time Activity Feed</div>
            <div class="card-body-inner">
                <?php if (empty($auditLog)): ?>
                <p style="color:var(--text-muted);font-size:.82rem;">No activity yet.</p>
                <?php else: foreach ($auditLog as $log): ?>
                <div class="feed-item">
                    <div class="feed-dot <?= $log['to_status'] === 'open' ? 'green' : ($log['to_status'] === 'closed' ? 'gold' : 'red') ?>"></div>
                    <div>
                        <div class="feed-text">
                            <strong><?= h($log['changed_by_name'] ?? 'System') ?></strong>
                            advanced <em><?= h($log['election_title']) ?></em>
                            to <strong><?= h($log['to_status']) ?></strong>
                        </div>
                        <div class="feed-time"><?= h(date('M d, Y H:i', strtotime($log['changed_at']))) ?></div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
