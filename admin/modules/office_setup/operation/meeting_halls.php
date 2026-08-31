<?php
/**
 * SB-Tech — meeting halls CRUD (US-SET-04.2).
 * Included by admin/operation.php (CSRF + permission already verified).
 */
$db = Database::instance();
$action = $_POST['action'] ?? 'save';
$back = pageUrl('office_setup', 'meeting_halls');

if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    $db->delete('tbl_office_meeting_hall_setup', '`id` = ?', [$id]);
    setFlash('success', 'Meeting hall deleted.');
    redirect($back);
}

$id = (int) ($_POST['id'] ?? 0);
$hallName = trim((string) ($_POST['hall_name'] ?? ''));
$occupancy = ($_POST['occupancy'] ?? '') !== '' ? (int) $_POST['occupancy'] : null;
if ($hallName === '') {
    setFlash('error', 'Hall name is required.');
    redirect($back);
}
try {
    if ($id > 0) {
        $db->update('tbl_office_meeting_hall_setup', ['hall_name' => $hallName, 'occupancy' => $occupancy, 'updated_by' => Auth::id()], '`id` = ?', [$id]);
        setFlash('success', 'Meeting hall updated.');
    } else {
        $db->insert('tbl_office_meeting_hall_setup', ['hall_name' => $hallName, 'occupancy' => $occupancy, 'added_by' => Auth::id()]);
        setFlash('success', 'Meeting hall added.');
    }
} catch (Throwable $e) {
    setFlash('error', 'Could not save meeting hall: ' . $e->getMessage());
}
redirect($back);
