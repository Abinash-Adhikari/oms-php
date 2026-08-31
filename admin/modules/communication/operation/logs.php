<?php
/**
 * SB-Tech — Communication / Logs operations.
 * export_csv
 */
$db = Database::instance();
$action = (string) ($_POST['action'] ?? '');

try {
    if ($action === 'export_csv') {
        $filterType = $_POST['type'] ?? '';
        $filterStatus = $_POST['status'] ?? '';
        $search = trim((string) ($_POST['q'] ?? ''));

        $where = ['1=1'];
        $params = [];
        if ($filterType && in_array($filterType, ['Email', 'SMS'], true)) {
            $where[] = '`type` = ?';
            $params[] = $filterType;
        }
        if ($filterStatus && in_array($filterStatus, ['Queued', 'Sent', 'Failed'], true)) {
            $where[] = '`status` = ?';
            $params[] = $filterStatus;
        }
        if ($search !== '') {
            $where[] = '`recipient` LIKE ?';
            $params[] = '%' . $db->escapeLike($search) . '%';
        }

        $logs = $db->select(
            'SELECT l.*, u.fullname AS actor_name FROM `tbl_communication_logs` l
             LEFT JOIN `tbl_users_login` u ON u.id = l.added_by
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY l.id DESC',
            $params
        );

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="communication_logs_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Type', 'Recipient', 'Subject/Event', 'Campaign ID', 'Status', 'Error', 'Sent On', 'Actor', 'Created']);
        foreach ($logs as $l) {
            fputcsv($out, [
                $l['id'], $l['type'], $l['recipient'], $l['subject'],
                $l['campaign_id'], $l['status'], $l['error_message'],
                $l['sent_on'], $l['actor_name'], $l['added_on'],
            ]);
        }
        fclose($out);
        exit;
    }

    setFlash('error', 'Unknown action.');
    redirect(pageUrl('communication', 'logs'));
} catch (Throwable $e) {
    setFlash('error', 'Export failed: ' . $e->getMessage());
    redirect(pageUrl('communication', 'logs'));
}
