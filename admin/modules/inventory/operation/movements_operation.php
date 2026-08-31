<?php
/**
 * SB-Tech — Inventory / Stock Movements operations (record movement / export).
 */
$db = Database::instance();
$action = (string) ($_POST['action'] ?? '');
$back = pageUrl('inventory', 'movements');

try {
    if ($action === 'record_movement') {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $type = (string) ($_POST['movement_type'] ?? '');
        $quantity = max(1, (int) ($_POST['quantity'] ?? 1));
        $direction = (string) ($_POST['direction'] ?? 'In');
        $date = trim((string) ($_POST['date'] ?? '')) ?: date('Y-m-d');
        $supplierId = (int) ($_POST['supplier_id'] ?? 0) ?: null;
        $issuedTo = (int) ($_POST['issued_to'] ?? 0) ?: null;
        $unitCost = ($_POST['unit_cost'] ?? '') !== '' ? round((float) $_POST['unit_cost'], 4) : null;
        $location = trim((string) ($_POST['location'] ?? '')) ?: 'Main';
        $referenceNo = trim((string) ($_POST['reference_no'] ?? '')) ?: null;
        $remarks = trim((string) ($_POST['remarks'] ?? '')) ?: null;

        if (!$itemId || !in_array($type, ['Purchase','Issue','Return','Transfer','Adjustment','Write-off','Opening'], true)) {
            setFlash('error', 'Item and valid movement type are required.');
            redirect($back);
        }
        if (!in_array($direction, ['In', 'Out'], true)) {
            $direction = 'In';
        }

        inventoryRecordMovement($itemId, $type, $quantity, $direction, $referenceNo, null, $location, $unitCost, $supplierId, $issuedTo, $remarks, $date, Auth::id());
        setFlash('success', ucfirst($type) . ' recorded: ' . $quantity . ' units.');
        redirect($back . '&add=1');
    }

    if ($action === 'export_movements') {
        $filterType = $_POST['type'] ?? '';
        $filterDateFrom = $_POST['date_from'] ?? '';
        $filterDateTo = $_POST['date_to'] ?? '';
        $filterItem = (int) ($_POST['item_id'] ?? 0);

        $where = ['1=1'];
        $params = [];
        if ($filterType) { $where[] = 'm.movement_type = ?'; $params[] = $filterType; }
        if ($filterDateFrom) { $where[] = 'm.date >= ?'; $params[] = $filterDateFrom; }
        if ($filterDateTo) { $where[] = 'm.date <= ?'; $params[] = $filterDateTo; }
        if ($filterItem) { $where[] = 'm.item_id = ?'; $params[] = $filterItem; }

        $movements = $db->select(
            "SELECT m.*, i.name AS item_name, i.sku, s.name AS supplier_name, u.fullname AS issued_to_name, a.fullname AS actor
             FROM `tbl_inv_stock_movements` m
             LEFT JOIN `tbl_inv_items` i ON i.id = m.item_id
             LEFT JOIN `tbl_inv_suppliers` s ON s.id = m.supplier_id
             LEFT JOIN `tbl_users_login` u ON u.id = m.issued_to
             LEFT JOIN `tbl_users_login` a ON a.id = m.added_by
             WHERE " . implode(' AND ', $where) . "
             ORDER BY m.date DESC, m.id DESC",
            $params
        );
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="stock_movements_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Date', 'Type', 'SKU', 'Item', 'Qty', 'Direction', 'Ref', 'Supplier', 'Unit Cost', 'Total Cost', 'Actor', 'Remarks']);
        foreach ($movements as $m) {
            fputcsv($out, [
                $m['date'], $m['movement_type'], $m['sku'], $m['item_name'],
                $m['quantity'], $m['direction'], $m['reference_no'],
                $m['supplier_name'] ?? $m['issued_to_name'] ?? '',
                $m['unit_cost'], $m['total_cost'], $m['actor'], $m['remarks'],
            ]);
        }
        fclose($out);
        exit;
    }

    setFlash('error', 'Unknown action.');
    redirect($back);
} catch (Throwable $e) {
    setFlash('error', 'Movement operation failed: ' . $e->getMessage());
    redirect($back);
}
