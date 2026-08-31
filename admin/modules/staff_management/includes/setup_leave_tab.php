<?php
/**
 * SB-Tech — Leave Management / Setup (US-LV-01, AC-LV-01.1).
 * Leave type CRUD: title, max allowed, requires-approval, gender-specific
 * documentation, carry-forward + max carry-forward, active flag.
 */
$db = Database::instance();
$edit = null;
if (isset($_GET['type_id'])) {
    $edit = $db->selectOne('SELECT * FROM `tbl_office_leave_configs` WHERE `id` = ?', [(int) $_GET['type_id']]);
}
$rows = $db->select('SELECT * FROM `tbl_office_leave_configs` ORDER BY `title`');

$drawerOpen = ($edit !== null);
?>

<!-- Data Table (full width) -->
<div class="card card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-calendar-times mr-1"></i>Leave Types (<?= count($rows) ?>)</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary btn-sm" onclick="openDrawer()">
                <i class="fas fa-plus mr-1"></i>Add Leave Type
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover mb-0">
                <thead><tr><th>#</th><th>Title</th><th class="text-center">Max / year</th><th class="text-center">Carry fwd</th><th>Gender</th><th class="text-center">Active</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $i => $t): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><strong><?= e($t['title']) ?></strong></td>
                        <td class="text-center"><?= (int) $t['max_allowed'] ?></td>
                        <td class="text-center"><?= $t['carry_forward'] ? (int) $t['max_carry_forward'] : '—' ?></td>
                        <td><?= e($t['gender_specific']) ?></td>
                        <td class="text-center"><span class="badge badge-<?= $t['is_active'] ? 'success' : 'secondary' ?>"><?= $t['is_active'] ? 'Yes' : 'No' ?></span></td>
                        <td class="text-right">
                            <button type="button" class="btn btn-xs btn-outline-primary" onclick="openDrawer(<?= (int) $t['id'] ?>)"><i class="fas fa-edit"></i></button>
                            <form action="operation.php?module=staff_management&page=leave_management" method="post" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete_type">
                                <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete leave type '<?= e($t['title']) ?>'? This fails if allocations or applications use it."><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted">No leave types yet. Add your first leave type.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Slide-in Drawer Backdrop -->
<div class="cms-drawer-backdrop" id="drawerBackdrop" onclick="closeDrawer()"></div>

<!-- Slide-in Drawer -->
<div class="cms-drawer" id="formDrawer">
    <div class="cms-drawer-header">
        <h3><i class="fas fa-calendar-times"></i><?= $edit ? 'Edit Leave Type' : 'Add Leave Type' ?></h3>
        <button type="button" class="cms-drawer-close" onclick="closeDrawer()" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="cms-drawer-body">
        <form action="operation.php?module=staff_management&page=leave_management" method="post" id="leaveTypeForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_type">
            <input type="hidden" name="id" id="formId" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" class="form-control" id="formTitle" required value="<?= $edit ? e($edit['title']) : '' ?>">
            </div>
            <div class="form-group">
                <label>Max allowed days per year</label>
                <input type="number" name="max_allowed" class="form-control" id="formMaxAllowed" min="0" value="<?= $edit ? (int) $edit['max_allowed'] : 0 ?>">
            </div>
            <div class="form-group">
                <label>Leave year</label>
                <input type="text" name="leave_year" class="form-control" id="formLeaveYear" placeholder="e.g. 2026" value="<?= $edit ? e($edit['leave_year']) : '' ?>">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" id="formDescription" rows="3"><?= $edit ? e($edit['description']) : '' ?></textarea>
            </div>
            <div class="form-row">
                <div class="col-6 form-group">
                    <label>Gender specific</label>
                    <select name="gender_specific" class="form-control" id="formGender">
                        <?php foreach (['Both', 'Male', 'Female'] as $g): ?>
                            <option value="<?= $g ?>" <?= $edit && $edit['gender_specific'] === $g ? 'selected' : '' ?>><?= $g ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 form-group">
                    <label>Carry-forward days</label>
                    <input type="number" name="max_carry_forward" class="form-control" id="formCarryForward" min="0" value="<?= $edit ? (int) $edit['max_carry_forward'] : 0 ?>">
                </div>
            </div>
            <div class="form-group">
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="cfg_requires_approval" name="requires_approval" value="1" <?= !$edit || $edit['requires_approval'] ? 'checked' : '' ?>>
                    <label class="custom-control-label" for="cfg_requires_approval">Requires approval</label>
                </div>
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="cfg_carry_forward" name="carry_forward" value="1" <?= $edit && $edit['carry_forward'] ? 'checked' : '' ?>>
                    <label class="custom-control-label" for="cfg_carry_forward">Carry forward unused days</label>
                </div>
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="cfg_documentation" name="documentation_required" value="1" <?= $edit && $edit['documentation_required'] ? 'checked' : '' ?>>
                    <label class="custom-control-label" for="cfg_documentation">Documentation required</label>
                </div>
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="cfg_active" name="is_active" value="1" <?= !$edit || $edit['is_active'] ? 'checked' : '' ?>>
                    <label class="custom-control-label" for="cfg_active">Active</label>
                </div>
            </div>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="leaveTypeForm" class="btn btn-primary btn-block">
            <i class="fas fa-save mr-1"></i><?= $edit ? 'Update' : 'Save' ?>
        </button>
    </div>
</div>

<script>
var leaveTypesData = <?= json_encode(array_values($rows)) ?>;

function openDrawer(editId) {
    var drawer = document.getElementById('formDrawer');
    var backdrop = document.getElementById('drawerBackdrop');
    drawer.classList.add('open');
    backdrop.classList.add('active');
    document.body.style.overflow = 'hidden';

    var title = drawer.querySelector('.cms-drawer-header h3');
    if (editId) {
        var lt = leaveTypesData.find(function(t) { return t.id == editId; });
        if (lt) {
            title.innerHTML = '<i class="fas fa-calendar-times"></i>Edit Leave Type';
            document.getElementById('formId').value = lt.id;
            document.getElementById('formTitle').value = lt.title;
            document.getElementById('formMaxAllowed').value = lt.max_allowed || 0;
            document.getElementById('formLeaveYear').value = lt.leave_year || '';
            document.getElementById('formDescription').value = lt.description || '';
            document.getElementById('formGender').value = lt.gender_specific || 'Both';
            document.getElementById('formCarryForward').value = lt.max_carry_forward || 0;
            document.getElementById('cfg_requires_approval').checked = lt.requires_approval == 1;
            document.getElementById('cfg_carry_forward').checked = lt.carry_forward == 1;
            document.getElementById('cfg_documentation').checked = lt.documentation_required == 1;
            document.getElementById('cfg_active').checked = lt.is_active == 1;
        }
    } else {
        title.innerHTML = '<i class="fas fa-calendar-times"></i>Add Leave Type';
        document.getElementById('formId').value = '0';
        document.getElementById('formTitle').value = '';
        document.getElementById('formMaxAllowed').value = '0';
        document.getElementById('formLeaveYear').value = '';
        document.getElementById('formDescription').value = '';
        document.getElementById('formGender').value = 'Both';
        document.getElementById('formCarryForward').value = '0';
        document.getElementById('cfg_requires_approval').checked = true;
        document.getElementById('cfg_carry_forward').checked = false;
        document.getElementById('cfg_documentation').checked = false;
        document.getElementById('cfg_active').checked = true;
    }
}

function closeDrawer() {
    document.getElementById('formDrawer').classList.remove('open');
    document.getElementById('drawerBackdrop').classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeDrawer(); });

<?php if ($drawerOpen): ?>
document.addEventListener('DOMContentLoaded', function() { openDrawer(<?= (int) $edit['id'] ?>); });
<?php endif; ?>
</script>
