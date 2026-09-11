<?php
/**
 * SB-Tech — Client Projects operations.
 *   save_client_project / delete_client_project
 * Manually maintains the won-deployment registry (tbl_client_projects).
 * Rows are normally auto-created when a lead reaches Won; module grants and
 * the client database are edited from the Client Access screen.
 */
$db = Database::instance();
$me = (int) Auth::id();
if (!(Auth::isSuperAdmin() || Auth::hasSpecial('manage_leads'))) {
    http_response_code(403);
    die('Access denied: you need the manage_leads permission.');
}
$action = (string) ($_POST['action'] ?? '');
$fromClientId = (int) ($_POST['from_client_id'] ?? 0);
$back = $fromClientId
    ? pageUrl('clients', 'detail') . '&id=' . $fromClientId
    : 'show_page.php?module=leads&page=client_projects';
$sourceFilter = (int) ($_POST['source_filter'] ?? 0);
if (!$fromClientId && $sourceFilter) {
    $back .= '&source=' . $sourceFilter;
}

function validateProjectDates(array $post): array
{
    $start = trim((string) ($post['start_date'] ?? ''));
    $end = trim((string) ($post['end_date'] ?? ''));
    if ($start !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
        $start = '';
    }
    if ($end !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
        $end = '';
    }
    return [$start ?: null, $end ?: null];
}

try {
    if ($action === 'save_client_project') {
        $id = (int) ($_POST['id'] ?? 0);
        $businessSourceId = (int) ($_POST['client_id'] ?? 0);
        $catalogProjectId = (int) ($_POST['project_id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            setFlash('error', 'Project title is required.');
            redirect($back);
        }
        if ($businessSourceId && !$db->selectOne('SELECT id FROM `tbl_clients` WHERE `id` = ?', [$businessSourceId])) {
            setFlash('error', 'Business source not found.');
            redirect($back);
        }
        if ($catalogProjectId && !$db->selectOne('SELECT id FROM `tbl_projects` WHERE `id` = ?', [$catalogProjectId])) {
            setFlash('error', 'Catalog project not found.');
            redirect($back);
        }
        $status = (string) ($_POST['status'] ?? 'Active');
        if (!in_array($status, ['Active', 'Completed', 'On Hold', 'Cancelled'], true)) {
            $status = 'Active';
        }
        $dbName = trim((string) ($_POST['db_name'] ?? '')) ?: null;
        if ($dbName !== null && !preg_match('/^[A-Za-z0-9_\-]+$/', $dbName)) {
            setFlash('error', 'Database name may only contain letters, numbers, underscores and dashes.');
            redirect($back);
        }
        [$start, $end] = validateProjectDates($_POST);
        $data = [
            'client_id' => $businessSourceId ?: null,
            'project_id'         => $catalogProjectId ?: null,
            'title'              => $title,
            'package'            => trim((string) ($_POST['package'] ?? '')) ?: null,
            'db_name'            => $dbName,
            'description'        => trim((string) ($_POST['description'] ?? '')) ?: null,
            'value'              => ($_POST['value'] ?? '') !== '' ? round((float) $_POST['value'], 4) : null,
            'start_date'         => $start,
            'end_date'           => $end,
            'status'             => $status,
            'updated_by'         => $me,
        ];
        if ($id) {
            $existing = $db->selectOne('SELECT id FROM `tbl_client_projects` WHERE `id` = ?', [$id]);
            if (!$existing) {
                setFlash('error', 'Client project not found.');
                redirect($back);
            }
            $db->update('tbl_client_projects', $data, '`id` = ?', [$id]);
            setFlash('success', 'Client project updated.');
        } else {
            $data['added_by'] = $me;
            $id = $db->insert('tbl_client_projects', $data);
            setFlash('success', 'Client project added.');
        }
        redirect($back);
    }

    if ($action === 'delete_client_project') {
        $id = (int) ($_POST['id'] ?? 0);
        $p = $db->selectOne('SELECT * FROM `tbl_client_projects` WHERE `id` = ?', [$id]);
        $db->delete('tbl_client_projects', '`id` = ?', [$id]);
        setFlash('success', 'Client project deleted.');
        redirect($fromClientId
            ? pageUrl('clients', 'detail') . '&id=' . $fromClientId
            : $back . ($p && $p['client_id'] ? '&source=' . (int) $p['client_id'] : '')
        );
    }

    setFlash('error', 'Unknown client project action.');
    redirect($back);
} catch (Throwable $e) {
    setFlash('error', 'Client project operation failed: ' . $e->getMessage());
    redirect($back);
}