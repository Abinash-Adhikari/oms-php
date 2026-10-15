<?php
/**
 * SB-Tech — Payroll / Payroll Runs.
 * Start a run for a BS month and see every run with its status and totals.
 * Opening a run (payroll/run&id=) is where lines are reviewed and approved.
 */
if (!Auth::hasSpecial('manage_payroll') && !Auth::hasSpecial('approve_payroll')) {
    echo '<div class="callout callout-warning"><h5>Payroll access required</h5><p>Ask an administrator for the <b>Manage payroll</b> or <b>Approve payroll</b> permission.</p></div>';
    return;
}
$db = Database::instance();
$svc = new PayrollRunService($db);
$today = date('Y-m-d');
$canManage = Auth::hasSpecial('manage_payroll');

$runs = $db->select(
    'SELECT r.*, p.fullname AS prepared_name, a.fullname AS approved_name, t.title AS tax_year_title
     FROM tbl_payroll_runs r
     LEFT JOIN tbl_users_login p ON p.id = r.prepared_by
     LEFT JOIN tbl_users_login a ON a.id = r.approved_by
     LEFT JOIN tbl_payroll_tax_years t ON t.id = r.tax_year_id
     ORDER BY r.period_start DESC, r.id DESC'
);
$taken = [];
foreach ($runs as $r) {
    if ($r['status'] !== 'Cancelled') {
        $taken[$r['period_key']] = $r['status'];
    }
}
$options = $svc->periodOptions($today);
$defaultKey = null;
foreach ($options as $o) {
    if (!isset($taken[$o['key']])) {
        $defaultKey = $o['key'];
        break;
    }
}

// Year-to-date over approved runs in the tax year covering today.
$taxYear = (new PayrollService($db))->taxYearFor($today);
$ytd = $taxYear ? $db->selectOne(
    "SELECT COUNT(*) AS runs, COALESCE(SUM(total_gross), 0) AS gross, COALESCE(SUM(total_tds), 0) AS tds,
            COALESCE(SUM(total_employee_funds + total_employer_funds), 0) AS funds, COALESCE(SUM(total_net), 0) AS net
     FROM tbl_payroll_runs WHERE tax_year_id = ? AND status IN ('Approved','Posted','Paid')",
    [(int) $taxYear['id']]
) : null;
$monthly = [];
foreach (array_reverse(array_filter($runs, static fn ($r) => in_array($r['status'], ['Approved', 'Posted', 'Paid'], true))) as $r) {
    $monthly[] = (float) $r['total_net'];
}
$drafts = count(array_filter($runs, static fn ($r) => $r['status'] === 'Draft'));
$pill = ['Draft' => 'warning', 'Approved' => 'good', 'Posted' => 'good', 'Paid' => 'good', 'Cancelled' => ''];
?>

<div class="ana-toolbar">
    <div>
        <h2 class="ana-toolbar__title">Payroll runs</h2>
        <p class="ana-toolbar__sub">One run per BS month. Draft → review &amp; adjust → a second person approves → payslips go out.</p>
    </div>
    <?php if ($canManage): ?>
        <form action="operation.php?module=payroll&page=runs" method="post" class="ana-toolbar__actions">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">
            <select name="period_key" class="form-control form-control-sm" aria-label="Month to run" required>
                <?php foreach ($options as $o): ?>
                    <option value="<?= e($o['key']) ?>" <?= isset($taken[$o['key']]) ? 'disabled' : '' ?> <?= $o['key'] === $defaultKey ? 'selected' : '' ?>>
                        <?= e($o['label']) ?><?= isset($taken[$o['key']]) ? ' — ' . e($taken[$o['key']]) : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm" <?= $defaultKey === null ? 'disabled' : '' ?>><i class="fas fa-play mr-1"></i>Start payroll</button>
        </form>
    <?php endif; ?>
</div>

<div class="ana-grid">
    <?= stat_tile(['label' => 'Paid this tax year', 'icon' => 'fas fa-money-bill-wave', 'tone' => 'success',
        'value' => format_compact_money((float) ($ytd['net'] ?? 0), 'NPR', 'NPR 0'),
        'sub' => $taxYear ? 'net · FY ' . $taxYear['title'] . ' · ' . (int) ($ytd['runs'] ?? 0) . ' approved runs' : 'no tax year covers today',
        'spark' => $monthly]) ?>
    <?= stat_tile(['label' => 'Gross this tax year', 'icon' => 'fas fa-coins', 'tone' => 'primary',
        'value' => format_compact_money((float) ($ytd['gross'] ?? 0), 'NPR', 'NPR 0')]) ?>
    <?= stat_tile(['label' => 'TDS withheld', 'icon' => 'fas fa-percentage', 'tone' => 'neutral',
        'value' => format_compact_money((float) ($ytd['tds'] ?? 0), 'NPR', 'NPR 0'), 'sub' => 'to remit to IRD']) ?>
    <?= stat_tile(['label' => 'Fund contributions', 'icon' => 'fas fa-piggy-bank', 'tone' => 'teal',
        'value' => format_compact_money((float) ($ytd['funds'] ?? 0), 'NPR', 'NPR 0'), 'sub' => 'employee + employer, to remit']) ?>
</div>

<?php if ($drafts > 0): ?>
    <div class="callout callout-warning small"><?= (int) $drafts ?> draft run<?= $drafts === 1 ? ' is' : 's are' ?> waiting for review and approval.</div>
<?php endif; ?>

<div class="ana-card ana-card--flush">
    <div class="table-responsive">
        <table class="table table-sm ana-table">
            <thead><tr><th>Month</th><th>Status</th><th class="text-right">Staff</th><th class="text-right">Gross</th><th class="text-right">TDS</th><th class="text-right">Net pay</th><th>Prepared / approved</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($runs as $r): ?>
                <tr<?= $r['status'] === 'Cancelled' ? ' class="text-muted"' : '' ?>>
                    <td><strong><?= e($r['period_label']) ?></strong><br><small class="text-muted"><?= e(formatDateView($r['period_start'])) ?> – <?= e(formatDateView($r['period_end'])) ?></small></td>
                    <td><span class="ana-pill<?= $pill[$r['status']] ? ' ana-pill--' . $pill[$r['status']] : '' ?>"><?= e($r['status']) ?></span></td>
                    <td class="text-right"><?= (int) $r['headcount'] ?></td>
                    <td class="text-right"><?= e(formatMoney($r['total_gross'])) ?></td>
                    <td class="text-right"><?= e(formatMoney($r['total_tds'])) ?></td>
                    <td class="text-right font-weight-bold"><?= e(formatMoney($r['total_net'])) ?></td>
                    <td class="small"><?= e($r['prepared_name'] ?? '—') ?><?= $r['approved_name'] ? ' · ' . e($r['approved_name']) : '' ?></td>
                    <td class="text-right"><a class="btn btn-xs btn-outline-primary" href="<?= e(pageUrl('payroll', 'run') . '&id=' . (int) $r['id']) ?>">Open</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$runs): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No payroll runs yet. Set up pay under <a href="<?= e(pageUrl('payroll', 'salary_setup')) ?>">Salary Setup</a>, then start the first month.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
