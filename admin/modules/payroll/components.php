<?php
/**
 * SB-Tech — Payroll / Pay Components.
 * Catalogue of earnings (allowances, bonuses) and deductions used by salary
 * structures. Basic salary / stipend is set per staff, not here.
 */
if (!Auth::hasSpecial('manage_payroll')) {
    echo '<div class="callout callout-warning"><h5>Payroll access required</h5><p>Ask an administrator for the <b>Manage payroll</b> permission.</p></div>';
    return;
}
$db = Database::instance();
$components = $db->select(
    'SELECT c.*, (SELECT COUNT(*) FROM `tbl_staff_salary_structure_items` i WHERE i.`component_id` = c.`id`) AS used
     FROM `tbl_payroll_components` c ORDER BY c.`kind`, c.`position`, c.`name`'
);
?>

<div class="ana-toolbar">
    <div>
        <h2 class="ana-toolbar__title">Pay components</h2>
        <p class="ana-toolbar__sub">Allowances and deductions you can add to a salary. Amounts are set per staff on Salary Setup.</p>
    </div>
    <div class="ana-toolbar__actions">
        <button type="button" class="btn btn-primary btn-sm" onclick="openComponent()"><i class="fas fa-plus mr-1"></i>Add component</button>
    </div>
</div>

<div class="ana-card ana-card--flush">
    <div class="table-responsive">
        <table class="table table-sm ana-table">
            <thead><tr><th>Component</th><th>Type</th><th>Amount is</th><th>Taxable</th><th>Cut for unpaid days</th><th class="text-right">Used by</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($components as $c): ?>
                <tr>
                    <td><strong><?= e($c['name']) ?></strong> <span class="text-muted small"><?= e($c['code']) ?></span></td>
                    <td><span class="ana-pill<?= $c['kind'] === 'earning' ? ' ana-pill--good' : ' ana-pill--warning' ?>"><?= $c['kind'] === 'earning' ? 'Earning' : 'Deduction' ?></span></td>
                    <td><?= $c['calc'] === 'percent_of_basic' ? '% of basic' : 'Fixed amount' ?></td>
                    <td><?= $c['kind'] === 'earning' ? ($c['taxable'] ? 'Yes' : 'No') : '<span class="text-muted">—</span>' ?></td>
                    <td><?= $c['prorate'] ? 'Yes' : 'No' ?></td>
                    <td class="text-right"><?= (int) $c['used'] ?></td>
                    <td><span class="ana-pill<?= $c['is_active'] ? ' ana-pill--good' : '' ?>"><?= $c['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                    <td class="text-right text-nowrap">
                        <button type="button" class="btn btn-xs btn-outline-primary" onclick='openComponent(<?= e(json_encode($c)) ?>)' aria-label="Edit <?= e($c['name']) ?>"><i class="fas fa-edit"></i></button>
                        <form action="operation.php?module=payroll&page=components" method="post" class="d-inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                            <?php if ((int) $c['used'] > 0): ?>
                                <button type="button" class="btn btn-xs btn-outline-danger" disabled title="Used in salary structures — deactivate instead"><i class="fas fa-trash"></i></button>
                            <?php else: ?>
                                <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete <?= e($c['name']) ?>?" aria-label="Delete <?= e($c['name']) ?>"><i class="fas fa-trash"></i></button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$components): ?>
                <tr><td colspan="8" class="text-muted text-center py-4">No components yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="componentModal" tabindex="-1" role="dialog" aria-labelledby="componentTitle" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form class="modal-content" action="operation.php?module=payroll&page=components" method="post" id="componentForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="0">
            <div class="modal-header"><h5 class="modal-title" id="componentTitle">Component</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group col-4"><label class="small mb-1">Code</label><input type="text" name="code" class="form-control" required maxlength="30"></div>
                    <div class="form-group col-8"><label class="small mb-1">Name</label><input type="text" name="name" class="form-control" required maxlength="191"></div>
                </div>
                <div class="form-row">
                    <div class="form-group col-6"><label class="small mb-1">Type</label>
                        <select name="kind" class="form-control"><option value="earning">Earning</option><option value="deduction">Deduction</option></select></div>
                    <div class="form-group col-6"><label class="small mb-1">Amount is</label>
                        <select name="calc" class="form-control"><option value="fixed">Fixed amount</option><option value="percent_of_basic">% of basic</option></select></div>
                </div>
                <div class="form-group"><label class="small mb-1">Display order</label><input type="number" name="position" class="form-control" value="0" step="1"></div>
                <div class="custom-control custom-checkbox mb-1"><input type="checkbox" class="custom-control-input" id="cTaxable" name="taxable" value="1"><label class="custom-control-label" for="cTaxable">Taxable (earnings only)</label></div>
                <div class="custom-control custom-checkbox mb-1"><input type="checkbox" class="custom-control-input" id="cProrate" name="prorate" value="1"><label class="custom-control-label" for="cProrate">Reduce for unpaid days (untick for one-off amounts like a festival bonus)</label></div>
                <div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="cActive" name="is_active" value="1"><label class="custom-control-label" for="cActive">Active</label></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save</button></div>
        </form>
    </div>
</div>

<script>
function openComponent(c) {
    c = c || {id: 0, code: '', name: '', kind: 'earning', calc: 'fixed', position: 0, taxable: 1, prorate: 1, is_active: 1};
    var form = document.getElementById('componentForm');
    ['id', 'code', 'name', 'kind', 'calc', 'position'].forEach(function (k) { form.elements[k].value = c[k] == null ? '' : c[k]; });
    form.elements.taxable.checked = String(c.taxable) === '1';
    form.elements.prorate.checked = String(c.prorate) === '1';
    form.elements.is_active.checked = String(c.is_active) === '1';
    document.getElementById('componentTitle').textContent = c.id ? 'Edit component' : 'Add component';
    $('#componentModal').modal('show');
}
</script>
