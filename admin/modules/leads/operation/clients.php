<?php
/**
 * SB-Tech — Clients operations (AC-LE-06).
 *   save_client / delete_client  (delete cascades to projects)
 *   save_project / delete_project
 */
$db = Database::instance();
$me = (int) Auth::id();
if (!(Auth::isSuperAdmin() || Auth::hasSpecial('manage_leads'))) {
    http_response_code(403);
    die('Access denied: you need the manage_leads permission.');
}
$action = (string) ($_POST['action'] ?? '');
$back = 'show_page.php?module=leads&page=clients';

try {
    if ($action === 'save_client') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            setFlash('error', 'Client name is required.');
            redirect($back);
        }
        $data = [
            'name'           => $name,
            'contact_person' => trim((string) ($_POST['contact_person'] ?? '')) ?: null,
            'email'          => trim((string) ($_POST['email'] ?? '')) ?: null,
            'phone'          => trim((string) ($_POST['phone'] ?? '')) ?: null,
            'address'        => trim((string) ($_POST['address'] ?? '')) ?: null,
            'pan_num'        => trim((string) ($_POST['pan_num'] ?? '')) ?: null,
            'notes'          => trim((string) ($_POST['notes'] ?? '')) ?: null,
            'updated_by'     => $me,
        ];
        if ($id) {
            $db->update('tbl_clients', $data, '`id` = ?', [$id]);
            setFlash('success', 'Client updated.');
        } else {
            $data['added_by'] = $me;
            $id = $db->insert('tbl_clients', $data);
            setFlash('success', 'Client created.');
        }
        redirect($back . '&id=' . $id);
    }

    if ($action === 'delete_client') {
        $id = (int) ($_POST['id'] ?? 0);
        $db->delete('tbl_clients', '`id` = ?', [$id]); // projects cascade
        setFlash('success', 'Client deleted.');
        redirect($back);
    }

    if ($action === 'save_project') {
        $clientId = (int) ($_POST['client_id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        if (!$clientId || $title === '') {
            setFlash('error', 'Client and project title are required.');
            redirect($back);
        }
        $start = trim((string) ($_POST['start_date'] ?? ''));
        $end = trim((string) ($_POST['end_date'] ?? ''));
        if ($start !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
            $start = '';
        }
        if ($end !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            $end = '';
        }
        $status = (string) ($_POST['status'] ?? 'Active');
        if (!in_array($status, ['Active', 'Completed', 'On Hold', 'Cancelled'], true)) {
            $status = 'Active';
        }
        $db->insert('tbl_client_projects', [
            'client_id'   => $clientId,
            'title'       => $title,
            'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
            'value'       => ($_POST['value'] ?? '') !== '' ? round((float) $_POST['value'], 4) : null,
            'start_date'  => $start ?: null,
            'end_date'    => $end ?: null,
            'status'      => $status,
            'added_by'    => $me,
        ]);
        setFlash('success', 'Project added.');
        redirect($back . '&id=' . $clientId);
    }

    if ($action === 'delete_project') {
        $id = (int) ($_POST['id'] ?? 0);
        $p = $db->selectOne('SELECT * FROM `tbl_client_projects` WHERE `id` = ?', [$id]);
        $db->delete('tbl_client_projects', '`id` = ?', [$id]);
        setFlash('success', 'Project deleted.');
        redirect($back . ($p ? '&id=' . (int) $p['client_id'] : ''));
    }

    setFlash('error', 'Unknown client action.');
    redirect($back);
} catch (Throwable $e) {
    setFlash('error', 'Client operation failed: ' . $e->getMessage());
    redirect($back);
}
