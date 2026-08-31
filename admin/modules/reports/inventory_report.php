<?php
/**
 * SB-Tech — Reports / Inventory.
 * Stock value, low stock alerts, asset register, movement summary.
 */
$db = Database::instance();

// Stock summary by category
$stockSummary = $db->select(
    "SELECT c.title AS category_title,
            COUNT(DISTINCT i.id) AS item_count,
            COALESCE(SUM(s.total), 0) AS total_qty,
            COALESCE(SUM(s.total * i.cost_price), 0) AS total_value
     FROM tbl_inv_categories c
     LEFT JOIN tbl_inv_items i ON i.category_id = c.id AND i.is_active = 1
     LEFT JOIN (SELECT item_id, SUM(quantity) AS total FROM tbl_inv_stock GROUP BY item_id) s ON s.item_id = i.id
     GROUP BY c.id, c.title
     ORDER BY c.title"
);

$totalItems = array_sum(array_column($stockSummary, 'item_count'));
$totalQty = array_sum(array_column($stockSummary, 'total_qty'));
$totalValue = array_sum(array_column($stockSummary, 'total_value'));

// Low stock
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

// Asset summary
$assetSummary = $db->select(
    "SELECT current_status, COUNT(*) AS c, SUM(purchase_price) AS total_price
     FROM tbl_inv_assets WHERE is_active = 1 GROUP BY current_status"
);

// Recent movements
$recentMovements = $db->select(
    "SELECT m.*, i.name AS item_name, i.sku
     FROM tbl_inv_stock_movements m
     LEFT JOIN tbl_inv_items i ON i.id = m.item_id
     ORDER BY m.date DESC, m.id DESC LIMIT 10"
);
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-boxes mr-1"></i>Inventory Report</h3>
        <div class="card-tools">
            <form action="operation.php?module=reports&page=inventory_operation" method="post" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="export_inventory">
                <button class="btn btn-success btn-sm"><i class="fas fa-download mr-1"></i>CSV</button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-3"><div class="callout callout-info"><h6>Total Items</h6><h3><?= $totalItems ?></h3></div></div>
            <div class="col-md-3"><div class="callout callout-success"><h6>Total Units</h6><h3><?= $totalQty ?></h3></div></div>
            <div class="col-md-3"><div class="callout callout-primary"><h6>Stock Value</h6><h3><?= formatMoney($totalValue) ?></h3></div></div>
            <div class="col-md-3"><div class="callout callout-warning"><h6>Low Stock Items</h6><h3><?= count($lowStock) ?></h3></div></div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Stock by Category -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h5 class="card-title">Stock Value by Category</h5></div>
            <div class="card-body p-0">
                <table class="table table-sm table-striped">
                    <thead><tr><th>Category</th><th class="text-center">Items</th><th class="text-center">Qty</th><th class="text-right">Value</th></tr></thead>
                    <tbody>
                    <?php foreach ($stockSummary as $s): ?>
                        <tr>
                            <td><strong><?= e($s['category_title'] ?: 'Uncategorized') ?></strong></td>
                            <td class="text-center"><?= (int) $s['item_count'] ?></td>
                            <td class="text-center"><?= (int) $s['total_qty'] ?></td>
                            <td class="text-right"><?= formatMoney($s['total_value']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr class="font-weight-bold"><td>TOTAL</td><td class="text-center"><?= $totalItems ?></td><td class="text-center"><?= $totalQty ?></td><td class="text-right"><?= formatMoney($totalValue) ?></td></tr></tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Asset Summary -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h5 class="card-title">Asset Summary</h5></div>
            <div class="card-body p-0">
                <table class="table table-sm table-striped">
                    <thead><tr><th>Status</th><th class="text-center">Count</th><th class="text-right">Purchase Value</th></tr></thead>
                    <tbody>
                    <?php foreach ($assetSummary as $as): ?>
                        <tr>
                            <td><span class="badge badge-<?= ['In Stock'=>'success','Assigned'=>'primary','Under Maintenance'=>'warning','Retired'=>'secondary','Disposed'=>'dark'][$as['current_status']] ?? 'secondary' ?>"><?= e($as['current_status']) ?></span></td>
                            <td class="text-center"><?= (int) $as['c'] ?></td>
                            <td class="text-right"><?= formatMoney($as['total_price']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$assetSummary): ?>
                        <tr><td colspan="3" class="text-muted text-center">No assets.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Low Stock -->
<?php if ($lowStock): ?>
<div class="card card-outline card-warning">
    <div class="card-header"><h5 class="card-title"><i class="fas fa-exclamation-triangle mr-1"></i>Low Stock Items</h5></div>
    <div class="card-body p-0">
        <table class="table table-sm table-striped">
            <thead><tr><th>SKU</th><th>Item</th><th>Category</th><th class="text-center">Current</th><th class="text-center">Reorder</th></tr></thead>
            <tbody>
            <?php foreach ($lowStock as $ls): ?>
                <tr class="table-warning">
                    <td><code><?= e($ls['sku']) ?></code></td>
                    <td><strong><?= e($ls['name']) ?></strong></td>
                    <td><?= e($ls['category_title'] ?? '—') ?></td>
                    <td class="text-center"><strong class="text-danger"><?= (int) $ls['current_stock'] ?></strong></td>
                    <td class="text-center"><?= (int) $ls['reorder_point'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Recent Movements -->
<div class="card">
    <div class="card-header"><h5 class="card-title">Recent Stock Movements</h5></div>
    <div class="card-body p-0">
        <table class="table table-sm table-striped">
            <thead><tr><th>Date</th><th>Type</th><th>Item</th><th class="text-center">Qty</th><th>Dir</th><th>By</th></tr></thead>
            <tbody>
            <?php foreach ($recentMovements as $m): ?>
                <tr>
                    <td class="small"><?= e($m['date']) ?></td>
                    <td><span class="badge badge-info"><?= e($m['movement_type']) ?></span></td>
                    <td><?= e($m['item_name'] ?? '—') ?></td>
                    <td class="text-center"><strong><?= (int) $m['quantity'] ?></strong></td>
                    <td><?= $m['direction'] === 'In' ? '<span class="text-success">IN</span>' : '<span class="text-danger">OUT</span>' ?></td>
                    <td class="small"><?= e($m['added_by'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
