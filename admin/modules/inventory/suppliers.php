<?php
/**
 * SB-Tech — Inventory / Suppliers (US-INV-03).
 * Supplier/vendor master with CRUD.
 */
$db = Database::instance();

$editSupplier = null;
if (isset($_GET['id'])) {
    $editSupplier = $db->selectOne('SELECT * FROM `tbl_inv_suppliers` WHERE `id` = ?', [(int) $_GET['id']]);
}

$search = trim((string) ($_GET['q'] ?? ''));
$where = ['1=1'];
$params = [];
if ($search !== '') {
    $where[] = '(name LIKE ? OR contact_person LIKE ? OR email LIKE ?)';
    $p = '%' . $db->escapeLike($search) . '%';
    $params = [$p, $p, $p];
}
$suppliers = $db->select(
    'SELECT * FROM `tbl_inv_suppliers` WHERE ' . implode(' AND ', $where) . ' ORDER BY name',
    $params
);

$drawerOpen = ($editSupplier !== null);
?>

<!-- Data Table (full width) -->
<div class="card card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-truck mr-1"></i>Suppliers (<?= count($suppliers) ?>)</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary btn-sm" onclick="openDrawer()">
                <i class="fas fa-plus mr-1"></i>Add Supplier
            </button>
            <form method="get" class="form-inline d-inline ml-2">
                <input type="hidden" name="module" value="inventory">
                <input type="hidden" name="page" value="suppliers">
                <input type="text" name="q" class="form-control form-control-sm mr-1" placeholder="Search suppliers..." value="<?= e($search) ?>">
                <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-search"></i></button>
            </form>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover mb-0">
                <thead><tr><th>#</th><th>Name</th><th>Contact</th><th>Email</th><th>Phone</th><th>PAN</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                <?php if (!$suppliers): ?>
                    <tr><td colspan="8" class="text-center text-muted">No suppliers yet. Add your first supplier.</td></tr>
                <?php else: foreach ($suppliers as $i => $s): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><strong><?= e($s['name']) ?></strong></td>
                        <td><?= e($s['contact_person']) ?></td>
                        <td><?= e($s['email']) ?></td>
                        <td><?= e($s['phone']) ?></td>
                        <td><?= e($s['pan_num']) ?></td>
                        <td><?= $s['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>' ?></td>
                        <td class="text-right">
                            <button type="button" class="btn btn-xs btn-outline-primary" onclick="openDrawer(<?= (int) $s['id'] ?>)"><i class="fas fa-edit"></i></button>
                            <form action="operation.php?module=inventory&page=suppliers_operation" method="post" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete supplier '<?= e($s['name']) ?>'?"><i class="fas fa-trash"></i></button>
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
        <h3><i class="fas fa-truck"></i><?= $editSupplier ? 'Edit Supplier' : 'Add Supplier' ?></h3>
        <button type="button" class="cms-drawer-close" onclick="closeDrawer()" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="cms-drawer-body">
        <form action="operation.php?module=inventory&page=suppliers_operation" method="post" id="supplierForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="formId" value="<?= $editSupplier ? (int) $editSupplier['id'] : 0 ?>">
            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name" class="form-control" id="formName" required value="<?= $editSupplier ? e($editSupplier['name']) : '' ?>">
            </div>
            <div class="form-group">
                <label>Contact Person</label>
                <input type="text" name="contact_person" class="form-control" id="formContactPerson" value="<?= $editSupplier ? e($editSupplier['contact_person']) : '' ?>">
            </div>
            <div class="form-group">
                <label>PAN No</label>
                <input type="text" name="pan_num" class="form-control" id="formPanNum" value="<?= $editSupplier ? e($editSupplier['pan_num']) : '' ?>">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control" id="formEmail" value="<?= $editSupplier ? e($editSupplier['email']) : '' ?>">
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone" class="form-control" id="formPhone" value="<?= $editSupplier ? e($editSupplier['phone']) : '' ?>">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="is_active" class="form-control" id="formStatus">
                    <option value="1" <?= $editSupplier && $editSupplier['is_active'] ? 'selected' : '' ?>>Active</option>
                    <option value="0" <?= $editSupplier && !$editSupplier['is_active'] ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="form-group">
                <label>Address</label>
                <input type="text" name="address" class="form-control" id="formAddress" value="<?= $editSupplier ? e($editSupplier['address']) : '' ?>">
            </div>
            <div class="form-group">
                <label>Bank Name</label>
                <input type="text" name="bank_name" class="form-control" id="formBankName" value="<?= $editSupplier ? e($editSupplier['bank_name']) : '' ?>">
            </div>
            <div class="form-group">
                <label>Bank Account No</label>
                <input type="text" name="bank_account_num" class="form-control" id="formBankAccount" value="<?= $editSupplier ? e($editSupplier['bank_account_num']) : '' ?>">
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" class="form-control" id="formNotes" rows="3"><?= $editSupplier ? e($editSupplier['notes']) : '' ?></textarea>
            </div>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="supplierForm" class="btn btn-primary btn-block">
            <i class="fas fa-save mr-1"></i><?= $editSupplier ? 'Update' : 'Create' ?> Supplier
        </button>
    </div>
</div>

<script>
var suppliersData = <?= json_encode(array_values($suppliers)) ?>;

function openDrawer(editId) {
    var drawer = document.getElementById('formDrawer');
    var backdrop = document.getElementById('drawerBackdrop');
    drawer.classList.add('open');
    backdrop.classList.add('active');
    document.body.style.overflow = 'hidden';

    var title = drawer.querySelector('.cms-drawer-header h3');
    if (editId) {
        var s = suppliersData.find(function(x) { return x.id == editId; });
        if (s) {
            title.innerHTML = '<i class="fas fa-truck"></i>Edit Supplier';
            document.getElementById('formId').value = s.id;
            document.getElementById('formName').value = s.name || '';
            document.getElementById('formContactPerson').value = s.contact_person || '';
            document.getElementById('formPanNum').value = s.pan_num || '';
            document.getElementById('formEmail').value = s.email || '';
            document.getElementById('formPhone').value = s.phone || '';
            document.getElementById('formStatus').value = s.is_active ?? '1';
            document.getElementById('formAddress').value = s.address || '';
            document.getElementById('formBankName').value = s.bank_name || '';
            document.getElementById('formBankAccount').value = s.bank_account_num || '';
            document.getElementById('formNotes').value = s.notes || '';
        }
    } else {
        title.innerHTML = '<i class="fas fa-truck"></i>Add Supplier';
        document.getElementById('formId').value = '0';
        document.getElementById('formName').value = '';
        document.getElementById('formContactPerson').value = '';
        document.getElementById('formPanNum').value = '';
        document.getElementById('formEmail').value = '';
        document.getElementById('formPhone').value = '';
        document.getElementById('formStatus').value = '1';
        document.getElementById('formAddress').value = '';
        document.getElementById('formBankName').value = '';
        document.getElementById('formBankAccount').value = '';
        document.getElementById('formNotes').value = '';
    }
}

function closeDrawer() {
    document.getElementById('formDrawer').classList.remove('open');
    document.getElementById('drawerBackdrop').classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeDrawer(); });

<?php if ($drawerOpen): ?>
document.addEventListener('DOMContentLoaded', function() { openDrawer(<?= (int) $editSupplier['id'] ?>); });
<?php endif; ?>
</script>
