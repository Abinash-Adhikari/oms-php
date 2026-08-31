<?php
/**
 * SB-Tech — bank details CRUD (US-SET-04).
 * Included by admin/operation.php (CSRF + permission already verified).
 */
$db = Database::instance();
$action = $_POST['action'] ?? 'save';
$back = pageUrl('office_setup', 'bank_details');

if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    $db->delete('tbl_office_bank_details', '`id` = ?', [$id]);
    setFlash('success', 'Bank detail deleted.');
    redirect($back);
}

$id = (int) ($_POST['id'] ?? 0);
$data = [
    'bank_name'      => trim((string) ($_POST['bank_name'] ?? '')),
    'account_name'   => trim((string) ($_POST['account_name'] ?? '')),
    'branch'         => trim((string) ($_POST['branch'] ?? '')),
    'account_number' => trim((string) ($_POST['account_number'] ?? '')),
    'account_type'   => trim((string) ($_POST['account_type'] ?? '')),
    'swift_code'     => trim((string) ($_POST['swift_code'] ?? '')),
    'other_detail'   => trim((string) ($_POST['other_detail'] ?? '')),
];
if ($data['bank_name'] === '' || $data['account_name'] === '' || $data['branch'] === '' || $data['account_number'] === '' || $data['account_type'] === '') {
    setFlash('error', 'Bank name, account name, branch, account number and account type are required.');
    redirect($back);
}
try {
    if ($id > 0) {
        $data['updated_by'] = Auth::id();
        $db->update('tbl_office_bank_details', $data, '`id` = ?', [$id]);
        setFlash('success', 'Bank detail updated.');
    } else {
        $data['added_by'] = Auth::id();
        $db->insert('tbl_office_bank_details', $data);
        setFlash('success', 'Bank detail added.');
    }
} catch (Throwable $e) {
    setFlash('error', 'Could not save bank detail: ' . $e->getMessage());
}
redirect($back);
