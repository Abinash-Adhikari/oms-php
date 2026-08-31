<?php
/**
 * SB-Tech — Inventory / Stock (US-INV-04).
 * Current stock levels with quick adjustment capability.
 */
$db = Database::instance();

$catFilter = (int) ($_GET['cat_id'] ?? 0);
$search = trim((string) ($_GET['q'] ?? ''));
$showLow = isset($_GET['low']);

$where = ['1=1'];
$params = [];
if ($search !== '') {
    $where[] = '(i.name LIKE ? OR i.sku LIKE ?)';
    $p = '%' . $db->escapeLike($search) . '%';
    $params = [$p, $p];
}
if ($catFilter) {
    $where[] = 'i.category_id = ?';
    $params[] = $catFilter;
}
if ($showLow) {
    $where[] = 'i.reorder_point > 0 AND COALESCE(s.total, 0) <= i.reorder_point';
}

$categories = $db->select('SELECT * FROM `tbl_inv_categories` WHERE `is_active` = 1 ORDER BY position, title');
$stocks = $db->select(
    "SELECT i.id, i.sku, i.name, i.unit, i.cost_price, i.reorder_point, i.min_stock,
            c.title AS category_title,
            COALESCE(s.total, 0) AS total_stock,
            COALESCE(s.reserved, 0) AS reserved
     FROM `tbl_inv_items` i
     LEFT JOIN `tbl_inv_categories` c ON c.id = i.category_id
     LEFT JOIN (
        SELECT item_id, SUM(quantity) AS total, SUM(reserved) AS reserved
        FROM `tbl_inv_stock` GROUP BY item_id
     ) s ON s.item_id = i.id
     WHERE i.is_active = 1 AND " . implode(' AND ', $where) . "
     ORDER BY c.title, i.name",
    $params
);

$totalValue = 0;
foreach ($stocks as $s) {
    $totalValue += (float) $s['cost_price'] * (int) $s['total_stock'];
}
?>

<div class="row mb-3">
    <div class="col-md-3">
        <div class="info-box"><span class="info-box-icon bg-info"><i class="fas fa-box"></i></span>
            <div class="info-box-content"><span class="info-box-text">Total Items</span><span class="info-box-number"><?= count($stocks) ?></span></div></div>
    </div>
    <div class="col-md-3">
        <div class="info-box"><span class="info-box-icon bg-success"><i class="fas fa-cubes"></i></span>
            <div class="info-box-content"><span class="info-box-text">Total Units</span><span class="info-box-number"><?= array_sum(array_column($stocks, 'total_stock')) ?></span></div></div>
    </div>
    <div class="col-md-3">
        <div class="info-box"><span class="info-box-icon bg-warning"><i class="fas fa-exclamation-triangle"></i></span>
            <div class="info-box-content"><span class="info-box-text">Low Stock</span><span class="info-box-number"><?= count(inventoryLowStockItems()) ?></span></div></div>
    </div>
    <div class="col-md-3">
        <div class="info-box"><span class="info-box-icon bg-primary"><i class="fas fa-money-bill"></i></span>
            <div class="info-box-content"><span class="info-box-text">Stock Value</span><span class="info-box-number"><?= formatMoney($totalValue) ?></span></div></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-warehouse mr-1"></i>Stock Levels</h3>
        <div class="card-tools">
            <a href="<?= pageUrl('inventory', 'stock') ?><?= $showLow ? '' : '&low=1' ?>" class="btn btn-sm btn-<?= $showLow ? 'warning' : 'outline-warning' ?>"><i class="fas fa-exclamation mr-1"></i>Low Stock</a>
            <a href="<?= pageUrl('inventory', 'stock') ?>&adjust=1" class="btn btn-sm btn-primary"><i class="fas fa-sliders-h mr-1"></i>Quick Adjust</a>
        </div>
    </div>
    <div class="card-body">
        <form class="form-inline mb-2" method="get">
            <input type="hidden" name="module" value="inventory">
            <input type="hidden" name="page" value="stock">
            <select name="cat_id" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                <option value="0">All Categories</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= $catFilter === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="q" class="form-control form-control-sm mr-2" placeholder="Search..." value="<?= e($search) ?>">
            <button class="btn btn-sm btn-primary"><i class="fas fa-search"></i></button>
        </form>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm table-striped">
            <thead><tr><th>SKU</th><th>Item</th><th>Category</th><th>Unit</th><th>Qty</th><th>Reserved</th><th>Available</th><th>Value</th><th>Min</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (!$stocks): ?>
                <tr><td colspan="10" class="text-muted text-center">No stock data.</td></tr>
            <?php else: foreach ($stocks as $s): ?>
                <tr>
                    <td><code><?= e($s['sku']) ?></code></td>
                    <td><strong><?= e($s['name']) ?></strong></td>
                    <td><?= e($s['category_title'] ?? '—') ?></td>
                    <td><?= e($s['unit']) ?></td>
                    <td><strong><?= (int) $s['total_stock'] ?></strong></td>
                    <td><?= (int) $s['reserved'] ?></td>
                    <td><strong><?= (int) $s['total_stock'] - (int) $s['reserved'] ?></strong></td>
                    <td><?= formatMoney((float) $s['cost_price'] * (int) $s['total_stock']) ?></td>
                    <td><?= (int) $s['min_stock'] ?></td>
                    <td>
                        <?php if ((int) $s['total_stock'] <= 0): ?>
                            <span class="badge badge-danger">Out</span>
                        <?php elseif ((int) $s['reorder_point'] > 0 && (int) $s['total_stock'] <= (int) $s['reorder_point']): ?>
                            <span class="badge badge-warning">Low</span>
                        <?php else: ?>
                            <span class="badge badge-success">OK</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
