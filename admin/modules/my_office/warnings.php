<?php
/**
 * SB-Tech — My Office / Warnings.
 * Record verbal/written/final warnings issued to staff.
 */
$db = Database::instance();
$editWarning = null;

if (isset($_GET['edit_id'])) {
    $editWarning = $db->selectOne('SELECT * FROM `tbl_staff_warnings` WHERE `id` = ?', [(int) $_GET['edit_id']]);
}

$warnings = $db->select(
    'SELECT w.*, s.fullname AS staff_name, u.fullname AS added_by_name
     FROM `tbl_staff_warnings` w
     LEFT JOIN `tbl_users_login` s ON s.id = w.staff_id
     LEFT JOIN `tbl_users_login` u ON u.id = w.added_by
     ORDER BY w.added_on DESC, w.id DESC'
);

$staff = $db->select('SELECT id, fullname, username, status FROM `tbl_users_login` ORDER BY fullname');

$drawerOpen = ($editWarning !== null);
?>

<!-- Data Table (full width) -->
<div class="card card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-exclamation-triangle mr-1"></i>Warnings (<?= count($warnings) ?>)</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary btn-sm" onclick="openDrawer()">
                <i class="fas fa-plus mr-1"></i>Issue Warning
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover mb-0">
                <thead><tr><th>#</th><th>Staff</th><th>Type</th><th>Title</th><th>Description</th><th>Issued On</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                <?php if (!$warnings): ?>
                    <tr><td colspan="8" class="text-center text-muted">No warnings issued yet.</td></tr>
                <?php else: foreach ($warnings as $i => $w): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><strong><?= e($w['staff_name']) ?></strong></td>
                        <td>
                            <?php $typeBadge = ['Verbal' => 'info', 'Written' => 'warning', 'Final' => 'danger']; ?>
                            <span class="badge badge-<?= $typeBadge[$w['warning_type']] ?? 'secondary' ?>"><?= e($w['warning_type']) ?></span>
                        </td>
                        <td><?= e($w['title']) ?></td>
                        <td><?= e(mb_strimwidth((string) ($w['description'] ?? ''), 0, 40, '…')) ?></td>
                        <td class="small"><?= $w['issued_on'] ? e((string) $w['issued_on']) : '—' ?></td>
                        <td><span class="badge badge-<?= ($w['is_active'] ?? 1) ? 'success' : 'secondary' ?>"><?= ($w['is_active'] ?? 1) ? 'Active' : 'Inactive' ?></span></td>
                        <td class="text-right">
                            <button type="button" class="btn btn-xs btn-outline-primary" onclick="openDrawer(<?= (int) $w['id'] ?>)"><i class="fas fa-edit"></i></button>
                            <form action="operation.php?module=my_office&page=warnings" method="post" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $w['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete this warning?"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
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
        <h3><i class="fas fa-exclamation-triangle"></i><?= $editWarning ? 'Edit Warning' : 'Issue Warning' ?></h3>
        <button type="button" class="cms-drawer-close" onclick="closeDrawer()" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="cms-drawer-body">
        <form action="operation.php?module=my_office&page=warnings" method="post" id="warningForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="formId" value="<?= $editWarning ? (int) $editWarning['id'] : 0 ?>">
            <div class="form-group">
                <label>Staff *</label>
                <select name="staff_id" class="form-control" id="formStaff" required>
                    <option value="">— Select staff —</option>
                    <?php foreach ($staff as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= $editWarning && (int) $editWarning['staff_id'] === (int) $s['id'] ? 'selected' : '' ?>>
                            <?= e($s['fullname']) ?><?= !in_array($s['status'], ['Active', 'Current'], true) ? ' (' . e($s['status']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Type</label>
                <select name="warning_type" class="form-control" id="formType">
                    <option value="Verbal" <?= $editWarning && ($editWarning['warning_type'] ?? '') === 'Verbal' ? 'selected' : '' ?>>Verbal</option>
                    <option value="Written" <?= (!$editWarning || ($editWarning['warning_type'] ?? '') === 'Written') ? 'selected' : '' ?>>Written</option>
                    <option value="Final" <?= $editWarning && ($editWarning['warning_type'] ?? '') === 'Final' ? 'selected' : '' ?>>Final</option>
                </select>
            </div>
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" class="form-control" id="formTitle" required value="<?= $editWarning ? e($editWarning['title']) : '' ?>">
            </div>
            <div class="form-group">
                <label>Issued On</label>
                <input type="date" name="issued_on" class="form-control" id="formIssuedOn" value="<?= $editWarning ? e($editWarning['issued_on']) : '' ?>">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="is_active" class="form-control" id="formStatus">
                    <option value="1" <?= ($editWarning && ($editWarning['is_active'] ?? 1)) ? 'selected' : '' ?>>Active</option>
                    <option value="0" <?= $editWarning && !($editWarning['is_active'] ?? 1) ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" id="formDescription" rows="4"><?= $editWarning ? e($editWarning['description']) : '' ?></textarea>
            </div>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="warningForm" class="btn btn-primary btn-block">
            <i class="fas fa-save mr-1"></i><?= $editWarning ? 'Update Warning' : 'Issue Warning' ?>
        </button>
    </div>
</div>

<script>
// Warning data for edit population
var warningsData = <?= json_encode(array_values($warnings)) ?>;

function openDrawer(editId) {
    var drawer = document.getElementById('formDrawer');
    var backdrop = document.getElementById('drawerBackdrop');
    drawer.classList.add('open');
    backdrop.classList.add('active');
    document.body.style.overflow = 'hidden';

    var title = drawer.querySelector('.cms-drawer-header h3');
    if (editId) {
        var warning = warningsData.find(function(w) { return w.id == editId; });
        if (warning) {
            title.innerHTML = '<i class="fas fa-exclamation-triangle"></i>Edit Warning';
            document.getElementById('formId').value = warning.id;
            document.getElementById('formStaff').value = warning.staff_id;
            document.getElementById('formType').value = warning.warning_type || 'Written';
            document.getElementById('formTitle').value = warning.title;
            document.getElementById('formIssuedOn').value = warning.issued_on || '';
            document.getElementById('formStatus').value = warning.is_active ?? '1';
            document.getElementById('formDescription').value = warning.description || '';
        }
    } else {
        title.innerHTML = '<i class="fas fa-exclamation-triangle"></i>Issue Warning';
        document.getElementById('formId').value = '0';
        document.getElementById('formStaff').value = '';
        document.getElementById('formType').value = 'Written';
        document.getElementById('formTitle').value = '';
        document.getElementById('formIssuedOn').value = '';
        document.getElementById('formStatus').value = '1';
        document.getElementById('formDescription').value = '';
    }
}

function closeDrawer() {
    var drawer = document.getElementById('formDrawer');
    var backdrop = document.getElementById('drawerBackdrop');
    drawer.classList.remove('open');
    backdrop.classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeDrawer();
});

<?php if ($drawerOpen): ?>
document.addEventListener('DOMContentLoaded', function() {
    openDrawer(<?= (int) $editWarning['id'] ?>);
});
<?php endif; ?>
</script>