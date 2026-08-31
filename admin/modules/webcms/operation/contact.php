<?php
/**
 * SB-Tech — Website CMS / Contact operations.
 * Inquiry (tbl_cms_contacts_us) and inbox message (tbl_cms_messages)
 * status updates.
 */
$db = Database::instance();
$action = (string) ($_POST['action'] ?? '');
$back = 'show_page.php?module=webcms&page=contact';

try {
    if ($action === 'update_inquiry') {
        $id = (int) ($_POST['id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');
        if (!in_array($status, ['New', 'Read', 'Converted'], true)) {
            setFlash('error', 'Invalid inquiry status.');
            redirect($back);
        }
        $db->update('tbl_cms_contacts_us', ['status' => $status], '`id` = ?', [$id]);
        setFlash('success', 'Inquiry status updated to ' . e($status) . '.');
        redirect($back);
    }
    if ($action === 'update_message') {
        $id = (int) ($_POST['id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');
        if (!in_array($status, ['New', 'Read', 'Replied'], true)) {
            setFlash('error', 'Invalid message status.');
            redirect($back);
        }
        $db->update('tbl_cms_messages', ['status' => $status], '`id` = ?', [$id]);
        setFlash('success', 'Message status updated to ' . e($status) . '.');
        redirect($back);
    }
    setFlash('error', 'Unknown contact action.');
    redirect($back);
} catch (Throwable $e) {
    setFlash('error', 'Contact operation failed: ' . $e->getMessage());
    redirect($back);
}
