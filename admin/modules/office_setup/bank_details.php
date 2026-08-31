<?php
/**
 * SB-Tech — Office Setup / Bank Details (US-SET-04).
 * Referenced by payment vouchers and the website payment info.
 */
$db = Database::instance();
$edit = null;
if (isset($_GET['id'])) {
    $edit = $db->selectOne('SELECT * FROM `tbl_office_bank_details` WHERE `id` = ?', [(int) $_GET['id']]);
}
$rows = $db->select('SELECT * FROM `tbl_office_bank_details` ORDER BY bank_name');

$drawerOpen = ($edit !== null);
?>

<!-- Data Table (full width) -->
<div class="card card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-university mr-1"></i>Bank Details (<?= count($rows) ?>)</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary btn-sm" onclick="openDrawer()">
                <i class="fas fa-plus mr-1"></i>Add Bank Detail
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover mb-0">
                <thead><tr><th>#</th><th>Bank</th><th>Account name</th><th>Branch</th><th>Account no.</th><th>Type</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $i => $r): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><strong><?= e($r['bank_name']) ?></strong></td>
                        <td><?= e($r['account_name']) ?></td>
                        <td><?= e($r['branch']) ?></td>
                        <td><code><?= e($r['account_number']) ?></code></td>
                        <td><?= e($r['account_type']) ?></td>
                        <td class="text-right">
                            <button type="button" class="btn btn-xs btn-outline-primary" onclick="openDrawer(<?= (int) $r['id'] ?>)"><i class="fas fa-edit"></i></button>
                            <form action="operation.php?module=office_setup&page=bank_details" method="post" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete bank account '<?= e($r['bank_name']) ?>'?""><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted">No bank details yet. Add your first bank account.</td></tr><?php endif; ?>
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
        <h3><i class="fas fa-university"></i><?= $edit ? 'Edit Bank Detail' : 'Add Bank Detail' ?></h3>
        <button type="button" class="cms-drawer-close" onclick="closeDrawer()" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="cms-drawer-body">
        <form action="operation.php?module=office_setup&page=bank_details" method="post" id="bankForm">
            <?= csrfField() ?>
            <input type="hidden" name="id" id="formId" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
            <div class="form-group">
                <label>Bank name *</label>
                <input type="text" name="bank_name" class="form-control" id="formBankName" required value="<?= $edit ? e($edit['bank_name']) : '' ?>">
            </div>
            <div class="form-group">
                <label>Account name *</label>
                <input type="text" name="account_name" class="form-control" id="formAccountName" required value="<?= $edit ? e($edit['account_name']) : '' ?>">
            </div>
            <div class="form-group">
                <label>Branch *</label>
                <input type="text" name="branch" class="form-control" id="formBranch" required value="<?= $edit ? e($edit['branch']) : '' ?>">
            </div>
            <div class="form-group">
                <label>Account number *</label>
                <input type="text" name="account_number" class="form-control" id="formAccountNumber" required value="<?= $edit ? e($edit['account_number']) : '' ?>">
            </div>
            <div class="form-group">
                <label>Account type *</label>
                <input type="text" name="account_type" class="form-control" id="formAccountType" required value="<?= $edit ? e($edit['account_type']) : '' ?>">
            </div>
            <div class="form-group">
                <label>SWIFT code</label>
                <input type="text" name="swift_code" class="form-control" id="formSwiftCode" value="<?= $edit ? e($edit['swift_code']) : '' ?>">
            </div>
            <div class="form-group">
                <label>Other details</label>
                <textarea name="other_detail" class="form-control" id="formOtherDetail" rows="3"><?= $edit ? e($edit['other_detail']) : '' ?></textarea>
            </div>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="bankForm" class="btn btn-primary btn-block">
            <i class="fas fa-save mr-1"></i><?= $edit ? 'Update' : 'Save' ?>
        </button>
    </div>
</div>

<script>
// Bank data for edit population
var bankData = <?= json_encode($rows) ?>;

function openDrawer(editId) {
    var drawer = document.getElementById('formDrawer');
    var backdrop = document.getElementById('drawerBackdrop');
    drawer.classList.add('open');
    backdrop.classList.add('active');
    document.body.style.overflow = 'hidden';

    // Update header title
    var title = drawer.querySelector('.cms-drawer-header h3');
    if (editId) {
        var bank = bankData.find(function(b) { return b.id == editId; });
        if (bank) {
            title.innerHTML = '<i class="fas fa-university"></i>Edit Bank Detail';
            document.getElementById('formId').value = bank.id;
            document.getElementById('formBankName').value = bank.bank_name;
            document.getElementById('formAccountName').value = bank.account_name;
            document.getElementById('formBranch').value = bank.branch;
            document.getElementById('formAccountNumber').value = bank.account_number;
            document.getElementById('formAccountType').value = bank.account_type;
            document.getElementById('formSwiftCode').value = bank.swift_code || '';
            document.getElementById('formOtherDetail').value = bank.other_detail || '';
        }
    } else {
        title.innerHTML = '<i class="fas fa-university"></i>Add Bank Detail';
        document.getElementById('formId').value = '0';
        document.getElementById('formBankName').value = '';
        document.getElementById('formAccountName').value = '';
        document.getElementById('formBranch').value = '';
        document.getElementById('formAccountNumber').value = '';
        document.getElementById('formAccountType').value = '';
        document.getElementById('formSwiftCode').value = '';
        document.getElementById('formOtherDetail').value = '';
    }
}

function closeDrawer() {
    var drawer = document.getElementById('formDrawer');
    var backdrop = document.getElementById('drawerBackdrop');
    drawer.classList.remove('open');
    backdrop.classList.remove('active');
    document.body.style.overflow = '';
}

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeDrawer();
});

// Open drawer on page load if editing
<?php if ($drawerOpen): ?>
document.addEventListener('DOMContentLoaded', function() {
    openDrawer(<?= (int) $edit['id'] ?>);
});
<?php endif; ?>
</script>
