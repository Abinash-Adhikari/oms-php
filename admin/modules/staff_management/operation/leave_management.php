<?php
/**
 * SB-Tech — Leave Management operations dispatcher.
 * Routed through page=leave_management; gated by the manage_staff_leaves
 * special permission (Super Admin bypass).
 *
 *   setup:      save_type, delete_type
 *   allocations: save_allocations
 *   applications: update_status, export_report
 */
if (!Auth::isSuperAdmin() && !Auth::hasSpecial('manage_staff_leaves')) {
    http_response_code(403);
    die('Access denied: you need the manage_staff_leaves permission.');
}

$action = (string) ($_POST['action'] ?? '');
$setupActions = ['save_type', 'delete_type'];
$allocActions = ['save_allocations'];
$appActions = ['update_status', 'export_report'];

if (in_array($action, $setupActions, true)) {
    include __DIR__ . '/leave_setup.php';
} elseif (in_array($action, $allocActions, true)) {
    include __DIR__ . '/leave_allocations.php';
} elseif (in_array($action, $appActions, true)) {
    include __DIR__ . '/leave_applications.php';
} else {
    http_response_code(400);
    die('Unknown leave management operation.');
}
