<?php
/**
 * SB-Tech — Reports / Leads operations (CSV export).
 */
$db = Database::instance();
$action = (string) ($_POST['action'] ?? '');

try {
    if ($action === 'export_leads') {
        $rows = $db->select(
            "SELECT l.source, l.company, l.contact_name, l.email, l.phone, l.service_interest,
                    l.priority, l.estimated_value, l.stage, l.lost_reason,
                    u.fullname AS owner, l.added_on, l.last_activity_on
             FROM tbl_leads l
             LEFT JOIN tbl_users_login u ON u.id = l.assigned_to
             ORDER BY l.added_on DESC"
        );
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="leads_pipeline_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Source', 'Company', 'Contact', 'Email', 'Phone', 'Service', 'Priority', 'Value', 'Stage', 'Lost Reason', 'Owner', 'Created', 'Last Activity']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['source'], $r['company'], $r['contact_name'], $r['email'], $r['phone'],
                $r['service_interest'], $r['priority'], $r['estimated_value'], $r['stage'],
                $r['lost_reason'], $r['owner'], $r['added_on'], $r['last_activity_on'],
            ]);
        }
        fclose($out);
        exit;
    }
    setFlash('error', 'Unknown action.');
    redirect(pageUrl('reports', 'leads'));
} catch (Throwable $e) {
    setFlash('error', 'Export failed: ' . $e->getMessage());
    redirect(pageUrl('reports', 'leads'));
}
