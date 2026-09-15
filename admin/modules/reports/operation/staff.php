<?php
/**
 * SB-Tech — Reports / Staff operations (CSV export).
 */
$db = Database::instance();
$action = (string) ($_POST['action'] ?? '');

try {
    if ($action === 'export_staff') {
        $rows = $db->select(
            "SELECT u.fullname, u.username, u.email, u.phone1, u.gender, u.status,
                    d.title AS department, g.title AS designation, u.staff_type, u.join_date
             FROM tbl_users_login u
             LEFT JOIN tbl_office_departments d ON d.id = u.department_id
             LEFT JOIN tbl_office_designation g ON g.id = u.designation_id
             ORDER BY u.fullname"
        );
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="staff_directory_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Name', 'Username', 'Email', 'Phone', 'Gender', 'Status', 'Department', 'Designation', 'Type', 'Join Date']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['fullname'], $r['username'], $r['email'], $r['phone1'], $r['gender'], $r['status'], $r['department'], $r['designation'], $r['staff_type'], $r['join_date']]);
        }
        fclose($out);
        exit;
    }
    setFlash('error', 'Unknown action.');
    redirect(pageUrl('reports', 'staff'));
} catch (Throwable $e) {
    setFlash('error', 'Export failed: ' . $e->getMessage());
    redirect(pageUrl('reports', 'staff'));
}
