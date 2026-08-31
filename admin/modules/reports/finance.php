<?php
/**
 * SB-Tech — Reports / Finance.
 * Expense summary, voucher register, income vs expense, expense claims status.
 */
$db = Database::instance();
$fy = $db->selectOne("SELECT * FROM tbl_fiscal_years WHERE closing = 'Open' ORDER BY id DESC LIMIT 1");

// Voucher summary by type
$types = ['journal', 'receipt', 'payment', 'contra', 'purchase', 'sales'];
$voucherSummary = [];
foreach ($types as $t) {
    $table = "tbl_{$t}_vouchers";
    $row = $db->selectOne("SELECT COUNT(*) AS c, COALESCE(SUM(total_amount), 0) AS total FROM `{$table}`");
    $voucherSummary[$t] = ['count' => (int) ($row['c'] ?? 0), 'total' => (float) ($row['total'] ?? 0)];
}

// Expense claims summary
$claimsSummary = $db->select(
    "SELECT status, COUNT(*) AS c, COALESCE(SUM(amount), 0) AS total
     FROM tbl_expense_claims GROUP BY status"
);
$claims = [];
foreach ($claimsSummary as $cs) {
    $claims[$cs['status']] = ['count' => (int) $cs['c'], 'total' => (float) $cs['total']];
}

// Recent vouchers
$recentVouchers = $db->select(
    "SELECT 'Journal' AS type, voucher_no, voucher_date, narration, total_amount, status FROM tbl_journal_vouchers
     UNION ALL SELECT 'Receipt', voucher_no, voucher_date, narration, total_amount, status FROM tbl_receipt_vouchers
     UNION ALL SELECT 'Payment', voucher_no, voucher_date, narration, total_amount, status FROM tbl_payment_vouchers
     UNION ALL SELECT 'Contra', voucher_no, voucher_date, narration, total_amount, status FROM tbl_contra_vouchers
     UNION ALL SELECT 'Purchase', voucher_no, voucher_date, narration, total_amount, status FROM tbl_purchase_vouchers
     UNION ALL SELECT 'Sales', voucher_no, voucher_date, narration, total_amount, status FROM tbl_sales_vouchers
     ORDER BY voucher_date DESC LIMIT 15"
);

$totalReceipts = $voucherSummary['receipt']['total'] + $voucherSummary['sales']['total'];
$totalPayments = $voucherSummary['payment']['total'] + $voucherSummary['purchase']['total'];
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-calculator mr-1"></i>Finance Report</h3>
        <div class="card-tools">
            <form action="operation.php?module=reports&page=finance_operation" method="post" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="export_finance">
                <button class="btn btn-success btn-sm"><i class="fas fa-download mr-1"></i>CSV</button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <?php if ($fy): ?>
            <div class="callout callout-info mb-3"><strong>Active Fiscal Year:</strong> <?= e($fy['title']) ?> (<?= e($fy['starting_date']) ?> — <?= e($fy['ending_date']) ?>)</div>
        <?php endif; ?>

        <!-- Summary -->
        <div class="row mb-4">
            <div class="col-md-3"><div class="callout callout-success"><h6>Total Receipts</h6><h3><?= formatMoney($totalReceipts) ?></h3></div></div>
            <div class="col-md-3"><div class="callout callout-danger"><h6>Total Payments</h6><h3><?= formatMoney($totalPayments) ?></h3></div></div>
            <div class="col-md-3"><div class="callout callout-primary"><h6>Net Cash Flow</h6><h3><?= formatMoney($totalReceipts - $totalPayments) ?></h3></div></div>
            <div class="col-md-3"><div class="callout callout-warning"><h6>Claims Pending</h6><h3><?= formatMoney(($claims['Submitted']['total'] ?? 0) + ($claims['Approved']['total'] ?? 0)) ?></h3></div></div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Voucher Register -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h5 class="card-title">Voucher Register</h5></div>
            <div class="card-body p-0">
                <table class="table table-sm table-striped">
                    <thead><tr><th>Type</th><th class="text-center">Count</th><th class="text-right">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($voucherSummary as $t => $v): ?>
                        <tr>
                            <td><strong><?= ucfirst($t) ?></strong></td>
                            <td class="text-center"><?= $v['count'] ?></td>
                            <td class="text-right"><?= formatMoney($v['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Expense Claims -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h5 class="card-title">Expense Claims</h5></div>
            <div class="card-body p-0">
                <table class="table table-sm table-striped">
                    <thead><tr><th>Status</th><th class="text-center">Count</th><th class="text-right">Amount</th></tr></thead>
                    <tbody>
                    <?php foreach (['Draft','Submitted','Approved','Rejected','Paid'] as $st): ?>
                        <tr>
                            <td><span class="badge badge-<?= ['Draft'=>'secondary','Submitted'=>'warning','Approved'=>'success','Rejected'=>'danger','Paid'=>'info'][$st] ?>"><?= $st ?></span></td>
                            <td class="text-center"><?= $claims[$st]['count'] ?? 0 ?></td>
                            <td class="text-right"><?= formatMoney($claims[$st]['total'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Recent Vouchers -->
<div class="card">
    <div class="card-header"><h5 class="card-title">Recent Vouchers</h5></div>
    <div class="card-body p-0">
        <table class="table table-sm table-striped">
            <thead><tr><th>Type</th><th>Voucher No</th><th>Date</th><th>Narration</th><th class="text-right">Amount</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($recentVouchers as $v): ?>
                <tr>
                    <td><span class="badge badge-primary"><?= e($v['type']) ?></span></td>
                    <td><strong><?= e($v['voucher_no']) ?></strong></td>
                    <td class="small"><?= e($v['voucher_date']) ?></td>
                    <td class="small" style="max-width:250px;overflow:hidden;text-overflow:ellipsis"><?= e($v['narration']) ?></td>
                    <td class="text-right"><?= formatMoney($v['total_amount']) ?></td>
                    <td><span class="badge badge-<?= $v['status'] === 'Approved' ? 'success' : 'warning' ?>"><?= e($v['status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$recentVouchers): ?>
                <tr><td colspan="6" class="text-muted text-center">No vouchers yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
