<?php
/**
 * SB-Tech — Inventory / Assets operations (save / delete).
 */
$db = Database::instance();
$action = (string) ($_POST['action'] ?? '');
$back = pageUrl('inventory', 'assets');

try {
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $assetTag = trim((string) ($_POST['asset_tag'] ?? ''));
        if ($assetTag === '') {
            $assetTag = inventoryNextAssetTag();
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            setFlash('error', 'Asset name is required.');
            redirect($back);
        }

        $data = [
            'asset_tag'         => $assetTag,
            'item_id'           => (int) ($_POST['item_id'] ?? 0) ?: null,
            'name'              => $name,
            'serial_number'     => trim((string) ($_POST['serial_number'] ?? '')) ?: null,
            'brand'             => trim((string) ($_POST['brand'] ?? '')) ?: null,
            'model'             => trim((string) ($_POST['model'] ?? '')) ?: null,
            'purchase_date'     => trim((string) ($_POST['purchase_date'] ?? '')) ?: null,
            'purchase_price'    => round((float) ($_POST['purchase_price'] ?? 0), 4),
            'warranty_expiry'   => trim((string) ($_POST['warranty_expiry'] ?? '')) ?: null,
            'condition_status'  => (string) ($_POST['condition_status'] ?? 'New'),
            'current_status'    => (string) ($_POST['current_status'] ?? 'In Stock'),
            'assigned_to'       => (int) ($_POST['assigned_to'] ?? 0) ?: null,
            'assigned_on'       => ((int) ($_POST['assigned_to'] ?? 0)) ? date('Y-m-d') : null,
            'location'          => trim((string) ($_POST['location'] ?? '')) ?: null,
            'notes'             => trim((string) ($_POST['notes'] ?? '')) ?: null,
            'updated_by'        => Auth::id(),
        ];

        if ($id) {
            $old = $db->selectOne('SELECT * FROM `tbl_inv_assets` WHERE `id` = ?', [$id]);
            $db->update('tbl_inv_assets', $data, '`id` = ?', [$id]);

            // Log assignment change.
            if ($old && (int) $old['assigned_to'] !== (int) $data['assigned_to']) {
                $actionType = $data['assigned_to'] ? 'Assigned' : 'Returned';
                $oldName = $old['assigned_to'] ? ($db->selectOne('SELECT fullname FROM `tbl_users_login` WHERE id = ?', [(int) $old['assigned_to']])['fullname'] ?? 'Unknown') : 'Unassigned';
                $newName = $data['assigned_to'] ? ($db->selectOne('SELECT fullname FROM `tbl_users_login` WHERE id = ?', [(int) $data['assigned_to']])['fullname'] ?? 'Unknown') : 'Unassigned';
                $db->insert('tbl_inv_asset_logs', [
                    'asset_id' => $id, 'action' => $actionType,
                    'old_value' => $oldName, 'new_value' => $newName,
                    'performed_by' => Auth::id(),
                ]);
            }
            // Log condition change.
            if ($old && $old['condition_status'] !== $data['condition_status']) {
                $db->insert('tbl_inv_asset_logs', [
                    'asset_id' => $id, 'action' => 'Condition Change',
                    'old_value' => $old['condition_status'], 'new_value' => $data['condition_status'],
                    'performed_by' => Auth::id(),
                ]);
            }
            // Log status change.
            if ($old && $old['current_status'] !== $data['current_status']) {
                $db->insert('tbl_inv_asset_logs', [
                    'asset_id' => $id, 'action' => 'Status Change',
                    'old_value' => $old['current_status'], 'new_value' => $data['current_status'],
                    'performed_by' => Auth::id(),
                ]);
            }
            setFlash('success', 'Asset updated.');
        } else {
            $data['added_by'] = Auth::id();
            $newId = $db->insert('tbl_inv_assets', $data);
            $db->insert('tbl_inv_asset_logs', [
                'asset_id' => $newId, 'action' => 'Note',
                'new_value' => 'Asset created', 'performed_by' => Auth::id(),
            ]);
            setFlash('success', 'Asset created: ' . $assetTag);
        }
        redirect($back);
    }

    setFlash('error', 'Unknown action.');
    redirect($back);
} catch (Throwable $e) {
    setFlash('error', 'Asset operation failed: ' . $e->getMessage());
    redirect($back);
}
