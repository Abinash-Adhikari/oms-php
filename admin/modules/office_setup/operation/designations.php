<?php
/**
 * SB-Tech — designations CRUD (US-SET-02).
 * Included by admin/operation.php (CSRF + permission already verified).
 */
$db = Database::instance();
$action = $_POST['action'] ?? 'save';
$back = pageUrl('office_setup', 'designations');

if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    $count = (int) ($db->selectOne('SELECT COUNT(*) AS c FROM `tbl_users_login` WHERE `designation_id` = ?', [$id])['c'] ?? 0);
    if ($count > 0) {
        setFlash('error', 'Cannot delete: staff hold this designation.');
        redirect($back);
    }
    $db->delete('tbl_office_designation', '`id` = ?', [$id]);
    setFlash('success', 'Designation deleted.');
    redirect($back);
}

$id = (int) ($_POST['id'] ?? 0);
$title = trim((string) ($_POST['title'] ?? ''));
$position = (int) ($_POST['position'] ?? 0);
if ($title === '') {
    setFlash('error', 'Title is required.');
    redirect($back);
}
try {
    if ($id > 0) {
        $db->update('tbl_office_designation', ['title' => $title, 'position' => $position, 'updated_by' => Auth::id()], '`id` = ?', [$id]);
        setFlash('success', 'Designation updated.');
    } else {
        $db->insert('tbl_office_designation', ['title' => $title, 'position' => $position, 'added_by' => Auth::id()]);
        setFlash('success', 'Designation added.');
    }
} catch (Throwable $e) {
    setFlash('error', 'Could not save: ' . (str_contains($e->getMessage(), 'Duplicate') ? 'A designation with this title already exists.' : $e->getMessage()));
}
redirect($back);
