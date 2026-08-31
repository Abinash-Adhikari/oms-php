<?php
/**
 * SB-Tech — Reports / Leave operations (CSV export).
 */
$db = Database::instance();
$action = (string) ($_POST['action'] ?? '');

try {
    if ($action === 'export_leave') {
        $year = (int) ($_POST['year'] ?? date('Y'));
        $staffFilter = (int) ($_POST['staff_id'] ?? 0);

        $where = 'la.year = ?';
        $params = [$year];
        if ($staffFilter) { $where .= ' AND la.staff_id = ?'; $params[] = $staffFilter; }

        $rows = $db->select(
            "SELECT u.fullname, lc.title AS leave_type, la.allocated_days, la.carry_forward_days, la.used_days,
                    COALESCE(p.pending, 0) AS pending_days,
                    (la.allocated_days + la.carry_forward_days - la.used_days - COALESCE(p.pending, 0)) AS remaining
             FROM tbl_office_staff_leave_allocation la
             JOIN tbl_users_login u ON u.id = la.staff_id
             JOIN tbl_office_leave_configs lc ON lc.id = la.leave_id
             LEFT JOIN (
                SELECT staff_id, leave_type_id, SUM(leave_days) AS pending
                FROM tbl_staff_leave_applications WHERE status IN ('Pending','Verified')
                GROUP BY staff_id, leave_type_id
             ) p ON p.staff_id = la.staff_id AND p.leave_type_id = la.leave_id
             WHERE {$where} ORDER BY u.fullname, lc.title",
            $params
        );
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="leave_' . $year . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Staff', 'Leave Type', 'Allocated', 'Carry Forward', 'Used', 'Pending', 'Remaining']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['fullname'], $r['leave_type'], $r['allocated_days'], $r['carry_forward_days'], $r['used_days'], $r['pending_days'], $r['remaining']]);
        }
        fclose($out);
        exit;
    }
    setFlash('error', 'Unknown action.');
    redirect(pageUrl('reports', 'leave'));
} catch (Throwable $e) {
    setFlash('error', 'Export failed: ' . $e->getMessage());
    redirect(pageUrl('reports', 'leave'));
}
