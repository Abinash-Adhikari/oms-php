<?php
/**
 * SB-Tech — Reports / Tasks operations (CSV export).
 */
$db = Database::instance();
$action = (string) ($_POST['action'] ?? '');

try {
    if ($action === 'export_tasks') {
        $rows = $db->select(
            "SELECT t.title, t.status, t.deadline, u.fullname AS author,
                    GROUP_CONCAT(DISTINCT au.fullname SEPARATOR ', ') AS assignees,
                    t.added_on, t.updated_on
             FROM tbl_office_tasks t
             JOIN tbl_users_login u ON u.id = t.author
             LEFT JOIN tbl_office_task_assignees ta ON ta.task_id = t.id
             LEFT JOIN tbl_users_login au ON au.id = ta.staff_id
             GROUP BY t.id ORDER BY t.added_on DESC"
        );
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="tasks_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Title', 'Status', 'Deadline', 'Author', 'Assignees', 'Created', 'Updated']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['title'], $r['status'], $r['deadline'], $r['author'], $r['assignees'], $r['added_on'], $r['updated_on']]);
        }
        fclose($out);
        exit;
    }
    setFlash('error', 'Unknown action.');
    redirect(pageUrl('reports', 'tasks'));
} catch (Throwable $e) {
    setFlash('error', 'Export failed: ' . $e->getMessage());
    redirect(pageUrl('reports', 'tasks'));
}
