<?php
/**
 * SB-Tech — Payroll / Salary Setup.
 * List: every active staff member's pay basis and a monthly estimate.
 * Edit (&id=): salary or fixed stipend, tax on/off, unpaid-day handling,
 * allowances/deductions, fund enrollments (SSF / PF / CIT / custom) and a
 * payslip preview computed by the same calculator the payroll run uses.
 */
if (!Auth::hasSpecial('manage_payroll')) {
    echo '<div class="callout callout-warning"><h5>Payroll access required</h5><p>Ask an administrator for the <b>Manage payroll</b> permission.</p></div>';
    return;
}
$db = Database::instance();
$svc = new PayrollService($db);
$today = date('Y-m-d');
$taxYear = $svc->taxYearFor($today);
$base = pageUrl('payroll', 'salary_setup');
$money = static fn ($v) => number_format((float) $v, 2);

// ================================================================== edit
if (isset($_GET['id'])) {
    $staff = $db->selectOne(
        "SELECT u.*, d.title AS designation, dp.title AS department
         FROM tbl_users_login u
         LEFT JOIN tbl_office_designation d ON d.id = u.designation_id
         LEFT JOIN tbl_office_departments dp ON dp.id = u.department_id
         WHERE u.id = ? AND u.status <> 'Terminated'",
        [(int) $_GET['id']]
    );
    if (!$staff) {
        echo '<div class="callout callout-danger"><h5>Staff member not found</h5></div>';
        return;
    }
    $current = $svc->structureFor((int) $staff['id'], $today)
        ?? $db->selectOne('SELECT * FROM tbl_staff_salary_structures WHERE staff_id = ? ORDER BY effective_from ASC LIMIT 1', [(int) $staff['id']]);
    $itemValues = [];
    if ($current) {
        foreach ($db->select('SELECT component_id, value FROM tbl_staff_salary_structure_items WHERE structure_id = ?', [(int) $current['id']]) as $it) {
            $itemValues[(int) $it['component_id']] = (float) $it['value'];
        }
    }
    $components = $db->select('SELECT * FROM tbl_payroll_components WHERE is_active = 1 ORDER BY kind, position, name');
    $funds = $db->select('SELECT * FROM tbl_payroll_funds WHERE is_active = 1 ORDER BY code');
    $enrolled = [];
    foreach ($db->select('SELECT * FROM tbl_staff_fund_enrollments WHERE staff_id = ?', [(int) $staff['id']]) as $en) {
        $enrolled[(int) $en['fund_id']] = $en;
    }
    $history = $db->select('SELECT * FROM tbl_staff_salary_structures WHERE staff_id = ? ORDER BY effective_from DESC', [(int) $staff['id']]);
    $estimate = $current ? $svc->estimate($staff, $today, $taxYear) : null;
    $memberNoFallback = ['SSF' => $staff['ssf_number'] ?? '', 'PF' => $staff['pf_number'] ?? '', 'CIT' => $staff['cit_number'] ?? ''];
    $payType = (string) old('pay_type', $current['pay_type'] ?? ($staff['employment_type'] === 'Intern' ? 'Stipend' : 'Salary'));
    ?>
    <div class="d-flex align-items-center mb-3">
        <a href="<?= e($base) ?>" class="btn btn-sm btn-outline-secondary mr-2" aria-label="Back to salary setup"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h2 class="ana-toolbar__title mb-0"><?= e($staff['fullname']) ?></h2>
            <p class="ana-toolbar__sub mb-0">
                <?= e(implode(' · ', array_filter([$staff['designation'], $staff['department'], $staff['employment_type'], 'tax filing: ' . $staff['tax_filing_status'], $staff['gender'] ?: null]))) ?>
                · <a href="<?= pageUrl('staff_management', 'add_staff') ?>&id=<?= (int) $staff['id'] ?>">edit staff details</a>
            </p>
        </div>
    </div>

    <div class="row ana-row">
        <div class="col-lg-7">
            <form action="operation.php?module=payroll&page=salary_setup" method="post" class="ana-card">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save_structure">
                <input type="hidden" name="staff_id" value="<?= (int) $staff['id'] ?>">

                <div class="ana-card__head"><div><h3 class="ana-card__title">Pay</h3>
                    <p class="ana-card__hint">A new “effective from” date keeps the previous pay as history; the same date updates it.</p></div></div>
                <div class="form-row">
                    <div class="form-group col-md-4"><label class="small mb-1">Effective from</label>
                        <input type="date" name="effective_from" class="form-control form-control-sm" required value="<?= e(old('effective_from', $current['effective_from'] ?? ($staff['join_date'] ?: $today))) ?>"></div>
                    <div class="form-group col-md-4"><label class="small mb-1">Pay type</label>
                        <select name="pay_type" id="payType" class="form-control form-control-sm">
                            <option value="Salary" <?= $payType === 'Salary' ? 'selected' : '' ?>>Salary</option>
                            <option value="Stipend" <?= $payType === 'Stipend' ? 'selected' : '' ?>>Fixed stipend</option>
                        </select></div>
                    <div class="form-group col-md-4"><label class="small mb-1" id="basicLabel"><?= $payType === 'Stipend' ? 'Monthly stipend' : 'Basic salary / month' ?></label>
                        <input type="number" step="0.01" min="0" name="basic" class="form-control form-control-sm" required value="<?= e(old('basic', isset($current['basic']) ? (float) $current['basic'] : '')) ?>"></div>
                </div>
                <div class="custom-control custom-switch mb-1">
                    <input type="checkbox" class="custom-control-input" id="taxApplicable" name="tax_applicable" value="1" <?= old_checkbox('save_structure', 'tax_applicable', (bool) ($current['tax_applicable'] ?? ($staff['employment_type'] !== 'Intern'))) ? 'checked' : '' ?>>
                    <label class="custom-control-label" for="taxApplicable">Deduct income tax (TDS)</label>
                </div>
                <div class="custom-control custom-switch mb-3">
                    <input type="checkbox" class="custom-control-input" id="deductUnpaid" name="deduct_unpaid_days" value="1" <?= old_checkbox('save_structure', 'deduct_unpaid_days', (bool) ($current['deduct_unpaid_days'] ?? true)) ? 'checked' : '' ?>>
                    <label class="custom-control-label" for="deductUnpaid">Reduce pay for unpaid days (absences, unpaid or unapproved leave)</label>
                </div>

                <h4 class="ana-section-title">Allowances &amp; deductions</h4>
                <?php if ($components): ?>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm ana-table">
                            <thead><tr><th>Component</th><th>Type</th><th style="width:34%">Amount</th></tr></thead>
                            <tbody>
                            <?php foreach ($components as $c): ?>
                                <tr>
                                    <td><?= e($c['name']) ?><?= !$c['prorate'] ? ' <small class="text-muted">(not cut for unpaid days)</small>' : '' ?></td>
                                    <td class="small"><?= $c['kind'] === 'earning' ? 'Earning' . ($c['taxable'] ? '' : ', tax-free') : 'Deduction' ?></td>
                                    <td><div class="input-group input-group-sm">
                                        <input type="number" step="0.01" min="0" name="component[<?= (int) $c['id'] ?>]" class="form-control" placeholder="Not applied"
                                               value="<?= isset($itemValues[(int) $c['id']]) ? e($itemValues[(int) $c['id']]) : '' ?>" aria-label="<?= e($c['name']) ?>">
                                        <div class="input-group-append"><span class="input-group-text"><?= $c['calc'] === 'percent_of_basic' ? '% of basic' : 'NPR' ?></span></div>
                                    </div></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted small">No components yet — add them under <a href="<?= pageUrl('payroll', 'components') ?>">Pay Components</a>.</p>
                <?php endif; ?>

                <h4 class="ana-section-title">Funds</h4>
                <p class="small text-muted">Tick to enrol. Leave a rate blank to use the fund’s default; a fixed amount (e.g. a CIT pledge) replaces the employee rate.</p>
                <?php foreach ($funds as $f):
                    $en = $enrolled[(int) $f['id']] ?? null;
                    $fid = (int) $f['id'];
                ?>
                    <div class="border rounded p-2 mb-2">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="fund<?= e($fid) ?>" name="fund[<?= e($fid) ?>][enrolled]" value="1" <?= $en ? 'checked' : '' ?>>
                            <label class="custom-control-label font-weight-bold" for="fund<?= e($fid) ?>"><?= e($f['code']) ?> · <?= e($f['name']) ?></label>
                            <small class="text-muted ml-1">default <?= e((float) $f['employee_rate']) ?>% + <?= e((float) $f['employer_rate']) ?>% of <?= e($f['contribution_base']) ?></small>
                        </div>
                        <div class="form-row mt-2">
                            <div class="col-md-3 mb-1"><input type="text" name="fund[<?= e($fid) ?>][member_no]" class="form-control form-control-sm" placeholder="Member no." value="<?= e($en['member_no'] ?? ($memberNoFallback[$f['code']] ?? '')) ?>" aria-label="<?= e($f['code']) ?> member number"></div>
                            <div class="col-md-3 mb-1"><input type="number" step="0.0001" min="0" max="100" name="fund[<?= e($fid) ?>][employee_rate]" class="form-control form-control-sm" placeholder="Employee %" value="<?= isset($en['employee_rate']) ? e((float) $en['employee_rate']) : '' ?>" aria-label="<?= e($f['code']) ?> employee rate override"></div>
                            <div class="col-md-3 mb-1"><input type="number" step="0.01" min="0" name="fund[<?= e($fid) ?>][employee_fixed]" class="form-control form-control-sm" placeholder="or fixed NPR" value="<?= isset($en['employee_fixed']) ? e((float) $en['employee_fixed']) : '' ?>" aria-label="<?= e($f['code']) ?> fixed employee amount"></div>
                            <div class="col-md-3 mb-1"><input type="number" step="0.0001" min="0" max="100" name="fund[<?= e($fid) ?>][employer_rate]" class="form-control form-control-sm" placeholder="Employer %" value="<?= isset($en['employer_rate']) ? e((float) $en['employer_rate']) : '' ?>" aria-label="<?= e($f['code']) ?> employer rate override"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$funds): ?>
                    <p class="text-muted small">No active funds — add them under <a href="<?= pageUrl('payroll', 'funds') ?>">Funds</a>.</p>
                <?php endif; ?>

                <div class="form-group mt-3"><label class="small mb-1">Notes</label>
                    <input type="text" name="notes" maxlength="500" class="form-control form-control-sm" value="<?= e(old('notes', $current['notes'] ?? '')) ?>"></div>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i>Save pay setup</button>
            </form>
        </div>

        <div class="col-lg-5">
            <div class="ana-card">
                <div class="ana-card__head"><div><h3 class="ana-card__title">Estimated monthly payslip</h3>
                    <p class="ana-card__hint">Full month worked · tax annualised<?= $taxYear ? ' with FY ' . e($taxYear['title']) . ' slabs' : '' ?> · saved settings</p></div></div>
                <?php if (!$estimate): ?>
                    <div class="tms-empty-state"><i class="fas fa-calculator"></i><p>Save a pay setup to see the estimate.</p></div>
                <?php else: ?>
                    <?php if (!$taxYear && $current['tax_applicable']): ?>
                        <div class="alert alert-warning small">No tax year covers today — TDS shows 0. Add one under <a href="<?= pageUrl('payroll', 'tax_slabs') ?>">Tax Slabs</a>.</div>
                    <?php endif; ?>
                    <?php foreach ($estimate['warnings'] as $w): ?>
                        <div class="alert alert-warning small"><?= e($w) ?></div>
                    <?php endforeach; ?>
                    <table class="table table-sm ana-table mb-2">
                        <tbody>
                        <?php foreach ($estimate['earnings'] as $er): ?>
                            <tr><td><?= e($er['name']) ?><?= !$er['taxable'] ? ' <small class="text-muted">tax-free</small>' : '' ?></td><td class="text-right"><?= e($money($er['amount'])) ?></td></tr>
                        <?php endforeach; ?>
                        <tr class="font-weight-bold"><td>Gross pay</td><td class="text-right"><?= e($money($estimate['gross'])) ?></td></tr>
                        <?php foreach ($estimate['funds'] as $fr): ?>
                            <?php if ($fr['employee'] > 0): ?><tr><td><?= e($fr['code']) ?> (employee)</td><td class="text-right">−<?= e($money($fr['employee'])) ?></td></tr><?php endif; ?>
                        <?php endforeach; ?>
                        <tr><td>Income tax (TDS)</td><td class="text-right">−<?= e($money($estimate['tds'])) ?></td></tr>
                        <?php foreach ($estimate['deductions'] as $dr): ?>
                            <tr><td><?= e($dr['name']) ?></td><td class="text-right">−<?= e($money($dr['amount'])) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot><tr><td>Net pay</td><td class="text-right"><?= e($money($estimate['net'])) ?></td></tr></tfoot>
                    </table>
                    <div class="ana-kv">
                        <div><span class="ana-kv__label">Employer funds</span><span class="ana-kv__value"><?= e($money($estimate['employer_contributions'])) ?></span></div>
                        <div><span class="ana-kv__label">Cost to company</span><span class="ana-kv__value"><?= e($money($estimate['employer_cost'])) ?></span></div>
                        <div><span class="ana-kv__label">Annual tax</span><span class="ana-kv__value"><?= e($money($estimate['annual']['tax'])) ?></span></div>
                    </div>
                    <?php if ($estimate['annual']['assessable'] > 0): ?>
                        <p class="small text-muted mt-3 mb-0">
                            Assessable <?= e($money($estimate['annual']['assessable'])) ?> (incl. employer fund contributions)
                            − retirement deduction <?= e($money($estimate['annual']['retirement_deduction'])) ?>
                            = taxable <?= e($money($estimate['annual']['taxable'])) ?> a year.
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="ana-card ana-card--flush">
                <div class="ana-card__head"><div><h3 class="ana-card__title">Pay history</h3></div></div>
                <table class="table table-sm ana-table">
                    <thead><tr><th>From</th><th>Type</th><th class="text-right">Amount</th><th>Tax</th></tr></thead>
                    <tbody>
                    <?php foreach ($history as $h): ?>
                        <tr<?= $current && (int) $h['id'] === (int) $current['id'] ? ' class="font-weight-bold"' : '' ?>>
                            <td><?= e(formatDateView((string) $h['effective_from'])) ?></td>
                            <td><?= e($h['pay_type']) ?></td>
                            <td class="text-right"><?= e($money($h['basic'])) ?></td>
                            <td><?= $h['tax_applicable'] ? 'Yes' : 'No' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$history): ?><tr><td colspan="4" class="text-muted text-center py-3">No pay set up yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script>
    document.getElementById('payType').addEventListener('change', function () {
        document.getElementById('basicLabel').textContent = this.value === 'Stipend' ? 'Monthly stipend' : 'Basic salary / month';
    });
    </script>
    <?php
    return;
}

// ================================================================== list
$staffs = $db->select(
    "SELECT u.id, u.fullname, u.gender, u.tax_filing_status, u.employment_type, d.title AS designation
     FROM tbl_users_login u
     LEFT JOIN tbl_office_designation d ON d.id = u.designation_id
     WHERE u.status = 'Active'
     ORDER BY u.fullname"
);
$fundCodes = [];
foreach ($db->select(
    'SELECT e.staff_id, f.code FROM tbl_staff_fund_enrollments e JOIN tbl_payroll_funds f ON f.id = e.fund_id WHERE f.is_active = 1 ORDER BY f.code'
) as $r) {
    $fundCodes[(int) $r['staff_id']][] = $r['code'];
}
$rows = [];
$totals = ['gross' => 0.0, 'cost' => 0.0, 'tds' => 0.0, 'net' => 0.0, 'set' => 0];
foreach ($staffs as $s) {
    $est = $svc->estimate($s, $today, $taxYear);
    $rows[] = ['staff' => $s, 'est' => $est];
    if ($est) {
        $totals['set']++;
        $totals['gross'] += $est['gross'];
        $totals['cost'] += $est['employer_cost'];
        $totals['tds'] += $est['tds'];
        $totals['net'] += $est['net'];
    }
}
$missing = count($staffs) - $totals['set'];
?>

<div class="ana-toolbar">
    <div>
        <h2 class="ana-toolbar__title">Salary setup</h2>
        <p class="ana-toolbar__sub">Monthly estimates for a full month worked<?= $taxYear ? ', tax per FY ' . e($taxYear['title']) : '' ?>.</p>
    </div>
</div>

<?php if (!$taxYear): ?>
    <div class="alert alert-warning small">No tax year covers today, so TDS is 0 in these estimates. Add one under <a href="<?= pageUrl('payroll', 'tax_slabs') ?>">Tax Slabs</a>.</div>
<?php endif; ?>

<div class="ana-grid">
    <?= stat_tile(['label' => 'Pay set up', 'icon' => 'fas fa-user-check', 'tone' => $missing > 0 ? 'warning' : 'success',
        'value' => $totals['set'] . ' / ' . count($staffs), 'sub' => $missing > 0 ? $missing . ' active staff without pay' : 'every active staff member']) ?>
    <?= stat_tile(['label' => 'Monthly gross', 'icon' => 'fas fa-money-bill-wave', 'tone' => 'primary',
        'value' => format_compact_money($totals['gross'], 'NPR', 'NPR 0'), 'sub' => format_compact_money($totals['net'], 'NPR', 'NPR 0') . ' net to staff']) ?>
    <?= stat_tile(['label' => 'Cost to company', 'icon' => 'fas fa-building', 'tone' => 'info',
        'value' => format_compact_money($totals['cost'], 'NPR', 'NPR 0'), 'sub' => 'gross + employer fund contributions']) ?>
    <?= stat_tile(['label' => 'TDS per month', 'icon' => 'fas fa-percentage', 'tone' => 'neutral',
        'value' => format_compact_money($totals['tds'], 'NPR', 'NPR 0'), 'sub' => 'estimated income tax withheld']) ?>
</div>

<div class="ana-card ana-card--flush">
    <div class="table-responsive">
        <table class="table table-sm ana-table">
            <thead><tr><th>Staff</th><th>Employment</th><th>Pay</th><th>Funds</th><th>Tax</th><th class="text-right">Gross</th><th class="text-right">Net</th><th class="text-right"></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r):
                $s = $r['staff'];
                $est = $r['est'];
            ?>
                <tr>
                    <td><strong><?= e($s['fullname']) ?></strong><?= $s['designation'] ? '<br><small class="text-muted">' . e($s['designation']) . '</small>' : '' ?></td>
                    <td><?= e($s['employment_type']) ?></td>
                    <td><?= $est ? e($est['structure']['pay_type']) . ' · ' . $money($est['structure']['basic']) : '<span class="ana-pill ana-pill--warning">Not set</span>' ?></td>
                    <td><?php foreach ($fundCodes[(int) $s['id']] ?? [] as $code): ?><span class="ana-pill mr-1"><?= e($code) ?></span><?php endforeach; ?><?= empty($fundCodes[(int) $s['id']]) ? '<span class="text-muted">—</span>' : '' ?></td>
                    <td><?= $est ? ($est['structure']['tax_applicable'] ? 'Yes' : 'No') : '—' ?></td>
                    <td class="text-right"><?= $est ? $money($est['gross']) : '—' ?></td>
                    <td class="text-right font-weight-bold"><?= $est ? $money($est['net']) : '—' ?></td>
                    <td class="text-right"><a href="<?= e($base . '&id=' . (int) $s['id']) ?>" class="btn btn-xs <?= $est ? 'btn-outline-primary' : 'btn-primary' ?>"><?= $est ? 'Edit' : 'Set up' ?></a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="8" class="text-muted text-center py-4">No active staff.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
