<?php
/**
 * SB-Tech — Communication / Send test email operation.
 * Sends a test email to the current user to verify SMTP configuration.
 */
$db = Database::instance();
$back = pageUrl('communication', 'email_sms');

try {
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

    $orgName = config('organization_name', 'SB-Tech');
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
        // Log the test email.
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
} catch (Throwable $e) {
    setFlash('error', 'Test email failed: ' . $e->getMessage());
    redirect($back);
}
