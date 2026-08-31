<?php
/**
 * SB-Tech — departments CRUD (US-SET-02).
 * Included by admin/operation.php (CSRF + permission already verified).
 */
$db = Database::instance();
$action = $_POST['action'] ?? 'save';
$back = pageUrl('office_setup', 'departments');

if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    $count = (int) ($db->selectOne('SELECT COUNT(*) AS c FROM `tbl_users_login` WHERE `department_id` = ?', [$id])['c'] ?? 0);
    if ($count > 0) {
        setFlash('error', 'Cannot delete: staff are assigned to this department.');
        redirect($back);
    }
    $db->delete('tbl_office_departments', '`id` = ?', [$id]);
    setFlash('success', 'Department deleted.');
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
        $db->update('tbl_office_departments', ['title' => $title, 'position' => $position, 'updated_by' => Auth::id()], '`id` = ?', [$id]);
        setFlash('success', 'Department updated.');
    } else {
        $db->insert('tbl_office_departments', ['title' => $title, 'position' => $position, 'added_by' => Auth::id()]);
        setFlash('success', 'Department added.');
    }
} catch (Throwable $e) {
    setFlash('error', 'Could not save: ' . (str_contains($e->getMessage(), 'Duplicate') ? 'A department with this title already exists.' : $e->getMessage()));
}
redirect($back);
