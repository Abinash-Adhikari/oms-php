<?php
/**
 * SB-Tech — Correspondence / Chalani operations.
 * save (reserve number + create/edit) / dispatch / cancel — PRG, no delete
 * (Cancelled keeps the number visible in the register).
 */
$db = Database::instance();
$action = (string) ($_POST['action'] ?? '');
$back = pageUrl('correspondence', 'chalani');

require_once __DIR__ . '/../includes/uploads.php';

try {
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $fyId = (int) ($_POST['fiscal_year_id'] ?? 0);
        $letterDate = normalizeDateInput(trim((string) ($_POST['letter_date'] ?? '')));
        $recipientType = (string) ($_POST['recipient_type'] ?? 'Office');
        $recipientName = trim((string) ($_POST['recipient_name'] ?? ''));
        $recipientAddress = trim((string) ($_POST['recipient_address'] ?? ''));
        $ccRecipients = trim((string) ($_POST['cc_recipients'] ?? ''));
        $dispatchMode = (string) ($_POST['dispatch_mode'] ?? 'Hand');
        $subject = trim((string) ($_POST['subject'] ?? ''));
        // Rich text (Summernote): strip unsafe tags/attributes before storing —
        // it is reloaded into the editor as live HTML on edit.
        $description = trim(sanitizeRichHtml(trim((string) ($_POST['description'] ?? ''))));
        if ($description !== '' && trim(strip_tags($description)) === '' && stripos($description, '<img') === false) {
            $description = '';
        }
        $refDartaId = (int) ($_POST['ref_darta_id'] ?? 0) ?: null;

        $back .= $id > 0 ? '&edit_id=' . $id : '&new=1';

        if ($recipientName === '' || $subject === '' || $letterDate === null || $letterDate === ''
            || strtotime($letterDate) === false) {
            setFlash('error', 'Recipient name, subject and a valid letter date are required'
                . (useBsDates() ? ' (B.S. — YYYY-MM-DD)' : '') . '.');
            redirect($back);
        }
        if (!in_array($recipientType, ['Office', 'Person', 'Other'], true)) {
            $recipientType = 'Office';
        }
        if (!in_array($dispatchMode, ['Hand', 'Post', 'Email', 'Courier', 'Other'], true)) {
            $dispatchMode = 'Hand';
        }
        if (!$id && correspondence_current_fy($db) === null) {
            setFlash('error', 'No fiscal year covers today — chalani numbers cannot be allocated. Create/open one under Setup → Fiscal Years.');
            redirect($back);
        }
        $fyRow = $fyId > 0 ? $db->selectOne('SELECT * FROM `tbl_fiscal_years` WHERE `id` = ?', [$fyId]) : null;
        if ($fyRow === null) {
            setFlash('error', 'Select a valid fiscal year (Setup → Fiscal Years).');
            redirect($back);
        }
        if (!$id && ($fyRow['closing'] ?? '') !== 'Open') {
            setFlash('error', 'Fiscal year ' . $fyRow['title'] . ' is closed — new chalani numbers can only be allocated into an open fiscal year. Open it under Setup → Fiscal Years.');
            redirect($back);
        }
        // P2 attachments: reject a bad file before a number is allocated.
        $upErr = correspondence_validate_uploads();
        if ($upErr !== null) {
            setFlash('error', 'Attachment: ' . $upErr);
            redirect($back);
        }
        if ($refDartaId !== null) {
            // Any darta that is not Cancelled may carry replies: the dispose
            // flow reserves the reply चलानी *after* the darta is closed, and
            // existing links must stay editable (D7/§6).
            $refDarta = $db->selectOne(
                "SELECT `id` FROM `tbl_darta_entries` WHERE `id` = ? AND `status` <> 'Cancelled'",
                [$refDartaId]
            );
            if ($refDarta === null) {
                setFlash('error', 'The linked darta was not found (or it was cancelled). Pick another darta, or unlink it.');
                $refDartaId = null;
                if ($id > 0) {
                    redirect($back);
                }
                redirect($back . '&ref_darta=0');
            }
        }

        $data = [
            'letter_date'      => date('Y-m-d', strtotime($letterDate)),
            'recipient_type'   => $recipientType,
            'recipient_name'   => $recipientName,
            'recipient_address'=> $recipientAddress !== '' ? $recipientAddress : null,
            'cc_recipients'    => $ccRecipients !== '' ? $ccRecipients : null,
            'dispatch_mode'    => $dispatchMode,
            'subject'          => $subject,
            'description'      => $description !== '' ? $description : null,
            'ref_darta_id'     => $refDartaId,
            'updated_by'       => Auth::id(),
        ];

        if ($id > 0) {
            $old = $db->selectOne(
                'SELECT c.*, fy.`title` AS fy_title FROM `tbl_chalani_entries` c
                 JOIN `tbl_fiscal_years` fy ON fy.`id` = c.`fiscal_year_id` WHERE c.`id` = ?',
                [$id]
            );
            if ($old === null) {
                setFlash('error', 'Chalani entry not found.');
                redirect(pageUrl('correspondence', 'chalani'));
            }
            if ($old['status'] !== 'Reserved') {
                setFlash('error', 'Chalani ' . correspondence_display_no('chalani', (int) $old['chalani_no'], (string) $old['fy_title']) . ' is ' . $old['status'] . ' and can no longer be edited.');
                redirect($back);
            }
            $db->update('tbl_chalani_entries', $data, '`id` = ?', [$id]);
            $upErr = correspondence_store_uploads($db, 'chalani', $id, Auth::id());
            if ($upErr !== null) {
                setFlash('error', 'Attachment upload failed: ' . $upErr . ' — nothing was saved.');
                redirect($back);
            }
            auditLog('correspondence', 'update', 'chalani', $id, $old, $data);
            setFlash('success', 'Chalani ' . correspondence_display_no('chalani', (int) $old['chalani_no'], (string) $old['fy_title']) . ' updated.');
            redirect($back);
        }

        // Reserve the number inside the ambient transaction (D7: number first).
        $chalaniNo = correspondence_allocate_no($db, 'chalani', (int) $fyRow['id']);
        $data += [
            'fiscal_year_id' => (int) $fyRow['id'],
            'chalani_no'     => $chalaniNo,
            'status'         => 'Reserved',
            'added_by'       => Auth::id(),
        ];
        $newId = $db->insert('tbl_chalani_entries', $data);
        $upErr = correspondence_store_uploads($db, 'chalani', $newId, Auth::id());
        if ($upErr !== null) {
            setFlash('error', 'Attachment upload failed: ' . $upErr . ' — nothing was saved.');
            redirect(pageUrl('correspondence', 'chalani'));
        }
        auditLog('correspondence', 'create', 'chalani', $newId, null, $data);
        setFlash('success', 'Chalani number ' . correspondence_display_no('chalani', $chalaniNo, (string) $fyRow['title']) . ' reserved — write it on the letter, then mark it dispatched.');
        redirect(pageUrl('correspondence', 'chalani'));
    }

    if ($action === 'dispatch') {
        $id = (int) ($_POST['id'] ?? 0);
        $dispatchedOn = normalizeDateInput(trim((string) ($_POST['dispatched_on'] ?? '')));
        $dispatchMode = (string) ($_POST['dispatch_mode'] ?? 'Hand');

        if ($dispatchedOn === null || $dispatchedOn === '' || strtotime($dispatchedOn) === false) {
            setFlash('error', 'A valid dispatch date is required' . (useBsDates() ? ' (B.S. — YYYY-MM-DD)' : '') . '.');
            redirect($back);
        }
        if (!in_array($dispatchMode, ['Hand', 'Post', 'Email', 'Courier', 'Other'], true)) {
            $dispatchMode = 'Hand';
        }
        $old = $db->selectOne(
            'SELECT c.*, fy.`title` AS fy_title FROM `tbl_chalani_entries` c
             JOIN `tbl_fiscal_years` fy ON fy.`id` = c.`fiscal_year_id` WHERE c.`id` = ?',
            [$id]
        );
        if ($old === null) {
            setFlash('error', 'Chalani entry not found.');
            redirect(pageUrl('correspondence', 'chalani'));
        }
        if ($old['status'] !== 'Reserved') {
            setFlash('error', 'Only reserved chalanis can be dispatched.');
            redirect($back);
        }

        $data = [
            'status'        => 'Dispatched',
            'dispatched_on' => date('Y-m-d', strtotime($dispatchedOn)) . ' ' . date('H:i:s'),
            'dispatch_mode' => $dispatchMode,
            'sent_by'       => Auth::id(),
            'updated_by'    => Auth::id(),
        ];
        $db->update('tbl_chalani_entries', $data, '`id` = ?', [$id]);
        auditLog('correspondence', 'dispatch', 'chalani', $id, $old, $data);
        $setFlashMsg = 'Chalani ' . correspondence_display_no('chalani', (int) $old['chalani_no'], (string) $old['fy_title']) . ' marked Dispatched.';

        // P2: optional offer on the dispatch form — mark the linked darta
        // Disposed too. Never automatic: an acknowledgement reply should not
        // close the darta by itself (docs/DARTA_CHALANI_PLAN.md §8 P2).
        if (($_POST['dispose_darta'] ?? '') === '1' && $old['ref_darta_id'] !== null) {
            $refDarta = $db->selectOne(
                'SELECT d.*, fy.`title` AS fy_title FROM `tbl_darta_entries` d
                 JOIN `tbl_fiscal_years` fy ON fy.`id` = d.`fiscal_year_id` WHERE d.`id` = ?',
                [(int) $old['ref_darta_id']]
            );
            if ($refDarta !== null && correspondence_can_dispose((string) $refDarta['status'])) {
                $refLabel = correspondence_display_no('darta', (int) $refDarta['darta_no'], (string) $refDarta['fy_title']);
                $chalaniLabel = correspondence_display_no('chalani', (int) $old['chalani_no'], (string) $old['fy_title']);
                $dartaData = [
                    'status'        => 'Disposed',
                    'disposal_note' => trim((string) ($refDarta['disposal_note'] ?? '')
                        . "\nDisposed on dispatch of reply chalani " . $chalaniLabel . '.'),
                    'disposed_on'   => date('Y-m-d H:i:s'),
                    'disposed_by'   => Auth::id(),
                    'updated_by'    => Auth::id(),
                ];
                $db->update('tbl_darta_entries', $dartaData, '`id` = ?', [(int) $refDarta['id']]);
                auditLog('correspondence', 'dispose', 'darta', (int) $refDarta['id'], $refDarta, $dartaData);
                $setFlashMsg .= ' Linked darta ' . $refLabel . ' marked Disposed.';
                // P3 §7: the registrar and handler of the disposed darta are told.
                foreach (correspondence_dispose_recipients($refDarta, (int) Auth::id()) as $notifyId) {
                    notifyUser(
                        $notifyId,
                        'Darta ' . $refLabel . ' was marked Disposed (reply ' . $chalaniLabel . ').',
                        'Correspondence',
                        (string) (int) $refDarta['id'],
                        Auth::id(),
                        pageUrl('correspondence', 'darta') . '&edit_id=' . (int) $refDarta['id']
                    );
                }
            } else {
                $setFlashMsg .= ' (Linked darta was not disposed — it is no longer pending.)';
            }
        }
        setFlash('success', $setFlashMsg);
        redirect(pageUrl('correspondence', 'chalani'));
    }

    if ($action === 'cancel') {
        $id = (int) ($_POST['id'] ?? 0);
        $old = $db->selectOne(
            'SELECT c.*, fy.`title` AS fy_title FROM `tbl_chalani_entries` c
             JOIN `tbl_fiscal_years` fy ON fy.`id` = c.`fiscal_year_id` WHERE c.`id` = ?',
            [$id]
        );
        if ($old === null) {
            setFlash('error', 'Chalani entry not found.');
            redirect(pageUrl('correspondence', 'chalani'));
        }
        if ($old['status'] !== 'Reserved') {
            setFlash('error', 'Only reserved chalanis can be cancelled (a dispatched letter is already out).');
            redirect($back);
        }

        $data = [
            'status'     => 'Cancelled',
            'updated_by' => Auth::id(),
        ];
        $db->update('tbl_chalani_entries', $data, '`id` = ?', [$id]);
        auditLog('correspondence', 'cancel', 'chalani', $id, $old, $data);
        setFlash('success', 'Chalani ' . correspondence_display_no('chalani', (int) $old['chalani_no'], (string) $old['fy_title']) . ' cancelled — its number stays visible in the register.');
        redirect(pageUrl('correspondence', 'chalani'));
    }

    if ($action === 'delete_file') {
        $fileId = (int) ($_POST['file_id'] ?? 0);
        $file = $db->selectOne(
            "SELECT * FROM `tbl_correspondence_files` WHERE `id` = ? AND `register` = 'chalani'",
            [$fileId]
        );
        if ($file === null) {
            setFlash('error', 'Attachment not found.');
            redirect(pageUrl('correspondence', 'chalani'));
        }
        $entryId = (int) $file['entry_id'];
        $entry = $db->selectOne('SELECT `id`, `status` FROM `tbl_chalani_entries` WHERE `id` = ?', [$entryId]);
        if ($entry === null) {
            setFlash('error', 'Chalani entry not found.');
            redirect(pageUrl('correspondence', 'chalani'));
        }
        if ($entry['status'] !== 'Reserved') {
            setFlash('error', 'Attachments of a ' . $entry['status'] . ' chalani cannot be removed.');
            redirect(pageUrl('correspondence', 'chalani') . '&edit_id=' . $entryId);
        }
        if (!empty($file['file_location'])) {
            $path = dirname(__DIR__, 4) . '/user_uploads/' . $file['file_location'];
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $db->delete('tbl_correspondence_files', '`id` = ?', [$fileId]);
        auditLog('correspondence', 'delete_file', 'chalani', $entryId, $file, null);
        setFlash('success', 'Attachment removed.');
        redirect(pageUrl('correspondence', 'chalani') . '&edit_id=' . $entryId);
    }

    setFlash('error', 'Unknown action.');
    redirect(pageUrl('correspondence', 'chalani'));
} catch (Throwable $e) {
    setFlash('error', 'Chalani operation failed: ' . $e->getMessage());
    redirect($back);
    exit;
}
