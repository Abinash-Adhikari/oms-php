<?php

/**
 * SB-Tech — Inventory service functions.
 * Business rules for stock management, movements, requisitions, and assets.
 * Loaded by config/bootstrap.php.
 */

/** Generate next PR number: PR-YYYYMMDD-NNNN */
function inventoryNextPrNo(): string
{
    $db = Database::instance();
    $prefix = 'PR-' . date('Ymd') . '-';
    $row = $db->selectOne(
        "SELECT `pr_no` FROM `tbl_inv_purchase_requisitions`
         WHERE `pr_no` LIKE ? ORDER BY `id` DESC LIMIT 1",
        [$prefix . '%']
    );
    $seq = 1;
    if ($row && preg_match('/(\d+)$/', $row['pr_no'], $m)) {
        $seq = (int) $m[1] + 1;
    }
    return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
}

/** Generate next asset tag: AST-YYYY-NNNN */
function inventoryNextAssetTag(): string
{
    $db = Database::instance();
    $prefix = 'AST-' . date('Y') . '-';
    $row = $db->selectOne(
        "SELECT `asset_tag` FROM `tbl_inv_assets`
         WHERE `asset_tag` LIKE ? ORDER BY `id` DESC LIMIT 1",
        [$prefix . '%']
    );
    $seq = 1;
    if ($row && preg_match('/(\d+)$/', $row['asset_tag'], $m)) {
        $seq = (int) $m[1] + 1;
    }
    return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
}

/** Get available (non-reserved) stock for an item. */
function inventoryAvailableStock(int $itemId): int
{
    $row = Database::instance()->selectOne(
        "SELECT COALESCE(SUM(`quantity`), 0) - COALESCE(SUM(`reserved`), 0) AS available
         FROM `tbl_inv_stock` WHERE `item_id` = ?",
        [$itemId]
    );
    return (int) ($row['available'] ?? 0);
}

/** Get total stock for an item across all locations. */
function inventoryTotalStock(int $itemId): int
{
    $row = Database::instance()->selectOne(
        "SELECT COALESCE(SUM(`quantity`), 0) AS total
         FROM `tbl_inv_stock` WHERE `item_id` = ?",
        [$itemId]
    );
    return (int) ($row['total'] ?? 0);
}

/**
 * Record a stock movement and adjust the stock level.
 * Returns the movement ID.
 */
function inventoryRecordMovement(
    int $itemId,
    string $type,
    int $quantity,
    string $direction,
    ?string $referenceNo = null,
    ?string $fromLocation = null,
    ?string $toLocation = null,
    ?float $unitCost = null,
    ?int $supplierId = null,
    ?int $issuedTo = null,
    ?string $remarks = null,
    ?string $date = null,
    ?int $addedBy = null
): int {
    $db = Database::instance();
    $date = $date ?: date('Y-m-d');
    $totalCost = $unitCost !== null ? round($unitCost * $quantity, 4) : null;

    $movementId = $db->insert('tbl_inv_stock_movements', [
        'item_id'        => $itemId,
        'movement_type'  => $type,
        'quantity'       => $quantity,
        'direction'      => $direction,
        'reference_no'   => $referenceNo,
        'from_location'  => $fromLocation,
        'to_location'    => $toLocation,
        'unit_cost'      => $unitCost,
        'total_cost'     => $totalCost,
        'supplier_id'    => $supplierId,
        'issued_to'      => $issuedTo,
        'remarks'        => $remarks,
        'date'           => $date,
        'added_by'       => $addedBy,
    ]);

    // Adjust stock level at destination location.
    $loc = $toLocation ?: ($fromLocation ?: 'Main');
    if ($direction === 'In') {
        $existing = $db->selectOne(
            'SELECT `id`, `quantity` FROM `tbl_inv_stock` WHERE `item_id` = ? AND `location` = ?',
            [$itemId, $loc]
        );
        if ($existing) {
            $db->update('tbl_inv_stock', [
                'quantity'    => $existing['quantity'] + $quantity,
                'updated_by'  => $addedBy,
            ], '`id` = ?', [(int) $existing['id']]);
        } else {
            $db->insert('tbl_inv_stock', [
                'item_id'    => $itemId,
                'quantity'   => $quantity,
                'location'   => $loc,
                'updated_by' => $addedBy,
            ]);
        }
    } elseif ($direction === 'Out') {
        $existing = $db->selectOne(
            'SELECT `id`, `quantity` FROM `tbl_inv_stock` WHERE `item_id` = ? AND `location` = ?',
            [$itemId, $loc]
        );
        if ($existing) {
            $newQty = max(0, $existing['quantity'] - $quantity);
            $db->update('tbl_inv_stock', [
                'quantity'    => $newQty,
                'updated_by'  => $addedBy,
            ], '`id` = ?', [(int) $existing['id']]);
        }
    }

    // Audit log.
    if (function_exists('auditLog')) {
        auditLog('inventory', $type, 'item', $itemId, null, [
            'quantity' => $quantity,
            'direction' => $direction,
            'location' => $loc,
        ], $remarks);
    }

    return $movementId;
}

/** Check if an item is below reorder point. */
function inventoryIsLowStock(int $itemId): bool
{
    $item = Database::instance()->selectOne(
        'SELECT `reorder_point` FROM `tbl_inv_items` WHERE `id` = ?',
        [$itemId]
    );
    if (!$item || (int) $item['reorder_point'] <= 0) {
        return false;
    }
    return inventoryTotalStock($itemId) <= (int) $item['reorder_point'];
}

/** Get all low-stock items. */
function inventoryLowStockItems(): array
{
    return Database::instance()->select(
        "SELECT i.*, c.title AS category_title,
                COALESCE(s.total, 0) AS current_stock
         FROM `tbl_inv_items` i
         LEFT JOIN `tbl_inv_categories` c ON c.id = i.category_id
         LEFT JOIN (
            SELECT item_id, SUM(quantity) AS total
            FROM `tbl_inv_stock` GROUP BY item_id
         ) s ON s.item_id = i.id
         WHERE i.is_active = 1
           AND i.reorder_point > 0
           AND COALESCE(s.total, 0) <= i.reorder_point
         ORDER BY (COALESCE(s.total, 0) / GREATEST(i.reorder_point, 1)) ASC"
    );
}

/** Get items expiring warranty within N days. */
function inventoryWarrantyExpiring(int $days = 30): array
{
    $from = date('Y-m-d');
    $to = date('Y-m-d', strtotime("+{$days} days"));
    return Database::instance()->select(
        "SELECT a.*, i.name AS item_name, u.fullname AS assigned_name
         FROM `tbl_inv_assets` a
         LEFT JOIN `tbl_inv_items` i ON i.id = a.item_id
         LEFT JOIN `tbl_users_login` u ON u.id = a.assigned_to
         WHERE a.warranty_expiry BETWEEN ? AND ?
           AND a.current_status != 'Disposed'
         ORDER BY a.warranty_expiry",
        [$from, $to]
    );
}

/** Stock summary grouped by category. */
function inventoryStockSummary(): array
{
    return Database::instance()->select(
        "SELECT c.title AS category_title,
                COUNT(DISTINCT i.id) AS item_count,
                COALESCE(SUM(s.total), 0) AS total_qty,
                COALESCE(SUM(s.total * i.cost_price), 0) AS total_value
         FROM `tbl_inv_categories` c
         LEFT JOIN `tbl_inv_items` i ON i.category_id = c.id AND i.is_active = 1
         LEFT JOIN (
            SELECT item_id, SUM(quantity) AS total
            FROM `tbl_inv_stock` GROUP BY item_id
         ) s ON s.item_id = i.id
         GROUP BY c.id, c.title
         ORDER BY c.title"
    );
}
