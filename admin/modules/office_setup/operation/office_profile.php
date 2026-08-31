<?php
/**
 * SB-Tech — save the office profile (US-SET-01).
 * Included by admin/operation.php (CSRF + permission already verified).
 */
$db = Database::instance();
$id = (int) ($_POST['id'] ?? 1);

$data = [
    'name'            => trim((string) ($_POST['name'] ?? '')),
    'accronym'        => trim((string) ($_POST['accronym'] ?? '')),
    'address1'        => trim((string) ($_POST['address1'] ?? '')),
    'address2'        => trim((string) ($_POST['address2'] ?? '')),
    'email'           => trim((string) ($_POST['email'] ?? '')),
    'phone1'          => trim((string) ($_POST['phone1'] ?? '')),
    'phone2'          => trim((string) ($_POST['phone2'] ?? '')),
    'vat_no'          => trim((string) ($_POST['vat_no'] ?? '')),
    'website'         => trim((string) ($_POST['website'] ?? '')),
    'slogan'          => trim((string) ($_POST['slogan'] ?? '')),
    'estd'            => trim((string) ($_POST['estd'] ?? '')),
    'use_date'        => ($_POST['use_date'] ?? 'AD') === 'BS' ? 'BS' : 'AD',
    'leave_year_mode' => ($_POST['leave_year_mode'] ?? 'AD') === 'BS' ? 'BS' : 'AD',
    'backup_email'    => trim((string) ($_POST['backup_email'] ?? '')),
    'allow_ips'       => trim((string) ($_POST['allow_ips'] ?? 'All')),
    'updated_by'      => Auth::id(),
];

if ($data['name'] === '') {
    setFlash('error', 'Office name is required.');
    redirect(pageUrl('office_setup', 'office_profile'));
}

// Logo upload (X-03 whitelist).
if (!empty($_FILES['logo']['name'])) {
    $check = validateUpload($_FILES['logo'], ['jpg', 'jpeg', 'png']);
    if (!$check['ok']) {
        setFlash('error', $check['message']);
        redirect(pageUrl('office_setup', 'office_profile'));
    }
    $stored = storeUpload($_FILES['logo'], 'office_setup', $check['extension']);
    if (!$stored) {
        setFlash('error', 'Logo upload failed.');
        redirect(pageUrl('office_setup', 'office_profile'));
    }
    // Remove previous logo file(s): current path, plus any legacy
    // `<id>.<extension>` file left by older versions of this handler.
    $old = $db->selectOne('SELECT `logo`, `logo_extension` FROM `tbl_office_profiles` WHERE `id` = ?', [$id]);
    $newAbs = dirname(__DIR__, 4) . '/user_uploads/' . ltrim($stored, '/');
    $candidates = [];
    if (!empty($old['logo'])) {
        $candidates[] = dirname(__DIR__, 4) . '/user_uploads/' . ltrim((string) $old['logo'], '/');
    }
    if (!empty($old['logo_extension'])) {
        $candidates[] = dirname(__DIR__, 4) . '/user_uploads/office_setup/' . $id . $old['logo_extension'];
    }
    foreach (array_unique($candidates) as $oldFile) {
        if (is_file($oldFile) && realpath($oldFile) !== realpath($newAbs)) {
            @unlink($oldFile);
        }
    }
    $data['logo'] = $stored;
    $data['logo_extension'] = $check['extension'];
}

try {
    $db->update('tbl_office_profiles', $data, '`id` = ?', [$id]);
    setFlash('success', 'Office profile updated.');
} catch (Throwable $e) {
    setFlash('error', 'Could not save profile: ' . $e->getMessage());
}
redirect(pageUrl('office_setup', 'office_profile'));
