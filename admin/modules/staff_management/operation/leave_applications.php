<?php
/**
 * SB-Tech — Leave Management / Applications operations (US-LV-03).
 *   update_status — Pending → Verified → Approved | Rejected.
 *                   Verify/Approve record actor; rejection requires a reason.
 *                   used_days is re-synced on every change (so revoking an
 *                   approval restores balance).
 *   export_report — CSV of leave usage for the Report tab.
 */
$db = Database::instance();
$me = (int) Auth::id();
$action = (string) ($_POST['action'] ?? '');

try {
    if ($action === 'update_status') {
        $id = (int) ($_POST['id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');
        if (!in_array($status, ['Verified', 'Approved', 'Rejected'], true)) {
            setFlash('error', 'Invalid target status.');
            redirect('show_page.php?module=staff_management&page=leave_management&tab=leave_application');
        }

        $leave = $db->selectOne('SELECT * FROM `tbl_staff_leave_applications` WHERE `id` = ?', [$id]);
        if (!$leave) {
            setFlash('error', 'Leave application not found.');
            redirect('show_page.php?module=staff_management&page=leave_management&tab=leave_application');
        }

        $current = $leave['status'];
        if ($status === 'Verified' && !in_array($current, ['Pending'], true)) {
            setFlash('error', 'Only Pending applications can be verified.');
            redirect('show_page.php?module=staff_management&page=leave_management&tab=leave_application');
        }
        if ($status === 'Approved' && !in_array($current, ['Pending', 'Verified'], true)) {
            setFlash('error', 'Only Pending or Verified applications can be approved.');
            redirect('show_page.php?module=staff_management&page=leave_management&tab=leave_application');
        }
        if ($status === 'Rejected' && !in_array($current, ['Pending', 'Verified'], true)) {
            setFlash('error', 'This application is already final.');
            redirect('show_page.php?module=staff_management&page=leave_management&tab=leave_application');
        }

        $data = ['status' => $status, 'updated_by' => $me];
        if ($status === 'Verified') {
            $data['verified_by'] = $me;
            $data['reject_reason'] = null;
        } elseif ($status === 'Approved') {
            $data['approved_by'] = $me;
            $data['reject_reason'] = null;
        } else { // Rejected
            $reason = trim((string) ($_POST['reason'] ?? ''));
            if ($reason === '') {
                setFlash('error', 'A rejection reason is required (shown to the staff member).');
                redirect('show_page.php?module=staff_management&page=leave_management&tab=leave_application');
            }
            $data['reject_reason'] = $reason;
        }

        $db->update('tbl_staff_leave_applications', $data, '`id` = ?', [$id]);

        // Keep the allocation's used_days aligned with Approved applications.
        syncStaffLeaveAllocationUsedDays((int) $leave['staff_id'], (int) $leave['leave_type_id']);

        $staff = $db->selectOne('SELECT `fullname` FROM `tbl_users_login` WHERE `id` = ?', [(int) $leave['staff_id']]);
        $type = $db->selectOne('SELECT `title` FROM `tbl_office_leave_configs` WHERE `id` = ?', [(int) $leave['leave_type_id']]);
        notifyUser(
            (int) $leave['staff_id'],
            'Your ' . e($type['title'] ?? 'leave') . ' application (' . e((float) $leave['leave_days']) . ' day(s), '
                . e(formatDateView($leave['from_date'])) . ' → ' . e(formatDateView($leave['to_date'])) . ') was '
                . strtolower($status) . ($status === 'Rejected' ? ': ' . e($data['reject_reason']) : '') . '.',
            'leave',
            (string) $id,
            $me
        );

        setFlash('success', 'Leave application ' . strtolower($status) . '.');
        redirect('show_page.php?module=staff_management&page=leave_management&tab=leave_application');
    }

    if ($action === 'export_report') {
        $year = (int) ($_POST['year'] ?? currentLeaveYear());
        if ($year < 2000 || $year > 2100) {
            $year = currentLeaveYear();
        }
        $staffFilter = (int) ($_POST['staff_id'] ?? 0);
        $where = 'WHERE la.year = ?';
        $params = [$year];
        if ($staffFilter) {
            $where .= ' AND la.staff_id = ?';
            $params[] = $staffFilter;
        }
        $rows = $db->select(
            'SELECT u.fullname, lc.title AS leave_title, la.allocated_days, la.carry_forward_days, la.used_days,
                    COALESCE(p.days, 0) AS pending_days
             FROM `tbl_office_staff_leave_allocation` la
             JOIN `tbl_users_login` u ON u.id = la.staff_id
             JOIN `tbl_office_leave_configs` lc ON lc.id = la.leave_id
             LEFT JOIN (
                SELECT staff_id, leave_type_id, SUM(leave_days) AS days
                FROM `tbl_staff_leave_applications`
                WHERE status IN ("Pending","Verified")
                GROUP BY staff_id, leave_type_id
             ) p ON p.staff_id = la.staff_id AND p.leave_type_id = la.leave_id
             ' . $where . '
             ORDER BY u.fullname, lc.title',
            $params
        );
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="leave_report_' . $year . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Staff', 'Leave type', 'Allocated', 'Carry forward', 'Used', 'Pending', 'Remaining']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['fullname'],
                $r['leave_title'],
                (float) $r['allocated_days'],
                (float) $r['carry_forward_days'],
                (float) $r['used_days'],
                (float) $r['pending_days'],
                round((float) $r['allocated_days'] + (float) $r['carry_forward_days'] - (float) $r['used_days'] - (float) $r['pending_days'], 1),
            ]);
        }
        fclose($out);
        exit;
    }

    setFlash('error', 'Unknown leave application action.');
    redirect('show_page.php?module=staff_management&page=leave_management&tab=leave_application');
} catch (Throwable $e) {
    setFlash('error', 'Leave application operation failed: ' . $e->getMessage());
    redirect('show_page.php?module=staff_management&page=leave_management&tab=leave_application');
}
