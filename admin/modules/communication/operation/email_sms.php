<?php
/**
 * SB-Tech — Communication / Email & SMS settings operations.
 * save_email_settings / save_sms_settings
 */
$db = Database::instance();
$action = (string) ($_POST['action'] ?? '');
$back = pageUrl('communication', 'email_sms');

try {
    if ($action === 'save_email_settings') {
        $existing = $db->selectOne('SELECT * FROM `tbl_communication_settings` WHERE `is_active` = 1 ORDER BY `id` DESC LIMIT 1');

        $data = [
            'smtp_host'        => trim((string) ($_POST['smtp_host'] ?? '')),
            'smtp_port'        => (int) ($_POST['smtp_port'] ?? 587),
            'smtp_username'    => trim((string) ($_POST['smtp_username'] ?? '')),
            'smtp_from_name'   => trim((string) ($_POST['smtp_from_name'] ?? '')),
            'smtp_from_email'  => trim((string) ($_POST['smtp_from_email'] ?? '')),
            'is_active'        => 1,
            'updated_by'       => Auth::id(),
        ];

        // Only encrypt and store password if a new one is provided.
        $password = (string) ($_POST['smtp_password'] ?? '');
        if ($password !== '') {
            $data['smtp_password_enc'] = CommunicationService::encryptSetting($password);
        }

        if ($existing) {
            $db->update('tbl_communication_settings', $data, '`id` = ?', [(int) $existing['id']]);
        } else {
            $data['added_by'] = Auth::id();
            $db->insert('tbl_communication_settings', $data);
        }

        setFlash('success', 'Email settings saved.');
        redirect($back);
    }

    if ($action === 'save_sms_settings') {
        $existing = $db->selectOne('SELECT * FROM `tbl_communication_settings` WHERE `is_active` = 1 ORDER BY `id` DESC LIMIT 1');

        $data = [
            'sms_provider'  => trim((string) ($_POST['sms_provider'] ?? '')),
            'sms_sender_id' => trim((string) ($_POST['sms_sender_id'] ?? '')),
            'is_active'     => 1,
            'updated_by'    => Auth::id(),
        ];

        $apiKey = (string) ($_POST['sms_api_key'] ?? '');
        if ($apiKey !== '') {
            $data['sms_api_key_enc'] = CommunicationService::encryptSetting($apiKey);
        }

        if ($existing) {
            $db->update('tbl_communication_settings', $data, '`id` = ?', [(int) $existing['id']]);
        } else {
            $data['added_by'] = Auth::id();
            $db->insert('tbl_communication_settings', $data);
        }

        setFlash('success', 'SMS settings saved.');
        redirect($back);
    }

    setFlash('error', 'Unknown action.');
    redirect($back);
} catch (Throwable $e) {
    setFlash('error', 'Settings save failed: ' . $e->getMessage());
    redirect($back);
}
