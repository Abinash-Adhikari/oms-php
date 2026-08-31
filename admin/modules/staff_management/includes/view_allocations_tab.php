<?php
/**
 * SB-Tech — Leave Management / Allocations (AC-LV-01.2).
 * Matrix of staff × leave type for the selected leave year. Allocated and
 * carry-forward days are editable; used days are synced from Approved
 * applications; remaining is derived (never stored).
 */
$db = Database::instance();
$year = (int) ($_GET['year'] ?? currentLeaveYear());
if ($year < 2000 || $year > 2100) {
    $year = currentLeaveYear();
}
$years = range(date('Y') - 1, date('Y') + 1);

$staffs = $db->select(
    "SELECT `id`, `fullname`, `department_id` FROM `tbl_users_login` WHERE `status` = 'Active' ORDER BY `fullname`"
);
$types = $db->select('SELECT * FROM `tbl_office_leave_configs` WHERE `is_active` = 1 ORDER BY `title`');

// Existing allocations for the year, keyed staff_id => leave_id => row.
$existing = [];
foreach ($db->select(
    'SELECT * FROM `tbl_office_staff_leave_allocation` WHERE `year` = ?',
    [$year]
) as $a) {
    $existing[(int) $a['staff_id']][(int) $a['leave_id']] = $a;
}
?>
<div class="row mb-2">
    <div class="col-md-6">
        <form method="get" class="form-inline">
            <input type="hidden" name="module" value="staff_management">
            <input type="hidden" name="page" value="leave_management">
            <input type="hidden" name="tab" value="view_allocations">
            <label class="mr-2">Leave year</label>
            <select name="year" class="form-control form-control-sm" onchange="this.form.submit()">
                <?php foreach ($years as $y): ?>
                    <option value="<?= $y ?>" <?= $year === $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<form action="operation.php?module=staff_management&page=leave_management" method="post">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save_allocations">
    <input type="hidden" name="year" value="<?= (int) $year ?>">
    <div class="table-responsive">
        <table class="table table-sm table-striped table-hover">
            <thead>
                <tr>
                    <th>Staff</th>
                    <?php foreach ($types as $t): ?>
                        <th class="text-center" style="min-width:130px"><?= e($t['title']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php if (!$staffs): ?>
                <tr><td colspan="<?= count($types) + 1 ?>" class="text-center text-muted">No active staff.</td></tr>
            <?php elseif (!$types): ?>
                <tr><td colspan="<?= count($staffs) + 1 ?>" class="text-center text-muted">Create leave types in the Setup tab first.</td></tr>
            <?php endif; ?>
            <?php foreach ($staffs as $s): ?>
                <tr>
                    <td><?= e($s['fullname']) ?></td>
                    <?php foreach ($types as $t): ?>
                        <?php
                        $a = $existing[(int) $s['id']][(int) $t['id']] ?? null;
                        $used = (float) ($a['used_days'] ?? 0);
                        $carry = (float) ($a['carry_forward_days'] ?? 0);
                        $allocated = (float) ($a['allocated_days'] ?? 0);
                        $remaining = $allocated + $carry - $used;
                        ?>
                        <td class="text-center align-middle">
                            <div class="input-group input-group-sm">
                                <input type="number" name="alloc[<?= (int) $s['id'] ?>][<?= (int) $t['id'] ?>]"
                                       class="form-control form-control-sm" min="0" step="0.5" value="<?= e($allocated) ?>"
                                       title="Allocated days">
                                <input type="number" name="carry[<?= (int) $s['id'] ?>][<?= (int) $t['id'] ?>]"
                                       class="form-control form-control-sm" min="0" step="0.5" value="<?= e($carry) ?>"
                                       title="Carry-forward days">
                            </div>
                            <small class="text-muted">used <?= e($used) ?> · left <strong><?= e(round($remaining, 1)) ?></strong></small>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($staffs && $types): ?>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save allocations for <?= (int) $year ?></button>
        <small class="text-muted ml-2">Used days are synced automatically from Approved applications.</small>
    <?php endif; ?>
</form>
