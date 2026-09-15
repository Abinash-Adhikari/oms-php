<?php
/**
 * SB-Tech — Reports / Audit operations (CSV export).
 */
$db = Database::instance();
$action = (string) ($_POST['action'] ?? '');

try {
    if ($action === 'export_audit') {
        $filterModule = (string) ($_POST['module_filter'] ?? '');
        $filterAction = (string) ($_POST['action_filter'] ?? '');
        $search = trim((string) ($_POST['q'] ?? ''));

        $where = ['1=1'];
        $params = [];
        if ($filterModule !== '') {
            $where[] = 'a.module = ?';
            $params[] = $filterModule;
        }
        if ($filterAction !== '') {
            $where[] = 'a.action = ?';
            $params[] = $filterAction;
        }
        if ($search !== '') {
            $where[] = '(a.description LIKE ? OR a.entity_type LIKE ?)';
            $p = '%' . $db->escapeLike($search) . '%';
            $params[] = $p;
            $params[] = $p;
        }
        $whereSql = implode(' AND ', $where);
        $rows = $db->select(
            "SELECT a.*, u.fullname AS actor_name
             FROM tbl_audit_log a
             LEFT JOIN tbl_users_login u ON u.id = a.actor_id
             WHERE {$whereSql}
             ORDER BY a.added_on DESC",
            $params
        );
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="audit_log_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Date', 'Module', 'Action', 'Entity Type', 'Entity ID', 'Actor', 'IP', 'Description']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['added_on'], $r['module'], $r['action'], $r['entity_type'],
                $r['entity_id'], $r['actor_name'] ?? 'System', $r['actor_ip'], $r['description'],
            ]);
        }
        fclose($out);
        exit;
    }
    setFlash('error', 'Unknown action.');
    redirect(pageUrl('reports', 'audit'));
} catch (Throwable $e) {
    setFlash('error', 'Export failed: ' . $e->getMessage());
    redirect(pageUrl('reports', 'audit'));
}