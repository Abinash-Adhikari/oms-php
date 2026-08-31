<?php
/**
 * SB-Tech — My Office / Attendance operations (US-ATT-01, US-ATT-02).
 *
 *   checkin / checkout   — stamp time for self or a colleague whose
 *                          allow_checkin_by_other = 'Yes' (AC-ATT-01.4)
 *   adjust / delete      — admin correction (Super Admin or view_all_attendance)
 *   export_monthly       — CSV monthly report
 *
 * All writes recompute derived fields server-side (never trust the client).
 */
$db = Database::instance();
$me = (int) Auth::id();
$canSeeAll = Auth::isSuperAdmin() || Auth::hasSpecial('view_all_attendance');
$action = (string) ($_POST['action'] ?? '');
$today = date('Y-m-d');

$back = 'show_page.php?module=staff_management&page=hr_care&tab=attendance';

/** Resolve the attendance target: self, or a proxy-selected colleague. */
function attendanceTarget(Database $db, int $me, bool $canSeeAll, array $post): array
{
    $target = $me;
    if (!empty($post['for_user'])) {
        $target = (int) $post['for_user'];
        if ($target === $me) {
            return [$me, true, null];
        }
        $t = $db->selectOne(
            'SELECT `id`, `status`, `allow_checkin_by_other` FROM `tbl_users_login` WHERE `id` = ?',
            [$target]
        );
        if (!$t || $t['status'] !== 'Active') {
            return [0, false, 'Target staff not found or not active.'];
        }
        if ($t['allow_checkin_by_other'] !== 'Yes' && !$canSeeAll) {
            return [0, false, 'This staff member has not enabled check-in by other staff.'];
        }
    }
    return [$target, true, null];
}

try {
    if ($action === 'checkin' || $action === 'checkout') {
        [$target, $ok, $err] = attendanceTarget($db, $me, $canSeeAll, $_POST);
        if (!$ok) {
            setFlash('error', $err);
            redirect($back);
        }
        $user = $db->selectOne('SELECT * FROM `tbl_users_login` WHERE `id` = ?', [$target]);
        $now = date('H:i:s');
        $existing = $db->selectOne(
            'SELECT * FROM `tbl_staff_attendances` WHERE `user_id` = ? AND `date` = ?',
            [$target, $today]
        );

        if ($action === 'checkin') {
            if ($existing && $existing['checkin']) {
                setFlash('error', 'Already checked in today.');
                redirect($back);
            }
            $metrics = computeAttendanceMetrics($now, null, $user['checkin'] ?? null, $user['checkout'] ?? null);
            $status = autoAttendanceStatus($target, $today, true);
            if ($existing) {
                $db->update('tbl_staff_attendances', [
                    'checkin'               => $now,
                    'checkin_delay'         => $metrics['checkin_delay'],
                    'late_checkin'          => $metrics['late_checkin'],
                    'late_checkin_minutes'  => $metrics['late_checkin_minutes'],
                    'reason_checkin'        => trim((string) ($_POST['reason_checkin'] ?? '')) ?: null,
                    'config_checkin'        => $user['checkin'] ?? null,
                    'config_checkout'       => $user['checkout'] ?? null,
                    'status'                => $status,
                    'updated_by'            => $me,
                ], '`id` = ?', [(int) $existing['id']]);
            } else {
                $db->insert('tbl_staff_attendances', [
                    'user_id'               => $target,
                    'date'                  => $today,
                    'checkin'               => $now,
                    'checkin_delay'         => $metrics['checkin_delay'],
                    'late_checkin'          => $metrics['late_checkin'],
                    'late_checkin_minutes'  => $metrics['late_checkin_minutes'],
                    'reason_checkin'        => trim((string) ($_POST['reason_checkin'] ?? '')) ?: null,
                    'config_checkin'        => $user['checkin'] ?? null,
                    'config_checkout'       => $user['checkout'] ?? null,
                    'status'                => $status,
                    'added_by'              => $me,
                    'updated_by'            => $me,
                ]);
            }
            setFlash('success', 'Checked in at ' . $now . '.');
        } else {
            if (!$existing || !$existing['checkin']) {
                setFlash('error', 'You have not checked in today.');
                redirect($back);
            }
            if ($existing['checkout']) {
                setFlash('error', 'Already checked out today.');
                redirect($back);
            }
            $metrics = computeAttendanceMetrics($existing['checkin'], $now, $user['checkin'] ?? null, $user['checkout'] ?? null);
            $status = autoAttendanceStatus($target, $today, true);
            $db->update('tbl_staff_attendances', [
                'checkout'          => $now,
                'checkout_early'    => $metrics['checkout_early'],
                'early_checkout'    => $metrics['early_checkout'],
                'reason_checkout'   => trim((string) ($_POST['reason_checkout'] ?? '')) ?: null,
                'working_hours'     => $metrics['working_hours'],
                'status'            => $status,
                'updated_by'        => $me,
            ], '`id` = ?', [(int) $existing['id']]);
            setFlash('success', 'Checked out at ' . $now . '.');
        }
        redirect($back);
    }

    if ($action === 'adjust') {
        if (!$canSeeAll) {
            http_response_code(403);
            die('Access denied: attendance adjustment requires the view_all_attendance permission.');
        }
        $userId = (int) ($_POST['user_id'] ?? 0);
        $date = (string) ($_POST['date'] ?? '');
        if (!$userId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            setFlash('error', 'Invalid staff or date.');
            redirect($back);
        }
        $user = $db->selectOne('SELECT * FROM `tbl_users_login` WHERE `id` = ?', [$userId]);
        if (!$user) {
            setFlash('error', 'Staff not found.');
            redirect($back);
        }
        $checkin = trim((string) ($_POST['checkin'] ?? '')) ?: null;
        $checkout = trim((string) ($_POST['checkout'] ?? '')) ?: null;
        if ($checkin && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $checkin)) {
            $checkin = null;
        }
        if ($checkout && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $checkout)) {
            $checkout = null;
        }
        $metrics = computeAttendanceMetrics($checkin, $checkout, $user['checkin'] ?? null, $user['checkout'] ?? null);
        $status = (string) ($_POST['status'] ?? '');
        if (!in_array($status, ['present', 'absent', 'leave', 'holiday'], true)) {
            $status = autoAttendanceStatus($userId, $date, (bool) $checkin);
        }
        $reason = trim((string) ($_POST['reason'] ?? '')) ?: null;
        $existing = $db->selectOne(
            'SELECT * FROM `tbl_staff_attendances` WHERE `user_id` = ? AND `date` = ?',
            [$userId, $date]
        );
        $data = [
            'checkin'              => $checkin,
            'checkout'             => $checkout,
            'checkin_delay'        => $metrics['checkin_delay'],
            'late_checkin'         => $metrics['late_checkin'],
            'late_checkin_minutes' => $metrics['late_checkin_minutes'],
            'checkout_early'       => $metrics['checkout_early'],
            'early_checkout'       => $metrics['early_checkout'],
            'working_hours'        => $metrics['working_hours'],
            'reason_checkin'       => $reason,
            'reason_checkout'      => $reason,
            'config_checkin'       => $user['checkin'] ?? null,
            'config_checkout'      => $user['checkout'] ?? null,
            'status'               => $status,
            'updated_by'           => $me,
        ];
        if ($existing) {
            $db->update('tbl_staff_attendances', $data, '`id` = ?', [(int) $existing['id']]);
        } else {
            $data['user_id'] = $userId;
            $data['date'] = $date;
            $data['added_by'] = $me;
            $db->insert('tbl_staff_attendances', $data);
        }
        setFlash('success', 'Attendance adjusted for ' . e($user['fullname']) . ' on ' . e($date) . '.');
        redirect($back . '&month=' . urlencode(substr($date, 0, 7)));
    }

    if ($action === 'delete') {
        if (!$canSeeAll) {
            http_response_code(403);
            die('Access denied: attendance deletion requires the view_all_attendance permission.');
        }
        $id = (int) ($_POST['id'] ?? 0);
        $row = $db->selectOne('SELECT * FROM `tbl_staff_attendances` WHERE `id` = ?', [$id]);
        if (!$row) {
            setFlash('error', 'Attendance record not found.');
        } else {
            $db->delete('tbl_staff_attendances', '`id` = ?', [$id]);
            setFlash('success', 'Attendance record deleted.');
        }
        redirect($back . '&month=' . urlencode(substr((string) $row['date'], 0, 7)));
    }

    if ($action === 'export_monthly') {
        $month = (string) ($_POST['month'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $rows = $db->select(
            "SELECT u.fullname,
                    COALESCE(SUM(a.status = 'present'), 0) AS present_days,
                    COALESCE(SUM(a.status = 'absent'), 0) AS absent_rows,
                    COALESCE(SUM(a.status = 'leave'), 0) AS leave_days,
                    COALESCE(SUM(a.status = 'holiday'), 0) AS holiday_days,
                    COALESCE(SUM(a.late_checkin), 0) AS late_days,
                    COALESCE(SUM(a.early_checkout), 0) AS early_days,
                    COALESCE(SUM(a.working_hours), 0) AS working_hours
             FROM `tbl_users_login` u
             LEFT JOIN `tbl_staff_attendances` a
               ON a.user_id = u.id AND a.date LIKE ?
             WHERE u.status != 'Terminated' AND u.id = ?
             GROUP BY u.id, u.fullname",
            [$month . '%', $me]
        );
        if ($canSeeAll) {
            $rows = $db->select(
                "SELECT u.fullname,
                        COALESCE(SUM(a.status = 'present'), 0) AS present_days,
                        COALESCE(SUM(a.status = 'absent'), 0) AS absent_rows,
                        COALESCE(SUM(a.status = 'leave'), 0) AS leave_days,
                        COALESCE(SUM(a.status = 'holiday'), 0) AS holiday_days,
                        COALESCE(SUM(a.late_checkin), 0) AS late_days,
                        COALESCE(SUM(a.early_checkout), 0) AS early_days,
                        COALESCE(SUM(a.working_hours), 0) AS working_hours
                 FROM `tbl_users_login` u
                 LEFT JOIN `tbl_staff_attendances` a
                   ON a.user_id = u.id AND a.date LIKE ?
                 WHERE u.status != 'Terminated'
                 GROUP BY u.id, u.fullname
                 ORDER BY u.fullname",
                [$month . '%']
            );
        }
        $daysInMonth = (int) date('t', strtotime($month . '-01'));
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="attendance_' . $month . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Staff', 'Present', 'Late', 'Early', 'Leave', 'Holiday', 'Absent', 'Working hours']);
        foreach ($rows as $r) {
            $absent = max(0, $daysInMonth - (int) $r['present_days'] - (int) $r['leave_days'] - (int) $r['holiday_days']);
            fputcsv($out, [
                $r['fullname'],
                (int) $r['present_days'],
                (int) $r['late_days'],
                (int) $r['early_days'],
                (int) $r['leave_days'],
                (int) $r['holiday_days'],
                $absent,
                round((float) $r['working_hours'], 2),
            ]);
        }
        fclose($out);
        exit;
    }

    setFlash('error', 'Unknown attendance action.');
    redirect($back);
} catch (Throwable $e) {
    setFlash('error', 'Attendance operation failed: ' . $e->getMessage());
    redirect($back);
}
