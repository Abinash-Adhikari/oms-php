<?php
/**
 * SB-Tech — Payroll / Advances writes (PRG): create, update, close, cancel.
 */
$back = pageUrl('payroll', 'advances');
if (!Auth::hasSpecial('manage_payroll')) {
    setFlash('error', 'You need the Manage payroll permission.');
    redirect($back);
    exit;
}
$db = Database::instance();
$me = (int) Auth::id();
$action = (string) ($_POST['action'] ?? '');
$id = (int) ($_POST['id'] ?? 0);

try {
    switch ($action) {
        case 'create':
            $staffId = (int) ($_POST['staff_id'] ?? 0);
            $amount = round((float) ($_POST['amount'] ?? 0), 2);
            $inst = round((float) ($_POST['monthly_installment'] ?? 0), 2);
            $issued = (string) ($_POST['issued_on'] ?? '');
            $start = (string) ($_POST['start_period_key'] ?? '');
            $title = trim((string) ($_POST['title'] ?? '')) ?: 'Salary advance';
            if (!$db->selectOne("SELECT id FROM tbl_users_login WHERE id = ? AND status = 'Active'", [$staffId])) {
                throw new RuntimeException('Choose an active staff member.');
            }
            if ($amount <= 0 || $inst <= 0) {
                throw new RuntimeException('Amount and monthly recovery must be above 0.');
            }
            if ($inst > $amount) {
                $inst = $amount;
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $issued) || $issued > date('Y-m-d')) {
                throw new RuntimeException('The issue date must be today or earlier.');
            }
            $row = [
                'staff_id' => $staffId, 'title' => mb_substr($title, 0, 191), 'amount' => $amount, 'monthly_installment' => $inst,
                'issued_on' => $issued, 'start_period_key' => preg_match('/^\d{4}-\d{2}$/', $start) ? $start : null,
                'status' => 'Active', 'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null, 'added_by' => $me,
            ];
            $db->transaction(function (Database $db) use ($row, $me): void {
                $advId = $db->insert('tbl_staff_advances', $row);
                if (!empty($_POST['record_payment'])) {
                    (new PayrollPostingService($db))->advancePayment($advId, (int) ($_POST['payment_terminal_id'] ?? 0), $row['issued_on'], $me);
                }
                auditLog('payroll', 'advance_created', 'staff_advance', $advId, null, $row);
            });
            setFlash('success', 'Advance created — it will be recovered through payroll' . (!empty($_POST['record_payment']) ? ', and a Pending payment voucher was added in Accounts.' : '.'));
            break;

        case 'update':
            $adv = $db->selectOne("SELECT * FROM tbl_staff_advances WHERE id = ? AND status = 'Active'", [$id]);
            if (!$adv) {
                throw new RuntimeException('Active advance not found.');
            }
            $inst = round((float) ($_POST['monthly_installment'] ?? 0), 2);
            if ($inst <= 0) {
                throw new RuntimeException('Monthly recovery must be above 0.');
            }
            $new = ['title' => mb_substr(trim((string) ($_POST['title'] ?? '')) ?: $adv['title'], 0, 191),
                'monthly_installment' => min($inst, (float) $adv['amount']), 'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null, 'updated_by' => $me];
            $db->transaction(function (Database $db) use ($id, $adv, $new): void {
                $db->update('tbl_staff_advances', $new, '`id` = ?', [$id]);
                auditLog('payroll', 'advance_updated', 'staff_advance', $id, $adv, $new);
            });
            setFlash('success', 'Advance updated. Draft payroll runs pick this up when recalculated.');
            break;

        case 'close':
        case 'cancel':
            $adv = $db->selectOne("SELECT * FROM tbl_staff_advances WHERE id = ? AND status = 'Active'", [$id]);
            if (!$adv) {
                throw new RuntimeException('Active advance not found.');
            }
            $recovered = (float) $adv['amount'] - (new PayrollRunService($db))->advanceOutstanding($id);
            if ($action === 'cancel' && $recovered > 0.005) {
                throw new RuntimeException('Part of this advance is already recovered — close it instead of cancelling.');
            }
            $db->transaction(function (Database $db) use ($id, $adv, $action, $me): void {
                if ($action === 'cancel') {
                    (new PayrollPostingService($db))->removeAdvancePayment($id, $me);
                }
                $status = $action === 'cancel' ? 'Cancelled' : 'Closed';
                $db->update('tbl_staff_advances', ['status' => $status, 'updated_by' => $me], '`id` = ?', [$id]);
                auditLog('payroll', 'advance_' . strtolower($status), 'staff_advance', $id, ['status' => $adv['status']], ['status' => $status]);
            });
            setFlash('success', $action === 'cancel' ? 'Advance cancelled.' : 'Advance closed — no further recovery.');
            break;

        default:
            throw new RuntimeException('Unknown action.');
    }
} catch (RuntimeException $e) {
    setFlash('error', $e->getMessage());
}
redirect($back);
exit;
