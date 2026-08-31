<?php
/**
 * SB-Tech — Inventory / Categories (US-INV-01).
 * Manage item categories with hierarchy support.
 */
$db = Database::instance();

$editCat = null;
if (isset($_GET['edit_id'])) {
    $editCat = $db->selectOne('SELECT * FROM `tbl_inv_categories` WHERE `id` = ?', [(int) $_GET['edit_id']]);
}

$categories = $db->select(
    'SELECT c.*, p.title AS parent_title,
            (SELECT COUNT(*) FROM `tbl_inv_items` WHERE category_id = c.id) AS item_count
     FROM `tbl_inv_categories` c
     LEFT JOIN `tbl_inv_categories` p ON p.id = c.parent_id
     ORDER BY c.position, c.title'
);

$drawerOpen = ($editCat !== null);
?>

<!-- Data Table (full width) -->
<div class="card card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-tags mr-1"></i>Item Categories (<?= count($categories) ?>)</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary btn-sm" onclick="openDrawer()">
                <i class="fas fa-plus mr-1"></i>Add Category
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover mb-0">
                <thead><tr><th>#</th><th>Title</th><th>Parent</th><th>Items</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                <?php if (!$categories): ?>
                    <tr><td colspan="6" class="text-center text-muted">No categories yet. Add your first category.</td></tr>
                <?php else: foreach ($categories as $i => $c): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><strong><?= e($c['title']) ?></strong></td>
                        <td><?= e($c['parent_title'] ?? '—') ?></td>
                        <td><span class="badge badge-info"><?= (int) $c['item_count'] ?></span></td>
                        <td><?= $c['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>' ?></td>
                        <td class="text-right">
                            <button type="button" class="btn btn-xs btn-outline-primary" onclick="openDrawer(<?= (int) $c['id'] ?>)"><i class="fas fa-edit"></i></button>
                            <form action="operation.php?module=inventory&page=categories_operation" method="post" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete this category?" <?= $c['item_count'] > 0 ? 'disabled title="Items exist in this category"' : '' ?>><i class="fas fa-trash"></i></button>
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
        <h3><i class="fas fa-tags"></i><?= $editCat ? 'Edit Category' : 'Add Category' ?></h3>
        <button type="button" class="cms-drawer-close" onclick="closeDrawer()" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="cms-drawer-body">
        <form action="operation.php?module=inventory&page=categories_operation" method="post" id="categoryForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="formId" value="<?= $editCat ? (int) $editCat['id'] : 0 ?>">
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" class="form-control" id="formTitle" required value="<?= $editCat ? e($editCat['title']) : '' ?>">
            </div>
            <div class="form-group">
                <label>Parent Category</label>
                <select name="parent_id" class="form-control" id="formParentId">
                    <option value="">— None (top-level) —</option>
                    <?php foreach ($categories as $c): ?>
                        <?php if (!$editCat || (int) $c['id'] !== (int) ($editCat['id'] ?? 0)): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= $editCat && (int) $editCat['parent_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Position</label>
                <input type="number" name="position" class="form-control" id="formPosition" value="<?= $editCat ? (int) $editCat['position'] : 0 ?>">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="is_active" class="form-control" id="formStatus">
                    <option value="1" <?= ($editCat && $editCat['is_active']) ? 'selected' : '' ?>>Active</option>
                    <option value="0" <?= $editCat && !$editCat['is_active'] ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" id="formDescription" rows="3"><?= $editCat ? e($editCat['description']) : '' ?></textarea>
            </div>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="categoryForm" class="btn btn-primary btn-block">
            <i class="fas fa-save mr-1"></i><?= $editCat ? 'Update' : 'Create' ?> Category
        </button>
    </div>
</div>

<script>
var categoriesData = <?= json_encode(array_values($categories)) ?>;

function openDrawer(editId) {
    var drawer = document.getElementById('formDrawer');
    var backdrop = document.getElementById('drawerBackdrop');
    drawer.classList.add('open');
    backdrop.classList.add('active');
    document.body.style.overflow = 'hidden';

    var title = drawer.querySelector('.cms-drawer-header h3');
    if (editId) {
        var cat = categoriesData.find(function(c) { return c.id == editId; });
        if (cat) {
            title.innerHTML = '<i class="fas fa-tags"></i>Edit Category';
            document.getElementById('formId').value = cat.id;
            document.getElementById('formTitle').value = cat.title;
            document.getElementById('formParentId').value = cat.parent_id || '';
            document.getElementById('formPosition').value = cat.position || 0;
            document.getElementById('formStatus').value = cat.is_active ?? '1';
            document.getElementById('formDescription').value = cat.description || '';
        }
    } else {
        title.innerHTML = '<i class="fas fa-tags"></i>Add Category';
        document.getElementById('formId').value = '0';
        document.getElementById('formTitle').value = '';
        document.getElementById('formParentId').value = '';
        document.getElementById('formPosition').value = '0';
        document.getElementById('formStatus').value = '1';
        document.getElementById('formDescription').value = '';
    }
}

function closeDrawer() {
    document.getElementById('formDrawer').classList.remove('open');
    document.getElementById('drawerBackdrop').classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeDrawer(); });

<?php if ($drawerOpen): ?>
document.addEventListener('DOMContentLoaded', function() { openDrawer(<?= (int) $editCat['id'] ?>); });
<?php endif; ?>
</script>
