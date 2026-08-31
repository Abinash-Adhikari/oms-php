<?php
/**
 * SB-Tech — Inventory / Stock Movements (US-INV-05).
 * Movement log with filters for type, date range, and item.
 */
$db = Database::instance();

$filterType = $_GET['type'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';
$filterItem = (int) ($_GET['item_id'] ?? 0);

$where = ['1=1'];
$params = [];
if ($filterType && in_array($filterType, ['Purchase','Issue','Return','Transfer','Adjustment','Write-off','Opening'], true)) {
    $where[] = 'm.movement_type = ?';
    $params[] = $filterType;
}
if ($filterDateFrom) {
    $where[] = 'm.date >= ?';
    $params[] = $filterDateFrom;
}
if ($filterDateTo) {
    $where[] = 'm.date <= ?';
    $params[] = $filterDateTo;
}
if ($filterItem) {
    $where[] = 'm.item_id = ?';
    $params[] = $filterItem;
}

$whereSql = implode(' AND ', $where);
$page = max(1, (int) ($_GET['p'] ?? 1));
$total = (int) ($db->selectOne("SELECT COUNT(*) AS c FROM `tbl_inv_stock_movements` m WHERE {$whereSql}", $params)['c'] ?? 0);
$pp = paginationParams($total, $page);

$movements = $db->select(
    "SELECT m.*, i.name AS item_name, i.sku, s.name AS supplier_name, u.fullname AS issued_to_name,
            a.fullname AS added_by_name
     FROM `tbl_inv_stock_movements` m
     LEFT JOIN `tbl_inv_items` i ON i.id = m.item_id
     LEFT JOIN `tbl_inv_suppliers` s ON s.id = m.supplier_id
     LEFT JOIN `tbl_users_login` u ON u.id = m.issued_to
     LEFT JOIN `tbl_users_login` a ON a.id = m.added_by
     WHERE {$whereSql}
     ORDER BY m.date DESC, m.id DESC
     LIMIT {$pp['per_page']} OFFSET {$pp['offset']}",
    $params
);
$items = $db->select('SELECT id, name, sku FROM `tbl_inv_items` ORDER BY name');
$typeBadges = ['Purchase' => 'success', 'Issue' => 'warning', 'Return' => 'info', 'Transfer' => 'primary', 'Adjustment' => 'secondary', 'Write-off' => 'danger', 'Opening' => 'dark'];
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-exchange-alt mr-1"></i>Stock Movements (<?= number_format($total) ?>)</h3>
        <div class="card-tools">
            <form action="operation.php?module=inventory&page=movements_operation" method="post" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="export_movements">
                <input type="hidden" name="type" value="<?= e($filterType) ?>">
                <input type="hidden" name="date_from" value="<?= e($filterDateFrom) ?>">
                <input type="hidden" name="date_to" value="<?= e($filterDateTo) ?>">
                <input type="hidden" name="item_id" value="<?= (int) $filterItem ?>">
                <button class="btn btn-success btn-sm"><i class="fas fa-download mr-1"></i>CSV</button>
            </form>
            <a href="<?= pageUrl('inventory', 'movements') ?>&add=1" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i>Record Movement</a>
        </div>
    </div>
    <div class="card-body">
        <form class="form-inline" method="get">
            <input type="hidden" name="module" value="inventory">
            <input type="hidden" name="page" value="movements">
            <select name="type" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                <option value="">All Types</option>
                <?php foreach ($typeBadges as $t => $c): ?>
                    <option value="<?= $t ?>" <?= $filterType === $t ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
            </select>
            <select name="item_id" class="form-control form-control-sm mr-2">
                <option value="0">All Items</option>
                <?php foreach ($items as $i): ?>
                    <option value="<?= (int) $i['id'] ?>" <?= $filterItem === (int) $i['id'] ? 'selected' : '' ?>><?= e($i['name']) ?> (<?= e($i['sku']) ?>)</option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="date_from" class="form-control form-control-sm mr-1" value="<?= e($filterDateFrom) ?>" placeholder="From">
            <input type="date" name="date_to" class="form-control form-control-sm mr-1" value="<?= e($filterDateTo) ?>" placeholder="To">
            <button class="btn btn-sm btn-primary"><i class="fas fa-search"></i></button>
        </form>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm table-striped">
            <thead><tr><th>Date</th><th>Type</th><th>Item</th><th>Qty</th><th>Direction</th><th>Ref</th><th>Supplier/To</th><th>Cost</th><th>By</th><th>Remarks</th></tr></thead>
            <tbody>
            <?php if (!$movements): ?>
                <tr><td colspan="10" class="text-muted text-center">No movements found.</td></tr>
            <?php else: foreach ($movements as $m): ?>
                <tr>
                    <td class="small"><?= e($m['date']) ?></td>
                    <td><span class="badge badge-<?= $typeBadges[$m['movement_type']] ?? 'secondary' ?>"><?= e($m['movement_type']) ?></span></td>
                    <td><strong><?= e($m['item_name']) ?></strong><br><small class="text-muted"><?= e($m['sku']) ?></small></td>
                    <td><strong><?= (int) $m['quantity'] ?></strong></td>
                    <td><?= $m['direction'] === 'In' ? '<span class="text-success font-weight-bold">IN</span>' : '<span class="text-danger font-weight-bold">OUT</span>' ?></td>
                    <td class="small"><?= e($m['reference_no']) ?></td>
                    <td class="small"><?= e($m['supplier_name'] ?? $m['issued_to_name'] ?? '—') ?></td>
                    <td class="small"><?= $m['unit_cost'] ? formatMoney((float) $m['unit_cost'] * (int) $m['quantity']) : '—' ?></td>
                    <td class="small"><?= e($m['added_by_name']) ?></td>
                    <td class="small" style="max-width:200px;overflow:hidden;text-overflow:ellipsis"><?= e($m['remarks']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pp['pages'] > 1): ?>
    <div class="card-footer">
        <nav><ul class="pagination pagination-sm mb-0">
            <?php for ($i = max(1, $pp['page'] - 3); $i <= min($pp['pages'], $pp['page'] + 3); $i++): ?>
                <li class="page-item<?= $i === $pp['page'] ? ' active' : '' ?>">
                    <a class="page-link" href="?module=inventory&page=movements&type=<?= urlencode($filterType) ?>&date_from=<?= urlencode($filterDateFrom) ?>&date_to=<?= urlencode($filterDateTo) ?>&item_id=<?= (int) $filterItem ?>&p=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>
