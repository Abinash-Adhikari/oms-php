<?php
/**
 * SB-Tech — Accounts / Ledger (US-FIN-04).
 * Account ledger for one terminal: opening balance, Approved lines with a
 * running balance, drill-down to the source voucher, CSV export.
 */
$db = Database::instance();

$terminals = $db->select(
    'SELECT t.*, g.title AS group_title, sg.title AS subgroup_title
     FROM `tbl_account_terminals` t
     JOIN `tbl_account_sub_groups` sg ON sg.id = t.account_subgroup_id
     JOIN `tbl_account_groups` g ON g.id = sg.group_id
     ORDER BY g.position, sg.position, t.position, t.title'
);
$fys = $db->select('SELECT * FROM `tbl_fiscal_years` ORDER BY `starting_date` DESC');

$openFy = accountingCurrentOpenFy();
$terminalId = (int) ($_GET['terminal_id'] ?? 0);
$fyId = (int) ($_GET['fy_id'] ?? ($openFy['id'] ?? 0));
$fy = null;
foreach ($fys as $f) {
    if ((int) $f['id'] === $fyId) {
        $fy = $f;
        break;
    }
}
$from = (string) ($_GET['from'] ?? ($fy['starting_date'] ?? date('Y-m-01')));
$to = (string) ($_GET['to'] ?? ($fy['ending_date'] ?? date('Y-m-d')));
$onlyApproved = ($_GET['status'] ?? 'Approved') === 'Approved';

$terminal = null;
$lines = [];
$opening = 0.0;
if ($terminalId) {
    $terminal = $db->selectOne('SELECT * FROM `tbl_account_terminals` WHERE `id` = ?', [$terminalId]);
    if ($terminal) {
        $extra = $onlyApproved ? ['lp.voucher_status = ?'] : [];
        $params = $onlyApproved ? ['Approved'] : [];
        $lines = accountingLedgerLines($db, $terminalId, $from, $to, $extra, $params);
        $opening = accountingOpeningBalance($db, $terminalId, $from);
    }
}
$running = $opening;
?>
<div class="card card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-book mr-1"></i>Account ledger</h3>
    </div>
    <div class="card-body">
        <form method="get" class="form-row align-items-end">
            <input type="hidden" name="module" value="accounts">
            <input type="hidden" name="page" value="ledger">
            <div class="form-group col-md-4 mb-2">
                <label>Account</label>
                <select name="terminal_id" class="form-control" required>
                    <option value="">— select terminal —</option>
                    <?php foreach ($terminals as $t): ?>
                        <option value="<?= (int) $t['id'] ?>" <?= $terminalId === (int) $t['id'] ? 'selected' : '' ?>>
                            <?= e($t['group_title']) ?> / <?= e($t['subgroup_title']) ?> → <?= e($t['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-2 mb-2">
                <label>Fiscal year</label>
                <select name="fy_id" class="form-control">
                    <?php foreach ($fys as $f): ?>
                        <option value="<?= (int) $f['id'] ?>" <?= $fyId === (int) $f['id'] ? 'selected' : '' ?>><?= e($f['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-2 mb-2">
                <label>From</label>
                <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
            </div>
            <div class="form-group col-md-2 mb-2">
                <label>To</label>
                <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
            </div>
            <div class="form-group col-md-2 mb-2">
                <label>Lines</label>
                <select name="status" class="form-control">
                    <option value="Approved" <?= $onlyApproved ? 'selected' : '' ?>>Approved only</option>
                    <option value="All" <?= !$onlyApproved ? 'selected' : '' ?>>All (incl. pending)</option>
                </select>
            </div>
            <div class="form-group col-md-12">
                <button class="btn btn-primary"><i class="fas fa-search mr-1"></i>Show ledger</button>
            </div>
        </form>
    </div>
</div>

<?php if ($terminal): ?>
    <div class="card card-outline">
        <div class="card-header">
            <h3 class="card-title"><?= e($terminal['title']) ?>
                <small class="text-muted">— <?= e(formatDateView($from)) ?> to <?= e(formatDateView($to)) ?></small>
            </h3>
            <div class="card-tools">
                <form action="operation.php?module=accounts&page=ledger" method="post" class="d-inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="export_ledger">
                    <input type="hidden" name="terminal_id" value="<?= (int) $terminalId ?>">
                    <input type="hidden" name="from" value="<?= e($from) ?>">
                    <input type="hidden" name="to" value="<?= e($to) ?>">
                    <input type="hidden" name="status" value="<?= $onlyApproved ? 'Approved' : 'All' ?>">
                    <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-file-csv mr-1"></i>CSV</button>
                </form>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr><th>Date</th><th>Voucher</th><th>Narration / Remark</th><th class="text-right">Debit</th><th class="text-right">Credit</th><th class="text-right">Balance</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <tr class="table-light">
                            <td colspan="5"><strong>Opening balance</strong></td>
                            <td class="text-right font-weight-bold"><?= e(formatMoney($opening)) ?></td>
                            <td></td>
                        </tr>
                        <?php foreach ($lines as $lp): ?>
                            <?php $running = round($running + (float) $lp['debit'] - (float) $lp['credit'], 4); ?>
                            <?php
                            $voucherLink = '';
                            $typeKeyBy = array_search($lp['voucher_type'], array_column(accountingVoucherConfig(), 'type'), true);
                            if ($typeKeyBy !== false && $lp['voucher_no']) {
                                $voucherLink = '<a href="' . pageUrl('accounts', 'postings') . '&tab=' . urlencode($typeKeyBy) . '&view_id=' . (int) $lp['voucher_type_id'] . '">' . e($lp['voucher_no']) . '</a>';
                            }
                            ?>
                            <tr>
                                <td><?= e(formatDateView($lp['particulars_date'])) ?></td>
                                <td><?= $voucherLink ?: '—' ?></td>
                                <td><?= e($lp['narration'] ?? ($lp['remarks'] ?? '')) ?></td>
                                <td class="text-right"><?= $lp['debit'] > 0 ? e(formatMoney($lp['debit'])) : '' ?></td>
                                <td class="text-right"><?= $lp['credit'] > 0 ? e(formatMoney($lp['credit'])) : '' ?></td>
                                <td class="text-right font-weight-bold"><?= e(formatMoney($running)) ?></td>
                                <td><span class="badge badge-<?= $lp['voucher_status'] === 'Approved' ? 'success' : 'warning' ?>"><?= e($lp['voucher_status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$lines): ?><tr><td colspan="7" class="text-center text-muted">No ledger lines in this range.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="callout callout-info"><p>Select an account to view its ledger.</p></div>
<?php endif; ?>
