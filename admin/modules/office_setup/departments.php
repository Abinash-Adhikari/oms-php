<?php
/**
 * SB-Tech — Office Setup / Departments (US-SET-02).
 * Delete is blocked while staff are assigned (AC-SET-02.2).
 */
$db = Database::instance();
$edit = null;
if (isset($_GET['id'])) {
    $edit = $db->selectOne('SELECT * FROM `tbl_office_departments` WHERE `id` = ?', [(int) $_GET['id']]);
}
$rows = $db->select('SELECT d.*, (SELECT COUNT(*) FROM `tbl_users_login` u WHERE u.department_id = d.id) AS staff_count FROM `tbl_office_departments` d ORDER BY d.position, d.title');
$drawerOpen = ($edit !== null);
?>

<!-- Data Table (full width) -->
<div class="card card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-building mr-1"></i>Departments</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary btn-sm" onclick="openDrawer()">
                <i class="fas fa-plus mr-1"></i>Add Department
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover mb-0">
                <thead><tr><th>#</th><th>Title</th><th>Position</th><th>Staff</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $i => $r): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= e($r['title']) ?></td>
                        <td><?= (int) $r['position'] ?></td>
                        <td><span class="badge badge-<?= (int) $r['staff_count'] > 0 ? 'info' : 'secondary' ?>"><?= (int) $r['staff_count'] ?></span></td>
                        <td class="text-right">
                            <button type="button" class="btn btn-xs btn-outline-primary" onclick="openDrawer(<?= (int) $r['id'] ?>)"><i class="fas fa-edit"></i></button>
                            <form action="operation.php?module=office_setup&page=departments" method="post" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <?php if ((int) $r['staff_count'] > 0): ?>
                                    <button type="button" class="btn btn-xs btn-outline-danger" title="Cannot delete: staff assigned" disabled><i class="fas fa-trash"></i></button>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete department '<?= e($r['title']) ?>'?"><i class="fas fa-trash"></i></button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted">No departments yet.</td></tr><?php endif; ?>
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
        <h3><i class="fas fa-building"></i><span id="drawerTitle"><?= $edit ? 'Edit' : 'Add' ?> Department</span></h3>
        <button type="button" class="cms-drawer-close" onclick="closeDrawer()" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="cms-drawer-body">
        <form action="operation.php?module=office_setup&page=departments" method="post" id="drawerForm">
            <?= csrfField() ?>
            <input type="hidden" name="id" id="formId" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" class="form-control" id="formTitle" required value="<?= $edit ? e($edit['title']) : '' ?>">
            </div>
            <div class="form-group">
                <label>Sort position</label>
                <input type="number" name="position" class="form-control" id="formPosition" value="<?= $edit ? (int) $edit['position'] : 0 ?>">
            </div>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="drawerForm" class="btn btn-primary btn-block">
            <i class="fas fa-save mr-1"></i><span id="drawerBtnText"><?= $edit ? 'Update' : 'Save' ?></span>
        </button>
    </div>
</div>

<script>
function openDrawer(editId) {
    var drawer = document.getElementById('formDrawer');
    var backdrop = document.getElementById('drawerBackdrop');
    drawer.classList.add('open');
    backdrop.classList.add('active');
    document.body.style.overflow = 'hidden';

    if (editId) {
        window.location.href = '<?= pageUrl('office_setup', 'departments') ?>&id=' + editId;
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
    openDrawer();
});
<?php endif; ?>
</script>
