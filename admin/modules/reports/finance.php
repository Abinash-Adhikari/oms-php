<?php
/**
 * SB-Tech — Reports / Finance.
 * Cash in vs out (approved vouchers, active fiscal year), monthly trend,
 * voucher register, expense-claim pipeline and recent vouchers.
 *
 * Cash in  = receipt + sales vouchers
 * Cash out = payment + purchase vouchers
 * Only Approved vouchers count toward totals; pending ones are listed apart.
 */
$db = Database::instance();
$fy = $db->selectOne("SELECT * FROM tbl_fiscal_years WHERE closing = 'Open' ORDER BY id DESC LIMIT 1");
$fyId = $fy ? (int) $fy['id'] : 0;
$months12 = month_keys(date('Y-m'), 12);

$types = ['receipt' => 'in', 'sales' => 'in', 'payment' => 'out', 'purchase' => 'out', 'journal' => null, 'contra' => null];

// Fixed table whitelist — the only interpolated identifiers below.
$union = implode(' UNION ALL ', array_map(
    static fn ($t) => "SELECT '{$t}' AS type, voucher_date, total_amount, status, fiscal_year_id FROM `tbl_{$t}_vouchers`",
    array_keys($types)
));

$register = [];
foreach ($db->select(
    "SELECT type,
            SUM(status = 'Approved' AND (? = 0 OR fiscal_year_id = ?)) AS approved_count,
            COALESCE(SUM(CASE WHEN status = 'Approved' AND (? = 0 OR fiscal_year_id = ?) THEN total_amount END), 0) AS approved_total,
            SUM(status = 'Pending') AS pending_count,
            COALESCE(SUM(CASE WHEN status = 'Pending' THEN total_amount END), 0) AS pending_total
     FROM ({$union}) v GROUP BY type",
    [$fyId, $fyId, $fyId, $fyId]
) as $r) {
    $register[$r['type']] = $r;
}
$sumBy = static function (string $dir, string $col) use ($types, $register): float {
    $t = 0.0;
    foreach ($types as $type => $d) {
        if ($d === $dir) {
            $t += (float) ($register[$type][$col] ?? 0);
        }
    }

    return $t;
};
$cashIn = $sumBy('in', 'approved_total');
$cashOut = $sumBy('out', 'approved_total');
$pendingCount = (int) array_sum(array_column($register, 'pending_count'));
$pendingTotal = (float) array_sum(array_column($register, 'pending_total'));

$monthlyIn = [];
$monthlyOut = [];
foreach ($db->select(
    "SELECT type, DATE_FORMAT(voucher_date, '%Y-%m') AS m, SUM(total_amount) AS v
     FROM ({$union}) v WHERE status = 'Approved' AND voucher_date >= ? GROUP BY type, m",
    [$months12[0] . '-01']
) as $r) {
    $dir = $types[$r['type']] ?? null;
    if ($dir === 'in') {
        $monthlyIn[$r['m']] = ($monthlyIn[$r['m']] ?? 0) + (float) $r['v'];
    } elseif ($dir === 'out') {
        $monthlyOut[$r['m']] = ($monthlyOut[$r['m']] ?? 0) + (float) $r['v'];
    }
}

$claimStatuses = ['Draft', 'Submitted', 'Approved', 'Paid', 'Rejected'];
$claims = [];
foreach ($db->select('SELECT status, COUNT(*) AS c, COALESCE(SUM(amount), 0) AS total FROM tbl_expense_claims GROUP BY status') as $cs) {
    $claims[$cs['status']] = ['count' => (int) $cs['c'], 'total' => (float) $cs['total']];
}
$claimsOwed = ($claims['Submitted']['total'] ?? 0) + ($claims['Approved']['total'] ?? 0);
$claimsByCategory = $db->select(
    "SELECT COALESCE(NULLIF(category, ''), 'Uncategorised') AS category, SUM(amount) AS total
     FROM tbl_expense_claims WHERE status IN ('Submitted','Approved','Paid') GROUP BY category ORDER BY total DESC LIMIT 6"
);

$recentVouchers = $db->select(
    "SELECT 'Journal' AS type, voucher_no, voucher_date, narration, total_amount, status FROM tbl_journal_vouchers
     UNION ALL SELECT 'Receipt', voucher_no, voucher_date, narration, total_amount, status FROM tbl_receipt_vouchers
     UNION ALL SELECT 'Payment', voucher_no, voucher_date, narration, total_amount, status FROM tbl_payment_vouchers
     UNION ALL SELECT 'Contra', voucher_no, voucher_date, narration, total_amount, status FROM tbl_contra_vouchers
     UNION ALL SELECT 'Purchase', voucher_no, voucher_date, narration, total_amount, status FROM tbl_purchase_vouchers
     UNION ALL SELECT 'Sales', voucher_no, voucher_date, narration, total_amount, status FROM tbl_sales_vouchers
     ORDER BY voucher_date DESC LIMIT 12"
);
$statusPill = ['Approved' => 'good', 'Pending' => 'warning', 'Rejected' => 'critical'];
?>

<div class="ana-toolbar">
    <div>
        <h2 class="ana-toolbar__title">Finance<?= $fy ? ' — ' . e($fy['title']) : '' ?></h2>
        <p class="ana-toolbar__sub">
            <?= $fy ? e(formatDateView((string) $fy['starting_date'])) . ' – ' . e(formatDateView((string) $fy['ending_date'])) . ' · ' : 'All fiscal years · ' ?>approved vouchers only
        </p>
    </div>
    <div class="ana-toolbar__actions">
        <form action="operation.php?module=reports&page=finance" method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="export_finance">
            <button class="btn btn-success btn-sm"><i class="fas fa-download mr-1"></i>CSV</button>
        </form>
    </div>
</div>

<div class="ana-grid">
    <?= stat_tile(['label' => 'Cash in', 'icon' => 'fas fa-arrow-down', 'tone' => 'success',
        'value' => format_compact_money($cashIn, 'NPR', 'NPR 0'), 'sub' => 'receipts + sales',
        'spark' => fill_series($months12, $monthlyIn)]) ?>
    <?= stat_tile(['label' => 'Cash out', 'icon' => 'fas fa-arrow-up', 'tone' => 'danger',
        'value' => format_compact_money($cashOut, 'NPR', 'NPR 0'), 'sub' => 'payments + purchases',
        'spark' => fill_series($months12, $monthlyOut)]) ?>
    <?= stat_tile(['label' => 'Net cash flow', 'icon' => 'fas fa-balance-scale', 'tone' => $cashIn - $cashOut >= 0 ? 'teal' : 'danger',
        'value' => format_compact_money($cashIn - $cashOut, 'NPR', 'NPR 0'),
        'sub' => $pendingCount > 0 ? $pendingCount . ($pendingCount === 1 ? ' pending voucher (' : ' pending vouchers (') . format_compact_money($pendingTotal) . ') not counted' : 'No pending vouchers']) ?>
    <?= stat_tile(['label' => 'Claims owed to staff', 'icon' => 'fas fa-receipt', 'tone' => 'warning',
        'value' => format_compact_money($claimsOwed, 'NPR', 'NPR 0'),
        'sub' => (($claims['Submitted']['count'] ?? 0) + ($claims['Approved']['count'] ?? 0)) . ' submitted or approved, unpaid',
        'href' => pageUrl('my_office', 'expense_claims')]) ?>
</div>

<div class="ana-card">
    <div class="ana-card__head">
        <div>
            <h3 class="ana-card__title">Cash in vs. cash out</h3>
            <p class="ana-card__hint">Approved vouchers per month, last 12 months</p>
        </div>
    </div>
    <div class="ana-chart">
        <canvas role="img" aria-label="Bar chart of monthly cash in and cash out"
                data-chart="<?= chart_attr([
                    'type' => 'bar', 'format' => 'money',
                    'labels' => array_map(static fn ($m) => date('M y', strtotime($m . '-01')), $months12),
                    'series' => [
                        ['label' => 'Cash in', 'data' => fill_series($months12, $monthlyIn), 'slot' => 1],
                        ['label' => 'Cash out', 'data' => fill_series($months12, $monthlyOut), 'slot' => 2],
                    ],
                    'empty' => 'No approved vouchers in the last 12 months.',
                ]) ?>"></canvas>
    </div>
</div>

<div class="row ana-row">
    <div class="col-lg-6">
        <div class="ana-card ana-card--flush">
            <div class="ana-card__head">
                <div>
                    <h3 class="ana-card__title">Voucher register</h3>
                    <p class="ana-card__hint">Approved<?= $fy ? ' this fiscal year' : '' ?> · pending shown separately</p>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm ana-table">
                    <thead><tr><th>Type</th><th class="text-right">Approved</th><th class="text-right">Amount</th><th class="text-right">Pending</th></tr></thead>
                    <tbody>
                    <?php foreach ($types as $t => $dir):
                        $r = $register[$t] ?? [];
                    ?>
                        <tr>
                            <td><strong><?= e(ucfirst($t)) ?></strong> <span class="text-muted small"><?= $dir === 'in' ? 'in' : ($dir === 'out' ? 'out' : '') ?></span></td>
                            <td class="text-right"><?= (int) ($r['approved_count'] ?? 0) ?></td>
                            <td class="text-right"><?= e(formatMoney($r['approved_total'] ?? 0)) ?></td>
                            <td class="text-right"><?= (int) ($r['pending_count'] ?? 0) ? '<span class="ana-pill ana-pill--warning">' . (int) $r['pending_count'] . '</span>' : '<span class="text-muted">0</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="ana-card ana-card--flush">
            <div class="ana-card__head">
                <div>
                    <h3 class="ana-card__title">Expense claims</h3>
                    <p class="ana-card__hint">By status · top categories (excl. draft & rejected) below</p>
                </div>
                <a class="ana-card__link" href="<?= pageUrl('my_office', 'expense_claims') ?>">Open claims</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm ana-table">
                    <thead><tr><th>Status</th><th class="text-right">Claims</th><th class="text-right">Amount</th></tr></thead>
                    <tbody>
                    <?php foreach ($claimStatuses as $st): ?>
                        <tr>
                            <td><span class="ana-dot" style="background:var(--viz-<?= ['Draft' => 'neutral', 'Submitted' => 'warning', 'Approved' => '1', 'Paid' => 'good', 'Rejected' => 'critical'][$st] ?>)"></span><?= e($st) ?></td>
                            <td class="text-right"><?= (int) ($claims[$st]['count'] ?? 0) ?></td>
                            <td class="text-right"><?= e(formatMoney($claims[$st]['total'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($claimsByCategory):
                $catMax = (float) max(array_column($claimsByCategory, 'total'));
            ?>
                <ul class="ana-list" style="padding:.25rem 1.25rem 1.1rem">
                    <?php foreach ($claimsByCategory as $c): ?>
                        <li>
                            <div class="ana-list__main">
                                <span class="ana-list__title"><?= e($c['category']) ?></span>
                                <?= meter_bar(percent_of((float) $c['total'], $catMax)) ?>
                            </div>
                            <span class="ana-pill"><?= e(format_compact_money((float) $c['total'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="ana-card ana-card--flush">
    <div class="ana-card__head">
        <div><h3 class="ana-card__title">Recent vouchers</h3></div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm ana-table">
            <thead><tr><th>Type</th><th>Voucher no</th><th>Date</th><th>Narration</th><th class="text-right">Amount</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($recentVouchers as $v): ?>
                <tr>
                    <td><?= e($v['type']) ?></td>
                    <td><strong><?= e($v['voucher_no']) ?></strong></td>
                    <td class="text-muted"><?= e(formatDateView((string) $v['voucher_date'])) ?></td>
                    <td class="text-muted" style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($v['narration']) ?></td>
                    <td class="text-right"><?= e(formatMoney($v['total_amount'])) ?></td>
                    <td><span class="ana-pill ana-pill--<?= e($statusPill[$v['status']] ?? '') ?>"><?= e($v['status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$recentVouchers): ?>
                <tr><td colspan="6" class="text-muted text-center py-4">No vouchers yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
