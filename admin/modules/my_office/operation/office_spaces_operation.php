<?php
/**
 * SB-Tech — My Office / Office Spaces operations.
 * save / delete
 */
$db = Database::instance();
$action = (string) ($_POST['action'] ?? '');
$back = pageUrl('my_office', 'office_spaces');

try {
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $capacity = (int) ($_POST['capacity'] ?? 0);
        $isActive = (int) ($_POST['is_active'] ?? 1);

        if ($title === '') {
            setFlash('error', 'Title is required.');
            redirect($back);
        }

        $data = [
            'title'       => $title,
            'description' => $description ?: null,
            'capacity'    => $capacity ?: null,
            'is_active'   => $isActive ? 1 : 0,
            'updated_by'  => Auth::id(),
        ];

        if ($id) {
            $db->update('tbl_office_spaces', $data, '`id` = ?', [$id]);
            setFlash('success', 'Space updated.');
        } else {
            $data['added_by'] = Auth::id();
            $db->insert('tbl_office_spaces', $data);
            setFlash('success', 'Space created.');
        }
        redirect($back);
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $db->delete('tbl_office_spaces', '`id` = ?', [$id]);
        setFlash('success', 'Space deleted.');
        redirect($back);
    }

    setFlash('error', 'Unknown action.');
    redirect($back);
} catch (Throwable $e) {
    setFlash('error', 'Space operation failed: ' . $e->getMessage());
    redirect($back);
}
