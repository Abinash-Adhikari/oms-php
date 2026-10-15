<?php
/**
 * SB-Tech — Payroll / Funds.
 * Retirement and social-security funds (SSF, PF, CIT, …). Staff opt in per
 * fund on Salary Setup; these are the default rates they inherit.
 */
if (!Auth::hasSpecial('manage_payroll')) {
    echo '<div class="callout callout-warning"><h5>Payroll access required</h5><p>Ask an administrator for the <b>Manage payroll</b> permission.</p></div>';
    return;
}
$db = Database::instance();
$funds = $db->select(
    'SELECT f.*, (SELECT COUNT(*) FROM `tbl_staff_fund_enrollments` e WHERE e.`fund_id` = f.`id`) AS enrolled
     FROM `tbl_payroll_funds` f ORDER BY f.`is_active` DESC, f.`code`'
);
?>

<div class="ana-toolbar">
    <div>
        <h2 class="ana-toolbar__title">Funds</h2>
        <p class="ana-toolbar__sub">Default contribution rates. Each staff member opts in on Salary Setup and can override the rates there.</p>
    </div>
    <div class="ana-toolbar__actions">
        <button type="button" class="btn btn-primary btn-sm" onclick="openFund()"><i class="fas fa-plus mr-1"></i>Add fund</button>
    </div>
</div>

<div class="ana-card ana-card--flush">
    <div class="table-responsive">
        <table class="table table-sm ana-table">
            <thead><tr><th>Fund</th><th class="text-right">Employee</th><th class="text-right">Employer</th><th>Based on</th><th>Tax treatment</th><th class="text-right">Enrolled</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($funds as $f): ?>
                <tr>
                    <td><strong><?= e($f['code']) ?></strong> <span class="text-muted">· <?= e($f['name']) ?></span>
                        <?php if ($f['notes']): ?><br><small class="text-muted"><?= e($f['notes']) ?></small><?php endif; ?></td>
                    <td class="text-right"><?= e((float) $f['employee_rate']) ?>%</td>
                    <td class="text-right"><?= e((float) $f['employer_rate']) ?>%</td>
                    <td><?= $f['contribution_base'] === 'gross' ? 'Gross pay' : 'Basic pay' ?><?= (float) $f['max_base'] > 0 ? '<br><small class="text-muted">capped at ' . e(formatMoney($f['max_base'])) . '</small>' : '' ?></td>
                    <td class="small">
                        <?= $f['tax_deductible'] ? 'Deductible' : 'Not deductible' ?>
                        <?= $f['waives_social_security_tax'] ? '<br><span class="ana-pill ana-pill--good">no 1% SST</span>' : '' ?>
                    </td>
                    <td class="text-right"><?= (int) $f['enrolled'] ?></td>
                    <td><span class="ana-pill<?= $f['is_active'] ? ' ana-pill--good' : '' ?>"><?= $f['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                    <td class="text-right text-nowrap">
                        <button type="button" class="btn btn-xs btn-outline-primary" onclick='openFund(<?= e(json_encode($f)) ?>)' aria-label="Edit <?= e($f['code']) ?>"><i class="fas fa-edit"></i></button>
                        <form action="operation.php?module=payroll&page=funds" method="post" class="d-inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                            <?php if ((int) $f['enrolled'] > 0): ?>
                                <button type="button" class="btn btn-xs btn-outline-danger" disabled title="Staff are enrolled — deactivate instead"><i class="fas fa-trash"></i></button>
                            <?php else: ?>
                                <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete fund <?= e($f['code']) ?>?" aria-label="Delete <?= e($f['code']) ?>"><i class="fas fa-trash"></i></button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$funds): ?>
                <tr><td colspan="8" class="text-muted text-center py-4">No funds yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="fundModal" tabindex="-1" role="dialog" aria-labelledby="fundTitle" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form class="modal-content" action="operation.php?module=payroll&page=funds" method="post" id="fundForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="0">
            <div class="modal-header"><h5 class="modal-title" id="fundTitle">Fund</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group col-4"><label class="small mb-1">Code</label><input type="text" name="code" class="form-control" required maxlength="20"></div>
                    <div class="form-group col-8"><label class="small mb-1">Name</label><input type="text" name="name" class="form-control" required maxlength="191"></div>
                </div>
                <div class="form-row">
                    <div class="form-group col-6"><label class="small mb-1">Employee contribution %</label><input type="number" step="0.0001" min="0" max="100" name="employee_rate" class="form-control" value="0"></div>
                    <div class="form-group col-6"><label class="small mb-1">Employer contribution %</label><input type="number" step="0.0001" min="0" max="100" name="employer_rate" class="form-control" value="0"></div>
                </div>
                <div class="form-row">
                    <div class="form-group col-6"><label class="small mb-1">Calculated on</label>
                        <select name="contribution_base" class="form-control"><option value="basic">Basic pay</option><option value="gross">Gross pay</option></select></div>
                    <div class="form-group col-6"><label class="small mb-1">Monthly ceiling (optional)</label><input type="number" step="0.01" min="0" name="max_base" class="form-control" placeholder="No ceiling"></div>
                </div>
                <div class="custom-control custom-checkbox mb-1"><input type="checkbox" class="custom-control-input" id="fTaxDed" name="tax_deductible" value="1"><label class="custom-control-label" for="fTaxDed">Contributions are deductible from taxable income (within the year’s cap)</label></div>
                <div class="custom-control custom-checkbox mb-1"><input type="checkbox" class="custom-control-input" id="fSst" name="waives_social_security_tax" value="1"><label class="custom-control-label" for="fSst">Members don’t pay the 1% social security tax band (SSF / pension fund)</label></div>
                <div class="custom-control custom-checkbox mb-2"><input type="checkbox" class="custom-control-input" id="fActive" name="is_active" value="1"><label class="custom-control-label" for="fActive">Active</label></div>
                <div class="form-group mb-0"><label class="small mb-1">Notes</label><textarea name="notes" rows="2" class="form-control"></textarea></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save</button></div>
        </form>
    </div>
</div>

<script>
function openFund(f) {
    f = f || {id: 0, code: '', name: '', employee_rate: 0, employer_rate: 0, contribution_base: 'basic', max_base: '', tax_deductible: 1, waives_social_security_tax: 0, is_active: 1, notes: ''};
    var form = document.getElementById('fundForm');
    ['id', 'code', 'name', 'employee_rate', 'employer_rate', 'contribution_base', 'notes'].forEach(function (k) {
        form.elements[k].value = f[k] == null ? '' : (k.indexOf('rate') > -1 ? parseFloat(f[k]) : f[k]);
    });
    form.elements.max_base.value = f.max_base && parseFloat(f.max_base) > 0 ? parseFloat(f.max_base) : '';
    form.elements.tax_deductible.checked = String(f.tax_deductible) === '1';
    form.elements.waives_social_security_tax.checked = String(f.waives_social_security_tax) === '1';
    form.elements.is_active.checked = String(f.is_active) === '1';
    document.getElementById('fundTitle').textContent = f.id ? 'Edit fund' : 'Add fund';
    $('#fundModal').modal('show');
}
</script>
