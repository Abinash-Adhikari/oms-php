<?php
/**
 * SB-Tech — Inventory / Suppliers operations (save / delete).
 */
$db = Database::instance();
$action = (string) ($_POST['action'] ?? '');
$back = pageUrl('inventory', 'suppliers');

try {
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            setFlash('error', 'Supplier name is required.');
            redirect($back);
        }
        $data = [
            'name'            => $name,
            'contact_person'  => trim((string) ($_POST['contact_person'] ?? '')) ?: null,
            'email'           => trim((string) ($_POST['email'] ?? '')) ?: null,
            'phone'           => trim((string) ($_POST['phone'] ?? '')) ?: null,
            'address'         => trim((string) ($_POST['address'] ?? '')) ?: null,
            'pan_num'         => trim((string) ($_POST['pan_num'] ?? '')) ?: null,
            'bank_name'       => trim((string) ($_POST['bank_name'] ?? '')) ?: null,
            'bank_account_num'=> trim((string) ($_POST['bank_account_num'] ?? '')) ?: null,
            'notes'           => trim((string) ($_POST['notes'] ?? '')) ?: null,
            'is_active'       => (int) ($_POST['is_active'] ?? 1) ? 1 : 0,
            'updated_by'      => Auth::id(),
        ];
        if ($id) {
            $db->update('tbl_inv_suppliers', $data, '`id` = ?', [$id]);
            setFlash('success', 'Supplier updated.');
        } else {
            $data['added_by'] = Auth::id();
            $db->insert('tbl_inv_suppliers', $data);
            setFlash('success', 'Supplier created.');
        }
        redirect($back);
    }
    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $db->delete('tbl_inv_suppliers', '`id` = ?', [$id]);
        setFlash('success', 'Supplier deleted.');
        redirect($back);
    }
    setFlash('error', 'Unknown action.');
    redirect($back);
} catch (Throwable $e) {
    setFlash('error', 'Supplier operation failed: ' . $e->getMessage());
    redirect($back);
}
