<?php
/**
 * SB-Tech — Staff Management / CSV export (US-RPT-02.1).
 * Exports the active staff directory.
 */
$db = Database::instance();

try {
    $rows = $db->select(
        'SELECT u.*, d.title AS department_title, g.title AS designation_title
         FROM `tbl_users_login` u
         LEFT JOIN `tbl_office_departments` d ON d.id = u.department_id
         LEFT JOIN `tbl_office_designation` g ON g.id = u.designation_id
         WHERE u.status != \'Terminated\'
         ORDER BY u.fullname'
    );

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="staff_directory_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Full Name', 'Username', 'Email', 'Phone', 'Gender', 'Department', 'Designation', 'Staff Type', 'Join Date', 'Status', 'PAN', 'Bank', 'Account No']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'], $r['fullname'], $r['username'], $r['email'],
            $r['phone1'], $r['gender'], $r['department_title'] ?? '',
            $r['designation_title'] ?? '', $r['staff_type'],
            $r['join_date'], $r['status'], $r['pan_num'],
            $r['bank'], $r['bank_account_num'],
        ]);
    }
    fclose($out);
    exit;
} catch (Throwable $e) {
    setFlash('error', 'Export failed: ' . $e->getMessage());
    redirect(pageUrl('staff_management', 'add_staff'));
}
