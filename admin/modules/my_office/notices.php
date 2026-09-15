<?php
/**
 * SB-Tech — My Office / Notices.
 * Manage office-wide notices/announcements.
 */
$db = Database::instance();
$editNotice = null;

if (isset($_GET['edit_id'])) {
    $editNotice = $db->selectOne('SELECT * FROM `tbl_office_notices` WHERE `id` = ?', [(int) $_GET['edit_id']]);
}

$notices = $db->select(
    'SELECT n.*, u.fullname AS added_by_name
     FROM `tbl_office_notices` n
     LEFT JOIN `tbl_users_login` u ON u.id = n.added_by
     ORDER BY n.added_on DESC, n.id DESC'
);

$drawerOpen = ($editNotice !== null);
?>

<!-- Data Table (full width) -->
<div class="card card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-bullhorn mr-1"></i>Notices (<?= count($notices) ?>)</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary btn-sm" onclick="openDrawer()">
                <i class="fas fa-plus mr-1"></i>Add Notice
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover mb-0">
                <thead><tr><th>#</th><th>Title</th><th>Description</th><th>Status</th><th>Added By</th><th>Added On</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                <?php if (!$notices): ?>
                    <tr><td colspan="7" class="text-center text-muted">No notices published yet.</td></tr>
                <?php else: foreach ($notices as $i => $row): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><strong><?= e($row['title']) ?></strong></td>
                        <td><?= e(mb_strimwidth((string) ($row['description'] ?? ''), 0, 50, '…')) ?></td>
                        <td><span class="badge badge-<?= ($row['is_active'] ?? 1) ? 'success' : 'secondary' ?>"><?= ($row['is_active'] ?? 1) ? 'Active' : 'Inactive' ?></span></td>
                        <td class="small"><?= e($row['added_by_name']) ?></td>
                        <td class="small"><?= e(date('Y-m-d H:i', strtotime((string) $row['added_on']))) ?></td>
                        <td class="text-right">
                            <button type="button" class="btn btn-xs btn-outline-primary" onclick="openDrawer(<?= (int) $row['id'] ?>)"><i class="fas fa-edit"></i></button>
                            <form action="operation.php?module=my_office&page=notices" method="post" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete this notice?"><i class="fas fa-trash"></i></button>
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
        <h3><i class="fas fa-bullhorn"></i><?= $editNotice ? 'Edit Notice' : 'Add Notice' ?></h3>
        <button type="button" class="cms-drawer-close" onclick="closeDrawer()" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="cms-drawer-body">
        <form action="operation.php?module=my_office&page=notices" method="post" id="noticeForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="formId" value="<?= $editNotice ? (int) $editNotice['id'] : 0 ?>">
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" class="form-control" id="formTitle" required value="<?= $editNotice ? e($editNotice['title']) : '' ?>">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="is_active" class="form-control" id="formStatus">
                    <option value="1" <?= ($editNotice && ($editNotice['is_active'] ?? 1)) ? 'selected' : '' ?>>Active</option>
                    <option value="0" <?= $editNotice && !($editNotice['is_active'] ?? 1) ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" id="formDescription" rows="4"><?= $editNotice ? e($editNotice['description']) : '' ?></textarea>
            </div>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="noticeForm" class="btn btn-primary btn-block">
            <i class="fas fa-save mr-1"></i><?= $editNotice ? 'Update Notice' : 'Create Notice' ?>
        </button>
    </div>
</div>

<script>
// Notice data for edit population
var noticesData = <?= json_encode(array_values($notices)) ?>;

function openDrawer(editId) {
    var drawer = document.getElementById('formDrawer');
    var backdrop = document.getElementById('drawerBackdrop');
    drawer.classList.add('open');
    backdrop.classList.add('active');
    document.body.style.overflow = 'hidden';

    var title = drawer.querySelector('.cms-drawer-header h3');
    if (editId) {
        var notice = noticesData.find(function(n) { return n.id == editId; });
        if (notice) {
            title.innerHTML = '<i class="fas fa-bullhorn"></i>Edit Notice';
            document.getElementById('formId').value = notice.id;
            document.getElementById('formTitle').value = notice.title;
            document.getElementById('formStatus').value = notice.is_active ?? '1';
            document.getElementById('formDescription').value = notice.description || '';
        }
    } else {
        title.innerHTML = '<i class="fas fa-bullhorn"></i>Add Notice';
        document.getElementById('formId').value = '0';
        document.getElementById('formTitle').value = '';
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
    openDrawer(<?= (int) $editNotice['id'] ?>);
});
<?php endif; ?>
</script>