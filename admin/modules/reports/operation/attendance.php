<?php
/**
 * SB-Tech — Reports / Attendance operations (CSV export).
 */
$db = Database::instance();
$action = (string) ($_POST['action'] ?? '');

try {
    if ($action === 'export_attendance') {
        $month = $_POST['month'] ?? date('Y-m');
        $staffFilter = (int) ($_POST['staff_id'] ?? 0);

        $where = "u.status != 'Terminated'";
        $params = [$month . '%'];
        if ($staffFilter) { $where .= ' AND u.id = ?'; $params[] = $staffFilter; }

        $rows = $db->select(
            "SELECT u.fullname, d.title AS department,
                    COALESCE(SUM(a.status = 'present'), 0) AS present,
                    COALESCE(SUM(a.late_checkin), 0) AS late,
                    COALESCE(SUM(a.early_checkout), 0) AS early_out,
                    COALESCE(SUM(CASE WHEN a.status = 'leave' THEN 1 ELSE 0 END), 0) AS leave_days,
                    COALESCE(SUM(a.working_hours), 0) AS total_hours
             FROM tbl_users_login u
             LEFT JOIN tbl_office_departments d ON d.id = u.department_id
             LEFT JOIN tbl_staff_attendances a ON a.user_id = u.id AND a.date LIKE ?
             WHERE {$where}
             GROUP BY u.id, u.fullname, d.title ORDER BY u.fullname",
            $params
        );
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="attendance_' . $month . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Staff', 'Department', 'Present', 'Late', 'Early Out', 'Leave', 'Total Hours']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['fullname'], $r['department'], $r['present'], $r['late'], $r['early_out'], $r['leave_days'], round($r['total_hours'], 1)]);
        }
        fclose($out);
        exit;
    }
    setFlash('error', 'Unknown action.');
    redirect(pageUrl('reports', 'attendance'));
} catch (Throwable $e) {
    setFlash('error', 'Export failed: ' . $e->getMessage());
    redirect(pageUrl('reports', 'attendance'));
}
