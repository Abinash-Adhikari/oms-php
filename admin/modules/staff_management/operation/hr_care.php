<?php
/**
 * SB-Tech — Staff Management / HR Care operations dispatcher.
 *
 * Tab actions are routed through page=hr_care so that the standard
 * module+submodule RBAC grant (staff_management → hr_care) covers them:
 *
 *   attendance: checkin, checkout, adjust, delete, export_monthly
 *   leaves:     save_leave, delete_leave
 *   tasks:      save_task, delete_task, post_update
 *   meetings:   save_event, delete_event
 *   speak up:   save_grievance, delete_grievance, post_grievance_update,
 *               admin_update_grievance
 *
 * Phase 2: the dispatcher now lives in staff_management/operation; the shared
 * tab handlers remain at my_office/operation (also POSTed directly by other
 * office surfaces), so they are included by absolute path.
 */
$action = (string) ($_POST['action'] ?? '');

$attendanceActions = ['checkin', 'checkout', 'adjust', 'delete', 'export_monthly'];
$leaveActions = ['save_leave', 'delete_leave'];
$taskActions = ['save_task', 'delete_task', 'post_update'];
$meetingActions = ['save_event', 'delete_event'];
$grievanceActions = ['save_grievance', 'delete_grievance', 'post_grievance_update', 'admin_update_grievance'];

$hrOpsRoot = dirname(__DIR__, 2) . '/my_office/operation/';

if (in_array($action, $attendanceActions, true)) {
    include $hrOpsRoot . 'attendance.php';
} elseif (in_array($action, $leaveActions, true)) {
    include $hrOpsRoot . 'leaves.php';
} elseif (in_array($action, $taskActions, true)) {
    include $hrOpsRoot . 'tasks.php';
} elseif (in_array($action, $meetingActions, true)) {
    include $hrOpsRoot . 'meetings.php';
} elseif (in_array($action, $grievanceActions, true)) {
    include $hrOpsRoot . 'grievances.php';
} else {
    http_response_code(400);
    die('Unknown HR Care operation.');
}
