<?php
/**
 * SB-Tech — Leads operations (US-LE-02/03/04/06).
 * save_lead / update_lead / delete_lead / add_activity / add_lead_file /
 * create_followup / convert_lead / reopen_lead / merge_leads / export_leads.
 * Every state change is recorded on the activity timeline (AC-LE-02.4) and
 * bumps last_activity_on.
 */
$db = Database::instance();
$me = (int) Auth::id();
$canManage = Auth::isSuperAdmin() || Auth::hasSpecial('manage_leads');
if (!$canManage) {
    http_response_code(403);
    die('Access denied: you need the manage_leads permission.');
}
$action = (string) ($_POST['action'] ?? '');
$back = 'show_page.php?module=leads&page=leads';

/** Append an activity + refresh last_activity_on. */
function logLeadActivity(Database $db, int $leadId, string $type, string $note, int $actor): void
{
    $db->insert('tbl_lead_activities', [
        'lead_id' => $leadId,
        'type'    => $type,
        'note'    => $note,
        'added_by'=> $actor,
    ]);
    $db->update('tbl_leads', ['last_activity_on' => date('Y-m-d H:i:s'), 'updated_by' => $actor], '`id` = ?', [$leadId]);
}

try {
    if ($action === 'save_lead') {
        $id = (int) ($_POST['id'] ?? 0);
        $contactName = trim((string) ($_POST['contact_name'] ?? ''));
        if ($contactName === '') {
            setFlash('error', 'Contact name is required.');
            redirect($back);
        }
        $stage = (string) ($_POST['stage'] ?? 'New');
        if (!in_array($stage, ['New', 'Contacted', 'Qualified', 'Proposal', 'Won', 'Lost'], true)) {
            $stage = 'New';
        }
        $priority = (string) ($_POST['priority'] ?? 'Warm');
        if (!in_array($priority, ['Hot', 'Warm', 'Cold'], true)) {
            $priority = 'Warm';
        }
        $source = (string) ($_POST['source'] ?? 'Website');
        if (!in_array($source, ['Website', 'Phone', 'Email', 'Walk-in', 'Referral', 'Social', 'Other'], true)) {
            $source = 'Website';
        }
        $data = [
            'source'            => $source,
            'company'           => trim((string) ($_POST['company'] ?? '')) ?: null,
            'contact_name'      => $contactName,
            'email'             => trim((string) ($_POST['email'] ?? '')) ?: null,
            'phone'             => trim((string) ($_POST['phone'] ?? '')) ?: null,
            'service_interest'  => trim((string) ($_POST['service_interest'] ?? '')) ?: null,
            'message'           => trim((string) ($_POST['message'] ?? '')) ?: null,
            'priority'          => $priority,
            'estimated_value'   => ($_POST['estimated_value'] ?? '') !== '' ? round((float) $_POST['estimated_value'], 4) : null,
            'stage'             => $stage,
            'assigned_to'       => (int) ($_POST['assigned_to'] ?? 0) ?: null,
            'lost_reason'       => $stage === 'Lost' ? (trim((string) ($_POST['lost_reason'] ?? '')) ?: null) : null,
            'updated_by'        => $me,
        ];

        if ($id) {
            $existing = $db->selectOne('SELECT * FROM `tbl_leads` WHERE `id` = ?', [$id]);
            if (!$existing) {
                setFlash('error', 'Lead not found.');
                redirect($back);
            }
            if ($data['stage'] !== $existing['stage']) {
                logLeadActivity($db, $id, 'Status Change', 'Stage changed from ' . $existing['stage'] . ' to ' . $data['stage'], $me);
            } elseif (!empty($data['assigned_to']) && (int) $data['assigned_to'] !== (int) $existing['assigned_to']) {
                logLeadActivity($db, $id, 'Note', 'Owner reassigned', $me);
            }
            $db->update('tbl_leads', $data, '`id` = ?', [$id]);
            setFlash('success', 'Lead updated.');
        } else {
            $newId = $db->insert('tbl_leads', array_merge($data, ['added_by' => $me]));
            logLeadActivity($db, $newId, 'Note', 'Lead created (source: ' . $source . ')', $me);
            setFlash('success', 'Lead created.');
        }
        redirect($back);
    }

    if ($action === 'update_lead') {
        $id = (int) ($_POST['id'] ?? 0);
        $lead = $db->selectOne('SELECT * FROM `tbl_leads` WHERE `id` = ?', [$id]);
        if (!$lead) {
            setFlash('error', 'Lead not found.');
            redirect($back);
        }
        $stage = (string) ($_POST['stage'] ?? $lead['stage']);
        if (!in_array($stage, ['New', 'Contacted', 'Qualified', 'Proposal', 'Won', 'Lost'], true)) {
            $stage = $lead['stage'];
        }
        $priority = (string) ($_POST['priority'] ?? $lead['priority']);
        if (!in_array($priority, ['Hot', 'Warm', 'Cold'], true)) {
            $priority = $lead['priority'];
        }
        $assigned = (int) ($_POST['assigned_to'] ?? 0) ?: null;
        $lostReason = $stage === 'Lost' ? (trim((string) ($_POST['lost_reason'] ?? '')) ?: $lead['lost_reason']) : null;

        $log = [];
        if ($stage !== $lead['stage']) {
            $log[] = 'Stage: ' . $lead['stage'] . ' → ' . $stage;
        }
        if ((int) $assigned !== (int) $lead['assigned_to']) {
            $log[] = 'Owner reassigned';
        }
        if ($stage === 'Lost' && $lostReason) {
            $log[] = 'Lost reason: ' . $lostReason;
        }
        $db->update('tbl_leads', [
            'stage' => $stage, 'priority' => $priority, 'assigned_to' => $assigned,
            'lost_reason' => $lostReason, 'updated_by' => $me,
        ], '`id` = ?', [$id]);
        if ($log) {
            logLeadActivity($db, $id, 'Status Change', implode('; ', $log), $me);
        } else {
            $db->update('tbl_leads', ['updated_by' => $me], '`id` = ?', [$id]);
        }
        setFlash('success', 'Lead updated.');
        redirect($back . '&id=' . $id);
    }

    if ($action === 'delete_lead') {
        $id = (int) ($_POST['id'] ?? 0);
        foreach ($db->select('SELECT `file_location` FROM `tbl_lead_files` WHERE `lead_id` = ?', [$id]) as $f) {
            if (!empty($f['file_location'])) {
                $path = dirname(__DIR__, 3) . '/user_uploads/' . $f['file_location'];
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
        $db->delete('tbl_lead_files', '`lead_id` = ?', [$id]);
        $db->delete('tbl_leads', '`id` = ?', [$id]);
        setFlash('success', 'Lead deleted.');
        redirect($back);
    }

    if ($action === 'add_activity') {
        $id = (int) ($_POST['id'] ?? 0);
        $type = (string) ($_POST['type'] ?? 'Note');
        if (!in_array($type, ['Call', 'Email', 'Note', 'Meeting'], true)) {
            $type = 'Note';
        }
        $note = trim((string) ($_POST['note'] ?? ''));
        if ($note === '') {
            setFlash('error', 'Activity note is required.');
            redirect($back . '&id=' . $id);
        }
        logLeadActivity($db, $id, $type, $note, $me);
        setFlash('success', 'Activity logged.');
        redirect($back . '&id=' . $id);
    }

    if ($action === 'add_lead_file') {
        $id = (int) ($_POST['id'] ?? 0);
        if (!empty($_FILES['lead_file']['name'])) {
            $up = validateUpload($_FILES['lead_file']);
            if (!$up['ok']) {
                setFlash('error', $up['message']);
                redirect($back . '&id=' . $id);
            }
            $loc = storeUpload($_FILES['lead_file'], 'leads', $up['extension']);
            if ($loc) {
                $db->insert('tbl_lead_files', [
                    'lead_id' => $id, 'file_location' => $loc,
                    'file_name' => basename((string) $_FILES['lead_file']['name']),
                    'file_extension' => $up['extension'], 'file_size' => (int) $_FILES['lead_file']['size'],
                    'added_by' => $me,
                ]);
                logLeadActivity($db, $id, 'Note', 'File attached', $me);
                setFlash('success', 'File attached.');
            }
        }
        redirect($back . '&id=' . $id);
    }

    if ($action === 'create_followup') {
        $id = (int) ($_POST['id'] ?? 0);
        $lead = $db->selectOne('SELECT * FROM `tbl_leads` WHERE `id` = ?', [$id]);
        if (!$lead) {
            setFlash('error', 'Lead not found.');
            redirect($back);
        }
        $deadlineRaw = trim((string) ($_POST['deadline'] ?? ''));
        $deadline = $deadlineRaw !== '' ? date('Y-m-d H:i:s', strtotime($deadlineRaw)) : null;
        if (!$deadline) {
            setFlash('error', 'A follow-up deadline is required.');
            redirect($back . '&id=' . $id);
        }
        $assigned = (int) ($_POST['assigned_to'] ?? 0) ?: ($lead['assigned_to'] ?: null);
        $note = trim((string) ($_POST['note'] ?? '')) ?: 'Follow-up on lead: ' . ($lead['company'] ?: $lead['contact_name']);
        $title = 'Follow-up: ' . ($lead['company'] ?: $lead['contact_name']);

        $taskId = $db->insert('tbl_office_tasks', [
            'assignment_id' => uniqid('asn'),
            'title' => $title, 'description' => $note . ' (lead #' . $id . ')',
            'author' => $me, 'deadline' => $deadline, 'status' => 'Pending',
            'added_by' => $me,
        ]);
        if ($assigned) {
            $db->insert('tbl_office_task_assignees', ['task_id' => $taskId, 'staff_id' => $assigned, 'status' => 'Pending']);
            notifyUser($assigned, 'Follow-up task: "' . e($title) . '".', 'Task', (string) $taskId, $me);
        }
        logLeadActivity($db, $id, 'Task', 'Follow-up task created (due ' . e(date('M j, g:i A', strtotime($deadline))) . ')' . ($assigned ? ', assigned' : ''), $me);
        setFlash('success', 'Follow-up task created.');
        redirect($back . '&id=' . $id);
    }

    if ($action === 'convert_lead') {
        $id = (int) ($_POST['id'] ?? 0);
        $lead = $db->selectOne('SELECT * FROM `tbl_leads` WHERE `id` = ?', [$id]);
        if (!$lead) {
            setFlash('error', 'Lead not found.');
            redirect($back);
        }
        if ($lead['stage'] !== 'Won') {
            setFlash('error', 'Only Won leads can be converted to clients.');
            redirect($back . '&id=' . $id);
        }
        if ($lead['won_client_id']) {
            setFlash('error', 'This lead is already converted to a client.');
            redirect($back . '&id=' . $id);
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            setFlash('error', 'Client name is required.');
            redirect($back . '&id=' . $id);
        }
        $clientId = $db->insert('tbl_clients', [
            'name'           => $name,
            'contact_person' => trim((string) ($_POST['contact_person'] ?? '')) ?: $lead['contact_name'],
            'email'          => $lead['email'],
            'phone'          => $lead['phone'],
            'address'        => trim((string) ($_POST['address'] ?? '')) ?: null,
            'pan_num'        => trim((string) ($_POST['pan_num'] ?? '')) ?: null,
            'lead_id'        => $id,
            'added_by'       => $me,
        ]);
        $db->update('tbl_leads', ['won_client_id' => $clientId, 'updated_by' => $me], '`id` = ?', [$id]);
        logLeadActivity($db, $id, 'Status Change', 'Lead converted to client: ' . $name, $me);
        setFlash('success', 'Client created from lead.');
        redirect($back . '&id=' . $id);
    }

    if ($action === 'reopen_lead') {
        $id = (int) ($_POST['id'] ?? 0);
        $lead = $db->selectOne('SELECT * FROM `tbl_leads` WHERE `id` = ?', [$id]);
        if (!$lead || $lead['stage'] !== 'Lost') {
            setFlash('error', 'Only Lost leads can be reopened.');
        } else {
            $db->update('tbl_leads', ['stage' => 'Contacted', 'lost_reason' => null, 'updated_by' => $me], '`id` = ?', [$id]);
            logLeadActivity($db, $id, 'Status Change', 'Lead reopened (Lost → Contacted)', $me);
            setFlash('success', 'Lead reopened.');
        }
        redirect($back . '&id=' . $id);
    }

    if ($action === 'merge_leads') {
        $keepId = (int) ($_POST['keep_id'] ?? 0);
        $mergeId = (int) ($_POST['merge_id'] ?? 0);
        if (!$keepId || !$mergeId || $keepId === $mergeId) {
            setFlash('error', 'Invalid merge selection.');
            redirect($back);
        }
        $keep = $db->selectOne('SELECT * FROM `tbl_leads` WHERE `id` = ?', [$keepId]);
        $merge = $db->selectOne('SELECT * FROM `tbl_leads` WHERE `id` = ?', [$mergeId]);
        if (!$keep || !$merge) {
            setFlash('error', 'One of the leads no longer exists.');
            redirect($back);
        }
        $db->update('tbl_lead_activities', ['lead_id' => $keepId], '`lead_id` = ?', [$mergeId]);
        $db->update('tbl_lead_files', ['lead_id' => $keepId], '`lead_id` = ?', [$mergeId]);
        $db->update('tbl_cms_contacts_us', ['lead_id' => $keepId], '`lead_id` = ?', [$mergeId]);
        logLeadActivity($db, $keepId, 'Note', 'Merged duplicate lead #' . $mergeId, $me);
        $db->delete('tbl_leads', '`id` = ?', [$mergeId]);
        setFlash('success', 'Duplicate merged into this lead.');
        redirect($back . '&id=' . $keepId);
    }

    if ($action === 'export_leads') {
        $rows = $db->select(
            'SELECT l.*, o.fullname AS owner_name FROM `tbl_leads` l
             LEFT JOIN `tbl_users_login` o ON o.id = l.assigned_to
             ORDER BY l.added_on DESC'
        );
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="leads_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Company', 'Contact', 'Email', 'Phone', 'Interest', 'Priority', 'Value', 'Stage', 'Source', 'Owner', 'Added']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['company'], $r['contact_name'], $r['email'], $r['phone'],
                $r['service_interest'], $r['priority'], (float) $r['estimated_value'],
                $r['stage'], $r['source'], $r['owner_name'], $r['added_on'],
            ]);
        }
        fclose($out);
        exit;
    }

    setFlash('error', 'Unknown lead action.');
    redirect($back);
} catch (Throwable $e) {
    setFlash('error', 'Lead operation failed: ' . $e->getMessage());
    redirect($back);
}
