<?php
/**
 * SB-Tech — Inventory / Categories operations (save / delete).
 */
$db = Database::instance();
$action = (string) ($_POST['action'] ?? '');
$back = pageUrl('inventory', 'categories');

try {
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $parentId = (int) ($_POST['parent_id'] ?? 0) ?: null;
        $position = (int) ($_POST['position'] ?? 0);
        $isActive = (int) ($_POST['is_active'] ?? 1);
        $description = trim((string) ($_POST['description'] ?? ''));

        if ($title === '') {
            setFlash('error', 'Title is required.');
            redirect($back);
        }
        if ($parentId === $id && $id > 0) {
            setFlash('error', 'A category cannot be its own parent.');
            redirect($back);
        }

        $data = [
            'title'       => $title,
            'parent_id'   => $parentId,
            'position'    => $position,
            'is_active'   => $isActive ? 1 : 0,
            'description' => $description ?: null,
            'updated_by'  => Auth::id(),
        ];

        if ($id) {
            $db->update('tbl_inv_categories', $data, '`id` = ?', [$id]);
            setFlash('success', 'Category updated.');
        } else {
            $data['added_by'] = Auth::id();
            $db->insert('tbl_inv_categories', $data);
            setFlash('success', 'Category created.');
        }
        redirect($back);
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $itemCount = (int) ($db->selectOne('SELECT COUNT(*) AS c FROM `tbl_inv_items` WHERE `category_id` = ?', [$id])['c'] ?? 0);
        if ($itemCount > 0) {
            setFlash('error', 'Cannot delete: items exist in this category.');
            redirect($back);
        }
        $db->delete('tbl_inv_categories', '`id` = ?', [$id]);
        setFlash('success', 'Category deleted.');
        redirect($back);
    }

    setFlash('error', 'Unknown action.');
    redirect($back);
} catch (Throwable $e) {
    setFlash('error', 'Category operation failed: ' . $e->getMessage());
    redirect($back);
}
