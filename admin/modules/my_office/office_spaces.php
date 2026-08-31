<?php
/**
 * SB-Tech — My Office / Office Spaces (reference office_spaces).
 * Manage office rooms/areas.
 */
$db = Database::instance();
$editSpace = null;

if (isset($_GET['edit_id'])) {
    $editSpace = $db->selectOne('SELECT * FROM `tbl_office_spaces` WHERE `id` = ?', [(int) $_GET['edit_id']]);
}

$spaces = $db->select(
    'SELECT s.*, u.fullname AS added_by_name
     FROM `tbl_office_spaces` s
     LEFT JOIN `tbl_users_login` u ON u.id = s.added_by
     ORDER BY s.title'
);

$drawerOpen = ($editSpace !== null);
?>

<!-- Data Table (full width) -->
<div class="card card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-building mr-1"></i>Office Spaces (<?= count($spaces) ?>)</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary btn-sm" onclick="openDrawer()">
                <i class="fas fa-plus mr-1"></i>Add Space
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover mb-0">
                <thead><tr><th>#</th><th>Title</th><th>Description</th><th>Capacity</th><th>Status</th><th>Added By</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                <?php if (!$spaces): ?>
                    <tr><td colspan="7" class="text-center text-muted">No office spaces configured yet.</td></tr>
                <?php else: foreach ($spaces as $i => $s): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><strong><?= e($s['title']) ?></strong></td>
                        <td><?= e(mb_strimwidth($s['description'], 0, 50, '…')) ?></td>
                        <td><?= $s['capacity'] ? (int) $s['capacity'] : '—' ?></td>
                        <td><span class="badge badge-<?= ($s['is_active'] ?? 1) ? 'success' : 'secondary' ?>"><?= ($s['is_active'] ?? 1) ? 'Active' : 'Inactive' ?></span></td>
                        <td class="small"><?= e($s['added_by_name']) ?></td>
                        <td class="text-right">
                            <button type="button" class="btn btn-xs btn-outline-primary" onclick="openDrawer(<?= (int) $s['id'] ?>)"><i class="fas fa-edit"></i></button>
                            <form action="operation.php?module=my_office&page=office_spaces_operation" method="post" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete this space?"><i class="fas fa-trash"></i></button>
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
        <h3><i class="fas fa-building"></i><?= $editSpace ? 'Edit Space' : 'Add Space' ?></h3>
        <button type="button" class="cms-drawer-close" onclick="closeDrawer()" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="cms-drawer-body">
        <form action="operation.php?module=my_office&page=office_spaces_operation" method="post" id="spaceForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="formId" value="<?= $editSpace ? (int) $editSpace['id'] : 0 ?>">
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" class="form-control" id="formTitle" required value="<?= $editSpace ? e($editSpace['title']) : '' ?>">
            </div>
            <div class="form-group">
                <label>Capacity</label>
                <input type="number" name="capacity" class="form-control" id="formCapacity" value="<?= $editSpace ? e($editSpace['capacity']) : '' ?>">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="is_active" class="form-control" id="formStatus">
                    <option value="1" <?= ($editSpace && ($editSpace['is_active'] ?? 1)) ? 'selected' : '' ?>>Active</option>
                    <option value="0" <?= $editSpace && !($editSpace['is_active'] ?? 1) ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" id="formDescription" rows="3"><?= $editSpace ? e($editSpace['description']) : '' ?></textarea>
            </div>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="spaceForm" class="btn btn-primary btn-block">
            <i class="fas fa-save mr-1"></i><?= $editSpace ? 'Update Space' : 'Create Space' ?>
        </button>
    </div>
</div>

<script>
// Space data for edit population
var spacesData = <?= json_encode(array_values($spaces)) ?>;

function openDrawer(editId) {
    var drawer = document.getElementById('formDrawer');
    var backdrop = document.getElementById('drawerBackdrop');
    drawer.classList.add('open');
    backdrop.classList.add('active');
    document.body.style.overflow = 'hidden';

    var title = drawer.querySelector('.cms-drawer-header h3');
    if (editId) {
        var space = spacesData.find(function(s) { return s.id == editId; });
        if (space) {
            title.innerHTML = '<i class="fas fa-building"></i>Edit Space';
            document.getElementById('formId').value = space.id;
            document.getElementById('formTitle').value = space.title;
            document.getElementById('formCapacity').value = space.capacity || '';
            document.getElementById('formStatus').value = space.is_active ?? '1';
            document.getElementById('formDescription').value = space.description || '';
        }
    } else {
        title.innerHTML = '<i class="fas fa-building"></i>Add Space';
        document.getElementById('formId').value = '0';
        document.getElementById('formTitle').value = '';
        document.getElementById('formCapacity').value = '';
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
    openDrawer(<?= (int) $editSpace['id'] ?>);
});
<?php endif; ?>
</script>
