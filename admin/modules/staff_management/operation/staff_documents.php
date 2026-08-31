<?php
/**
 * SB-Tech — staff documents add/delete (US-STF-02).
 * Included by admin/operation.php (CSRF + permission already verified).
 */
$db = Database::instance();
$action = $_POST['action'] ?? 'add';
$staffId = (int) ($_POST['staff_id'] ?? 0);
$back = pageUrl('staff_management', 'add_staff') . '&id=' . $staffId;

if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    $doc = $db->selectOne('SELECT * FROM `tbl_staff_documents` WHERE `id` = ? AND `staff_id` = ?', [$id, $staffId]);
    if ($doc) {
        $file = dirname(__DIR__, 4) . '/user_uploads/' . $doc['file_path'];
        if (is_file($file)) {
            @unlink($file);
        }
        $db->delete('tbl_staff_documents', '`id` = ?', [$id]);
        setFlash('success', 'Document deleted.');
    }
    redirect($back);
}

$title = trim((string) ($_POST['title'] ?? ''));
$type = trim((string) ($_POST['document_type'] ?? ''));
if (empty($_FILES['document_file']['name'])) {
    setFlash('error', 'Choose a file to upload.');
    redirect($back);
}
$check = validateUpload($_FILES['document_file']);
if (!$check['ok']) {
    setFlash('error', $check['message']);
    redirect($back);
}
$stored = storeUpload($_FILES['document_file'], 'staff_documents', $check['extension']);
if (!$stored) {
    setFlash('error', 'Upload failed.');
    redirect($back);
}
$db->insert('tbl_staff_documents', [
    'title'         => $title !== '' ? $title : pathinfo($_FILES['document_file']['name'], PATHINFO_FILENAME),
    'staff_id'      => $staffId,
    'document_type' => $type !== '' ? $type : 'Other',
    'document_name' => $_FILES['document_file']['name'],
    'size'          => (string) round($_FILES['document_file']['size'] / 1024, 1) . ' KB',
    'file_path'     => $stored,
    'added_by'      => Auth::id(),
]);
setFlash('success', 'Document uploaded.');
redirect($back);
