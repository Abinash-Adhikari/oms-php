<?php
/**
 * SB-Tech — Accounts / Bank Reconciliation operations (US-FIN-05).
 * create_session / match_line / unmatch_line / close_session / delete_session.
 */
$db = Database::instance();
$me = (int) Auth::id();
$action = (string) ($_POST['action'] ?? '');
$back = pageUrl('accounts', 'bank_reconciliation');

try {
    if ($action === 'create_session') {
        $terminalId = (int) ($_POST['account_terminal_id'] ?? 0);
        $ref = trim((string) ($_POST['statement_ref'] ?? ''));
        $date = (string) ($_POST['statement_date'] ?? '');
        $fy = accountingCurrentOpenFy();
        if (!$terminalId || $ref === '') {
            setFlash('error', 'Bank account and statement reference are required.');
            redirect($back);
        }
        if (!$fy) {
            setFlash('error', 'No open fiscal year exists.');
            redirect($back);
        }
        $id = $db->insert('tbl_bank_reconciliation', [
            'fiscal_year_id'        => (int) $fy['id'],
            'account_terminal_id'   => $terminalId,
            'statement_ref'         => $ref,
            'statement_date'        => $date ?: null,
            'opening_balance'       => round((float) ($_POST['opening_balance'] ?? 0), 4),
            'total_statement_amount'=> round((float) ($_POST['total_statement_amount'] ?? 0), 4),
            'total_matched_amount'  => 0.0000,
            'status'                => 'Open',
            'remarks'               => trim((string) ($_POST['remarks'] ?? '')) ?: null,
            'added_by'              => $me,
        ]);
        setFlash('success', 'Reconciliation session created.');
        redirect($back . '&session_id=' . $id);
    }

    if ($action === 'match_line') {
        $id = (int) ($_POST['id'] ?? 0);
        $lineId = (int) ($_POST['line_id'] ?? 0);
        $session = $db->selectOne('SELECT * FROM `tbl_bank_reconciliation` WHERE `id` = ?', [$id]);
        if (!$session || $session['status'] === 'Closed') {
            setFlash('error', 'Session not found or closed.');
            redirect($back);
        }
        $line = $db->selectOne('SELECT * FROM `tbl_ledger_particulars` WHERE `id` = ?', [$lineId]);
        if (!$line || (int) $line['account_terminal_id'] !== (int) $session['account_terminal_id']) {
            setFlash('error', 'Ledger line not found for this terminal.');
            redirect($back);
        }
        if (!empty($line['reconcile_ref'])) {
            setFlash('error', 'Line is already reconciled to ' . $line['reconcile_ref'] . '.');
            redirect($back . '&session_id=' . $id);
        }
        $amount = (float) $line['debit'] + (float) $line['credit'];
        $db->update('tbl_ledger_particulars', [
            'reconcile_ref' => $session['statement_ref'],
            'reconciled_on' => date('Y-m-d H:i:s'),
            'reconciled_by' => $me,
        ], '`id` = ?', [$lineId]);
        $newMatched = round((float) $session['total_matched_amount'] + $amount, 4);
        $status = $newMatched >= (float) $session['total_statement_amount'] - 0.01 ? 'Matched' : $session['status'];
        $db->update('tbl_bank_reconciliation', [
            'total_matched_amount' => $newMatched,
            'status' => $status,
            'updated_by' => $me,
        ], '`id` = ?', [$id]);
        setFlash('success', 'Line matched (' . ($status === 'Matched' ? 'session fully matched' : '') . ').');
        redirect($back . '&session_id=' . $id);
    }

    if ($action === 'unmatch_line') {
        $id = (int) ($_POST['id'] ?? 0);
        $lineId = (int) ($_POST['line_id'] ?? 0);
        $session = $db->selectOne('SELECT * FROM `tbl_bank_reconciliation` WHERE `id` = ?', [$id]);
        if (!$session || $session['status'] === 'Closed') {
            setFlash('error', 'Session not found or closed.');
            redirect($back);
        }
        $line = $db->selectOne('SELECT * FROM `tbl_ledger_particulars` WHERE `id` = ?', [$lineId]);
        if (!$line || (string) ($line['reconcile_ref'] ?? '') !== (string) $session['statement_ref']) {
            setFlash('error', 'Line is not matched to this session.');
            redirect($back . '&session_id=' . $id);
        }
        $amount = (float) $line['debit'] + (float) $line['credit'];
        $db->update('tbl_ledger_particulars', [
            'reconcile_ref' => null, 'reconciled_on' => null, 'reconciled_by' => null,
        ], '`id` = ?', [$lineId]);
        $db->update('tbl_bank_reconciliation', [
            'total_matched_amount' => round(max(0, (float) $session['total_matched_amount'] - $amount), 4),
            'status' => 'Open',
            'updated_by' => $me,
        ], '`id` = ?', [$id]);
        setFlash('success', 'Line un-matched.');
        redirect($back . '&session_id=' . $id);
    }

    if ($action === 'close_session') {
        $id = (int) ($_POST['id'] ?? 0);
        $db->update('tbl_bank_reconciliation', ['status' => 'Closed', 'updated_by' => $me], '`id` = ?', [$id]);
        setFlash('success', 'Reconciliation closed.');
        redirect($back . '&session_id=' . $id);
    }

    if ($action === 'delete_session') {
        $id = (int) ($_POST['id'] ?? 0);
        $session = $db->selectOne('SELECT * FROM `tbl_bank_reconciliation` WHERE `id` = ?', [$id]);
        if ($session) {
            // Scope the un-reconcile to this session's account + ref to avoid
            // un-reconciling lines from other sessions with the same statement_ref.
            $db->execute(
                'UPDATE `tbl_ledger_particulars` SET `reconcile_ref` = NULL, `reconciled_on` = NULL, `reconciled_by` = NULL
                 WHERE `reconcile_ref` = ? AND `account_terminal_id` = ?',
                [$session['statement_ref'], (int) $session['account_terminal_id']]
            );
            $db->delete('tbl_bank_reconciliation', '`id` = ?', [$id]);
        }
        setFlash('success', 'Session deleted; lines un-reconciled.');
        redirect($back);
    }

    setFlash('error', 'Unknown bank reconciliation action.');
    redirect($back);
} catch (Throwable $e) {
    setFlash('error', 'Bank reconciliation operation failed: ' . $e->getMessage());
    redirect($back);
}
