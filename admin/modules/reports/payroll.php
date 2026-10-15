<?php
/**
 * SB-Tech — Reports / Payroll.
 * Cost of people over a tax year from approved / posted / paid runs: where
 * each month's money went (net pay, TDS, funds, other deductions), cost by
 * department, and year-to-date per person. Payroll roles only.
 */
if (!Auth::hasSpecial('manage_payroll') && !Auth::hasSpecial('approve_payroll')) {
    echo '<div class="callout callout-warning"><h5>Payroll access required</h5><p>Salary figures are visible to payroll roles only.</p></div>';
    return;
}
$db = Database::instance();
$today = date('Y-m-d');
$years = $db->select('SELECT * FROM tbl_payroll_tax_years ORDER BY start_date DESC');
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
$runIds = array_map(static fn ($r) => (int) $r['id'], $runs);
$in = $runIds ? implode(',', array_fill(0, count($runIds), '?')) : '0';

$sum = static fn (string $col) => (float) array_sum(array_column($runs, $col));
$gross = $sum('total_gross');
$employerFunds = $sum('total_employer_funds');
$cost = $gross + $employerFunds;
$latest = $runs ? end($runs) : null;
$prev = count($runs) > 1 ? $runs[count($runs) - 2] : null;
$latestCost = $latest ? (float) $latest['total_gross'] + (float) $latest['total_employer_funds'] : 0.0;
$prevCost = $prev ? (float) $prev['total_gross'] + (float) $prev['total_employer_funds'] : 0.0;
$labels = array_map(static fn ($r) => $r['period_label'], $runs);

$byDept = $runIds ? $db->select(
    "SELECT COALESCE(NULLIF(department, ''), 'No department') AS dept, SUM(gross + employer_funds) AS cost, COUNT(DISTINCT staff_id) AS people
     FROM tbl_payroll_run_lines WHERE run_id IN ({$in}) AND excluded = 0 GROUP BY dept ORDER BY cost DESC",
    $runIds
) : [];
$byPerson = $runIds ? $db->select(
    "SELECT staff_id, MAX(fullname) AS fullname, MAX(designation) AS designation, COUNT(*) AS months,
            SUM(gross) AS gross, SUM(employee_funds) AS emp_funds, SUM(employer_funds) AS er_funds,
            SUM(tds) AS tds, SUM(other_deductions) AS other, SUM(net) AS net
     FROM tbl_payroll_run_lines WHERE run_id IN ({$in}) AND excluded = 0 GROUP BY staff_id ORDER BY gross DESC",
    $runIds
) : [];
$money = static fn ($v): string => number_format((float) $v, 2);
?>

<div class="ana-toolbar">
    <div>
        <h2 class="ana-toolbar__title">Payroll<?= $year ? ' — FY ' . e($year['title']) : '' ?></h2>
        <p class="ana-toolbar__sub">Approved, posted and paid runs only · cost to company = gross pay + employer fund contributions</p>
    </div>
    <div class="ana-toolbar__actions">
        <?php if ($years): ?>
            <form method="get">
                <input type="hidden" name="module" value="reports"><input type="hidden" name="page" value="payroll">
                <select name="year" class="form-control form-control-sm" onchange="this.form.submit()" aria-label="Tax year">
                    <?php foreach ($years as $y): ?><option value="<?= (int) $y['id'] ?>" <?= $year && (int) $y['id'] === (int) $year['id'] ? 'selected' : '' ?>>FY <?= e($y['title']) ?></option><?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>
        <a href="<?= e(pageUrl('payroll', 'runs')) ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-file-invoice-dollar mr-1"></i>Payroll runs</a>
    </div>
</div>

<div class="ana-grid">
    <?= stat_tile(['label' => 'Cost to company (YTD)', 'icon' => 'fas fa-building', 'tone' => 'primary',
        'value' => format_compact_money($cost, 'NPR', 'NPR 0'), 'sub' => count($runs) . ' month' . (count($runs) === 1 ? '' : 's') . ' · gross ' . format_compact_money($gross, 'NPR', 'NPR 0'),
        'spark' => array_map(static fn ($r) => (float) $r['total_gross'] + (float) $r['total_employer_funds'], $runs)]) ?>
    <?= stat_tile(['label' => 'Latest month', 'icon' => 'fas fa-calendar-alt', 'tone' => 'info',
        'value' => format_compact_money($latestCost, 'NPR', 'NPR 0'),
        'delta' => $prev ? percent_change($prevCost, $latestCost) : null, 'higher_is_better' => false,
        'delta_label' => $prev ? 'vs ' . $prev['period_label'] : ($latest ? $latest['period_label'] : 'no runs yet')]) ?>
    <?= stat_tile(['label' => 'Average monthly cost', 'icon' => 'fas fa-balance-scale', 'tone' => 'teal',
        'value' => $runs ? format_compact_money($cost / count($runs)) : '—', 'sub' => $latest ? (int) $latest['headcount'] . ' paid in ' . $latest['period_label'] : '']) ?>
    <?= stat_tile(['label' => 'TDS withheld (YTD)', 'icon' => 'fas fa-landmark', 'tone' => 'neutral',
        'value' => format_compact_money($sum('total_tds'), 'NPR', 'NPR 0'), 'sub' => 'see Payroll → Remittance', 'href' => pageUrl('payroll', 'remittance')]) ?>
</div>

<div class="row ana-row">
    <div class="col-lg-8">
        <div class="ana-card">
            <div class="ana-card__head"><div><h3 class="ana-card__title">Where the money went</h3>
                <p class="ana-card__hint">Monthly cost to company, split by destination</p></div></div>
            <div class="ana-chart" style="--h:270px">
                <canvas role="img" aria-label="Stacked bar chart of monthly payroll cost split into net pay, TDS, fund contributions and other deductions"
                        data-chart="<?= chart_attr([
                            'type' => 'bar', 'stacked' => true, 'format' => 'money', 'labels' => $labels,
                            'series' => [
                                ['label' => 'Net pay to staff', 'data' => array_map(static fn ($r) => (float) $r['total_net'], $runs), 'slot' => 1],
                                ['label' => 'TDS', 'data' => array_map(static fn ($r) => (float) $r['total_tds'], $runs), 'slot' => 2],
                                ['label' => 'Funds (employee + employer)', 'data' => array_map(static fn ($r) => (float) $r['total_employee_funds'] + (float) $r['total_employer_funds'], $runs), 'slot' => 3],
                                ['label' => 'Other deductions', 'data' => array_map(static fn ($r) => (float) $r['total_deductions'], $runs), 'slot' => 4],
                            ],
                            'empty' => 'No approved payroll in this tax year yet.',
                        ]) ?>"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="ana-card">
            <div class="ana-card__head"><div><h3 class="ana-card__title">Cost by department</h3><p class="ana-card__hint">Year to date</p></div></div>
            <div class="ana-chart" style="--h:<?= max(200, 40 * count($byDept) + 40) ?>px">
                <canvas role="img" aria-label="Horizontal bar chart of payroll cost per department"
                        data-chart="<?= chart_attr([
                            'type' => 'hbar', 'format' => 'money', 'labels' => array_column($byDept, 'dept'),
                            'series' => [['label' => 'Cost', 'data' => array_map('floatval', array_column($byDept, 'cost')), 'slot' => 1]],
                            'empty' => 'No approved payroll yet.',
                        ]) ?>"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="ana-card ana-card--flush">
    <div class="ana-card__head"><div><h3 class="ana-card__title">By month</h3></div></div>
    <div class="table-responsive">
        <table class="table table-sm ana-table">
            <thead><tr><th>Month</th><th>Status</th><th class="text-right">Staff</th><th class="text-right">Gross</th><th class="text-right">Employer funds</th><th class="text-right">TDS</th><th class="text-right">Net pay</th><th class="text-right">Cost to company</th></tr></thead>
            <tbody>
            <?php foreach ($runs as $r): ?>
                <tr>
                    <td><a href="<?= e(pageUrl('payroll', 'run') . '&id=' . (int) $r['id']) ?>"><strong><?= e($r['period_label']) ?></strong></a></td>
                    <td><span class="ana-pill ana-pill--good"><?= e($r['status']) ?></span></td>
                    <td class="text-right"><?= (int) $r['headcount'] ?></td>
                    <td class="text-right"><?= e($money($r['total_gross'])) ?></td>
                    <td class="text-right"><?= e($money($r['total_employer_funds'])) ?></td>
                    <td class="text-right"><?= e($money($r['total_tds'])) ?></td>
                    <td class="text-right"><?= e($money($r['total_net'])) ?></td>
                    <td class="text-right font-weight-bold"><?= e($money((float) $r['total_gross'] + (float) $r['total_employer_funds'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$runs): ?><tr><td colspan="8" class="text-center text-muted py-4">No approved payroll in this tax year yet.</td></tr><?php endif; ?>
            </tbody>
            <?php if ($runs): ?>
                <tfoot><tr><td colspan="3">Total</td><td class="text-right"><?= e($money($gross)) ?></td><td class="text-right"><?= e($money($employerFunds)) ?></td><td class="text-right"><?= e($money($sum('total_tds'))) ?></td><td class="text-right"><?= e($money($sum('total_net'))) ?></td><td class="text-right"><?= e($money($cost)) ?></td></tr></tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<div class="ana-card ana-card--flush">
    <div class="ana-card__head"><div><h3 class="ana-card__title">By person — year to date</h3></div></div>
    <div class="table-responsive">
        <table class="table table-sm ana-table">
            <thead><tr><th>Staff</th><th class="text-right">Months</th><th class="text-right">Gross</th><th class="text-right">Funds (emp.)</th><th class="text-right">TDS</th><th class="text-right">Other</th><th class="text-right">Net pay</th><th style="width:16%">Share of cost</th></tr></thead>
            <tbody>
            <?php foreach ($byPerson as $p): ?>
                <tr>
                    <td><strong><?= e($p['fullname']) ?></strong><?= $p['designation'] ? '<br><small class="text-muted">' . e($p['designation']) . '</small>' : '' ?></td>
                    <td class="text-right"><?= (int) $p['months'] ?></td>
                    <td class="text-right"><?= e($money($p['gross'])) ?></td>
                    <td class="text-right"><?= e($money($p['emp_funds'])) ?></td>
                    <td class="text-right"><?= e($money($p['tds'])) ?></td>
                    <td class="text-right"><?= e($money($p['other'])) ?></td>
                    <td class="text-right font-weight-bold"><?= e($money($p['net'])) ?></td>
                    <td><?= meter_bar(percent_of((float) $p['gross'] + (float) $p['er_funds'], $cost, 1)) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$byPerson): ?><tr><td colspan="8" class="text-center text-muted py-4">No approved payroll in this tax year yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
