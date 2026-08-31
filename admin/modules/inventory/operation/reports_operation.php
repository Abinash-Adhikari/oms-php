<?php
/**
 * SB-Tech — Inventory / Reports operations (CSV exports).
 */
$db = Database::instance();
$action = (string) ($_POST['action'] ?? '');

try {
    if ($action === 'export_asset_register') {
        $assets = $db->select(
            "SELECT a.asset_tag, a.name, i.name AS item_name, a.serial_number, a.brand, a.model,
                    a.purchase_date, a.purchase_price, a.warranty_expiry, a.condition_status,
                    a.current_status, u.fullname AS assigned_name, a.location, a.notes
             FROM `tbl_inv_assets` a
             LEFT JOIN `tbl_inv_items` i ON i.id = a.item_id
             LEFT JOIN `tbl_users_login` u ON u.id = a.assigned_to
             WHERE a.is_active = 1 ORDER BY a.asset_tag"
        );
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="asset_register_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Asset Tag', 'Name', 'Item', 'Serial No', 'Brand', 'Model', 'Purchase Date', 'Purchase Price', 'Warranty Expiry', 'Condition', 'Status', 'Assigned To', 'Location', 'Notes']);
        foreach ($assets as $a) {
            fputcsv($out, [
                $a['asset_tag'], $a['name'], $a['item_name'], $a['serial_number'],
                $a['brand'], $a['model'], $a['purchase_date'], (float) $a['purchase_price'],
                $a['warranty_expiry'], $a['condition_status'], $a['current_status'],
                $a['assigned_name'], $a['location'], $a['notes'],
            ]);
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
