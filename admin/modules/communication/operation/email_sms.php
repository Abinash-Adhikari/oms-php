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

    if ($action === 'send_test_email') {
        $user = Auth::user();
        if (!$user || empty($user['email'])) {
            setFlash('error', 'Your account has no email address. Please update your profile first.');
            redirect($back);
        }

        $settings = $db->selectOne('SELECT * FROM `tbl_communication_settings` WHERE `is_active` = 1 ORDER BY `id` DESC LIMIT 1');
        if (!$settings || empty($settings['smtp_host']) || empty($settings['smtp_from_email'])) {
            setFlash('error', 'SMTP settings are not configured. Please save your email settings first.');
            redirect($back);
        }
        if (empty($settings['smtp_password_enc'])) {
            setFlash('error', 'SMTP password is not set. Please enter your SMTP password/API key and save first.');
            redirect($back);
        }

        $orgName = office_display_name();
        $subject = e($orgName) . ' — SMTP Test Email';
        $body = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">'
            . '<div style="background: #007bff; color: white; padding: 20px; text-align: center;">'
            . '<h2 style="margin: 0;">' . e($orgName) . ' Notification Test</h2>'
            . '</div>'
            . '<div style="padding: 20px; border: 1px solid #ddd; border-top: none;">'
            . '<p>Hello <strong>' . e($user['fullname']) . '</strong>,</p>'
            . '<p>This is a <strong>test email</strong> from the ' . e($orgName) . ' Office Management System.</p>'
            . '<p>If you received this email, your SMTP configuration is working correctly!</p>'
            . '<hr style="border: none; border-top: 1px solid #eee;">'
            . '<p style="color: #666; font-size: 12px;">'
            . 'Sent from: ' . e($settings['smtp_from_name'] ?? $orgName) . '<br>'
            . 'SMTP Host: ' . e($settings['smtp_host']) . ':' . e($settings['smtp_port']) . '<br>'
            . 'Date: ' . date('Y-m-d H:i:s')
            . '</p>'
            . '</div>'
            . '</div>';

        $result = CommunicationService::sendEmail($user['email'], $subject, $body, true);

        if ($result['ok']) {
            $db->insert('tbl_communication_logs', [
                'type'       => 'Email',
                'recipient'  => $user['email'],
                'subject'    => 'SMTP Test Email',
                'status'     => 'Sent',
                'sent_on'    => date('Y-m-d H:i:s'),
                'added_by'   => Auth::id(),
            ]);
            setFlash('success', 'Test email sent successfully to ' . $user['email'] . '. Check your inbox (and spam folder).');
        } else {
            $db->insert('tbl_communication_logs', [
                'type'         => 'Email',
                'recipient'    => $user['email'],
                'subject'      => 'SMTP Test Email',
                'status'       => 'Failed',
                'error_message'=> $result['message'],
                'added_by'     => Auth::id(),
            ]);
            setFlash('error', 'Test email failed: ' . $result['message'] . '. Please check your SMTP settings.');
        }

        redirect($back);
    }

    setFlash('error', 'Unknown action.');
    redirect($back);
} catch (Throwable $e) {
    setFlash('error', 'Settings save failed: ' . $e->getMessage());
    redirect($back);
}
