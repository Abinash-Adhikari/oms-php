<?php
/**
 * SB-Tech — Reports / Inventory.
 * Stock value by category, low-stock alerts, asset status, and stock
 * movement in vs out over the last 12 weeks.
 */
$db = Database::instance();
$weeks12 = week_keys(date('Y-m-d'), 12);

$stockSummary = $db->select(
    "SELECT c.title AS category_title,
            COUNT(DISTINCT i.id) AS item_count,
            COALESCE(SUM(s.total), 0) AS total_qty,
            COALESCE(SUM(s.total * i.cost_price), 0) AS total_value
     FROM tbl_inv_categories c
     LEFT JOIN tbl_inv_items i ON i.category_id = c.id AND i.is_active = 1
     LEFT JOIN (SELECT item_id, SUM(quantity) AS total FROM tbl_inv_stock GROUP BY item_id) s ON s.item_id = i.id
     GROUP BY c.id, c.title
     ORDER BY total_value DESC, c.title"
);
$totalItems = (int) array_sum(array_column($stockSummary, 'item_count'));
$totalQty = (float) array_sum(array_column($stockSummary, 'total_qty'));
$totalValue = (float) array_sum(array_column($stockSummary, 'total_value'));

$lowStock = $db->select(
    "SELECT i.sku, i.name, c.title AS category_title, i.reorder_point,
            COALESCE(s.total, 0) AS current_stock
     FROM tbl_inv_items i
     LEFT JOIN tbl_inv_categories c ON c.id = i.category_id
     LEFT JOIN (SELECT item_id, SUM(quantity) AS total FROM tbl_inv_stock GROUP BY item_id) s ON s.item_id = i.id
     WHERE i.is_active = 1 AND i.reorder_point > 0 AND COALESCE(s.total, 0) <= i.reorder_point
     ORDER BY (COALESCE(s.total, 0) / GREATEST(i.reorder_point, 1)) ASC
     LIMIT 20"
);
$outOfStock = count(array_filter($lowStock, static fn ($r) => (float) $r['current_stock'] <= 0));

$assetStatuses = ['In Stock', 'Assigned', 'Under Maintenance', 'Retired', 'Disposed'];
$assetRows = [];
foreach ($db->select(
    'SELECT current_status, COUNT(*) AS c, COALESCE(SUM(purchase_price), 0) AS total_price
     FROM tbl_inv_assets WHERE is_active = 1 GROUP BY current_status'
) as $r) {
    $assetRows[$r['current_status']] = ['count' => (int) $r['c'], 'value' => (float) $r['total_price']];
}
$assetCount = (int) array_sum(array_column($assetRows, 'count'));
$assetValue = (float) array_sum(array_column($assetRows, 'value'));

$weekExpr = "DATE_FORMAT(DATE_SUB(DATE(m.date), INTERVAL WEEKDAY(m.date) DAY), '%Y-%m-%d')";
$moveIn = [];
$moveOut = [];
foreach ($db->select(
    "SELECT {$weekExpr} AS wk, m.direction, SUM(m.quantity) AS q
     FROM tbl_inv_stock_movements m WHERE m.date >= ? GROUP BY wk, m.direction",
    [$weeks12[0]]
) as $r) {
    if ($r['direction'] === 'In') {
        $moveIn[$r['wk']] = (float) $r['q'];
    } else {
        $moveOut[$r['wk']] = (float) $r['q'];
    }
}

$recentMovements = $db->select(
    "SELECT m.*, i.name AS item_name, i.sku, u.fullname AS added_by_name
     FROM tbl_inv_stock_movements m
     LEFT JOIN tbl_inv_items i ON i.id = m.item_id
     LEFT JOIN tbl_users_login u ON u.id = m.added_by
     ORDER BY m.date DESC, m.id DESC LIMIT 10"
);
$valued = array_values(array_filter($stockSummary, static fn ($s) => (float) $s['total_value'] > 0));
?>

<div class="ana-toolbar">
    <div>
        <h2 class="ana-toolbar__title">Inventory</h2>
        <p class="ana-toolbar__sub">Stock valued at cost price · active items only</p>
    </div>
    <div class="ana-toolbar__actions">
        <form action="operation.php?module=reports&page=inventory" method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="export_inventory">
            <button class="btn btn-success btn-sm"><i class="fas fa-download mr-1"></i>CSV</button>
        </form>
    </div>
</div>

<div class="ana-grid">
    <?= stat_tile(['label' => 'Stock value', 'icon' => 'fas fa-warehouse', 'tone' => 'primary',
        'value' => format_compact_money($totalValue, 'NPR', 'NPR 0'),
        'sub' => number_format($totalQty) . ' units across ' . $totalItems . ' items']) ?>
    <?= stat_tile(['label' => 'Low stock', 'icon' => 'fas fa-exclamation-triangle', 'tone' => count($lowStock) > 0 ? 'danger' : 'neutral',
        'value' => number_format(count($lowStock)),
        'sub' => $outOfStock . ' out of stock · at or below reorder point']) ?>
    <?= stat_tile(['label' => 'Units moved (12 weeks)', 'icon' => 'fas fa-exchange-alt', 'tone' => 'info',
        'value' => number_format(array_sum($moveIn) + array_sum($moveOut)),
        'sub' => number_format(array_sum($moveIn)) . ' in · ' . number_format(array_sum($moveOut)) . ' out',
        'spark' => fill_series($weeks12, $moveOut)]) ?>
    <?= stat_tile(['label' => 'Assets', 'icon' => 'fas fa-laptop', 'tone' => 'teal',
        'value' => number_format($assetCount),
        'sub' => format_compact_money($assetValue, 'NPR', 'NPR 0') . ' purchase value']) ?>
</div>

<div class="row ana-row">
    <div class="col-lg-7">
        <div class="ana-card">
            <div class="ana-card__head"><div><h3 class="ana-card__title">Stock value by category</h3><p class="ana-card__hint">Quantity × cost price</p></div></div>
            <div class="ana-chart" style="--h:<?= max(200, 40 * count($valued) + 40) ?>px">
                <canvas role="img" aria-label="Horizontal bar chart of stock value per category"
                        data-chart="<?= chart_attr([
                            'type' => 'hbar', 'format' => 'money', 'labels' => array_map(static fn ($s) => $s['category_title'] ?: 'Uncategorised', $valued),
                            'series' => [['label' => 'Value', 'data' => array_map('floatval', array_column($valued, 'total_value')), 'slot' => 1]],
                            'empty' => 'No stock on hand yet.',
                        ]) ?>"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="ana-card">
            <div class="ana-card__head"><div><h3 class="ana-card__title">Stock movement</h3><p class="ana-card__hint">Units in vs. out per week</p></div></div>
            <div class="ana-chart">
                <canvas role="img" aria-label="Bar chart of stock units in and out per week"
                        data-chart="<?= chart_attr([
                            'type' => 'bar',
                            'labels' => array_map(static fn ($d) => date('j M', strtotime($d)), $weeks12),
                            'series' => [
                                ['label' => 'In', 'data' => fill_series($weeks12, $moveIn), 'slot' => 1],
                                ['label' => 'Out', 'data' => fill_series($weeks12, $moveOut), 'slot' => 2],
                            ],
                            'empty' => 'No stock movements in the last 12 weeks.',
                        ]) ?>"></canvas>
            </div>
        </div>
    </div>
</div>

<?php if ($lowStock): ?>
<div class="ana-card ana-card--flush">
    <div class="ana-card__head"><div><h3 class="ana-card__title">Reorder now</h3><p class="ana-card__hint">Lowest stock relative to reorder point first</p></div></div>
    <div class="table-responsive">
        <table class="table table-sm ana-table">
            <thead><tr><th>SKU</th><th>Item</th><th>Category</th><th class="text-right">On hand</th><th class="text-right">Reorder at</th><th style="width:24%">Stock vs. reorder point</th></tr></thead>
            <tbody>
            <?php foreach ($lowStock as $ls):
                $pct = percent_of((float) $ls['current_stock'], (float) $ls['reorder_point']);
            ?>
                <tr>
                    <td><code><?= e($ls['sku']) ?></code></td>
                    <td><strong><?= e($ls['name']) ?></strong></td>
                    <td class="text-muted"><?= e($ls['category_title'] ?? '—') ?></td>
                    <td class="text-right font-weight-bold<?= (float) $ls['current_stock'] <= 0 ? ' text-danger' : '' ?>"><?= (int) $ls['current_stock'] ?></td>
                    <td class="text-right"><?= (int) $ls['reorder_point'] ?></td>
                    <td><?= meter_bar($pct, $pct <= 25 ? 'critical' : 'warning') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="row ana-row">
    <div class="col-lg-6">
        <div class="ana-card ana-card--flush">
            <div class="ana-card__head"><div><h3 class="ana-card__title">By category</h3></div></div>
            <div class="table-responsive">
                <table class="table table-sm ana-table">
                    <thead><tr><th>Category</th><th class="text-right">Items</th><th class="text-right">Qty</th><th class="text-right">Value</th></tr></thead>
                    <tbody>
                    <?php foreach ($stockSummary as $s): ?>
                        <tr>
                            <td><strong><?= e($s['category_title'] ?: 'Uncategorised') ?></strong></td>
                            <td class="text-right"><?= (int) $s['item_count'] ?></td>
                            <td class="text-right"><?= (int) $s['total_qty'] ?></td>
                            <td class="text-right"><?= e(formatMoney($s['total_value'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$stockSummary): ?>
                        <tr><td colspan="4" class="text-muted text-center py-4">No categories yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                    <tfoot><tr><td>Total</td><td class="text-right"><?= $totalItems ?></td><td class="text-right"><?= (int) $totalQty ?></td><td class="text-right"><?= e(formatMoney($totalValue)) ?></td></tr></tfoot>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="ana-card ana-card--flush">
            <div class="ana-card__head"><div><h3 class="ana-card__title">Assets by status</h3></div></div>
            <div class="table-responsive">
                <table class="table table-sm ana-table">
                    <thead><tr><th>Status</th><th class="text-right">Assets</th><th class="text-right">Purchase value</th><th style="width:30%">Share</th></tr></thead>
                    <tbody>
                    <?php foreach ($assetStatuses as $st):
                        $c = $assetRows[$st]['count'] ?? 0;
                    ?>
                        <tr>
                            <td><?= e($st) ?></td>
                            <td class="text-right"><?= (int) $c ?></td>
                            <td class="text-right"><?= e(formatMoney($assetRows[$st]['value'] ?? 0)) ?></td>
                            <td><?= meter_bar(percent_of((float) $c, (float) $assetCount)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="ana-card ana-card--flush">
    <div class="ana-card__head"><div><h3 class="ana-card__title">Recent stock movements</h3></div></div>
    <div class="table-responsive">
        <table class="table table-sm ana-table">
            <thead><tr><th>Date</th><th>Type</th><th>Item</th><th class="text-right">Qty</th><th>Direction</th><th>By</th></tr></thead>
            <tbody>
            <?php foreach ($recentMovements as $m): ?>
                <tr>
                    <td class="text-muted"><?= e(formatDateView((string) $m['date'])) ?></td>
                    <td><?= e($m['movement_type']) ?></td>
                    <td><?= e($m['item_name'] ?? '—') ?></td>
                    <td class="text-right font-weight-bold"><?= (int) $m['quantity'] ?></td>
                    <td><span class="ana-pill ana-pill--<?= $m['direction'] === 'In' ? 'good' : 'critical' ?>"><?= $m['direction'] === 'In' ? 'In' : 'Out' ?></span></td>
                    <td class="text-muted"><?= e($m['added_by_name'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$recentMovements): ?>
                <tr><td colspan="6" class="text-muted text-center py-4">No stock movements yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
