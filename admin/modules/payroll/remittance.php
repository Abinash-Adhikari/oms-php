<?php
/**
 * SB-Tech — Payroll / Remittance.
 * What is owed to IRD (salary TDS) and to each fund (employee + employer
 * share) per month, from approved / posted / paid runs of a tax year.
 * Pay these through ordinary payment vouchers in Accounts (Dr TDS/fund
 * payable, Cr bank); this page gives the figures and a CSV.
 */
if (!Auth::hasSpecial('manage_payroll') && !Auth::hasSpecial('approve_payroll')) {
    echo '<div class="callout callout-warning"><h5>Payroll access required</h5></div>';
    return;
}
$db = Database::instance();
$years = $db->select('SELECT * FROM tbl_payroll_tax_years ORDER BY start_date DESC');
$today = date('Y-m-d');
$yearId = (int) ($_GET['year'] ?? 0);
$year = null;
foreach ($years as $y) {
    if ($yearId ? (int) $y['id'] === $yearId : ($today >= $y['start_date'] && $today <= $y['end_date'])) {
        $year = $y;
    }
}
$year = $year ?? ($years[0] ?? null);

$runs = $year ? $db->select(
    "SELECT * FROM tbl_payroll_runs WHERE tax_year_id = ? AND status IN ('Approved','Posted','Paid') ORDER BY period_start",
    [(int) $year['id']]
) : [];
$funds = [];
$byRun = [];
if ($runs) {
    $ids = array_map(static fn ($r) => (int) $r['id'], $runs);
    $in = implode(',', array_fill(0, count($ids), '?'));
    foreach ($db->select(
        "SELECT l.run_id, i.code, i.kind, SUM(i.amount) AS total
         FROM tbl_payroll_run_line_items i JOIN tbl_payroll_run_lines l ON l.id = i.line_id
         WHERE l.run_id IN ({$in}) AND l.excluded = 0 AND i.kind IN ('fund_employee','fund_employer')
         GROUP BY l.run_id, i.code, i.kind",
        $ids
    ) as $r) {
        $funds[$r['code']] = true;
        $byRun[(int) $r['run_id']][$r['code']][$r['kind']] = (float) $r['total'];
    }
}
$fundCodes = array_keys($funds);
sort($fundCodes);
$totTds = array_sum(array_map(static fn ($r) => (float) $r['total_tds'], $runs));
$fundTotal = static function (string $code) use ($byRun): float {
    $t = 0.0;
    foreach ($byRun as $f) {
        $t += ($f[$code]['fund_employee'] ?? 0) + ($f[$code]['fund_employer'] ?? 0);
    }
    return $t;
};
$money = static fn ($v): string => number_format((float) $v, 2);
?>

<div class="ana-toolbar">
    <div>
        <h2 class="ana-toolbar__title">Remittance</h2>
        <p class="ana-toolbar__sub">Salary TDS and fund contributions due, by month. Remit them with payment vouchers in Accounts (Dr the payable, Cr bank).</p>
    </div>
    <div class="ana-toolbar__actions">
        <?php if ($years): ?>
            <form method="get">
                <input type="hidden" name="module" value="payroll"><input type="hidden" name="page" value="remittance">
                <select name="year" class="form-control form-control-sm" onchange="this.form.submit()" aria-label="Tax year">
                    <?php foreach ($years as $y): ?>
                        <option value="<?= (int) $y['id'] ?>" <?= $year && (int) $year['id'] === (int) $y['id'] ? 'selected' : '' ?>>FY <?= e($y['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>
        <?php if ($runs): ?>
            <form action="operation.php?module=payroll&page=remittance" method="post">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="export"><input type="hidden" name="year" value="<?= (int) $year['id'] ?>">
                <button class="btn btn-success btn-sm"><i class="fas fa-download mr-1"></i>CSV</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="ana-grid" style="--ana-cols:<?= max(2, min(4, count($fundCodes) + 1)) ?>">
    <?= stat_tile(['label' => 'Salary TDS (IRD)', 'icon' => 'fas fa-landmark', 'tone' => 'primary',
        'value' => format_compact_money($totTds, 'NPR', 'NPR 0'), 'sub' => count($runs) . ' month' . (count($runs) === 1 ? '' : 's') . ($year ? ' · FY ' . $year['title'] : ''),
        'spark' => array_map(static fn ($r) => (float) $r['total_tds'], $runs)]) ?>
    <?php foreach (array_slice($fundCodes, 0, 3) as $code): ?>
        <?= stat_tile(['label' => $code . ' (employee + employer)', 'icon' => 'fas fa-piggy-bank', 'tone' => 'teal',
            'value' => format_compact_money($fundTotal($code), 'NPR', 'NPR 0')]) ?>
    <?php endforeach; ?>
</div>

<div class="ana-card ana-card--flush">
    <div class="table-responsive">
        <table class="table table-sm ana-table">
            <thead>
                <tr><th>Month</th><th>Run</th><th class="text-right">Staff</th><th class="text-right">TDS</th>
                <?php foreach ($fundCodes as $c): ?><th class="text-right"><?= e($c) ?> employee</th><th class="text-right"><?= e($c) ?> employer</th><th class="text-right"><?= e($c) ?> total</th><?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($runs as $r): ?>
                <tr>
                    <td><a href="<?= e(pageUrl('payroll', 'run') . '&id=' . (int) $r['id']) ?>"><strong><?= e($r['period_label']) ?></strong></a></td>
                    <td><span class="ana-pill ana-pill--good"><?= e($r['status']) ?></span></td>
                    <td class="text-right"><?= (int) $r['headcount'] ?></td>
                    <td class="text-right"><?= e($money($r['total_tds'])) ?></td>
                    <?php foreach ($fundCodes as $c):
                        $emp = $byRun[(int) $r['id']][$c]['fund_employee'] ?? 0;
                        $er = $byRun[(int) $r['id']][$c]['fund_employer'] ?? 0;
                    ?>
                        <td class="text-right"><?= e($money($emp)) ?></td><td class="text-right"><?= e($money($er)) ?></td><td class="text-right font-weight-bold"><?= e($money($emp + $er)) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (!$runs): ?>
                <tr><td colspan="<?= 4 + 3 * count($fundCodes) ?>" class="text-center text-muted py-4">No approved payroll in this tax year yet.</td></tr>
            <?php endif; ?>
            </tbody>
            <?php if ($runs): ?>
                <tfoot><tr><td colspan="3">Total</td><td class="text-right"><?= e($money($totTds)) ?></td>
                    <?php foreach ($fundCodes as $c):
                        $emp = array_sum(array_map(static fn ($f) => $f[$c]['fund_employee'] ?? 0, $byRun));
                        $er = array_sum(array_map(static fn ($f) => $f[$c]['fund_employer'] ?? 0, $byRun));
                    ?>
                        <td class="text-right"><?= e($money($emp)) ?></td><td class="text-right"><?= e($money($er)) ?></td><td class="text-right"><?= e($money($emp + $er)) ?></td>
                    <?php endforeach; ?>
                </tr></tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>
