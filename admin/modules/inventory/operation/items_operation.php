<?php
/**
 * SB-Tech — Inventory / Items operations (save / delete / export).
 */
$db = Database::instance();
$action = (string) ($_POST['action'] ?? '');
$back = pageUrl('inventory', 'items');

try {
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $sku = trim((string) ($_POST['sku'] ?? ''));
        $name = trim((string) ($_POST['name'] ?? ''));
        $categoryId = (int) ($_POST['category_id'] ?? 0) ?: null;
        $unit = trim((string) ($_POST['unit'] ?? 'pcs'));
        $costPrice = round((float) ($_POST['cost_price'] ?? 0), 4);
        $sellPrice = round((float) ($_POST['selling_price'] ?? 0), 4);
        $minStock = (int) ($_POST['min_stock'] ?? 0);
        $isSerialized = (int) ($_POST['is_serialized'] ?? 0);
        $description = trim((string) ($_POST['description'] ?? ''));

        if ($sku === '' || $name === '') {
            setFlash('error', 'SKU and Name are required.');
            redirect($back);
        }

        $data = [
            'sku'            => $sku,
            'name'           => $name,
            'category_id'    => $categoryId,
            'unit'           => $unit ?: 'pcs',
            'cost_price'     => $costPrice,
            'selling_price'  => $sellPrice,
            'min_stock'      => $minStock,
            'is_serialized'  => $isSerialized ? 1 : 0,
            'description'    => $description ?: null,
            'updated_by'     => Auth::id(),
        ];

        if ($id) {
            $db->update('tbl_inv_items', $data, '`id` = ?', [$id]);
            setFlash('success', 'Item updated.');
        } else {
            $exists = $db->selectOne('SELECT `id` FROM `tbl_inv_items` WHERE `sku` = ?', [$sku]);
            if ($exists) {
                setFlash('error', 'SKU already exists.');
                redirect($back);
            }
            $data['added_by'] = Auth::id();
            $db->insert('tbl_inv_items', $data);
            setFlash('success', 'Item created.');
        }
        redirect($back);
    }

    if ($action === 'delete_item') {
        $id = (int) ($_POST['id'] ?? 0);
        $db->delete('tbl_inv_items', '`id` = ?', [$id]);
        setFlash('success', 'Item deleted.');
        redirect($back);
    }

    if ($action === 'export_items') {
        $items = $db->select(
            "SELECT i.sku, i.name, c.title AS category_title, i.unit, i.cost_price, i.sell_price,
                    i.min_stock, i.is_serialized, i.is_active,
                    COALESCE(s.total, 0) AS current_stock
             FROM `tbl_inv_items` i
             LEFT JOIN `tbl_inv_categories` c ON c.id = i.category_id
             LEFT JOIN (SELECT item_id, SUM(quantity) AS total FROM `tbl_inv_stock` GROUP BY item_id) s ON s.item_id = i.id
             ORDER BY i.name"
        );
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="inventory_items_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['SKU', 'Name', 'Category', 'Unit', 'Cost Price', 'Selling Price', 'Current Stock', 'Min Stock', 'Serialized', 'Active']);
        foreach ($items as $r) {
            fputcsv($out, [
                $r['sku'], $r['name'], $r['category_title'], $r['unit'],
                (float) $r['cost_price'], (float) $r['sell_price'],
                (int) $r['current_stock'], (int) $r['min_stock'],
                $r['is_serialized'] ? 'Yes' : 'No', $r['is_active'] ? 'Yes' : 'No',
            ]);
        }
        fclose($out);
        exit;
    }

    setFlash('error', 'Unknown action.');
    redirect($back);
} catch (Throwable $e) {
    setFlash('error', 'Item operation failed: ' . $e->getMessage());
    redirect($back);
}
