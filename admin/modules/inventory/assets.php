<?php
/**
 * SB-Tech — Inventory / Assets (US-INV-07).
 * Individual tracked assets: serial numbers, assignment, warranty, maintenance.
 */
$db = Database::instance();

$editAsset = null;
if (isset($_GET['id']) && !isset($_GET['view_log'])) {
    $editAsset = $db->selectOne('SELECT * FROM `tbl_inv_assets` WHERE `id` = ?', [(int) $_GET['id']]);
    if ($editAsset) {
        $editAsset['assigned_name'] = $db->selectOne('SELECT fullname FROM `tbl_users_login` WHERE id = ?', [(int) $editAsset['assigned_to']])['fullname'] ?? '';
    }
}

$viewLogId = isset($_GET['view_log']) ? (int) $_GET['view_log'] : 0;

$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = $_GET['status'] ?? '';

$where = ['1=1'];
$params = [];
if ($search !== '') {
    $where[] = '(a.name LIKE ? OR a.asset_tag LIKE ? OR a.serial_number LIKE ?)';
    $p = '%' . $db->escapeLike($search) . '%';
    $params = [$p, $p, $p];
}
if ($statusFilter && in_array($statusFilter, ['In Stock','Assigned','Under Maintenance','Retired','Disposed'], true)) {
    $where[] = 'a.current_status = ?';
    $params[] = $statusFilter;
}
$whereSql = implode(' AND ', $where);

$assets = $db->select(
    "SELECT a.*, i.name AS item_name, u.fullname AS assigned_name
     FROM `tbl_inv_assets` a
     LEFT JOIN `tbl_inv_items` i ON i.id = a.item_id
     LEFT JOIN `tbl_users_login` u ON u.id = a.assigned_to
     WHERE {$whereSql}
     ORDER BY a.asset_tag",
    $params
);

$items = $db->select('SELECT id, name, sku FROM `tbl_inv_items` WHERE is_active = 1 ORDER BY name');
$staffs = $db->select("SELECT id, fullname FROM `tbl_users_login` WHERE status = 'Active' ORDER BY fullname");
$conditionBadges = ['New' => 'success', 'Good' => 'info', 'Fair' => 'warning', 'Poor' => 'danger', 'Damaged' => 'danger', 'Retired' => 'secondary'];
$statusBadges = ['In Stock' => 'success', 'Assigned' => 'primary', 'Under Maintenance' => 'warning', 'Retired' => 'secondary', 'Disposed' => 'dark'];

$drawerOpen = ($editAsset !== null);

if ($viewLogId):
    $asset = $db->selectOne('SELECT * FROM `tbl_inv_assets` WHERE `id` = ?', [$viewLogId]);
    $logs = $db->select(
        "SELECT l.*, u.fullname AS actor_name FROM `tbl_inv_asset_logs` l
         LEFT JOIN `tbl_users_login` u ON u.id = l.performed_by
         WHERE l.asset_id = ? ORDER BY l.performed_on DESC",
        [$viewLogId]
    );
?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-history mr-1"></i>Asset Log: <?= e($asset['name'] ?? '') ?> (<?= e($asset['asset_tag'] ?? '') ?>)</h3>
        <a href="<?= pageUrl('inventory', 'assets') ?>" class="btn btn-sm btn-default float-right">Back</a>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm table-striped">
            <thead><tr><th>Date</th><th>Action</th><th>Old</th><th>New</th><th>By</th><th>Remarks</th></tr></thead>
            <tbody>
            <?php if (!$logs): ?>
                <tr><td colspan="6" class="text-muted text-center">No logs yet.</td></tr>
            <?php else: foreach ($logs as $l): ?>
                <tr>
                    <td class="small"><?= e($l['performed_on']) ?></td>
                    <td><span class="badge badge-info"><?= e($l['action']) ?></span></td>
                    <td class="small"><?= e($l['old_value']) ?></td>
                    <td class="small"><?= e($l['new_value']) ?></td>
                    <td class="small"><?= e($l['actor_name']) ?></td>
                    <td class="small"><?= e($l['remarks']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>

<!-- Data Table (full width) -->
<div class="card card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-laptop mr-1"></i>Tracked Assets (<?= count($assets) ?>)</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary btn-sm" onclick="openDrawer()">
                <i class="fas fa-plus mr-1"></i>Add Asset
            </button>
            <form method="get" class="form-inline d-inline ml-2">
                <input type="hidden" name="module" value="inventory">
                <input type="hidden" name="page" value="assets">
                <select name="status" class="form-control form-control-sm mr-1" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <?php foreach (array_keys($statusBadges) as $s): ?>
                        <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="q" class="form-control form-control-sm mr-1" placeholder="Search..." value="<?= e($search) ?>">
                <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-search"></i></button>
            </form>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover mb-0">
                <thead><tr><th>Tag</th><th>Name</th><th>Item</th><th>Serial</th><th>Condition</th><th>Status</th><th>Assigned To</th><th>Warranty</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if (!$assets): ?>
                    <tr><td colspan="9" class="text-center text-muted">No assets tracked yet.</td></tr>
                <?php else: foreach ($assets as $a): ?>
                    <tr>
                        <td><code><?= e($a['asset_tag']) ?></code></td>
                        <td><strong><?= e($a['name']) ?></strong></td>
                        <td class="small"><?= e($a['item_name'] ?? '—') ?></td>
                        <td class="small"><?= e($a['serial_number']) ?></td>
                        <td><span class="badge badge-<?= $conditionBadges[$a['condition_status']] ?? 'secondary' ?>"><?= e($a['condition_status']) ?></span></td>
                        <td><span class="badge badge-<?= $statusBadges[$a['current_status']] ?? 'secondary' ?>"><?= e($a['current_status']) ?></span></td>
                        <td class="small"><?= e($a['assigned_name'] ?: '—') ?></td>
                        <td class="small"><?= $a['warranty_expiry'] ? e($a['warranty_expiry']) : '—' ?></td>
                        <td>
                            <button type="button" class="btn btn-xs btn-outline-primary" onclick="openDrawer(<?= (int) $a['id'] ?>)" title="Edit"><i class="fas fa-edit"></i></button>
                            <a href="<?= pageUrl('inventory', 'assets') ?>&view_log=<?= (int) $a['id'] ?>" class="btn btn-xs btn-outline-secondary" title="Log"><i class="fas fa-history"></i></a>
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
<div class="cms-drawer" id="assetDrawer">
    <div class="cms-drawer-header">
        <h3><i class="fas fa-laptop"></i><?= $editAsset ? 'Edit Asset' : 'Add Asset' ?></h3>
        <button type="button" class="cms-drawer-close" onclick="closeDrawer()" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="cms-drawer-body">
        <form action="operation.php?module=inventory&page=assets_operation" method="post" id="assetForm">
            <?= csrfField() ?>
            <input type="hidden" name="id" id="formId" value="<?= $editAsset ? (int) $editAsset['id'] : 0 ?>">
            <div class="form-row">
                <div class="col-6 form-group">
                    <label>Asset Tag *</label>
                    <input type="text" name="asset_tag" class="form-control" id="formAssetTag" required value="<?= $editAsset ? e($editAsset['asset_tag']) : '' ?>" placeholder="Auto-generated if empty">
                </div>
                <div class="col-6 form-group">
                    <label>Name *</label>
                    <input type="text" name="name" class="form-control" id="formName" required value="<?= $editAsset ? e($editAsset['name']) : '' ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Linked Item</label>
                <select name="item_id" class="form-control" id="formItemId">
                    <option value="0">— None —</option>
                    <?php foreach ($items as $i): ?>
                        <option value="<?= (int) $i['id'] ?>" <?= $editAsset && (int) $editAsset['item_id'] === (int) $i['id'] ? 'selected' : '' ?>><?= e($i['name']) ?> (<?= e($i['sku']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Serial Number</label>
                <input type="text" name="serial_number" class="form-control" id="formSerial" value="<?= $editAsset ? e($editAsset['serial_number']) : '' ?>">
            </div>
            <div class="form-row">
                <div class="col-6 form-group">
                    <label>Brand</label>
                    <input type="text" name="brand" class="form-control" id="formBrand" value="<?= $editAsset ? e($editAsset['brand']) : '' ?>">
                </div>
                <div class="col-6 form-group">
                    <label>Model</label>
                    <input type="text" name="model" class="form-control" id="formModel" value="<?= $editAsset ? e($editAsset['model']) : '' ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="col-6 form-group">
                    <label>Purchase Date</label>
                    <input type="date" name="purchase_date" class="form-control" id="formPurchaseDate" value="<?= $editAsset ? e($editAsset['purchase_date']) : '' ?>">
                </div>
                <div class="col-6 form-group">
                    <label>Purchase Price</label>
                    <input type="number" step="0.01" name="purchase_price" class="form-control" id="formPurchasePrice" value="<?= $editAsset ? e($editAsset['purchase_price']) : '' ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Warranty Expiry</label>
                <input type="date" name="warranty_expiry" class="form-control" id="formWarranty" value="<?= $editAsset ? e($editAsset['warranty_expiry']) : '' ?>">
            </div>
            <div class="form-row">
                <div class="col-6 form-group">
                    <label>Condition</label>
                    <select name="condition_status" class="form-control" id="formCondition">
                        <?php foreach (['New','Good','Fair','Poor','Damaged','Retired'] as $c): ?>
                            <option value="<?= $c ?>" <?= $editAsset && $editAsset['condition_status'] === $c ? 'selected' : '' ?>><?= $c ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 form-group">
                    <label>Status</label>
                    <select name="current_status" class="form-control" id="formStatus">
                        <?php foreach (['In Stock','Assigned','Under Maintenance','Retired','Disposed'] as $s): ?>
                            <option value="<?= $s ?>" <?= $editAsset && $editAsset['current_status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Assigned To</label>
                <select name="assigned_to" class="form-control" id="formAssignedTo">
                    <option value="0">— Nobody —</option>
                    <?php foreach ($staffs as $st): ?>
                        <option value="<?= (int) $st['id'] ?>" <?= $editAsset && (int) $editAsset['assigned_to'] === (int) $st['id'] ? 'selected' : '' ?>><?= e($st['fullname']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Location</label>
                <input type="text" name="location" class="form-control" id="formLocation" value="<?= $editAsset ? e($editAsset['location']) : '' ?>">
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" class="form-control" id="formNotes" rows="3"><?= $editAsset ? e($editAsset['notes']) : '' ?></textarea>
            </div>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="assetForm" class="btn btn-primary btn-block">
            <i class="fas fa-save mr-1"></i><?= $editAsset ? 'Update' : 'Create' ?> Asset
        </button>
    </div>
</div>

<script>
var assetsData = <?= json_encode(array_values($assets)) ?>;

function openDrawer(editId) {
    var drawer = document.getElementById('assetDrawer');
    var backdrop = document.getElementById('drawerBackdrop');
    drawer.classList.add('open');
    backdrop.classList.add('active');
    document.body.style.overflow = 'hidden';

    var title = drawer.querySelector('.cms-drawer-header h3');
    if (editId) {
        var a = assetsData.find(function(x) { return x.id == editId; });
        if (a) {
            title.innerHTML = '<i class="fas fa-laptop"></i>Edit Asset';
            document.getElementById('formId').value = a.id;
            document.getElementById('formAssetTag').value = a.asset_tag || '';
            document.getElementById('formName').value = a.name || '';
            document.getElementById('formItemId').value = a.item_id || '0';
            document.getElementById('formSerial').value = a.serial_number || '';
            document.getElementById('formBrand').value = a.brand || '';
            document.getElementById('formModel').value = a.model || '';
            document.getElementById('formPurchaseDate').value = a.purchase_date || '';
            document.getElementById('formPurchasePrice').value = a.purchase_price || '';
            document.getElementById('formWarranty').value = a.warranty_expiry || '';
            document.getElementById('formCondition').value = a.condition_status || 'New';
            document.getElementById('formStatus').value = a.current_status || 'In Stock';
            document.getElementById('formAssignedTo').value = a.assigned_to || '0';
            document.getElementById('formLocation').value = a.location || '';
            document.getElementById('formNotes').value = a.notes || '';
        }
    } else {
        title.innerHTML = '<i class="fas fa-laptop"></i>Add Asset';
        document.getElementById('formId').value = '0';
        document.getElementById('formAssetTag').value = '';
        document.getElementById('formName').value = '';
        document.getElementById('formItemId').value = '0';
        document.getElementById('formSerial').value = '';
        document.getElementById('formBrand').value = '';
        document.getElementById('formModel').value = '';
        document.getElementById('formPurchaseDate').value = '';
        document.getElementById('formPurchasePrice').value = '';
        document.getElementById('formWarranty').value = '';
        document.getElementById('formCondition').value = 'New';
        document.getElementById('formStatus').value = 'In Stock';
        document.getElementById('formAssignedTo').value = '0';
        document.getElementById('formLocation').value = '';
        document.getElementById('formNotes').value = '';
    }
}

function closeDrawer() {
    document.getElementById('assetDrawer').classList.remove('open');
    document.getElementById('drawerBackdrop').classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeDrawer(); });

<?php if ($drawerOpen): ?>
document.addEventListener('DOMContentLoaded', function() { openDrawer(<?= (int) $editAsset['id'] ?>); });
<?php endif; ?>
</script>
<?php endif; ?>
