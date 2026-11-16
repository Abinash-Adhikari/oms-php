<?php
/**
 * SB-Tech — Dashboard operations: self check-in / check-out.
 *
 * Lets any logged-in staff stamp their own attendance from the dashboard
 * without needing the staff_management → hr_care grant. Only `checkin` and
 * `checkout` are accepted, always for the current user (proxy `for_user` is
 * stripped). Delegates to the shared HR Care attendance handler and
 * redirects back to the dashboard.
 */
$action = (string) ($_POST['action'] ?? '');
if (!in_array($action, ['checkin', 'checkout'], true)) {
    setFlash('error', 'Unknown dashboard action.');
    redirect('show_page.php?module=dashboard');
}
unset($_POST['for_user']);
$attendanceBack = 'show_page.php?module=dashboard';
include dirname(__DIR__, 2) . '/my_office/operation/attendance.php';
