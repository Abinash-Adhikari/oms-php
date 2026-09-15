<?php
/**
 * SB-Tech — My Office / Warnings operations.
 * save / delete
 */
$db = Database::instance();
$action = (string) ($_POST['action'] ?? '');
$back = pageUrl('my_office', 'warnings');

try {
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $staffId = (int) ($_POST['staff_id'] ?? 0);
        $warningType = (string) ($_POST['warning_type'] ?? 'Written');
        $title = trim((string) ($_POST['title'] ?? ''));
        $issuedOn = trim((string) ($_POST['issued_on'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $isActive = (int) ($_POST['is_active'] ?? 1);

        if (!in_array($warningType, ['Verbal', 'Written', 'Final'], true)) {
            setFlash('error', 'Invalid warning type.');
            redirect($back);
        }
        if ($staffId <= 0) {
            setFlash('error', 'Staff is required.');
            redirect($back);
        }
        if ($title === '') {
            setFlash('error', 'Title is required.');
            redirect($back);
        }
        if ($issuedOn !== '' && !strtotime($issuedOn)) {
            setFlash('error', 'Invalid issued date.');
            redirect($back);
        }

        $data = [
            'staff_id'     => $staffId,
            'warning_type' => $warningType,
            'title'        => $title,
            'issued_on'    => $issuedOn !== '' ? $issuedOn : null,
            'description'  => $description ?: null,
            'is_active'    => $isActive ? 1 : 0,
            'updated_by'   => Auth::id(),
        ];

        if ($id) {
            $db->update('tbl_staff_warnings', $data, '`id` = ?', [$id]);
            setFlash('success', 'Warning updated.');
        } else {
            $data['added_by'] = Auth::id();
            $db->insert('tbl_staff_warnings', $data);
            setFlash('success', 'Warning issued.');
        }
        redirect($back);
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $db->delete('tbl_staff_warnings', '`id` = ?', [$id]);
        setFlash('success', 'Warning deleted.');
        redirect($back);
    }

    setFlash('error', 'Unknown action.');
    redirect($back);
} catch (Throwable $e) {
    setFlash('error', 'Warning operation failed: ' . $e->getMessage());
    redirect($back);
}