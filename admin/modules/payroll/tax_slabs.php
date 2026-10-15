<?php
/**
 * SB-Tech — Payroll / Tax Slabs.
 * CRUD for income-tax years and their progressive bands, plus a calculator
 * to check the schedule against the accountant's figures. Seeded with Nepal
 * FY 2083/84 (Finance Act 2083); every value is editable.
 */
if (!Auth::hasSpecial('manage_payroll')) {
    echo '<div class="callout callout-warning"><h5>Payroll access required</h5><p>Ask an administrator for the <b>Manage payroll</b> permission.</p></div>';
    return;
}
$db = Database::instance();
$years = $db->select('SELECT * FROM `tbl_payroll_tax_years` ORDER BY `start_date` DESC');
$today = date('Y-m-d');

$selectedId = (int) ($_GET['year'] ?? 0);
$year = null;
foreach ($years as $y) {
    if (($selectedId && (int) $y['id'] === $selectedId) || (!$selectedId && $today >= $y['start_date'] && $today <= $y['end_date'])) {
        $year = $y;
        break;
    }
}
$year = $year ?? ($years[0] ?? null);
$slabs = $year ? (new PayrollService($db))->slabs((int) $year['id']) : [];
$statuses = $year ? array_values(array_unique(array_column($slabs, 'filing_status'))) : [];

// Calculator (GET only — nothing is saved).
$calcIncome = isset($_GET['income']) ? max(0.0, (float) str_replace(',', '', (string) $_GET['income'])) : null;
$calcStatus = in_array($_GET['status'] ?? '', ['Single', 'Couple'], true) ? (string) $_GET['status'] : 'Single';
$calcSst = !empty($_GET['sst_waived']);
$calcFemale = !empty($_GET['female']);
$calc = null;
if ($year && $calcIncome !== null) {
    $calc = payroll_annual_tax($calcIncome, payroll_slabs_for($slabs, $calcStatus), $calcSst);
    $calc['rebate'] = $calcFemale ? round($calc['tax'] * (float) $year['female_rebate_percent'] / 100, 2) : 0.0;
}
$base = pageUrl('payroll', 'tax_slabs');
$money = static fn ($v) => number_format((float) $v, 2);
?>

<div class="ana-toolbar">
    <div>
        <h2 class="ana-toolbar__title">Income tax slabs</h2>
        <p class="ana-toolbar__sub">Bands are applied progressively to annual taxable income. Rates are editable for each tax year.</p>
    </div>
    <div class="ana-toolbar__actions">
        <?php if ($years): ?>
            <form method="get" class="d-inline">
                <input type="hidden" name="module" value="payroll">
                <input type="hidden" name="page" value="tax_slabs">
                <select name="year" class="form-control form-control-sm" onchange="this.form.submit()" aria-label="Tax year">
                    <?php foreach ($years as $y): ?>
                        <option value="<?= (int) $y['id'] ?>" <?= $year && (int) $year['id'] === (int) $y['id'] ? 'selected' : '' ?>>FY <?= e($y['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>
        <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#newYearModal"><i class="fas fa-plus mr-1"></i>New tax year</button>
    </div>
</div>

<?php if (!$year): ?>
    <div class="callout callout-info"><h5>No tax years yet</h5><p>Create one to start defining bands.</p></div>
<?php else: ?>

<?php if ($year['notes']): ?>
    <div class="callout callout-warning small mb-3"><i class="fas fa-info-circle mr-1"></i><?= e($year['notes']) ?></div>
<?php endif; ?>

<div class="row ana-row">
    <div class="col-lg-8">
        <div class="ana-card">
            <div class="ana-card__head">
                <div>
                    <h3 class="ana-card__title">Bands — FY <?= e($year['title']) ?></h3>
                    <p class="ana-card__hint">Leave “Up to” empty on the last band. Tick “SST” on the social security tax band that SSF / pension-fund contributors don’t pay.</p>
                </div>
            </div>
            <?php if ($slabs && !in_array('All', $statuses, true) && !(in_array('Single', $statuses, true) && in_array('Couple', $statuses, true))): ?>
                <div class="alert alert-danger small"><strong>Not every filing status is covered.</strong> Staff whose status has no bands pay no TDS — add an “Everyone” schedule or the missing Single / Couple one.</div>
            <?php endif; ?>
            <?php foreach ($statuses as $st):
                $problems = payroll_tax_slab_problems(payroll_slabs_for($slabs, $st));
            ?>
                <?php if ($problems): ?>
                    <div class="alert alert-danger small">
                        <strong><?= e($st === 'All' ? 'Schedule' : $st . ' schedule') ?> has problems:</strong>
                        <ul class="mb-0 pl-3"><?php foreach ($problems as $p): ?><li><?= e($p) ?></li><?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
            <form action="operation.php?module=payroll&page=tax_slabs" method="post" id="slabForm">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save_slabs">
                <input type="hidden" name="tax_year_id" value="<?= (int) $year['id'] ?>">
                <div class="table-responsive">
                    <table class="table table-sm ana-table">
                        <thead><tr><th style="width:18%">Applies to</th><th>From (NPR)</th><th>Up to (NPR)</th><th style="width:13%">Rate %</th><th class="text-center" style="width:8%">SST</th><th style="width:5%"></th></tr></thead>
                        <tbody id="slabRows">
                        <?php foreach ($slabs as $s): ?>
                            <tr>
                                <td><select name="filing_status[]" class="form-control form-control-sm">
                                    <?php foreach (['All' => 'Everyone', 'Single' => 'Single', 'Couple' => 'Couple'] as $k => $label): ?>
                                        <option value="<?= e($k) ?>" <?= $s['filing_status'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select></td>
                                <td><input type="number" step="0.01" min="0" name="lower_limit[]" class="form-control form-control-sm" value="<?= e((float) $s['lower_limit']) ?>" required></td>
                                <td><input type="number" step="0.01" min="0" name="upper_limit[]" class="form-control form-control-sm" value="<?= $s['upper_limit'] === null ? '' : e((float) $s['upper_limit']) ?>" placeholder="No limit"></td>
                                <td><input type="number" step="0.0001" min="0" max="100" name="rate[]" class="form-control form-control-sm" value="<?= e((float) $s['rate']) ?>" required></td>
                                <td class="text-center"><input type="checkbox" class="sst-box" <?= $s['is_social_security_tax'] ? 'checked' : '' ?> aria-label="Social security tax band"><input type="hidden" name="sst[]" value="<?= $s['is_social_security_tax'] ? 1 : 0 ?>"></td>
                                <td><button type="button" class="btn btn-xs btn-outline-danger" onclick="this.closest('tr').remove()" aria-label="Remove band"><i class="fas fa-times"></i></button></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addSlabRow()"><i class="fas fa-plus mr-1"></i>Add band</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-save mr-1"></i>Save bands</button>
                </div>
            </form>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="ana-card">
            <div class="ana-card__head"><div><h3 class="ana-card__title">Year settings</h3></div></div>
            <form action="operation.php?module=payroll&page=tax_slabs" method="post">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save_year">
                <input type="hidden" name="id" value="<?= (int) $year['id'] ?>">
                <div class="form-group"><label class="small mb-1">Title</label>
                    <input type="text" name="title" class="form-control form-control-sm" value="<?= e($year['title']) ?>" required maxlength="20"></div>
                <div class="form-row">
                    <div class="form-group col-6"><label class="small mb-1">Starts (AD)</label>
                        <input type="date" name="start_date" class="form-control form-control-sm" value="<?= e($year['start_date']) ?>" required></div>
                    <div class="form-group col-6"><label class="small mb-1">Ends (AD)</label>
                        <input type="date" name="end_date" class="form-control form-control-sm" value="<?= e($year['end_date']) ?>" required></div>
                </div>
                <div class="form-group"><label class="small mb-1">Retirement-fund deduction cap (NPR / year)</label>
                    <input type="number" step="0.01" min="0" name="retirement_cap" class="form-control form-control-sm" value="<?= e((float) $year['retirement_cap']) ?>">
                    <small class="text-muted">0 = no amount cap</small></div>
                <div class="form-group"><label class="small mb-1">…or this share of assessable income (%)</label>
                    <input type="number" step="0.000001" min="0" max="100" name="retirement_percent" class="form-control form-control-sm" value="<?= e(round((float) $year['retirement_fraction'] * 100, 6)) ?>">
                    <small class="text-muted">The lower of the two limits applies. 0 = no share limit</small></div>
                <div class="form-group"><label class="small mb-1">Tax rebate for women (% of tax)</label>
                    <input type="number" step="0.01" min="0" max="100" name="female_rebate_percent" class="form-control form-control-sm" value="<?= e((float) $year['female_rebate_percent']) ?>"></div>
                <div class="form-group"><label class="small mb-1">Notes / source</label>
                    <textarea name="notes" rows="3" class="form-control form-control-sm"><?= e($year['notes']) ?></textarea></div>
                <div class="d-flex justify-content-between">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-save mr-1"></i>Save settings</button>
                </div>
            </form>
            <form action="operation.php?module=payroll&page=tax_slabs" method="post" class="mt-2 text-right">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete_year">
                <input type="hidden" name="id" value="<?= (int) $year['id'] ?>">
                <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete FY <?= e($year['title']) ?> and all its bands?"><i class="fas fa-trash mr-1"></i>Delete this tax year</button>
            </form>
        </div>
    </div>
</div>

<div class="ana-card">
    <div class="ana-card__head">
        <div>
            <h3 class="ana-card__title">Tax calculator</h3>
            <p class="ana-card__hint">Annual tax on a taxable income (after retirement deductions) using the saved FY <?= e($year['title']) ?> bands</p>
        </div>
    </div>
    <form method="get" class="form-inline mb-3" style="gap:.5rem">
        <input type="hidden" name="module" value="payroll">
        <input type="hidden" name="page" value="tax_slabs">
        <input type="hidden" name="year" value="<?= (int) $year['id'] ?>">
        <input type="number" step="0.01" min="0" name="income" class="form-control form-control-sm" placeholder="Annual taxable income" value="<?= $calcIncome !== null ? e($calcIncome) : '' ?>" required aria-label="Annual taxable income">
        <select name="status" class="form-control form-control-sm" aria-label="Filing status">
            <option value="Single" <?= $calcStatus === 'Single' ? 'selected' : '' ?>>Single</option>
            <option value="Couple" <?= $calcStatus === 'Couple' ? 'selected' : '' ?>>Couple</option>
        </select>
        <label class="small mb-0"><input type="checkbox" name="sst_waived" value="1" <?= $calcSst ? 'checked' : '' ?> class="mr-1">SSF / pension contributor</label>
        <label class="small mb-0"><input type="checkbox" name="female" value="1" <?= $calcFemale ? 'checked' : '' ?> class="mr-1">Woman (rebate)</label>
        <button class="btn btn-sm btn-primary"><i class="fas fa-calculator mr-1"></i>Calculate</button>
    </form>
    <?php if ($calc): ?>
        <div class="table-responsive">
            <table class="table table-sm ana-table">
                <thead><tr><th>Band</th><th class="text-right">Rate</th><th class="text-right">Income in band</th><th class="text-right">Tax</th></tr></thead>
                <tbody>
                <?php foreach ($calc['bands'] as $b): ?>
                    <tr>
                        <td><?= e($money($b['lower'])) ?> – <?= $b['upper'] === null ? 'above' : $money($b['upper']) ?></td>
                        <td class="text-right"><?= e((float) $b['rate']) ?>%</td>
                        <td class="text-right"><?= e($money($b['amount'])) ?></td>
                        <td class="text-right"><?= $b['waived'] ? '<span class="ana-pill ana-pill--good">waived</span>' : $money($b['tax']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <?php if ($calc['rebate'] > 0): ?>
                        <tr><td colspan="3">Rebate for women (<?= e((float) $year['female_rebate_percent']) ?>%)</td><td class="text-right">−<?= e($money($calc['rebate'])) ?></td></tr>
                    <?php endif; ?>
                    <tr><td colspan="3">Annual tax · monthly ≈ <?= e($money(($calc['tax'] - $calc['rebate']) / 12)) ?></td><td class="text-right"><?= e($money($calc['tax'] - $calc['rebate'])) ?></td></tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- New tax year -->
<div class="modal fade" id="newYearModal" tabindex="-1" role="dialog" aria-labelledby="newYearTitle" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form class="modal-content" action="operation.php?module=payroll&page=tax_slabs" method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_year">
            <div class="modal-header"><h5 class="modal-title" id="newYearTitle">New tax year</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
            <div class="modal-body">
                <div class="form-group"><label class="small mb-1">Title (e.g. 2084/85)</label>
                    <input type="text" name="title" class="form-control" required maxlength="20"></div>
                <div class="form-row">
                    <div class="form-group col-6"><label class="small mb-1">Starts (AD)</label><input type="date" name="start_date" class="form-control" required></div>
                    <div class="form-group col-6"><label class="small mb-1">Ends (AD)</label><input type="date" name="end_date" class="form-control" required></div>
                </div>
                <div class="form-group"><label class="small mb-1">Copy bands and settings from</label>
                    <select name="copy_from" class="form-control">
                        <option value="0">— Start empty —</option>
                        <?php foreach ($years as $y): ?>
                            <option value="<?= (int) $y['id'] ?>" <?= $year && (int) $y['id'] === (int) $year['id'] ? 'selected' : '' ?>>FY <?= e($y['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Then edit whatever the new budget changed.</small></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Create</button></div>
        </form>
    </div>
</div>

<script>
// Checkboxes don't post when unticked; mirror each into its row's hidden sst[] so indexes stay aligned.
document.addEventListener('change', function (ev) {
    if (ev.target.classList && ev.target.classList.contains('sst-box')) {
        ev.target.nextElementSibling.value = ev.target.checked ? '1' : '0';
    }
});
function addSlabRow() {
    var tr = document.createElement('tr');
    tr.innerHTML =
        '<td><select name="filing_status[]" class="form-control form-control-sm"><option value="All">Everyone</option><option value="Single">Single</option><option value="Couple">Couple</option></select></td>' +
        '<td><input type="number" step="0.01" min="0" name="lower_limit[]" class="form-control form-control-sm" required></td>' +
        '<td><input type="number" step="0.01" min="0" name="upper_limit[]" class="form-control form-control-sm" placeholder="No limit"></td>' +
        '<td><input type="number" step="0.0001" min="0" max="100" name="rate[]" class="form-control form-control-sm" required></td>' +
        '<td class="text-center"><input type="checkbox" class="sst-box" aria-label="Social security tax band"><input type="hidden" name="sst[]" value="0"></td>' +
        '<td><button type="button" class="btn btn-xs btn-outline-danger" onclick="this.closest(\'tr\').remove()" aria-label="Remove band"><i class="fas fa-times"></i></button></td>';
    document.getElementById('slabRows').appendChild(tr);
}
</script>
