<?php
/**
 * SB-Tech — Accounts / Expense Claims (US-FIN-06, US-FIN-07, US-FIN-08).
 * Staff submit claims (Draft → Submitted); finance approves (→ Payment
 * voucher auto-created) or rejects with a reason; the claim becomes Paid
 * when its payment voucher is approved. Edit/delete only while Draft or
 * Rejected (AC-FIN-06.3).
 */
$db = Database::instance();
$me = (int) Auth::id();
$canApprove = Auth::isSuperAdmin() || Auth::hasSpecial('approve_expense_claims');

$projects = $db->select('SELECT id, title, client_id FROM `tbl_client_projects` ORDER BY title');
$clients = $db->select('SELECT id, name FROM `tbl_clients` ORDER BY name');

$edit = null;
$editId = (int) ($_GET['edit_id'] ?? 0);
if ($editId) {
    $edit = $db->selectOne('SELECT * FROM `tbl_expense_claims` WHERE `id` = ? AND `staff_id` = ?', [$editId, $me]);
    if ($edit && !in_array($edit['status'], ['Draft', 'Rejected'], true)) {
        $edit = null; // locked once submitted/approved/paid
    }
}
$editFiles = [];
if ($edit) {
    $editFiles = $db->select('SELECT * FROM `tbl_expense_claim_files` WHERE `claim_id` = ?', [(int) $edit['id']]);
}

// My claims.
$myClaims = $db->select(
    'SELECT c.*, pv.voucher_no AS payment_voucher_no
     FROM `tbl_expense_claims` c
     LEFT JOIN `tbl_payment_vouchers` pv ON pv.id = c.payment_voucher_id
     WHERE c.staff_id = ?
     ORDER BY c.id DESC LIMIT 100',
    [$me]
);

// Finance view: all claims + filters.
$allClaims = [];
if ($canApprove) {
    $where = ['1=1'];
    $params = [];
    $fStaff = (int) ($_GET['staff_id'] ?? 0);
    $fStatus = (string) ($_GET['status'] ?? '');
    $fCategory = trim((string) ($_GET['category'] ?? ''));
    if ($fStaff) {
        $where[] = 'c.staff_id = ?';
        $params[] = $fStaff;
    }
    if (in_array($fStatus, ['Draft', 'Submitted', 'Approved', 'Rejected', 'Paid'], true)) {
        $where[] = 'c.status = ?';
        $params[] = $fStatus;
    }
    if ($fCategory !== '') {
        $where[] = 'c.category LIKE ?';
        $params[] = '%' . $db->escapeLike($fCategory) . '%';
    }
    $allClaims = $db->select(
        'SELECT c.*, u.fullname AS staff_name, pv.voucher_no AS payment_voucher_no
         FROM `tbl_expense_claims` c
         LEFT JOIN `tbl_users_login` u ON u.id = c.staff_id
         LEFT JOIN `tbl_payment_vouchers` pv ON pv.id = c.payment_voucher_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY c.id DESC LIMIT 200',
        $params
    );
    $staffList = $db->select('SELECT id, fullname FROM `tbl_users_login` WHERE `status` = ? ORDER BY fullname', ['Active']);
}

// Summary
$summaryWhere = $canApprove ? '1=1' : 'staff_id = ?';
$summaryParams = $canApprove ? [] : [$me];
$summary = $db->selectOne(
    'SELECT COUNT(*) AS total,
            COALESCE(SUM(CASE WHEN status IN (\'Submitted\',\'Approved\') THEN amount ELSE 0 END), 0) AS outstanding,
            COALESCE(SUM(CASE WHEN status = \'Paid\' THEN amount ELSE 0 END), 0) AS paid
     FROM `tbl_expense_claims` WHERE ' . $summaryWhere,
    $summaryParams
);
$categories = $db->select(
    'SELECT category, COALESCE(SUM(amount),0) AS total, COUNT(*) AS c
     FROM `tbl_expense_claims` WHERE ' . $summaryWhere . ' GROUP BY category ORDER BY total DESC LIMIT 10',
    $summaryParams
);

$drawerOpen = ($edit !== null);
?>

<!-- Summary + My Claims (full width) -->
<div class="row">
    <div class="col-lg-3">
        <div class="card card-outline">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-chart-pie mr-1"></i>Summary</h3></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tr><td>Total claims</td><td class="text-right"><?= (int) $summary['total'] ?></td></tr>
                    <tr><td>Outstanding</td><td class="text-right"><?= e(formatMoney($summary['outstanding'])) ?></td></tr>
                    <tr><td>Paid</td><td class="text-right"><?= e(formatMoney($summary['paid'])) ?></td></tr>
                </table>
                <?php if ($categories): ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($categories as $cat): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center p-2">
                            <?= e($cat['category'] ?: '—') ?>
                            <span class="badge badge-light border"><?= e(formatMoney($cat['total'])) ?> (<?= (int) $cat['c'] ?>)</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-9">
        <div class="card card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-list mr-1"></i>My Claims</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary btn-sm" onclick="openDrawer()">
                        <i class="fas fa-plus mr-1"></i>New Claim
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover mb-0">
                        <thead><tr><th>Claim no</th><th>Date</th><th>Category</th><th class="text-right">Amount</th><th>Status</th><th>Voucher</th><th class="text-right">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($myClaims as $c): ?>
                            <tr>
                                <td><?= e($c['claim_no']) ?></td>
                                <td><?= e(formatDateView($c['expense_date'])) ?></td>
                                <td><?= e($c['category'] ?? '—') ?></td>
                                <td class="text-right"><?= e(formatMoney($c['amount'])) ?></td>
                                <td>
                                    <span class="badge badge-<?= $c['status'] === 'Paid' ? 'success' : ($c['status'] === 'Approved' ? 'info' : ($c['status'] === 'Rejected' ? 'danger' : ($c['status'] === 'Submitted' ? 'primary' : 'secondary'))) ?>">
                                        <?= e($c['status']) ?>
                                    </span>
                                    <?php if ($c['status'] === 'Rejected' && $c['reject_reason']): ?>
                                        <small class="d-block text-danger" title="<?= e($c['reject_reason']) ?>">rejected: <?= e(mb_strimwidth($c['reject_reason'], 0, 40, '…')) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($c['payment_voucher_no'] ?? '—') ?></td>
                                <td class="text-right">
                                    <?php if (in_array($c['status'], ['Draft', 'Rejected'], true)): ?>
                                        <button type="button" class="btn btn-xs btn-outline-primary" onclick="openDrawer(<?= (int) $c['id'] ?>)"><i class="fas fa-edit"></i></button>
                                        <form action="operation.php?module=accounts&page=expense_claims" method="post" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete_claim">
                                            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                            <button class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete claim <?= e($c['claim_no']) ?> and its receipt files?"><i class="fas fa-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$myClaims): ?><tr><td colspan="7" class="text-center text-muted">No claims yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($canApprove): ?>
<div class="card card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-clipboard-check mr-1"></i>All Claims — Review</h3>
        <div class="card-tools">
            <form method="get" class="form-inline">
                <input type="hidden" name="module" value="accounts">
                <input type="hidden" name="page" value="expense_claims">
                <select name="staff_id" class="form-control form-control-sm mr-1" onchange="this.form.submit()">
                    <option value="0">All staff</option>
                    <?php foreach ($staffList as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= $fStaff === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['fullname']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="status" class="form-control form-control-sm mr-1" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    <?php foreach (['Draft', 'Submitted', 'Approved', 'Rejected', 'Paid'] as $st): ?>
                        <option value="<?= $st ?>" <?= $fStatus === $st ? 'selected' : '' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-search"></i></button>
            </form>
            <form action="operation.php?module=accounts&page=expense_claims" method="post" class="d-inline ml-1">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="export_claims">
                <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-file-csv mr-1"></i>CSV</button>
            </form>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover mb-0">
                <thead><tr><th>Claim no</th><th>Staff</th><th>Date</th><th>Category</th><th class="text-right">Amount</th><th>Status</th><th>Voucher</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($allClaims as $c): ?>
                    <tr>
                        <td><?= e($c['claim_no']) ?></td>
                        <td><?= e($c['staff_name'] ?? '#' . $c['staff_id']) ?></td>
                        <td><?= e(formatDateView($c['expense_date'])) ?></td>
                        <td><?= e($c['category'] ?? '—') ?></td>
                        <td class="text-right"><?= e(formatMoney($c['amount'])) ?></td>
                        <td><span class="badge badge-<?= $c['status'] === 'Paid' ? 'success' : ($c['status'] === 'Approved' ? 'info' : ($c['status'] === 'Rejected' ? 'danger' : 'primary')) ?>"><?= e($c['status']) ?></span></td>
                        <td><?= e($c['payment_voucher_no'] ?? '—') ?></td>
                        <td class="text-right">
                            <?php if ($c['status'] === 'Submitted'): ?>
                                <form action="operation.php?module=accounts&page=expense_claims" method="post" class="d-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="approve_claim">
                                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                    <button class="btn btn-xs btn-outline-success confirm-submit" data-confirm="Approve claim <?= e($c['claim_no']) ?>? A Pending Payment voucher will be auto-created."><i class="fas fa-check mr-1"></i>Approve</button>
                                </form>
                                <form action="operation.php?module=accounts&page=expense_claims" method="post" class="d-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="reject_claim">
                                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                    <input type="text" name="reject_reason" class="form-control form-control-sm d-inline" style="max-width:150px" placeholder="Reason (required)" required>
                                    <button class="btn btn-xs btn-outline-danger"><i class="fas fa-times mr-1"></i>Reject</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$allClaims): ?><tr><td colspan="8" class="text-center text-muted">No claims match the filter.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Slide-in Drawer Backdrop -->
<div class="cms-drawer-backdrop" id="drawerBackdrop" onclick="closeDrawer()"></div>

<!-- Slide-in Drawer -->
<div class="cms-drawer" id="claimDrawer">
    <div class="cms-drawer-header">
        <h3><i class="fas fa-file-invoice-dollar"></i><?= $edit ? 'Edit Claim' : 'New Expense Claim' ?></h3>
        <button type="button" class="cms-drawer-close" onclick="closeDrawer()" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="cms-drawer-body">
        <form action="operation.php?module=accounts&page=expense_claims" method="post" enctype="multipart/form-data" id="claimForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_claim">
            <input type="hidden" name="id" id="claimId" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
            <div class="form-group">
                <label>Category *</label>
                <input type="text" name="category" class="form-control" list="claimCategories" id="claimCategory" required value="<?= $edit ? e($edit['category'] ?? '') : '' ?>" placeholder="e.g. Travel, Stationery">
                <datalist id="claimCategories">
                    <?php foreach ($categories as $cat): ?><option value="<?= e($cat['category']) ?>"><?php endforeach; ?>
                </datalist>
            </div>
            <div class="form-row">
                <div class="form-group col-6">
                    <label>Expense date *</label>
                    <input type="date" name="expense_date" class="form-control" id="claimDate" required value="<?= $edit ? e($edit['expense_date']) : date('Y-m-d') ?>">
                </div>
                <div class="form-group col-6">
                    <label>Amount (NPR) *</label>
                    <input type="number" name="amount" step="0.01" min="0.01" class="form-control" id="claimAmount" required value="<?= $edit ? e(number_format((float) $edit['amount'], 2, '.', '')) : '' ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Description *</label>
                <textarea name="description" class="form-control" id="claimDescription" rows="3" required><?= $edit ? e($edit['description'] ?? '') : '' ?></textarea>
            </div>
            <div class="form-row">
                <div class="form-group col-6">
                    <label>Project (optional)</label>
                    <select name="project_id" class="form-control" id="claimProject">
                        <option value="">—</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= (int) $p['id'] ?>" <?= $edit && (int) $edit['project_id'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-6">
                    <label>Client (optional)</label>
                    <select name="client_id" class="form-control" id="claimClient">
                        <option value="">—</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= $edit && (int) $edit['client_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Receipt files * <small class="text-muted">(jpg/png/pdf, required to submit)</small></label>
                <input type="file" name="receipt_files[]" class="form-control-file" multiple <?= $edit ? '' : 'required' ?>>
                <?php if ($editFiles): ?>
                    <ul class="list-unstyled mt-2 mb-0">
                        <?php foreach ($editFiles as $ef): ?>
                            <li><a href="<?= assetUrl('user_uploads/' . $ef['file_location']) ?>" target="_blank"><i class="fas fa-file mr-1"></i><?= e($ef['file_name']) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <small class="d-block text-muted mt-1">Submitting locks the claim for review (AC-FIN-06.2).</small>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="claimForm" class="btn btn-secondary btn-block mb-2">
            <i class="fas fa-save mr-1"></i>Save as Draft
        </button>
        <button type="submit" name="submit_now" value="1" form="claimForm" class="btn btn-primary btn-block">
            <i class="fas fa-paper-plane mr-1"></i>Save & Submit
        </button>
    </div>
</div>

<script>
var claimsData = <?= json_encode(array_values($myClaims)) ?>;

function openDrawer(editId) {
    var drawer = document.getElementById('claimDrawer');
    var backdrop = document.getElementById('drawerBackdrop');
    drawer.classList.add('open');
    backdrop.classList.add('active');
    document.body.style.overflow = 'hidden';

    var title = drawer.querySelector('.cms-drawer-header h3');
    if (editId) {
        var claim = claimsData.find(function(c) { return c.id == editId; });
        if (claim) {
            title.innerHTML = '<i class="fas fa-file-invoice-dollar"></i>Edit Claim';
            document.getElementById('claimId').value = claim.id;
            document.getElementById('claimCategory').value = claim.category || '';
            document.getElementById('claimDate').value = claim.expense_date || '';
            document.getElementById('claimAmount').value = claim.amount || '';
            document.getElementById('claimDescription').value = claim.description || '';
            document.getElementById('claimProject').value = claim.project_id || '';
            document.getElementById('claimClient').value = claim.client_id || '';
        }
    } else {
        title.innerHTML = '<i class="fas fa-file-invoice-dollar"></i>New Expense Claim';
        document.getElementById('claimId').value = '0';
        document.getElementById('claimCategory').value = '';
        document.getElementById('claimDate').value = '<?= date("Y-m-d") ?>';
        document.getElementById('claimAmount').value = '';
        document.getElementById('claimDescription').value = '';
        document.getElementById('claimProject').value = '';
        document.getElementById('claimClient').value = '';
    }
}

function closeDrawer() {
    document.getElementById('claimDrawer').classList.remove('open');
    document.getElementById('drawerBackdrop').classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeDrawer(); });

<?php if ($drawerOpen): ?>
document.addEventListener('DOMContentLoaded', function() { openDrawer(<?= (int) $edit['id'] ?>); });
<?php endif; ?>
</script>
