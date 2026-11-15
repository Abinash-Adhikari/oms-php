<?php
/**
 * SB-Tech — Correspondence / Darta operations.
 * save (create + edit) / dispose / cancel — PRG, no delete (Cancelled
 * replaces it so the register number sequence keeps no gaps).
 */
$db = Database::instance();
$action = (string) ($_POST['action'] ?? '');
$back = pageUrl('correspondence', 'darta');

require_once __DIR__ . '/../includes/uploads.php';

try {
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $fyId = (int) ($_POST['fiscal_year_id'] ?? 0);
        $senderRefNo = trim((string) ($_POST['sender_ref_no'] ?? ''));
        $senderChalaniNo = trim((string) ($_POST['sender_chalani_no'] ?? ''));
        $letterDate = normalizeDateInput(trim((string) ($_POST['letter_date'] ?? '')));
        $senderType = (string) ($_POST['sender_type'] ?? 'Person');
        $senderName = trim((string) ($_POST['sender_name'] ?? ''));
        $senderAddress = trim((string) ($_POST['sender_address'] ?? ''));
        $receivedVia = (string) ($_POST['received_via'] ?? 'Hand');
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $summary = trim((string) ($_POST['summary'] ?? ''));
        $priority = (string) ($_POST['priority'] ?? 'Normal') === 'Urgent' ? 'Urgent' : 'Normal';
        $assignedTo = (int) ($_POST['assigned_to'] ?? 0) ?: null;

        $back .= $id > 0 ? '&edit_id=' . $id : '&new=1';

        if ($senderName === '' || $subject === '' || $letterDate === null || $letterDate === ''
            || strtotime($letterDate) === false) {
            setFlash('error', 'Sender name, subject and a valid letter date are required'
                . (useBsDates() ? ' (B.S. — YYYY-MM-DD)' : '') . '.');
            redirect($back);
        }
        if (!in_array($senderType, ['Office', 'Person', 'Other'], true)) {
            $senderType = 'Person';
        }
        if (!in_array($receivedVia, ['Hand', 'Post', 'Email', 'Courier', 'Other'], true)) {
            $receivedVia = 'Hand';
        }
        if (!$id && correspondence_current_fy($db) === null) {
            setFlash('error', 'No fiscal year covers today — darta numbers cannot be allocated. Create/open one under Setup → Fiscal Years.');
            redirect($back);
        }
        $fyRow = $fyId > 0 ? $db->selectOne('SELECT * FROM `tbl_fiscal_years` WHERE `id` = ?', [$fyId]) : null;
        if ($fyRow === null) {
            setFlash('error', 'Select a valid fiscal year (Setup → Fiscal Years).');
            redirect($back);
        }
        if (!$id && ($fyRow['closing'] ?? '') !== 'Open') {
            setFlash('error', 'Fiscal year ' . $fyRow['title'] . ' is closed — new darta numbers can only be allocated into an open fiscal year. Open it under Setup → Fiscal Years.');
            redirect($back);
        }
        // P2 attachments: reject a bad file before a number is allocated.
        $upErr = correspondence_validate_uploads();
        if ($upErr !== null) {
            setFlash('error', 'Attachment: ' . $upErr);
            redirect($back);
        }

        $data = [
            'sender_ref_no'     => $senderRefNo !== '' ? $senderRefNo : null,
            'sender_chalani_no' => $senderChalaniNo !== '' ? $senderChalaniNo : null,
            'letter_date'       => date('Y-m-d', strtotime($letterDate)),
            'sender_type'       => $senderType,
            'sender_name'       => $senderName,
            'sender_address'    => $senderAddress !== '' ? $senderAddress : null,
            'received_via'      => $receivedVia,
            'subject'           => $subject,
            'summary'           => $summary !== '' ? $summary : null,
            'priority'          => $priority,
            'assigned_to'       => $assignedTo,
            'updated_by'        => Auth::id(),
        ];

        if ($id > 0) {
            $old = $db->selectOne(
                'SELECT d.*, fy.`title` AS fy_title FROM `tbl_darta_entries` d
                 JOIN `tbl_fiscal_years` fy ON fy.`id` = d.`fiscal_year_id` WHERE d.`id` = ?',
                [$id]
            );
            if ($old === null) {
                setFlash('error', 'Darta entry not found.');
                redirect(pageUrl('correspondence', 'darta'));
            }
            if (!in_array($old['status'], ['Pending', 'In Progress'], true)) {
                setFlash('error', 'Darta ' . correspondence_display_no('darta', (int) $old['darta_no'], (string) $old['fy_title']) . ' is ' . $old['status'] . ' and can no longer be edited.');
                redirect($back);
            }
            $db->update('tbl_darta_entries', $data, '`id` = ?', [$id]);
            $upErr = correspondence_store_uploads($db, 'darta', $id, Auth::id());
            if ($upErr !== null) {
                setFlash('error', 'Attachment upload failed: ' . $upErr . ' — nothing was saved.');
                redirect($back);
            }
            auditLog('correspondence', 'update', 'darta', $id, $old, $data);
            setFlash('success', 'Darta ' . correspondence_display_no('darta', (int) $old['darta_no'], (string) $old['fy_title']) . ' updated.');
            // P3 §7: tell the new handler when the assignment changes.
            if ($assignedTo !== null && $assignedTo !== (int) ($old['assigned_to'] ?? 0) && $assignedTo !== Auth::id()) {
                notifyUser(
                    $assignedTo,
                    'Darta ' . correspondence_display_no('darta', (int) $old['darta_no'], (string) $old['fy_title'])
                        . ' assigned to you: ' . e($subject) . '.',
                    'Correspondence',
                    (string) $id,
                    Auth::id(),
                    pageUrl('correspondence', 'darta') . '&edit_id=' . $id
                );
            }
            redirect($back);
        }

        // New entry — allocate the number inside the ambient transaction.
        $dartaNo = correspondence_allocate_no($db, 'darta', (int) $fyRow['id']);
        $data += [
            'fiscal_year_id' => (int) $fyRow['id'],
            'darta_no'       => $dartaNo,
            'received_on'    => date('Y-m-d H:i:s'),
            'status'         => 'Pending',
            'added_by'       => Auth::id(),
        ];
        $newId = $db->insert('tbl_darta_entries', $data);
        $upErr = correspondence_store_uploads($db, 'darta', $newId, Auth::id());
        if ($upErr !== null) {
            setFlash('error', 'Attachment upload failed: ' . $upErr . ' — nothing was saved.');
            redirect(pageUrl('correspondence', 'darta'));
        }
        auditLog('correspondence', 'create', 'darta', $newId, null, $data);
        // P3 §7: a darta registered straight onto a handler notifies them.
        if ($assignedTo !== null && $assignedTo !== Auth::id()) {
            notifyUser(
                $assignedTo,
                'Darta ' . correspondence_display_no('darta', $dartaNo, (string) $fyRow['title'])
                    . ' assigned to you: ' . e($subject) . '.',
                'Correspondence',
                (string) $newId,
                Auth::id(),
                pageUrl('correspondence', 'darta') . '&edit_id=' . $newId
            );
        }
        setFlash('success', 'Registered as ' . correspondence_display_no('darta', $dartaNo, (string) $fyRow['title']) . '.');
        redirect(pageUrl('correspondence', 'darta'));
    }

    if ($action === 'dispose') {
        $id = (int) ($_POST['id'] ?? 0);
        $outcome = (string) ($_POST['outcome'] ?? '');
        $note = trim((string) ($_POST['disposal_note'] ?? ''));
        $reserveReply = (string) ($_POST['reserve_reply'] ?? '') === '1';

        if (!in_array($outcome, ['Disposed', 'Filed'], true)) {
            setFlash('error', 'Choose a disposal outcome (Disposed or Filed).');
            redirect($back);
        }
        $old = $db->selectOne(
            'SELECT d.*, fy.`title` AS fy_title FROM `tbl_darta_entries` d
             JOIN `tbl_fiscal_years` fy ON fy.`id` = d.`fiscal_year_id` WHERE d.`id` = ?',
            [$id]
        );
        if ($old === null) {
            setFlash('error', 'Darta entry not found.');
            redirect(pageUrl('correspondence', 'darta'));
        }
        if (!in_array($old['status'], ['Pending', 'In Progress'], true)) {
            setFlash('error', 'Only pending or in-progress darta can be disposed.');
            redirect($back);
        }

        $data = [
            'status'        => $outcome,
            'disposal_note' => $note !== '' ? $note : ($old['disposal_note'] ?? null),
            'disposed_on'   => date('Y-m-d H:i:s'),
            'disposed_by'   => Auth::id(),
            'updated_by'    => Auth::id(),
        ];
        $db->update('tbl_darta_entries', $data, '`id` = ?', [$id]);
        auditLog('correspondence', 'dispose', 'darta', $id, $old, $data);
        setFlash('success', 'Darta ' . correspondence_display_no('darta', (int) $old['darta_no'], (string) $old['fy_title']) . ' marked ' . $outcome . '.');
        // P3 §7: the registrar and the handler learn their darta was closed.
        $disposeLabel = correspondence_display_no('darta', (int) $old['darta_no'], (string) $old['fy_title']);
        foreach (correspondence_dispose_recipients($old, (int) Auth::id()) as $notifyId) {
            notifyUser(
                $notifyId,
                'Darta ' . $disposeLabel . ' was marked ' . e($outcome) . '.',
                'Correspondence',
                (string) $id,
                Auth::id(),
                pageUrl('correspondence', 'darta') . '&edit_id=' . $id
            );
        }

        if ($reserveReply) {
            // Open the chalani form pre-linked (D7: number first, then write the letter).
            redirect(pageUrl('correspondence', 'chalani') . '&ref_darta=' . $id);
        }
        redirect(pageUrl('correspondence', 'darta'));
    }

    if ($action === 'cancel') {
        $id = (int) ($_POST['id'] ?? 0);
        $old = $db->selectOne(
            'SELECT d.*, fy.`title` AS fy_title FROM `tbl_darta_entries` d
             JOIN `tbl_fiscal_years` fy ON fy.`id` = d.`fiscal_year_id` WHERE d.`id` = ?',
            [$id]
        );
        if ($old === null) {
            setFlash('error', 'Darta entry not found.');
            redirect(pageUrl('correspondence', 'darta'));
        }
        if (!in_array($old['status'], ['Pending', 'In Progress'], true)) {
            setFlash('error', 'Only pending or in-progress darta can be cancelled.');
            redirect($back);
        }

        $data = [
            'status'      => 'Cancelled',
            'disposed_on' => date('Y-m-d H:i:s'),
            'disposed_by' => Auth::id(),
            'updated_by'  => Auth::id(),
        ];
        $db->update('tbl_darta_entries', $data, '`id` = ?', [$id]);
        auditLog('correspondence', 'cancel', 'darta', $id, $old, $data);
        setFlash('success', 'Darta ' . correspondence_display_no('darta', (int) $old['darta_no'], (string) $old['fy_title']) . ' cancelled — its number stays visible in the register.');
        redirect(pageUrl('correspondence', 'darta'));
    }

    if ($action === 'delete_file') {
        $fileId = (int) ($_POST['file_id'] ?? 0);
        $file = $db->selectOne(
            "SELECT * FROM `tbl_correspondence_files` WHERE `id` = ? AND `register` = 'darta'",
            [$fileId]
        );
        if ($file === null) {
            setFlash('error', 'Attachment not found.');
            redirect(pageUrl('correspondence', 'darta'));
        }
        $entryId = (int) $file['entry_id'];
        $entry = $db->selectOne('SELECT `id`, `status` FROM `tbl_darta_entries` WHERE `id` = ?', [$entryId]);
        if ($entry === null) {
            setFlash('error', 'Darta entry not found.');
            redirect(pageUrl('correspondence', 'darta'));
        }
        if (!in_array($entry['status'], ['Pending', 'In Progress'], true)) {
            setFlash('error', 'Attachments of a ' . $entry['status'] . ' darta cannot be removed.');
            redirect(pageUrl('correspondence', 'darta') . '&edit_id=' . $entryId);
        }
        if (!empty($file['file_location'])) {
            $path = dirname(__DIR__, 4) . '/user_uploads/' . $file['file_location'];
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $db->delete('tbl_correspondence_files', '`id` = ?', [$fileId]);
        auditLog('correspondence', 'delete_file', 'darta', $entryId, $file, null);
        setFlash('success', 'Attachment removed.');
        redirect(pageUrl('correspondence', 'darta') . '&edit_id=' . $entryId);
    }

    setFlash('error', 'Unknown action.');
    redirect(pageUrl('correspondence', 'darta'));
} catch (Throwable $e) {
    setFlash('error', 'Darta operation failed: ' . $e->getMessage());
    redirect($back);
    exit;
}
