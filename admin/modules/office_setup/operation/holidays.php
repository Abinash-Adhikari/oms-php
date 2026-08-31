<?php
/**
 * SB-Tech — holidays CRUD (US-SET-03).
 * Included by admin/operation.php (CSRF + permission already verified).
 */
$db = Database::instance();
$action = $_POST['action'] ?? 'save';
$back = pageUrl('office_setup', 'holidays');

if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    $db->delete('tbl_office_holidays', '`id` = ?', [$id]);
    setFlash('success', 'Holiday deleted.');
    redirect($back);
}

$id = (int) ($_POST['id'] ?? 0);
$title = trim((string) ($_POST['title'] ?? ''));
$fromDate = trim((string) ($_POST['from_date'] ?? ''));
$toDate = trim((string) ($_POST['to_date'] ?? ''));
$departmentId = ($_POST['department_id'] ?? '') !== '' ? (int) $_POST['department_id'] : null;
$genderTo = in_array($_POST['gender_to'] ?? '', ['Male', 'Female', 'Both'], true) ? $_POST['gender_to'] : 'Both';
$remarks = trim((string) ($_POST['remarks'] ?? ''));

if ($title === '' || $fromDate === '' || $toDate === '') {
    setFlash('error', 'Title, from date and to date are required.');
    redirect($back);
}
if (strtotime($toDate) < strtotime($fromDate)) {
    setFlash('error', 'To date cannot be before from date.');
    redirect($back);
}
$data = [
    'title'         => $title,
    'from_date'     => $fromDate,
    'to_date'       => $toDate,
    'department_id' => $departmentId,
    'gender_to'     => $genderTo,
    'remarks'       => $remarks,
];
try {
    if ($id > 0) {
        $data['updated_by'] = Auth::id();
        $db->update('tbl_office_holidays', $data, '`id` = ?', [$id]);
        setFlash('success', 'Holiday updated.');
    } else {
        $data['added_by'] = Auth::id();
        $db->insert('tbl_office_holidays', $data);
        setFlash('success', 'Holiday added.');
    }
} catch (Throwable $e) {
    setFlash('error', 'Could not save holiday: ' . $e->getMessage());
}
redirect($back);
