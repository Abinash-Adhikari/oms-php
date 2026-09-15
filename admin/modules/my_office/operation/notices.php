<?php
/**
 * SB-Tech — My Office / Notices operations.
 * save / delete
 */
$db = Database::instance();
$action = (string) ($_POST['action'] ?? '');
$back = pageUrl('my_office', 'notices');

try {
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $isActive = (int) ($_POST['is_active'] ?? 1);

        if ($title === '') {
            setFlash('error', 'Title is required.');
            redirect($back);
        }

        $data = [
            'title'       => $title,
            'description' => $description ?: null,
            'is_active'   => $isActive ? 1 : 0,
            'updated_by'  => Auth::id(),
        ];

        if ($id) {
            $db->update('tbl_office_notices', $data, '`id` = ?', [$id]);
            setFlash('success', 'Notice updated.');
        } else {
            $data['added_by'] = Auth::id();
            $db->insert('tbl_office_notices', $data);
            setFlash('success', 'Notice published.');
        }
        redirect($back);
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $db->delete('tbl_office_notices', '`id` = ?', [$id]);
        setFlash('success', 'Notice deleted.');
        redirect($back);
    }

    setFlash('error', 'Unknown action.');
    redirect($back);
} catch (Throwable $e) {
    setFlash('error', 'Notice operation failed: ' . $e->getMessage());
    redirect($back);
}