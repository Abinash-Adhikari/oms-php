<?php
/**
 * SB-Tech — Website CMS / Careers operations.
 * Section CRUD is handled by the shared dispatcher; application status
 * updates (New → Shortlisted → Interview → Offer → Rejected) here.
 */
require_once __DIR__ . '/../includes/cms_config.php';
$db = Database::instance();
$me = (int) Auth::id();
$action = (string) ($_POST['action'] ?? '');
$back = 'show_page.php?module=webcms&page=careers';

if ($action === 'update_career_app') {
    $id = (int) ($_POST['id'] ?? 0);
    $status = (string) ($_POST['status'] ?? '');
    if (!in_array($status, ['New', 'Shortlisted', 'Interview', 'Offer', 'Rejected'], true)) {
        setFlash('error', 'Invalid application status.');
        redirect($back);
    }
    $db->update('tbl_cms_career_applications', ['status' => $status], '`id` = ?', [$id]);
    setFlash('success', 'Application status updated to ' . e($status) . '.');
    redirect($back);
}

// Fall through to the generic section CRUD for the 'career' section.
$_GET['page'] = 'careers';
include __DIR__ . '/_dispatch.php';
