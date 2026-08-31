<?php
/**
 * SB-Tech — Accounts / Bank Reconciliation (US-FIN-05).
 * Create a statement session for a bank terminal, then match Approved
 * ledger lines to it (reconcile_ref + actor/time). Status per session:
 * Open → Matched → Closed (AC-FIN-05.1).
 */
$db = Database::instance();

$bankTerminals = $db->select(
    'SELECT t.*, sg.title AS subgroup_title, g.title AS group_title
     FROM `tbl_account_terminals` t
     JOIN `tbl_account_sub_groups` sg ON sg.id = t.account_subgroup_id
     JOIN `tbl_account_groups` g ON g.id = sg.group_id
     WHERE g.title = ? AND t.title != ?
     ORDER BY t.title',
    ['Assets', 'Cash in Hand']
);

$sessionId = (int) ($_GET['session_id'] ?? 0);
$sessions = $db->select(
    'SELECT r.*, t.title AS terminal_title, u.fullname AS added_by_name
     FROM `tbl_bank_reconciliation` r
     LEFT JOIN `tbl_account_terminals` t ON t.id = r.account_terminal_id
     LEFT JOIN `tbl_users_login` u ON u.id = r.added_by
     ORDER BY r.id DESC LIMIT 100'
);

$session = null;
$unmatched = [];
$matched = [];
if ($sessionId) {
    $session = $db->selectOne('SELECT * FROM `tbl_bank_reconciliation` WHERE `id` = ?', [$sessionId]);
    if ($session) {
        $unmatched = $db->select(
            'SELECT lp.*,
                    COALESCE(jv.voucher_no, rv.voucher_no, pv.voucher_no, cv.voucher_no, puv.voucher_no, sv.voucher_no) AS voucher_no
             FROM `tbl_ledger_particulars` lp
             LEFT JOIN `tbl_journal_vouchers`  jv  ON lp.voucher_type = \'Journal\'  AND jv.id  = lp.voucher_type_id
             LEFT JOIN `tbl_receipt_vouchers`  rv  ON lp.voucher_type = \'Receipt\'  AND rv.id  = lp.voucher_type_id
             LEFT JOIN `tbl_payment_vouchers`  pv  ON lp.voucher_type = \'Payment\'  AND pv.id  = lp.voucher_type_id
             LEFT JOIN `tbl_contra_vouchers`   cv  ON lp.voucher_type = \'Contra\'   AND cv.id  = lp.voucher_type_id
             LEFT JOIN `tbl_purchase_vouchers` puv ON lp.voucher_type = \'Purchase\' AND puv.id = lp.voucher_type_id
             LEFT JOIN `tbl_sales_vouchers`    sv  ON lp.voucher_type = \'Sales\'    AND sv.id  = lp.voucher_type_id
             WHERE lp.account_terminal_id = ? AND lp.voucher_status = ?
               AND lp.reconcile_ref IS NULL
             ORDER BY lp.particulars_date, lp.id',
            [(int) $session['account_terminal_id'], 'Approved']
        );
        $matched = $db->select(
            'SELECT lp.*, u.fullname AS matched_by_name,
                    COALESCE(jv.voucher_no, rv.voucher_no, pv.voucher_no, cv.voucher_no, puv.voucher_no, sv.voucher_no) AS voucher_no
             FROM `tbl_ledger_particulars` lp
             LEFT JOIN `tbl_users_login` u ON u.id = lp.reconciled_by
             LEFT JOIN `tbl_journal_vouchers`  jv  ON lp.voucher_type = \'Journal\'  AND jv.id  = lp.voucher_type_id
             LEFT JOIN `tbl_receipt_vouchers`  rv  ON lp.voucher_type = \'Receipt\'  AND rv.id  = lp.voucher_type_id
             LEFT JOIN `tbl_payment_vouchers`  pv  ON lp.voucher_type = \'Payment\'  AND pv.id  = lp.voucher_type_id
             LEFT JOIN `tbl_contra_vouchers`   cv  ON lp.voucher_type = \'Contra\'   AND cv.id  = lp.voucher_type_id
             LEFT JOIN `tbl_purchase_vouchers` puv ON lp.voucher_type = \'Purchase\' AND puv.id = lp.voucher_type_id
             LEFT JOIN `tbl_sales_vouchers`    sv  ON lp.voucher_type = \'Sales\'    AND sv.id  = lp.voucher_type_id
             WHERE lp.reconcile_ref = ?
             ORDER BY lp.particulars_date, lp.id',
            [$session['statement_ref']]
        );
    }
}
?>
<div class="row">
    <div class="col-lg-4">
        <div class="card card-outline mb-3">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-plus mr-1"></i>New reconciliation</h3></div>
            <div class="card-body">
                <form action="operation.php?module=accounts&page=bank_reconciliation" method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="create_session">
                    <div class="form-group">
                        <label>Bank account terminal *</label>
                        <select name="account_terminal_id" class="form-control" required>
                            <option value="">— select —</option>
                            <?php foreach ($bankTerminals as $t): ?>
                                <option value="<?= (int) $t['id'] ?>"><?= e($t['title']) ?> (<?= e($t['subgroup_title']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-6">
                            <label>Statement ref *</label>
                            <input type="text" name="statement_ref" class="form-control" required placeholder="e.g. BS-2026-07">
                        </div>
                        <div class="form-group col-6">
                            <label>Statement date</label>
                            <input type="date" name="statement_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-6">
                            <label>Opening balance</label>
                            <input type="number" name="opening_balance" step="0.01" class="form-control" value="0">
                        </div>
                        <div class="form-group col-6">
                            <label>Statement total</label>
                            <input type="number" name="total_statement_amount" step="0.01" class="form-control" value="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2"></textarea>
                    </div>
                    <button class="btn btn-primary btn-block"><i class="fas fa-plus mr-1"></i>Create session</button>
                </form>
            </div>
        </div>

        <div class="card card-outline">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-list mr-1"></i>Sessions</h3></div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush" style="max-height:420px;overflow:auto">
                    <?php foreach ($sessions as $s): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center p-2 <?= $sessionId === (int) $s['id'] ? 'list-group-item-primary' : '' ?>">
                            <a href="<?= pageUrl('accounts', 'bank_reconciliation') ?>&session_id=<?= (int) $s['id'] ?>" class="text-truncate" style="max-width:200px">
                                <strong><?= e($s['statement_ref']) ?></strong><br>
                                <small class="text-muted"><?= e($s['terminal_title']) ?> · <?= e(formatDateView($s['statement_date'])) ?></small>
                            </a>
                            <span class="badge badge-<?= $s['status'] === 'Closed' ? 'secondary' : ($s['status'] === 'Matched' ? 'success' : 'warning') ?>"><?= e($s['status']) ?></span>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$sessions): ?><li class="list-group-item text-muted">No sessions yet.</li><?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <?php if (!$session): ?>
            <div class="callout callout-info"><p>Create a reconciliation session, then match ledger lines to the statement (AC-FIN-05.1).</p></div>
        <?php else: ?>
            <div class="card card-outline mb-3">
                <div class="card-header">
                    <h3 class="card-title"><?= e($session['statement_ref']) ?>
                        <small class="text-muted">— <?= e($session['terminal_title']) ?></small>
                    </h3>
                    <div class="card-tools">
                        <span class="badge badge-<?= $session['status'] === 'Closed' ? 'secondary' : ($session['status'] === 'Matched' ? 'success' : 'warning') ?> mr-2"><?= e($session['status']) ?></span>
                        <?php if ($session['status'] !== 'Closed'): ?>
                            <form action="operation.php?module=accounts&page=bank_reconciliation" method="post" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="close_session">
                                <input type="hidden" name="id" value="<?= (int) $session['id'] ?>">
                                <button class="btn btn-sm btn-outline-secondary confirm-submit" data-confirm="Close this reconciliation?">Close</button>
                            </form>
                        <?php endif; ?>
                        <form action="operation.php?module=accounts&page=bank_reconciliation" method="post" class="d-inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete_session">
                            <input type="hidden" name="id" value="<?= (int) $session['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger confirm-submit" data-confirm="Delete this session? Matched lines will be un-reconciled."><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-3"><strong>Opening</strong><br><?= e(formatMoney($session['opening_balance'])) ?></div>
                        <div class="col-3"><strong>Statement total</strong><br><?= e(formatMoney($session['total_statement_amount'])) ?></div>
                        <div class="col-3"><strong>Matched</strong><br><?= e(formatMoney($session['total_matched_amount'])) ?></div>
                        <div class="col-3"><strong>Difference</strong><br><?= e(formatMoney((float) $session['total_statement_amount'] - (float) $session['total_matched_amount'])) ?></div>
                    </div>
                </div>
            </div>

            <div class="card card-outline mb-3">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-check-double mr-1"></i>Match ledger lines (approved)</h3></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0">
                            <thead><tr><th>Date</th><th>Voucher</th><th class="text-right">In (dr)</th><th class="text-right">Out (cr)</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($unmatched as $lp): ?>
                                <tr>
                                    <td><?= e(formatDateView($lp['particulars_date'])) ?></td>
                                    <td><?= e($lp['voucher_no'] ?? '') ?></td>
                                    <td class="text-right"><?= $lp['debit'] > 0 ? e(formatMoney($lp['debit'])) : '' ?></td>
                                    <td class="text-right"><?= $lp['credit'] > 0 ? e(formatMoney($lp['credit'])) : '' ?></td>
                                    <td class="text-right">
                                        <form action="operation.php?module=accounts&page=bank_reconciliation" method="post" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="match_line">
                                            <input type="hidden" name="id" value="<?= (int) $session['id'] ?>">
                                            <input type="hidden" name="line_id" value="<?= (int) $lp['id'] ?>">
                                            <button class="btn btn-xs btn-outline-success"><i class="fas fa-check mr-1"></i>Match</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$unmatched): ?><tr><td colspan="5" class="text-center text-muted">No unmatched approved lines.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card card-outline">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-check-circle mr-1"></i>Matched lines</h3></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Date</th><th>Voucher</th><th class="text-right">Amount</th><th>Matched by</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($matched as $lp): ?>
                                <tr>
                                    <td><?= e(formatDateView($lp['particulars_date'])) ?></td>
                                    <td><?= e($lp['voucher_no'] ?? '') ?></td>
                                    <td class="text-right"><?= e(formatMoney((float) $lp['debit'] + (float) $lp['credit'])) ?></td>
                                    <td><?= e($lp['matched_by_name'] ?? '#' . $lp['reconciled_by']) ?> <small class="text-muted"><?= e($lp['reconciled_on'] ?? '') ?></small></td>
                                    <td class="text-right">
                                        <form action="operation.php?module=accounts&page=bank_reconciliation" method="post" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="unmatch_line">
                                            <input type="hidden" name="id" value="<?= (int) $session['id'] ?>">
                                            <input type="hidden" name="line_id" value="<?= (int) $lp['id'] ?>">
                                            <button class="btn btn-xs btn-outline-warning"><i class="fas fa-undo"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$matched): ?><tr><td colspan="5" class="text-center text-muted">Nothing matched yet.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
