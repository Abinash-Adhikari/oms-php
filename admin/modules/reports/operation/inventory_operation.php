<?php
/**
 * SB-Tech — Reports / Inventory operations (CSV export).
 */
$db = Database::instance();
$action = (string) ($_POST['action'] ?? '');

try {
    if ($action === 'export_inventory') {
        $rows = $db->select(
            "SELECT i.sku, i.name, c.title AS category, i.unit, i.cost_price,
                    COALESCE(s.total, 0) AS stock_qty, i.reorder_point,
                    (COALESCE(s.total, 0) * i.cost_price) AS stock_value
             FROM tbl_inv_items i
             LEFT JOIN tbl_inv_categories c ON c.id = i.category_id
             LEFT JOIN (SELECT item_id, SUM(quantity) AS total FROM tbl_inv_stock GROUP BY item_id) s ON s.item_id = i.id
             WHERE i.is_active = 1 ORDER BY c.title, i.name"
        );
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="inventory_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['SKU', 'Name', 'Category', 'Unit', 'Cost Price', 'Stock Qty', 'Reorder Point', 'Stock Value']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['sku'], $r['name'], $r['category'], $r['unit'], $r['cost_price'], $r['stock_qty'], $r['reorder_point'], $r['stock_value']]);
        }
        fclose($out);
        exit;
    }
    setFlash('error', 'Unknown action.');
    redirect(pageUrl('reports', 'inventory'));
} catch (Throwable $e) {
    setFlash('error', 'Export failed: ' . $e->getMessage());
    redirect(pageUrl('reports', 'inventory'));
}
