<?php
/**
 * SB-Tech — Reports / Leads & Pipeline.
 * Stage value + weighted forecast, source mix and win rate, aging
 * distribution of open deals, owner performance, top lost reasons.
 */
$db = Database::instance();

$stages = ['New', 'Contacted', 'Qualified', 'Proposal', 'Won', 'Lost'];
$byStage = [];
foreach ($db->select('SELECT stage, COUNT(*) AS c, COALESCE(SUM(estimated_value), 0) AS val FROM tbl_leads GROUP BY stage') as $r) {
    $byStage[$r['stage']] = ['count' => (int) $r['c'], 'value' => (float) $r['val']];
}
$openValueByStage = [];
$openCount = 0;
foreach (LEAD_OPEN_STAGES as $s) {
    $openValueByStage[$s] = $byStage[$s]['value'] ?? 0.0;
    $openCount += $byStage[$s]['count'] ?? 0;
}
$openValue = array_sum($openValueByStage);
$forecast = weighted_pipeline($openValueByStage);
$totalLeads = array_sum(array_column($byStage, 'count'));
$wonCount = $byStage['Won']['count'] ?? 0;
$lostCount = $byStage['Lost']['count'] ?? 0;
$wonValue = $byStage['Won']['value'] ?? 0.0;
$closed = $wonCount + $lostCount;

$bySource = $db->select(
    "SELECT source,
            COUNT(*) AS total,
            SUM(stage = 'Won') AS won,
            SUM(stage = 'Lost') AS lost,
            SUM(stage NOT IN ('Won','Lost')) AS open_count,
            COALESCE(SUM(estimated_value), 0) AS total_value
     FROM tbl_leads GROUP BY source ORDER BY total DESC"
);

$byOwner = $db->select(
    "SELECT u.fullname,
            COUNT(l.id) AS total_leads,
            SUM(l.stage = 'Won') AS won,
            SUM(l.stage = 'Lost') AS lost,
            SUM(l.stage NOT IN ('Won','Lost')) AS active,
            SUM(l.stage NOT IN ('Won','Lost') AND COALESCE(l.last_activity_on, l.added_on) < DATE_SUB(NOW(), INTERVAL 7 DAY)) AS idle,
            COALESCE(SUM(CASE WHEN l.stage NOT IN ('Won','Lost') THEN l.estimated_value END), 0) AS pipeline_value,
            COALESCE(SUM(CASE WHEN l.stage = 'Won' THEN l.estimated_value ELSE 0 END), 0) AS won_value
     FROM tbl_leads l
     LEFT JOIN tbl_users_login u ON u.id = l.assigned_to
     GROUP BY l.assigned_to, u.fullname
     ORDER BY won_value DESC, total_leads DESC"
);

// Aging: days since the last touch (or creation) for every open deal.
$agingCounts = array_fill_keys(aging_bucket_labels(), 0);
$agingValues = array_fill_keys(aging_bucket_labels(), 0.0);
foreach ($db->select(
    "SELECT DATEDIFF(NOW(), COALESCE(last_activity_on, added_on)) AS d, COALESCE(estimated_value, 0) AS v
     FROM tbl_leads WHERE stage NOT IN ('Won','Lost')"
) as $r) {
    $b = aging_bucket(max(0, (int) $r['d']));
    $agingCounts[$b]++;
    $agingValues[$b] += (float) $r['v'];
}
$idleCount = $openCount - $agingCounts['0–7 days'];

$lostReasons = $db->select(
    "SELECT COALESCE(NULLIF(TRIM(lost_reason), ''), 'No reason given') AS reason, COUNT(*) AS c
     FROM tbl_leads WHERE stage = 'Lost' GROUP BY reason ORDER BY c DESC LIMIT 6"
);

$stageDot = ['New' => 'var(--viz-1)', 'Contacted' => 'var(--viz-1)', 'Qualified' => 'var(--viz-1)', 'Proposal' => 'var(--viz-1)', 'Won' => 'var(--viz-good)', 'Lost' => 'var(--viz-critical)'];
?>

<div class="ana-toolbar">
    <div>
        <h2 class="ana-toolbar__title">Lead pipeline</h2>
        <p class="ana-toolbar__sub">All-time · <?= number_format($totalLeads) ?> leads · forecast weights: <?php
            echo e(implode(', ', array_map(static fn ($s, $p) => $s . ' ' . (int) round($p * 100) . '%', array_keys(LEAD_STAGE_PROBABILITY), LEAD_STAGE_PROBABILITY)));
        ?></p>
    </div>
    <div class="ana-toolbar__actions">
        <a href="<?= pageUrl('sales', 'leads') ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-columns mr-1"></i>Open board</a>
        <form action="operation.php?module=reports&page=leads" method="post" class="d-inline">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="export_leads">
            <button class="btn btn-success btn-sm"><i class="fas fa-download mr-1"></i>CSV</button>
        </form>
    </div>
</div>

<div class="ana-grid">
    <?= stat_tile(['label' => 'Open pipeline', 'icon' => 'fas fa-coins', 'tone' => 'warning',
        'value' => format_compact_money($openValue, 'NPR', 'NPR 0'),
        'sub' => $openCount . ' deals · ' . format_compact_money($forecast, 'NPR', 'NPR 0') . ' weighted']) ?>
    <?= stat_tile(['label' => 'Win rate', 'icon' => 'fas fa-trophy', 'tone' => 'success',
        'value' => $closed > 0 ? percent_of($wonCount, $closed, 1) . '%' : '—',
        'sub' => $closed > 0 ? $wonCount . ' of ' . $closed . ' closed deals' : 'No closed deals yet']) ?>
    <?= stat_tile(['label' => 'Average won deal', 'icon' => 'fas fa-hand-holding-usd', 'tone' => 'teal',
        'value' => $wonCount > 0 ? format_compact_money($wonValue / $wonCount) : '—',
        'sub' => format_compact_money($wonValue, 'NPR', 'NPR 0') . ' won in total']) ?>
    <?= stat_tile(['label' => 'Idle 7+ days', 'icon' => 'fas fa-hourglass-half', 'tone' => $idleCount > 0 ? 'danger' : 'neutral',
        'value' => number_format($idleCount),
        'sub' => percent_of($idleCount, $openCount) . '% of open deals',
        'href' => pageUrl('sales', 'leads') . '&aging=1']) ?>
</div>

<div class="row ana-row">
    <div class="col-lg-5">
        <div class="ana-card">
            <div class="ana-card__head">
                <div>
                    <h3 class="ana-card__title">Open value by stage</h3>
                    <p class="ana-card__hint">Estimated value of open deals</p>
                </div>
            </div>
            <div class="ana-chart" style="--h:230px">
                <canvas role="img" aria-label="Horizontal bar chart of open pipeline value per stage"
                        data-chart="<?= chart_attr([
                            'type' => 'hbar', 'format' => 'money', 'labels' => LEAD_OPEN_STAGES,
                            'series' => [['label' => 'Value', 'data' => array_values($openValueByStage), 'slot' => 1]],
                            'empty' => 'No open deals with a value yet.',
                        ]) ?>"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="ana-card ana-card--flush">
            <div class="ana-card__head">
                <div>
                    <h3 class="ana-card__title">Stage breakdown</h3>
                    <p class="ana-card__hint">Weighted = value × stage win probability</p>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm ana-table">
                    <thead><tr><th>Stage</th><th class="text-right">Deals</th><th class="text-right">Value</th><th class="text-right">Weighted</th><th style="width:30%">Share of leads</th></tr></thead>
                    <tbody>
                    <?php foreach ($stages as $s):
                        $cnt = $byStage[$s]['count'] ?? 0;
                        $val = $byStage[$s]['value'] ?? 0.0;
                        $p = LEAD_STAGE_PROBABILITY[$s] ?? null;
                    ?>
                        <tr>
                            <td><span class="ana-dot" style="background:<?= $stageDot[$s] ?>"></span><?= e($s) ?></td>
                            <td class="text-right"><?= $cnt ?></td>
                            <td class="text-right"><?= e(format_compact_money($val)) ?></td>
                            <td class="text-right"><?= $p !== null ? e(format_compact_money($val * $p)) : '<span class="text-muted">—</span>' ?></td>
                            <td><?= meter_bar(percent_of($cnt, $totalLeads, 1)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr><td>Open total</td><td class="text-right"><?= $openCount ?></td><td class="text-right"><?= e(format_compact_money($openValue)) ?></td><td class="text-right"><?= e(format_compact_money($forecast)) ?></td><td></td></tr></tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row ana-row">
    <div class="col-lg-7">
        <div class="ana-card">
            <div class="ana-card__head">
                <div>
                    <h3 class="ana-card__title">Leads by source</h3>
                    <p class="ana-card__hint">Outcome mix per channel</p>
                </div>
            </div>
            <div class="ana-chart" style="--h:<?= max(200, 46 * count($bySource) + 60) ?>px">
                <canvas role="img" aria-label="Stacked horizontal bar chart of open, won and lost leads per source"
                        data-chart="<?= chart_attr([
                            'type' => 'hbar', 'stacked' => true,
                            'labels' => array_map(static fn ($r) => $r['source'] ?: 'Unknown', $bySource),
                            'series' => [
                                ['label' => 'Open', 'data' => array_map(static fn ($r) => (int) $r['open_count'], $bySource), 'slot' => 1],
                                ['label' => 'Won', 'data' => array_map(static fn ($r) => (int) $r['won'], $bySource), 'status' => 'good'],
                                ['label' => 'Lost', 'data' => array_map(static fn ($r) => (int) $r['lost'], $bySource), 'status' => 'critical'],
                            ],
                            'empty' => 'No leads yet.',
                        ]) ?>"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="ana-card">
            <div class="ana-card__head">
                <div>
                    <h3 class="ana-card__title">Aging of open deals</h3>
                    <p class="ana-card__hint">Days since last activity · open value per band below</p>
                </div>
            </div>
            <div class="ana-chart" style="--h:200px">
                <canvas role="img" aria-label="Bar chart of open deals grouped by days since last activity"
                        data-chart="<?= chart_attr([
                            'type' => 'bar', 'labels' => aging_bucket_labels(),
                            'series' => [['label' => 'Deals', 'data' => array_values($agingCounts), 'slot' => 1]],
                            'empty' => 'No open deals.',
                        ]) ?>"></canvas>
            </div>
            <div class="ana-kv" style="grid-template-columns:repeat(4,minmax(0,1fr))">
                <?php foreach ($agingValues as $label => $v): ?>
                    <div><span class="ana-kv__label"><?= e($label) ?></span><span class="ana-kv__value" style="font-size:.88rem"><?= e(format_compact_money($v)) ?></span></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="row ana-row">
    <div class="col-lg-8">
        <div class="ana-card ana-card--flush">
            <div class="ana-card__head">
                <div>
                    <h3 class="ana-card__title">Owner performance</h3>
                    <p class="ana-card__hint">Win rate = won ÷ (won + lost)</p>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm ana-table">
                    <thead><tr><th>Owner</th><th class="text-right">Leads</th><th class="text-right">Open</th><th class="text-right">Idle</th><th class="text-right">Pipeline</th><th class="text-right">Won value</th><th style="width:22%">Win rate</th></tr></thead>
                    <tbody>
                    <?php foreach ($byOwner as $o):
                        $oClosed = (int) $o['won'] + (int) $o['lost'];
                    ?>
                        <tr>
                            <td><strong><?= e($o['fullname'] ?: 'Unassigned') ?></strong></td>
                            <td class="text-right"><?= (int) $o['total_leads'] ?></td>
                            <td class="text-right"><?= (int) $o['active'] ?></td>
                            <td class="text-right<?= (int) $o['idle'] > 0 ? ' text-danger font-weight-bold' : '' ?>"><?= (int) $o['idle'] ?></td>
                            <td class="text-right"><?= e(format_compact_money((float) $o['pipeline_value'])) ?></td>
                            <td class="text-right"><strong><?= e(format_compact_money((float) $o['won_value'])) ?></strong></td>
                            <td><?= $oClosed > 0 ? meter_bar(percent_of((float) $o['won'], $oClosed), 'good') . '<small class="text-muted">' . (int) $o['won'] . ' of ' . $oClosed . '</small>' : '<span class="text-muted small">No closed deals</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$byOwner): ?>
                        <tr><td colspan="7" class="text-muted text-center py-4">No leads yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="ana-card">
            <div class="ana-card__head">
                <div>
                    <h3 class="ana-card__title">Why deals are lost</h3>
                    <p class="ana-card__hint"><?= (int) $lostCount ?> lost deal<?= $lostCount === 1 ? '' : 's' ?></p>
                </div>
            </div>
            <?php if ($lostReasons): ?>
                <ul class="ana-list">
                    <?php foreach ($lostReasons as $lr): ?>
                        <li>
                            <div class="ana-list__main">
                                <span class="ana-list__title" title="<?= e($lr['reason']) ?>"><?= e($lr['reason']) ?></span>
                                <?= meter_bar(percent_of((float) $lr['c'], (float) $lostCount), 'critical') ?>
                            </div>
                            <span class="ana-pill"><?= (int) $lr['c'] ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="tms-empty-state"><i class="fas fa-smile"></i><p>No lost deals yet.</p></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="ana-card ana-card--flush">
    <div class="ana-card__head">
        <div>
            <h3 class="ana-card__title">Source performance</h3>
            <p class="ana-card__hint">Table view of the source chart</p>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm ana-table">
            <thead><tr><th>Source</th><th class="text-right">Leads</th><th class="text-right">Open</th><th class="text-right">Won</th><th class="text-right">Lost</th><th class="text-right">Value</th><th style="width:22%">Win rate</th></tr></thead>
            <tbody>
            <?php foreach ($bySource as $src):
                $sClosed = (int) $src['won'] + (int) $src['lost'];
            ?>
                <tr>
                    <td><strong><?= e($src['source'] ?: 'Unknown') ?></strong></td>
                    <td class="text-right"><?= (int) $src['total'] ?></td>
                    <td class="text-right"><?= (int) $src['open_count'] ?></td>
                    <td class="text-right"><?= (int) $src['won'] ?></td>
                    <td class="text-right"><?= (int) $src['lost'] ?></td>
                    <td class="text-right"><?= e(format_compact_money((float) $src['total_value'])) ?></td>
                    <td><?= $sClosed > 0 ? meter_bar(percent_of((float) $src['won'], $sClosed), 'good') : '<span class="text-muted small">—</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$bySource): ?>
                <tr><td colspan="7" class="text-muted text-center py-4">No data.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
