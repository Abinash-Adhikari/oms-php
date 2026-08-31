<?php
/**
 * SB-Tech — Office Setup / Holidays (US-SET-03).
 * Holiday dates don't consume leave balance; attendance defaults to 'holiday'.
 */
$db = Database::instance();
$edit = null;
if (isset($_GET['id'])) {
    $edit = $db->selectOne('SELECT * FROM `tbl_office_holidays` WHERE `id` = ?', [(int) $_GET['id']]);
}
$rows = $db->select('SELECT h.*, d.title AS department_title FROM `tbl_office_holidays` h LEFT JOIN `tbl_office_departments` d ON d.id = h.department_id ORDER BY h.from_date DESC');
$departments = $db->select('SELECT * FROM `tbl_office_departments` ORDER BY position, title');
$drawerOpen = ($edit !== null);
?>

<!-- Data Table (full width) -->
<div class="card card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-calendar-alt mr-1"></i>Holidays</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary btn-sm" onclick="openDrawer()">
                <i class="fas fa-plus mr-1"></i>Add Holiday
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover mb-0">
                <thead><tr><th>#</th><th>Title</th><th>From</th><th>To</th><th>Department</th><th>Gender</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $i => $r): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= e($r['title']) ?></td>
                        <td><?= e(formatDateView($r['from_date'])) ?></td>
                        <td><?= e(formatDateView($r['to_date'])) ?></td>
                        <td><?= e($r['department_title'] ?? 'All') ?></td>
                        <td><?= e($r['gender_to']) ?></td>
                        <td class="text-right">
                            <button type="button" class="btn btn-xs btn-outline-primary" onclick="openDrawer(<?= (int) $r['id'] ?>)"><i class="fas fa-edit"></i></button>
                            <form action="operation.php?module=office_setup&page=holidays" method="post" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete holiday '<?= e($r['title']) ?>'?"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted">No holidays yet.</td></tr><?php endif; ?>
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
        <h3><i class="fas fa-calendar-alt"></i><span id="drawerTitle"><?= $edit ? 'Edit' : 'Add' ?> Holiday</span></h3>
        <button type="button" class="cms-drawer-close" onclick="closeDrawer()" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="cms-drawer-body">
        <form action="operation.php?module=office_setup&page=holidays" method="post" id="drawerForm">
            <?= csrfField() ?>
            <input type="hidden" name="id" id="formId" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" class="form-control" required value="<?= $edit ? e($edit['title']) : '' ?>">
            </div>
            <div class="form-group">
                <label>From date *</label>
                <input type="date" name="from_date" class="form-control" required value="<?= $edit ? e($edit['from_date']) : '' ?>">
            </div>
            <div class="form-group">
                <label>To date *</label>
                <input type="date" name="to_date" class="form-control" required value="<?= $edit ? e($edit['to_date']) : '' ?>">
            </div>
            <div class="form-group">
                <label>Department scope</label>
                <select name="department_id" class="form-control">
                    <option value="">All departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int) $d['id'] ?>" <?= $edit && (int) $edit['department_id'] === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Gender scope</label>
                <select name="gender_to" class="form-control">
                    <?php foreach (['Both', 'Male', 'Female'] as $g): ?>
                        <option value="<?= $g ?>" <?= $edit && $edit['gender_to'] === $g ? 'selected' : '' ?>><?= $g ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Remarks</label>
                <input type="text" name="remarks" class="form-control" value="<?= $edit ? e($edit['remarks']) : '' ?>">
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
        window.location.href = '<?= pageUrl('office_setup', 'holidays') ?>&id=' + editId;
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
