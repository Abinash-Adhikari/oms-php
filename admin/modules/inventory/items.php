<?php
/**
 * SB-Tech — Inventory / Items (US-INV-02).
 * Item master data with CRUD, stock level indicators, CSV export.
 */
$db = Database::instance();

$editItem = null;
if (isset($_GET['id'])) {
    $editItem = $db->selectOne('SELECT * FROM `tbl_inv_items` WHERE `id` = ?', [(int) $_GET['id']]);
}

$search = trim((string) ($_GET['q'] ?? ''));
$catFilter = (int) ($_GET['cat_id'] ?? 0);

$where = ['1=1'];
$params = [];
if ($search !== '') {
    $where[] = '(i.name LIKE ? OR i.sku LIKE ?)';
    $params[] = '%' . $db->escapeLike($search) . '%';
    $params[] = '%' . $db->escapeLike($search) . '%';
}
if ($catFilter) {
    $where[] = 'i.category_id = ?';
    $params[] = $catFilter;
}

$whereSql = implode(' AND ', $where);
$categories = $db->select('SELECT * FROM `tbl_inv_categories` WHERE `is_active` = 1 ORDER BY position, title');
$items = $db->select(
    "SELECT i.*, c.title AS category_title,
            COALESCE(s.total, 0) AS current_stock
     FROM `tbl_inv_items` i
     LEFT JOIN `tbl_inv_categories` c ON c.id = i.category_id
     LEFT JOIN (SELECT item_id, SUM(quantity) AS total FROM `tbl_inv_stock` GROUP BY item_id) s ON s.item_id = i.id
     WHERE {$whereSql}
     ORDER BY i.name",
    $params
);

$drawerOpen = ($editItem !== null);
?>

<!-- Data Table (full width) -->
<div class="card card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-box mr-1"></i>Items (<?= count($items) ?>)</h3>
        <div class="card-tools">
            <form action="operation.php?module=inventory&page=items_operation" method="post" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="export_items">
                <button class="btn btn-success btn-sm"><i class="fas fa-download mr-1"></i>CSV</button>
            </form>
            <button type="button" class="btn btn-primary btn-sm ml-1" onclick="openDrawer()">
                <i class="fas fa-plus mr-1"></i>Add Item
            </button>
            <form method="get" class="form-inline d-inline ml-2">
                <input type="hidden" name="module" value="inventory">
                <input type="hidden" name="page" value="items">
                <select name="cat_id" class="form-control form-control-sm mr-1" onchange="this.form.submit()">
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= $catFilter === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="q" class="form-control form-control-sm mr-1" placeholder="Search items..." value="<?= e($search) ?>">
                <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-search"></i></button>
            </form>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover mb-0">
                <thead><tr><th>SKU</th><th>Name</th><th>Category</th><th>Unit</th><th>Cost</th><th>Stock</th><th>Min</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                <?php if (!$items): ?>
                    <tr><td colspan="9" class="text-center text-muted">No items found.</td></tr>
                <?php else: foreach ($items as $item): ?>
                    <tr>
                        <td><code><?= e($item['sku']) ?></code></td>
                        <td><strong><?= e($item['name']) ?></strong><?= $item['is_serialized'] ? ' <span class="badge badge-info" title="Serialized">S</span>' : '' ?></td>
                        <td><?= e($item['category_title'] ?? '—') ?></td>
                        <td><?= e($item['unit'] ?? '—') ?></td>
                        <td><?= formatMoney($item['cost_price']) ?></td>
                        <td><strong><?= (int) $item['current_stock'] ?></strong></td>
                        <td><?= (int) $item['min_stock'] ?></td>
                        <td>
                            <?php if ((int) $item['current_stock'] <= 0): ?>
                                <span class="badge badge-danger">Out of Stock</span>
                            <?php elseif ((int) $item['current_stock'] <= (int) $item['min_stock']): ?>
                                <span class="badge badge-warning">Low Stock</span>
                            <?php else: ?>
                                <span class="badge badge-success">In Stock</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right">
                            <button type="button" class="btn btn-xs btn-outline-primary" onclick="openDrawer(<?= (int) $item['id'] ?>)"><i class="fas fa-edit"></i></button>
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
<div class="cms-drawer" id="itemDrawer">
    <div class="cms-drawer-header">
        <h3><i class="fas fa-box"></i><?= $editItem ? 'Edit Item' : 'Add Item' ?></h3>
        <button type="button" class="cms-drawer-close" onclick="closeDrawer()" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="cms-drawer-body">
        <form action="operation.php?module=inventory&page=items_operation" method="post" id="itemForm">
            <?= csrfField() ?>
            <input type="hidden" name="id" id="formId" value="<?= $editItem ? (int) $editItem['id'] : 0 ?>">
            <div class="form-group">
                <label>SKU *</label>
                <input type="text" name="sku" class="form-control" id="formSku" required value="<?= $editItem ? e($editItem['sku']) : '' ?>">
            </div>
            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name" class="form-control" id="formName" required value="<?= $editItem ? e($editItem['name']) : '' ?>">
            </div>
            <div class="form-group">
                <label>Category</label>
                <select name="category_id" class="form-control" id="formCategoryId">
                    <option value="0">— None —</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= $editItem && (int) $editItem['category_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Unit</label>
                <input type="text" name="unit" class="form-control" id="formUnit" placeholder="e.g. pcs, hrs, kg" value="<?= $editItem ? e($editItem['unit'] ?? '') : '' ?>">
            </div>
            <div class="form-row">
                <div class="col-6 form-group">
                    <label>Cost Price</label>
                    <input type="number" step="0.01" name="cost_price" class="form-control" id="formCostPrice" value="<?= $editItem ? e($editItem['cost_price']) : '' ?>">
                </div>
                <div class="col-6 form-group">
                    <label>Selling Price</label>
                    <input type="number" step="0.01" name="selling_price" class="form-control" id="formSellingPrice" value="<?= $editItem ? e($editItem['selling_price']) : '' ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="col-6 form-group">
                    <label>Min Stock</label>
                    <input type="number" name="min_stock" class="form-control" id="formMinStock" value="<?= $editItem ? e($editItem['min_stock']) : '0' ?>">
                </div>
                <div class="col-6 form-group">
                    <label>Serialized?</label>
                    <select name="is_serialized" class="form-control" id="formIsSerialized">
                        <option value="0" <?= $editItem && !$editItem['is_serialized'] ? 'selected' : '' ?>>No</option>
                        <option value="1" <?= $editItem && $editItem['is_serialized'] ? 'selected' : '' ?>>Yes</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" id="formDescription" rows="3"><?= $editItem ? e($editItem['description'] ?? '') : '' ?></textarea>
            </div>
            <?php if ($editItem): ?>
                <hr>
                <div class="form-group text-danger">
                    <form action="operation.php?module=inventory&page=items_operation" method="post" class="d-inline" onsubmit="return confirm('Delete this item?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_item">
                        <input type="hidden" name="id" value="<?= (int) $editItem['id'] ?>">
                        <button class="btn btn-danger btn-sm"><i class="fas fa-trash mr-1"></i>Delete Item</button>
                    </form>
                </div>
            <?php endif; ?>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="itemForm" class="btn btn-primary btn-block">
            <i class="fas fa-save mr-1"></i><?= $editItem ? 'Update Item' : 'Create Item' ?>
        </button>
    </div>
</div>

<script>
var itemsData = <?= json_encode(array_values($items)) ?>;

function openDrawer(editId) {
    var drawer = document.getElementById('itemDrawer');
    var backdrop = document.getElementById('drawerBackdrop');
    drawer.classList.add('open');
    backdrop.classList.add('active');
    document.body.style.overflow = 'hidden';

    var title = drawer.querySelector('.cms-drawer-header h3');
    if (editId) {
        var item = itemsData.find(function(i) { return i.id == editId; });
        if (item) {
            title.innerHTML = '<i class="fas fa-box"></i>Edit Item';
            document.getElementById('formId').value = item.id;
            document.getElementById('formSku').value = item.sku || '';
            document.getElementById('formName').value = item.name || '';
            document.getElementById('formCategoryId').value = item.category_id || '0';
            document.getElementById('formUnit').value = item.unit || '';
            document.getElementById('formCostPrice').value = item.cost_price || '';
            document.getElementById('formSellingPrice').value = item.selling_price || '';
            document.getElementById('formMinStock').value = item.min_stock || '0';
            document.getElementById('formIsSerialized').value = item.is_serialized || '0';
            document.getElementById('formDescription').value = item.description || '';
        }
    } else {
        title.innerHTML = '<i class="fas fa-box"></i>Add Item';
        document.getElementById('formId').value = '0';
        document.getElementById('formSku').value = '';
        document.getElementById('formName').value = '';
        document.getElementById('formCategoryId').value = '0';
        document.getElementById('formUnit').value = '';
        document.getElementById('formCostPrice').value = '';
        document.getElementById('formSellingPrice').value = '';
        document.getElementById('formMinStock').value = '0';
        document.getElementById('formIsSerialized').value = '0';
        document.getElementById('formDescription').value = '';
    }
}

function closeDrawer() {
    document.getElementById('itemDrawer').classList.remove('open');
    document.getElementById('drawerBackdrop').classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeDrawer(); });

<?php if ($drawerOpen): ?>
document.addEventListener('DOMContentLoaded', function() { openDrawer(<?= (int) $editItem['id'] ?>); });
<?php endif; ?>
</script>
