<?php
/**
 * SB-Tech — Communication / Templates operations.
 * save / delete
 */
$db = Database::instance();
$action = (string) ($_POST['action'] ?? '');
$back = pageUrl('communication', 'templates');

try {
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $type = (string) ($_POST['type'] ?? 'Email');
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));
        $smsBody = trim((string) ($_POST['sms_body'] ?? ''));
        $placeholders = trim((string) ($_POST['placeholders'] ?? ''));
        $isActive = (int) ($_POST['is_active'] ?? 1);

        if ($name === '' || $body === '') {
            setFlash('error', 'Name and body are required.');
            redirect($back);
        }
        if (!in_array($type, ['Email', 'SMS'], true)) {
            $type = 'Email';
        }

        $data = [
            'name'        => $name,
            'type'        => $type,
            'subject'     => $subject ?: null,
            'body'        => $body,
            'sms_body'    => $smsBody ?: null,
            'placeholders'=> $placeholders ?: null,
            'is_active'   => $isActive ? 1 : 0,
            'updated_by'  => Auth::id(),
        ];

        if ($id) {
            $db->update('tbl_communication_templates', $data, '`id` = ?', [$id]);
            setFlash('success', 'Template updated.');
        } else {
            $data['added_by'] = Auth::id();
            $db->insert('tbl_communication_templates', $data);
            setFlash('success', 'Template created.');
        }
        redirect($back);
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $db->delete('tbl_communication_templates', '`id` = ?', [$id]);
        setFlash('success', 'Template deleted.');
        redirect($back);
    }

    setFlash('error', 'Unknown action.');
    redirect($back);
} catch (Throwable $e) {
    setFlash('error', 'Template operation failed: ' . $e->getMessage());
    redirect($back);
}
