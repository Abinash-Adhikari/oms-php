<?php
/**
 * SB-Tech — Inventory / Reports (US-INV-08).
 * Stock summary by category, low stock alerts, asset register, warranty alerts.
 */
$db = Database::instance();

$tab = $_GET['tab'] ?? 'summary';
if (!in_array($tab, ['summary', 'low_stock', 'assets', 'warranty'], true)) {
    $tab = 'summary';
}
?>

<div class="card card-primary card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-chart-bar mr-1"></i>Inventory Reports</h3>
        <div class="card-tools">
            <?php foreach (['summary' => 'Stock Summary', 'low_stock' => 'Low Stock', 'assets' => 'Asset Register', 'warranty' => 'Warranty'] as $tk => $label): ?>
                <a href="<?= pageUrl('inventory', 'reports') ?>&tab=<?= $tk ?>" class="btn btn-sm btn-<?= $tab === $tk ? 'primary' : 'default' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card-body">

    <?php if ($tab === 'summary'):
        $summary = inventoryStockSummary();
    ?>
        <h5>Stock by Category</h5>
        <table class="table table-sm table-striped">
            <thead><tr><th>Category</th><th class="text-right">Items</th><th class="text-right">Total Qty</th><th class="text-right">Total Value</th></tr></thead>
            <tbody>
            <?php foreach ($summary as $s): ?>
                <tr>
                    <td><strong><?= e($s['category_title'] ?: 'Uncategorized') ?></strong></td>
                    <td class="text-right"><?= (int) $s['item_count'] ?></td>
                    <td class="text-right"><?= (int) $s['total_qty'] ?></td>
                    <td class="text-right"><?= formatMoney($s['total_value']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$summary): ?>
                <tr><td colspan="4" class="text-muted text-center">No data.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

    <?php elseif ($tab === 'low_stock'):
        $low = inventoryLowStockItems();
    ?>
        <h5>Low Stock Alerts (<?= count($low) ?>)</h5>
        <table class="table table-sm table-striped">
            <thead><tr><th>SKU</th><th>Item</th><th>Category</th><th class="text-right">Current</th><th class="text-right">Reorder Point</th><th class="text-right">Shortage</th></tr></thead>
            <tbody>
            <?php foreach ($low as $l): ?>
                <tr class="table-warning">
                    <td><code><?= e($l['sku']) ?></code></td>
                    <td><strong><?= e($l['name']) ?></strong></td>
                    <td><?= e($l['category_title'] ?? '—') ?></td>
                    <td class="text-right font-weight-bold"><?= (int) $l['current_stock'] ?></td>
                    <td class="text-right"><?= (int) $l['reorder_point'] ?></td>
                    <td class="text-right text-danger font-weight-bold"><?= max(0, (int) $l['reorder_point'] - (int) $l['current_stock']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$low): ?>
                <tr><td colspan="6" class="text-success text-center"><i class="fas fa-check-circle mr-1"></i>All items are adequately stocked.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

    <?php elseif ($tab === 'assets'):
        $assets = $db->select(
            "SELECT a.*, i.name AS item_name, u.fullname AS assigned_name
             FROM `tbl_inv_assets` a
             LEFT JOIN `tbl_inv_items` i ON i.id = a.item_id
             LEFT JOIN `tbl_users_login` u ON u.id = a.assigned_to
             WHERE a.is_active = 1 ORDER BY a.asset_tag"
        );
    ?>
        <div class="mb-2">
            <form action="operation.php?module=inventory&page=reports_operation" method="post" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="export_asset_register">
                <button class="btn btn-success btn-sm"><i class="fas fa-download mr-1"></i>Export Asset Register</button>
            </form>
        </div>
        <table class="table table-sm table-striped">
            <thead><tr><th>Tag</th><th>Name</th><th>Item</th><th>Serial</th><th>Condition</th><th>Status</th><th>Assigned To</th><th>Purchase Price</th><th>Warranty</th></tr></thead>
            <tbody>
            <?php foreach ($assets as $a): ?>
                <tr>
                    <td><code><?= e($a['asset_tag']) ?></code></td>
                    <td><strong><?= e($a['name']) ?></strong></td>
                    <td class="small"><?= e($a['item_name'] ?? '—') ?></td>
                    <td class="small"><?= e($a['serial_number']) ?></td>
                    <td><span class="badge badge-<?= ['New'=>'success','Good'=>'info','Fair'=>'warning','Poor'=>'danger','Damaged'=>'danger','Retired'=>'secondary'][$a['condition_status']] ?? 'secondary' ?>"><?= e($a['condition_status']) ?></span></td>
                    <td><span class="badge badge-<?= ['In Stock'=>'success','Assigned'=>'primary','Under Maintenance'=>'warning','Retired'=>'secondary','Disposed'=>'dark'][$a['current_status']] ?? 'secondary' ?>"><?= e($a['current_status']) ?></span></td>
                    <td class="small"><?= e($a['assigned_name'] ?? '—') ?></td>
                    <td class="text-right"><?= formatMoney($a['purchase_price']) ?></td>
                    <td class="small"><?= $a['warranty_expiry'] ? e($a['warranty_expiry']) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$assets): ?>
                <tr><td colspan="9" class="text-muted text-center">No assets tracked.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

    <?php elseif ($tab === 'warranty'):
        $expiring = inventoryWarrantyExpiring(90);
    ?>
        <h5>Warranty Expiring Within 90 Days (<?= count($expiring) ?>)</h5>
        <table class="table table-sm table-striped">
            <thead><tr><th>Asset Tag</th><th>Name</th><th>Item</th><th>Assigned To</th><th>Warranty Expiry</th><th>Days Left</th></tr></thead>
            <tbody>
            <?php foreach ($expiring as $w):
                $daysLeft = (int) ((strtotime($w['warranty_expiry']) - time()) / 86400);
            ?>
                <tr class="<?= $daysLeft <= 30 ? 'table-danger' : ($daysLeft <= 60 ? 'table-warning' : '') ?>">
                    <td><code><?= e($w['asset_tag']) ?></code></td>
                    <td><strong><?= e($w['name']) ?></strong></td>
                    <td class="small"><?= e($w['item_name'] ?? '—') ?></td>
                    <td class="small"><?= e($w['assigned_name'] ?? '—') ?></td>
                    <td><?= e($w['warranty_expiry']) ?></td>
                    <td><strong class="<?= $daysLeft <= 30 ? 'text-danger' : '' ?>"><?= $daysLeft ?> days</strong></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$expiring): ?>
                <tr><td colspan="6" class="text-success text-center"><i class="fas fa-check-circle mr-1"></i>No warranties expiring within 90 days.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>

    </div>
</div>
