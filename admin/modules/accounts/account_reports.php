<?php
/**
 * SB-Tech — Accounts / Account Reports (US-FIN-04).
 * Approved-only reports: Trial Balance · Cashbook · Daybook ·
 * Balance Sheet summary, each filterable by FY + date range with CSV.
 */
$db = Database::instance();

$reportTabs = [
    'trial_balance' => ['label' => 'Trial Balance', 'fa' => 'fa-balance-scale'],
    'cashbook'      => ['label' => 'Cashbook', 'fa' => 'fa-coins'],
    'daybook'       => ['label' => 'Daybook', 'fa' => 'fa-calendar-day'],
    'balance_sheet' => ['label' => 'Balance Sheet', 'fa' => 'fa-landmark'],
];
$tab = (string) ($_GET['tab'] ?? 'trial_balance');
if (!isset($reportTabs[$tab])) {
    $tab = 'trial_balance';
}

$fys = $db->select('SELECT * FROM `tbl_fiscal_years` ORDER BY `starting_date` DESC');
$openFy = accountingCurrentOpenFy();
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

/** Approved ledger rows in range. */
$rows = $db->select(
    'SELECT lp.account_group_id, lp.account_group_title, lp.account_subgroup_id, lp.account_subgroup_title,
            lp.account_terminal_id, lp.account_terminal_title,
            SUM(lp.debit) AS d, SUM(lp.credit) AS c
     FROM `tbl_ledger_particulars` lp
     WHERE lp.voucher_status = ? AND lp.particulars_date >= ? AND lp.particulars_date <= ?
     GROUP BY lp.account_group_id, lp.account_group_title, lp.account_subgroup_id,
              lp.account_subgroup_title, lp.account_terminal_id, lp.account_terminal_title
     ORDER BY lp.account_group_id, lp.account_subgroup_id, lp.account_terminal_title',
    ['Approved', $from, $to]
);

function reportTotals(array $rows): array
{
    $d = 0.0;
    $c = 0.0;
    foreach ($rows as $r) {
        $d += (float) ($r['d'] ?? $r['debit'] ?? 0);
        $c += (float) ($r['c'] ?? $r['credit'] ?? 0);
    }
    return [$d, $c];
}
?>
<div class="card card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-chart-bar mr-1"></i>Account reports <small class="text-muted">(approved entries)</small></h3>
        <div class="card-tools">
            <?php foreach ($reportTabs as $tk => $meta): ?>
                <a href="<?= pageUrl('accounts', 'account_reports') ?>&tab=<?= urlencode($tk) ?>"
                   class="btn btn-sm btn-<?= $tab === $tk ? 'primary' : 'default' ?>">
                    <i class="fas <?= e($meta['fa']) ?> mr-1"></i><?= e($meta['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card-body">
        <form method="get" class="form-row align-items-end mb-3">
            <input type="hidden" name="module" value="accounts">
            <input type="hidden" name="page" value="account_reports">
            <input type="hidden" name="tab" value="<?= e($tab) ?>">
            <div class="form-group col-md-3 mb-0">
                <label>Fiscal year</label>
                <select name="fy_id" class="form-control form-control-sm">
                    <?php foreach ($fys as $f): ?>
                        <option value="<?= (int) $f['id'] ?>" <?= $fyId === (int) $f['id'] ? 'selected' : '' ?>><?= e($f['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-3 mb-0">
                <label>From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>">
            </div>
            <div class="form-group col-md-3 mb-0">
                <label>To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>">
            </div>
            <div class="form-group col-md-3 mb-0">
                <button class="btn btn-sm btn-primary"><i class="fas fa-sync mr-1"></i>Refresh</button>
            </div>
        </form>

        <?php if ($tab === 'trial_balance'): ?>
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr><th>Group</th><th>Sub-group</th><th>Account</th><th class="text-right">Debit</th><th class="text-right">Credit</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= e($r['account_group_title']) ?></td>
                            <td><?= e($r['account_subgroup_title']) ?></td>
                            <td><?= e($r['account_terminal_title']) ?></td>
                            <td class="text-right"><?= $r['d'] > 0 ? e(formatMoney($r['d'])) : '' ?></td>
                            <td class="text-right"><?= $r['c'] > 0 ? e(formatMoney($r['c'])) : '' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted">No approved entries in this range.</td></tr><?php endif; ?>
                    </tbody>
                    <tfoot>
                        <?php [$td, $tc] = reportTotals($rows); ?>
                        <tr class="font-weight-bold">
                            <td colspan="3">Total</td>
                            <td class="text-right"><?= e(formatMoney($td)) ?></td>
                            <td class="text-right"><?= e(formatMoney($tc)) ?></td>
                        </tr>
                        <tr>
                            <td colspan="5" class="text-muted small"><?= $td === $tc ? '✓ Trial balance is in balance.' : '⚠ Trial balance is OUT of balance by ' . e(formatMoney(abs($td - $tc))) . '.' ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

        <?php elseif ($tab === 'cashbook'): ?>
            <?php
            $cashTerminals = array_values(array_filter($rows, function ($r) {
                return in_array($r['account_terminal_title'], ['Cash in Hand', 'Bank Accounts'], true);
            }));
            $cashIds = array_map(function ($r) {
                return (int) $r['account_terminal_id'];
            }, $cashTerminals);
            ?>
            <?php if ($cashIds): ?>
                <?php foreach ($cashIds as $cid): ?>
                    <?php
                    $t = $db->selectOne('SELECT * FROM `tbl_account_terminals` WHERE `id` = ?', [$cid]);
                    $lines = accountingLedgerLines($db, $cid, $from, $to, ['lp.voucher_status = ?'], ['Approved']);
                    $opening = accountingOpeningBalance($db, $cid, $from);
                    $running = $opening;
                    ?>
                    <h5 class="mt-2"><?= e($t['title']) ?></h5>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-striped mb-0">
                            <thead><tr><th>Date</th><th>Voucher</th><th class="text-right">In (dr)</th><th class="text-right">Out (cr)</th><th class="text-right">Balance</th></tr></thead>
                            <tbody>
                                <tr class="table-light"><td colspan="4"><strong>Opening</strong></td><td class="text-right font-weight-bold"><?= e(formatMoney($opening)) ?></td></tr>
                                <?php foreach ($lines as $lp): ?>
                                    <?php $running = round($running + (float) $lp['debit'] - (float) $lp['credit'], 4); ?>
                                    <tr>
                                        <td><?= e(formatDateView($lp['particulars_date'])) ?></td>
                                        <td><?= e($lp['voucher_no'] ?? '') ?></td>
                                        <td class="text-right"><?= $lp['debit'] > 0 ? e(formatMoney($lp['debit'])) : '' ?></td>
                                        <td class="text-right"><?= $lp['credit'] > 0 ? e(formatMoney($lp['credit'])) : '' ?></td>
                                        <td class="text-right font-weight-bold"><?= e(formatMoney($running)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$lines): ?><tr><td colspan="5" class="text-center text-muted">No entries.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="callout callout-info"><p>No Cash/Bank activity in this range. Post and approve a cash or bank voucher to see it here.</p></div>
            <?php endif; ?>

        <?php elseif ($tab === 'daybook'): ?>
            <?php
            $dayRows = $db->select(
                'SELECT lp.*,
                        COALESCE(jv.voucher_no, rv.voucher_no, pv.voucher_no, cv.voucher_no, puv.voucher_no, sv.voucher_no) AS voucher_no,
                        COALESCE(jv.narration, rv.narration, pv.narration, cv.narration, puv.narration, sv.narration) AS narration
                 FROM `tbl_ledger_particulars` lp
                 LEFT JOIN `tbl_journal_vouchers`  jv  ON lp.voucher_type = \'Journal\'  AND jv.id  = lp.voucher_type_id
                 LEFT JOIN `tbl_receipt_vouchers`  rv  ON lp.voucher_type = \'Receipt\'  AND rv.id  = lp.voucher_type_id
                 LEFT JOIN `tbl_payment_vouchers`  pv  ON lp.voucher_type = \'Payment\'  AND pv.id  = lp.voucher_type_id
                 LEFT JOIN `tbl_contra_vouchers`   cv  ON lp.voucher_type = \'Contra\'   AND cv.id  = lp.voucher_type_id
                 LEFT JOIN `tbl_purchase_vouchers` puv ON lp.voucher_type = \'Purchase\' AND puv.id = lp.voucher_type_id
                 LEFT JOIN `tbl_sales_vouchers`    sv  ON lp.voucher_type = \'Sales\'    AND sv.id  = lp.voucher_type_id
                 WHERE lp.voucher_status = ? AND lp.particulars_date >= ? AND lp.particulars_date <= ?
                 ORDER BY lp.particulars_date, lp.id',
                ['Approved', $from, $to]
            );
            ?>
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead><tr><th>Date</th><th>Voucher</th><th>Account</th><th class="text-right">Debit</th><th class="text-right">Credit</th><th>Narration</th></tr></thead>
                    <tbody>
                    <?php foreach ($dayRows as $lp): ?>
                        <tr>
                            <td><?= e(formatDateView($lp['particulars_date'])) ?></td>
                            <td><?= e($lp['voucher_no'] ?? '') ?></td>
                            <td><?= e($lp['account_terminal_title']) ?></td>
                            <td class="text-right"><?= $lp['debit'] > 0 ? e(formatMoney($lp['debit'])) : '' ?></td>
                            <td class="text-right"><?= $lp['credit'] > 0 ? e(formatMoney($lp['credit'])) : '' ?></td>
                            <td class="text-truncate" style="max-width:260px"><?= e($lp['narration'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$dayRows): ?><tr><td colspan="6" class="text-center text-muted">No approved entries in this range.</td></tr><?php endif; ?>
                    </tbody>
                    <?php [$dd, $dc] = reportTotals($dayRows); ?>
                    <tfoot><tr class="font-weight-bold"><td colspan="3">Total</td><td class="text-right"><?= e(formatMoney($dd)) ?></td><td class="text-right"><?= e(formatMoney($dc)) ?></td><td></td></tr></tfoot>
                </table>
            </div>

        <?php elseif ($tab === 'balance_sheet'): ?>
            <?php
            $assets = 0.0;
            $liabilities = 0.0;
            $equity = 0.0;
            $income = 0.0;
            $expenses = 0.0;
            foreach ($rows as $r) {
                $bal = (float) $r['d'] - (float) $r['c'];
                switch ((int) $r['account_group_id']) {
                    case 1: $assets += $bal; break;      // Assets (debit balance)
                    case 2: $liabilities += -$bal; break; // Liabilities (credit balance)
                    case 3: $income += -$bal; break;      // Income (credit balance)
                    case 4: $expenses += $bal; break;     // Expenses (debit balance)
                    case 5: $equity += -$bal; break;      // Capital (credit balance)
                }
            }
            $netProfit = $income - $expenses;
            $totalEquity = $equity + $netProfit;
            ?>
            <div class="row">
                <div class="col-md-6">
                    <h5>Assets</h5>
                    <table class="table table-sm">
                        <tr><th>Total assets</th><td class="text-right font-weight-bold"><?= e(formatMoney($assets)) ?></td></tr>
                    </table>
                    <h5>Liabilities & Equity</h5>
                    <table class="table table-sm">
                        <tr><th>Total liabilities</th><td class="text-right"><?= e(formatMoney($liabilities)) ?></td></tr>
                        <tr><th>Owner capital</th><td class="text-right"><?= e(formatMoney($equity)) ?></td></tr>
                        <tr><th>Net profit (income <?= e(formatMoney($income)) ?> − expenses <?= e(formatMoney($expenses)) ?>)</th><td class="text-right"><?= e(formatMoney($netProfit)) ?></td></tr>
                        <tr class="font-weight-bold"><td>Total liabilities + equity</td><td class="text-right"><?= e(formatMoney($liabilities + $totalEquity)) ?></td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <div class="callout <?= abs($assets - ($liabilities + $totalEquity)) < 0.01 ? 'callout-success' : 'callout-danger' ?>">
                        <h5><?= abs($assets - ($liabilities + $totalEquity)) < 0.01 ? '✓ Balanced' : '⚠ Unbalanced' ?></h5>
                        <p>Assets <?= e(formatMoney($assets)) ?> = Liabilities <?= e(formatMoney($liabilities)) ?> + Equity <?= e(formatMoney($totalEquity)) ?>.</p>
                    </div>
                    <p class="text-muted small">Summary from approved entries in the selected range.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
