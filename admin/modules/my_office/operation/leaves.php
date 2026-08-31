<?php
/**
 * SB-Tech — My Office / Leaves operations (US-LV-02, AC-LV-02.2/2.3).
 *
 *   save_leave    — create or edit (edit only allowed while Pending)
 *   delete_leave  — delete (only allowed while Pending)
 *
 * Server-side balance guard (X-09): requested days must not exceed
 * allocated + carry − used(approved) − pending.
 */
$db = Database::instance();
$me = (int) Auth::id();
$action = (string) ($_POST['action'] ?? '');
$back = 'show_page.php?module=staff_management&page=hr_care&tab=leaves';

try {
    if ($action === 'save_leave') {
        $leaveId = (int) ($_POST['leave_id'] ?? 0);
        $leaveTypeId = (int) ($_POST['leave_type_id'] ?? 0);
        $filler = (int) ($_POST['absence_filler'] ?? 0);
        $from = trim((string) ($_POST['from_date'] ?? ''));
        $to = trim((string) ($_POST['to_date'] ?? ''));
        $halfDay = !empty($_POST['half_day']);
        $firstHalf = (int) ($_POST['first_half'] ?? 1) === 1 ? 1 : 0;
        $reason = trim((string) ($_POST['reason'] ?? ''));

        if (!$leaveTypeId || !$filler) {
            setFlash('error', 'Leave type and substitute staff are required.');
            redirect($back);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            setFlash('error', 'Invalid date range.');
            redirect($back);
        }
        if ($to < $from) {
            setFlash('error', 'The "to" date must be on or after the "from" date.');
            redirect($back);
        }
        if ($reason === '') {
            setFlash('error', 'A reason is required.');
            redirect($back);
        }

        $type = $db->selectOne(
            'SELECT * FROM `tbl_office_leave_configs` WHERE `id` = ? AND `is_active` = 1',
            [$leaveTypeId]
        );
        if (!$type) {
            setFlash('error', 'Selected leave type is not available.');
            redirect($back);
        }

        $days = countLeaveDays($from, $to, $halfDay, $me);
        if ($days <= 0) {
            setFlash('error', 'The selected dates contain no countable days (all office holidays?).');
            redirect($back);
        }

        // Balance guard: allocated + carry − used(approved) − pending.
        $alloc = getStaffLeaveAllocation($me, $leaveTypeId);
        if (!$alloc) {
            setFlash('error', 'No leave allocation for this type in the current leave year.');
            redirect($back);
        }
        $pendingRow = $db->selectOne(
            "SELECT COALESCE(SUM(`leave_days`), 0) AS days
             FROM `tbl_staff_leave_applications`
             WHERE `staff_id` = ? AND `leave_type_id` = ? AND `status` IN ('Pending','Verified')
               AND `id` != ?",
            [$me, $leaveTypeId, $leaveId]
        );
        $remaining = round(
            (float) $alloc['allocated_days'] + (float) $alloc['carry_forward_days']
            - (float) $alloc['used_days'] - (float) ($pendingRow['days'] ?? 0),
            1
        );
        if ($days > $remaining + 0.0001) {
            setFlash('error', 'Requested ' . e($days) . ' days exceed your remaining balance of ' . e($remaining) . ' days.');
            redirect($back);
        }

        if ($leaveId) {
            $existing = $db->selectOne(
                'SELECT * FROM `tbl_staff_leave_applications` WHERE `id` = ? AND `staff_id` = ?',
                [$leaveId, $me]
            );
            if (!$existing || $existing['status'] !== 'Pending') {
                setFlash('error', 'You can only edit applications that are still Pending.');
                redirect($back);
            }
            $db->update('tbl_staff_leave_applications', [
                'leave_type_id' => $leaveTypeId,
                'absence_filler'=> $filler,
                'half_day'      => $halfDay ? 1 : 0,
                'first_half'    => $firstHalf,
                'from_date'     => $from,
                'to_date'       => $to,
                'leave_days'    => $days,
                'reason'        => $reason,
                'updated_by'    => $me,
            ], '`id` = ?', [$leaveId]);
            setFlash('success', 'Leave application updated.');
        } else {
            $year = currentLeaveYear();
            $id = $db->insert('tbl_staff_leave_applications', [
                'staff_id'      => $me,
                'leave_type_id' => $leaveTypeId,
                'absence_filler'=> $filler,
                'half_day'      => $halfDay ? 1 : 0,
                'first_half'    => $firstHalf,
                'from_date'     => $from,
                'to_date'       => $to,
                'leave_days'    => $days,
                'reason'        => $reason,
                'status'        => 'Pending',
                'year'          => (string) $year,
                'leave_year'    => (string) $year,
                'added_by'      => $me,
            ]);
            $meUser = $db->selectOne('SELECT `fullname` FROM `tbl_users_login` WHERE `id` = ?', [$me]);
            notifyPermissionHolders(
                'manage_staff_leaves',
                e($meUser['fullname'] ?? 'A staff member') . ' applied for ' . e($days) . ' day(s) of ' . e($type['title']) . ' leave (' . e($from) . ' → ' . e($to) . ').',
                'leave',
                (string) $id,
                $me
            );
            setFlash('success', 'Leave application submitted.');
        }
        redirect($back);
    }

    if ($action === 'delete_leave') {
        $leaveId = (int) ($_POST['leave_id'] ?? 0);
        $existing = $db->selectOne(
            'SELECT * FROM `tbl_staff_leave_applications` WHERE `id` = ? AND `staff_id` = ?',
            [$leaveId, $me]
        );
        if (!$existing) {
            setFlash('error', 'Leave application not found.');
        } elseif ($existing['status'] !== 'Pending') {
            setFlash('error', 'Only Pending applications can be deleted.');
        } else {
            $db->delete('tbl_staff_leave_applications', '`id` = ?', [$leaveId]);
            setFlash('success', 'Leave application deleted.');
        }
        redirect($back);
    }

    setFlash('error', 'Unknown leave action.');
    redirect($back);
} catch (Throwable $e) {
    setFlash('error', 'Leave operation failed: ' . $e->getMessage());
    redirect($back);
}
