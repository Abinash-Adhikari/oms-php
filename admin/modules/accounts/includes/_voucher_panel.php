<?php
/**
 * Accounts / Posting — generic voucher panel for one type (AC-FIN-03.x).
 * Expects: $vt (config row from accountingVoucherConfig), $tab, $canApprove.
 * Renders: filter bar, list with actions, new/edit form with dynamic
 * balanced lines, and a detail modal when ?view_id= is present.
 */
$db = Database::instance();
$me = (int) Auth::id();
$vtKey = (string) $tab;
$table = $vt['table'];
$voucherType = $vt['type'];

$statusFilter = (string) ($_GET['status'] ?? '');
$keyword = trim((string) ($_GET['q'] ?? ''));
$where = ['1=1'];
$params = [];
if (in_array($statusFilter, ['Pending', 'Approved', 'Rejected'], true)) {
    $where[] = 'v.status = ?';
    $params[] = $statusFilter;
}
if ($keyword !== '') {
    $like = '%' . $db->escapeLike($keyword) . '%';
    $where[] = '(v.voucher_no LIKE ? OR v.narration LIKE ? OR v.reference_no LIKE ?)';
    array_push($params, $like, $like, $like);
}
$vouchers = $db->select(
    'SELECT v.*, u.fullname AS added_by_name,
            (SELECT COUNT(*) FROM `tbl_ledger_particulars` lp
             WHERE lp.voucher_type = ? AND lp.voucher_type_id = v.id) AS line_count
     FROM `' . $table . '` v
     LEFT JOIN `tbl_users_login` u ON u.id = v.added_by
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY v.id DESC LIMIT 200',
    array_merge([$voucherType], $params)
);

// Edit mode: load voucher + its lines.
$edit = null;
$editLines = [];
$editId = (int) ($_GET['edit_id'] ?? 0);
if ($editId) {
    $edit = accountingVoucherById($db, $vtKey, $editId);
    if ($edit) {
        if ($edit['status'] !== 'Pending') {
            $edit = null; // Approved vouchers need un-approve before editing (AC-FIN-03.4)
        } else {
            $editLines = accountingParticularsFor($db, $voucherType, $editId);
        }
    }
}

// Detail modal data.
$view = null;
$viewLines = [];
$viewLogs = [];
$viewId = (int) ($_GET['view_id'] ?? 0);
if ($viewId) {
    $view = accountingVoucherById($db, $vtKey, $viewId);
    if ($view) {
        $viewLines = accountingParticularsFor($db, $voucherType, $viewId);
        $viewLogs = $db->select(
            'SELECT * FROM `tbl_voucher_logs` WHERE `voucher_type` = ? AND `voucher_type_id` = ? ORDER BY `id` DESC LIMIT 20',
            [$vtKey, $viewId]
        );
    }
}

$terminalTree = accountingTerminalOptions();
$openFy = accountingCurrentOpenFy();
?>

<div class="row">
    <div class="col-lg-7">
        <div class="card card-outline mb-3">
            <div class="card-header">
                <h3 class="card-title"><i class="fas <?= e($vt['fa']) ?> mr-1"></i><?= e($vt['label']) ?> list</h3>
                <div class="card-tools">
                    <form method="get" class="form-inline">
                        <input type="hidden" name="module" value="accounts">
                        <input type="hidden" name="page" value="postings">
                        <input type="hidden" name="tab" value="<?= e($vtKey) ?>">
                        <select name="status" class="form-control form-control-sm mr-1" onchange="this.form.submit()">
                            <option value="">All statuses</option>
                            <option value="Pending" <?= $statusFilter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="Approved" <?= $statusFilter === 'Approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="Rejected" <?= $statusFilter === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                        </select>
                        <input type="text" name="q" value="<?= e($keyword) ?>" class="form-control form-control-sm mr-1" placeholder="Search no / narration">
                        <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-search"></i></button>
                    </form>
                    <form action="operation.php?module=accounts&page=postings" method="post" class="d-inline ml-1">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="export_vouchers">
                        <input type="hidden" name="type" value="<?= e($vtKey) ?>">
                        <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-file-csv mr-1"></i>CSV</button>
                    </form>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover mb-0">
                        <thead>
                            <tr><th>Voucher No</th><th>Date</th><th>Reference</th><th>Narration</th><th class="text-right">Amount</th><th>Status</th><th class="text-right">Actions</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($vouchers as $v): ?>
                            <tr>
                                <td><a href="<?= pageUrl('accounts', 'postings') ?>&tab=<?= e($vtKey) ?>&view_id=<?= (int) $v['id'] ?>"><?= e($v['voucher_no']) ?></a></td>
                                <td><?= e(formatDateView($v['voucher_date'])) ?></td>
                                <td><?= e($v['reference_no'] ?? '—') ?></td>
                                <td class="text-truncate" style="max-width:220px" title="<?= e($v['narration'] ?? '') ?>"><?= e($v['narration'] ?? '—') ?></td>
                                <td class="text-right"><?= e(formatMoney($v['total_amount'] ?? $v['amount'])) ?></td>
                                <td>
                                    <span class="badge badge-<?= $v['status'] === 'Approved' ? 'success' : ($v['status'] === 'Rejected' ? 'danger' : 'warning') ?>">
                                        <?= e($v['status']) ?>
                                    </span>
                                    <?php if ($v['status'] === 'Approved' && $v['approved_by']): ?>
                                        <small class="d-block text-muted">by <?= e($v['approved_by'] ? '#' . $v['approved_by'] : '') ?> <?= e(date('M j, H:i', strtotime($v['updated_on'] ?: $v['added_on']))) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <a href="<?= pageUrl('accounts', 'postings') ?>&tab=<?= e($vtKey) ?>&view_id=<?= (int) $v['id'] ?>" class="btn btn-xs btn-outline-secondary" title="View"><i class="fas fa-eye"></i></a>
                                    <?php if ($v['status'] === 'Pending'): ?>
                                        <a href="<?= pageUrl('accounts', 'postings') ?>&tab=<?= e($vtKey) ?>&edit_id=<?= (int) $v['id'] ?>" class="btn btn-xs btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                        <form action="operation.php?module=accounts&page=postings" method="post" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="approve_voucher">
                                            <input type="hidden" name="type" value="<?= e($vtKey) ?>">
                                            <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                                            <button class="btn btn-xs btn-outline-success confirm-submit" data-confirm="Approve <?= e($v['voucher_no']) ?>?" title="Approve" <?= $canApprove ? '' : 'disabled' ?>><i class="fas fa-check"></i></button>
                                        </form>
                                        <form action="operation.php?module=accounts&page=postings" method="post" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete_voucher">
                                            <input type="hidden" name="type" value="<?= e($vtKey) ?>">
                                            <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                                            <button class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete <?= e($v['voucher_no']) ?> and its lines?" title="Delete"><i class="fas fa-trash"></i></button>
                                        </form>
                                    <?php elseif ($v['status'] === 'Approved' && $canApprove): ?>
                                        <form action="operation.php?module=accounts&page=postings" method="post" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="unapprove_voucher">
                                            <input type="hidden" name="type" value="<?= e($vtKey) ?>">
                                            <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                                            <button class="btn btn-xs btn-outline-warning confirm-submit" data-confirm="Un-approve <?= e($v['voucher_no']) ?>? (audited)" title="Un-approve"><i class="fas fa-undo"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$vouchers): ?>
                            <tr><td colspan="7" class="text-center text-muted">No <?= e(strtolower($vt['label'])) ?>s found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-pen mr-1"></i><?= $edit ? 'Edit ' : 'New ' ?><?= e($vt['label']) ?></h3>
                <?php if ($edit): ?>
                    <a href="<?= pageUrl('accounts', 'postings') ?>&tab=<?= e($vtKey) ?>" class="btn btn-xs btn-default float-right">Cancel</a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!$openFy): ?>
                    <div class="callout callout-danger mb-0"><p>No open fiscal year. Create one in Fiscal Years first.</p></div>
                <?php else: ?>
                <form action="operation.php?module=accounts&page=postings" method="post" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="save_voucher">
                    <input type="hidden" name="type" value="<?= e($vtKey) ?>">
                    <input type="hidden" name="id" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
                    <div class="form-row">
                        <div class="form-group col-6">
                            <label>Date * <small class="text-muted">(<?= e($openFy['title']) ?>)</small></label>
                            <input type="date" name="date" class="form-control" required value="<?= $edit ? e($edit['voucher_date']) : date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group col-6">
                            <label>Reference no</label>
                            <input type="text" name="reference_no" class="form-control" value="<?= $edit ? e($edit['reference_no'] ?? '') : '' ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Narration</label>
                        <textarea name="narration" class="form-control" rows="2"><?= $edit ? e($edit['narration'] ?? '') : '' ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Attachment (jpg/png/pdf)</label>
                        <input type="file" name="file" class="form-control-file">
                        <?php if ($edit && $edit['file_name']): ?>
                            <small class="d-block text-muted">Current: <?= e($edit['file_name']) ?></small>
                        <?php endif; ?>
                    </div>

                    <label>Entries — debits must equal credits *</label>
                    <div id="voucherLines">
                        <?php
                        $lineRows = $editLines ?: [[
                            'account_terminal_id' => 0, 'debit' => 0, 'credit' => 0, 'remarks' => null,
                        ]];
                        foreach ($lineRows as $li => $line):
                        ?>
                            <div class="voucher-line border rounded p-2 mb-2">
                                <div class="input-group input-group-sm mb-1">
                                    <select name="account_terminal_id[]" class="form-control v-line-terminal" required>
                                        <option value="">— account —</option>
                                        <?php foreach ($terminalTree as $gTitle => $subgroups): ?>
                                            <optgroup label="<?= e($gTitle) ?>">
                                                <?php foreach ($subgroups as $sgTitle => $terminals): ?>
                                                    <?php foreach ($terminals as $t): ?>
                                                        <option value="<?= (int) $t['id'] ?>" <?= (int) $line['account_terminal_id'] === (int) $t['id'] ? 'selected' : '' ?>>
                                                            <?= e($sgTitle) ?> → <?= e($t['title']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-row">
                                    <div class="col-4"><input type="number" step="0.01" min="0" name="debit[]" class="form-control form-control-sm v-line-debit" placeholder="Debit" value="<?= $line['debit'] ? e(number_format((float) $line['debit'], 2, '.', '')) : '' ?>"></div>
                                    <div class="col-4"><input type="number" step="0.01" min="0" name="credit[]" class="form-control form-control-sm v-line-credit" placeholder="Credit" value="<?= $line['credit'] ? e(number_format((float) $line['credit'], 2, '.', '')) : '' ?>"></div>
                                    <div class="col-4 text-right">
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeVoucherLine(this)"><i class="fas fa-times"></i></button>
                                    </div>
                                </div>
                                <input type="text" name="line_remarks[]" class="form-control form-control-sm mt-1" placeholder="Line remark (optional)" value="<?= e($line['remarks'] ?? '') ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary mb-2" onclick="addVoucherLine()"><i class="fas fa-plus mr-1"></i>Add line</button>
                    <div id="voucherBalance" class="small text-danger font-weight-bold"></div>
                    <button type="submit" id="voucherSaveBtn" class="btn btn-primary btn-block mt-2" disabled>
                        <i class="fas fa-save mr-1"></i><?= $edit ? 'Update voucher' : 'Save voucher (Pending)' ?>
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<template id="voucherLineTpl">
    <div class="voucher-line border rounded p-2 mb-2">
        <div class="input-group input-group-sm mb-1">
            <select name="account_terminal_id[]" class="form-control v-line-terminal" required>
                <option value="">— account —</option>
                <?php foreach ($terminalTree as $gTitle => $subgroups): ?>
                    <optgroup label="<?= e($gTitle) ?>">
                        <?php foreach ($subgroups as $sgTitle => $terminals): ?>
                            <?php foreach ($terminals as $t): ?>
                                <option value="<?= (int) $t['id'] ?>"><?= e($sgTitle) ?> → <?= e($t['title']) ?></option>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <div class="col-4"><input type="number" step="0.01" min="0" name="debit[]" class="form-control form-control-sm v-line-debit" placeholder="Debit"></div>
            <div class="col-4"><input type="number" step="0.01" min="0" name="credit[]" class="form-control form-control-sm v-line-credit" placeholder="Credit"></div>
            <div class="col-4 text-right">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeVoucherLine(this)"><i class="fas fa-times"></i></button>
            </div>
        </div>
        <input type="text" name="line_remarks[]" class="form-control form-control-sm mt-1" placeholder="Line remark (optional)">
    </div>
</template>

<?php if ($view): ?>
<div class="modal fade show" id="voucherDetailModal" style="display:block" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= e($view['voucher_no']) ?> — <?= e($vt['label']) ?></h5>
                <a href="<?= pageUrl('accounts', 'postings') ?>&tab=<?= e($vtKey) ?>" class="close"><span>&times;</span></a>
            </div>
            <div class="modal-body">
                <div class="row mb-2">
                    <div class="col-4"><strong>Date</strong><br><?= e(formatDateView($view['voucher_date'])) ?></div>
                    <div class="col-4"><strong>Reference</strong><br><?= e($view['reference_no'] ?? '—') ?></div>
                    <div class="col-4"><strong>Status</strong><br><span class="badge badge-<?= $view['status'] === 'Approved' ? 'success' : 'warning' ?>"><?= e($view['status']) ?></span></div>
                </div>
                <p><strong>Narration:</strong> <?= e($view['narration'] ?? '—') ?></p>
                <table class="table table-sm table-bordered">
                    <thead><tr><th>Account</th><th class="text-right">Debit</th><th class="text-right">Credit</th><th>Remark</th></tr></thead>
                    <tbody>
                    <?php foreach ($viewLines as $vl): ?>
                        <tr>
                            <td><?= e($vl['account_terminal_title']) ?></td>
                            <td class="text-right"><?= $vl['debit'] > 0 ? e(formatMoney($vl['debit'])) : '' ?></td>
                            <td class="text-right"><?= $vl['credit'] > 0 ? e(formatMoney($vl['credit'])) : '' ?></td>
                            <td><?= e($vl['remarks'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="font-weight-bold">
                            <td>Total</td>
                            <td class="text-right"><?= e(formatMoney(array_sum(array_column($viewLines, 'debit')))) ?></td>
                            <td class="text-right"><?= e(formatMoney(array_sum(array_column($viewLines, 'credit')))) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
                <?php if ($viewLogs): ?>
                    <h6 class="mt-3">Audit log</h6>
                    <ul class="list-unstyled">
                        <?php foreach ($viewLogs as $log): ?>
                            <li><span class="badge badge-light border"><?= e($log['action']) ?></span>
                                <small><?= e($log['added_on']) ?></small></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
<?php endif; ?>
