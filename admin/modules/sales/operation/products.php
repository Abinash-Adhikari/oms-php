<?php
/**
 * SB-Tech — Products (catalog) operations (US-PJ).
 *   save_project / delete_project
 * Operates on tbl_projects (the catalog). Won deployments live separately in
 * tbl_client_projects; deleting a catalog row clears the lead link (SET NULL).
 */
$db = Database::instance();
$me = (int) Auth::id();
if (!(Auth::isSuperAdmin() || Auth::hasSpecial('manage_leads'))) {
    http_response_code(403);
    die('Access denied: you need the manage_leads permission.');
}
$action = (string) ($_POST['action'] ?? '');
$back = 'show_page.php?module=sales&page=products';

try {
    if ($action === 'save_project') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            setFlash('error', 'Product name is required.');
            redirect($back);
        }
        $status = (string) ($_POST['status'] ?? 'Active');
        if (!in_array($status, ['Active', 'Inactive'], true)) {
            $status = 'Active';
        }
        $data = [
            'name'        => $name,
            'code'        => trim((string) ($_POST['code'] ?? '')) ?: null,
            'category'    => trim((string) ($_POST['category'] ?? '')) ?: null,
            'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
            'status'      => $status,
            'updated_by'  => $me,
        ];
        if ($id) {
            $existing = $db->selectOne('SELECT id FROM `tbl_projects` WHERE `id` = ?', [$id]);
            if (!$existing) {
                setFlash('error', 'Product not found.');
                redirect($back);
            }
            $db->update('tbl_projects', $data, '`id` = ?', [$id]);
            setFlash('success', 'Product updated.');
        } else {
            $data['added_by'] = $me;
            $id = $db->insert('tbl_projects', $data);
            setFlash('success', 'Product added.');
        }
        redirect($back);
    }

    if ($action === 'delete_project') {
        $id = (int) ($_POST['id'] ?? 0);
        $db->delete('tbl_projects', '`id` = ?', [$id]); // lead + client project links SET NULL
        setFlash('success', 'Product deleted.');
        redirect($back);
    }

    setFlash('error', 'Unknown product action.');
    redirect($back);
} catch (Throwable $e) {
    setFlash('error', 'Product operation failed: ' . $e->getMessage());
    redirect($back);
}