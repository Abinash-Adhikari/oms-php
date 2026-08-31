<?php
/**
 * SB-Tech — Reports / Leads & Pipeline.
 * Pipeline value by stage, conversion by source, aging, owner performance.
 */
$db = Database::instance();

// Pipeline by stage
$stages = ['New', 'Contacted', 'Qualified', 'Proposal', 'Won', 'Lost'];
$byStage = [];
foreach ($db->select("SELECT stage, COUNT(*) AS c, COALESCE(SUM(estimated_value), 0) AS val FROM tbl_leads GROUP BY stage") as $r) {
    $byStage[$r['stage']] = ['count' => (int) $r['c'], 'value' => (float) $r['val']];
}

// Conversion by source
$bySource = $db->select(
    "SELECT source,
            COUNT(*) AS total,
            SUM(stage = 'Won') AS won,
            SUM(stage = 'Lost') AS lost,
            COALESCE(SUM(estimated_value), 0) AS total_value
     FROM tbl_leads GROUP BY source ORDER BY total DESC"
);

// Owner performance
$byOwner = $db->select(
    "SELECT u.fullname,
            COUNT(l.id) AS total_leads,
            SUM(l.stage = 'Won') AS won,
            SUM(l.stage = 'Lost') AS lost,
            SUM(l.stage NOT IN ('Won','Lost')) AS active,
            COALESCE(SUM(l.estimated_value), 0) AS pipeline_value,
            COALESCE(SUM(CASE WHEN l.stage = 'Won' THEN l.estimated_value ELSE 0 END), 0) AS won_value
     FROM tbl_leads l
     LEFT JOIN tbl_users_login u ON u.id = l.assigned_to
     GROUP BY l.assigned_to, u.fullname
     ORDER BY won DESC"
);

// Aging (leads with no activity in N days)
$aging7 = (int) ($db->selectOne("SELECT COUNT(*) AS c FROM tbl_leads WHERE stage NOT IN ('Won','Lost') AND (last_activity_on IS NULL OR last_activity_on < DATE_SUB(NOW(), INTERVAL 7 DAY))")['c'] ?? 0);
$aging30 = (int) ($db->selectOne("SELECT COUNT(*) AS c FROM tbl_leads WHERE stage NOT IN ('Won','Lost') AND (last_activity_on IS NULL OR last_activity_on < DATE_SUB(NOW(), INTERVAL 30 DAY))")['c'] ?? 0);

$totalLeads = array_sum(array_column($byStage, 'count'));
$totalValue = array_sum(array_column($byStage, 'value'));
$wonCount = $byStage['Won']['count'] ?? 0;
$lostCount = $byStage['Lost']['count'] ?? 0;
$conversionRate = ($wonCount + $lostCount) > 0 ? round(($wonCount / ($wonCount + $lostCount)) * 100, 1) : 0;
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-funnel-dollar mr-1"></i>Lead Pipeline Report</h3>
        <div class="card-tools">
            <form action="operation.php?module=reports&page=leads_operation" method="post" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="export_leads">
                <button class="btn btn-success btn-sm"><i class="fas fa-download mr-1"></i>CSV</button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <!-- Summary -->
        <div class="row mb-4">
            <div class="col-md-3"><div class="callout callout-info"><h6>Total Leads</h6><h3><?= $totalLeads ?></h3></div></div>
            <div class="col-md-3"><div class="callout callout-success"><h6>Pipeline Value</h6><h3><?= formatMoney($totalValue) ?></h3></div></div>
            <div class="col-md-3"><div class="callout callout-success"><h6>Conversion Rate</h6><h3><?= $conversionRate ?>%</h3></div></div>
            <div class="col-md-3"><div class="callout callout-danger"><h6>Aging (>7 days)</h6><h3><?= $aging7 ?></h3></div></div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Pipeline by Stage -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h5 class="card-title">Pipeline by Stage</h5></div>
            <div class="card-body p-0">
                <table class="table table-sm table-striped">
                    <thead><tr><th>Stage</th><th class="text-center">Count</th><th class="text-right">Value</th><th class="text-right">% of Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($stages as $s):
                        $cnt = $byStage[$s]['count'] ?? 0;
                        $val = $byStage[$s]['value'] ?? 0;
                        $pct = $totalLeads > 0 ? round(($cnt / $totalLeads) * 100, 1) : 0;
                        $badge = ['New' => 'info', 'Contacted' => 'primary', 'Qualified' => 'warning', 'Proposal' => 'secondary', 'Won' => 'success', 'Lost' => 'danger'][$s] ?? 'secondary';
                    ?>
                        <tr>
                            <td><span class="badge badge-<?= $badge ?>"><?= $s ?></span></td>
                            <td class="text-center"><strong><?= $cnt ?></strong></td>
                            <td class="text-right"><?= formatMoney($val) ?></td>
                            <td class="text-right"><?= $pct ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Conversion by Source -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h5 class="card-title">Conversion by Source</h5></div>
            <div class="card-body p-0">
                <table class="table table-sm table-striped">
                    <thead><tr><th>Source</th><th class="text-center">Total</th><th class="text-center">Won</th><th class="text-center">Lost</th><th class="text-right">Value</th></tr></thead>
                    <tbody>
                    <?php foreach ($bySource as $src):
                        $convRate = (($src['won'] + $src['lost']) > 0) ? round(($src['won'] / ($src['won'] + $src['lost'])) * 100, 1) : 0;
                    ?>
                        <tr>
                            <td><strong><?= e($src['source'] ?: 'Unknown') ?></strong></td>
                            <td class="text-center"><?= (int) $src['total'] ?></td>
                            <td class="text-center"><span class="badge badge-success"><?= (int) $src['won'] ?></span></td>
                            <td class="text-center"><span class="badge badge-danger"><?= (int) $src['lost'] ?></span></td>
                            <td class="text-right"><?= formatMoney($src['total_value']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$bySource): ?>
                        <tr><td colspan="5" class="text-muted text-center">No data.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Owner Performance -->
<div class="card">
    <div class="card-header"><h5 class="card-title">Owner Performance</h5></div>
    <div class="card-body p-0">
        <table class="table table-sm table-striped">
            <thead><tr><th>Owner</th><th class="text-center">Total</th><th class="text-center">Won</th><th class="text-center">Lost</th><th class="text-center">Active</th><th class="text-right">Pipeline Value</th><th class="text-right">Won Value</th></tr></thead>
            <tbody>
            <?php foreach ($byOwner as $o): ?>
                <tr>
                    <td><strong><?= e($o['fullname'] ?: 'Unassigned') ?></strong></td>
                    <td class="text-center"><?= (int) $o['total_leads'] ?></td>
                    <td class="text-center"><span class="badge badge-success"><?= (int) $o['won'] ?></span></td>
                    <td class="text-center"><span class="badge badge-danger"><?= (int) $o['lost'] ?></span></td>
                    <td class="text-center"><span class="badge badge-warning"><?= (int) $o['active'] ?></span></td>
                    <td class="text-right"><?= formatMoney($o['pipeline_value']) ?></td>
                    <td class="text-right"><strong><?= formatMoney($o['won_value']) ?></strong></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$byOwner): ?>
                <tr><td colspan="7" class="text-muted text-center">No data.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
