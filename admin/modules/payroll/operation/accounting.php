<?php
/**
 * SB-Tech — Payroll / Accounting Setup writes (PRG).
 */
$back = pageUrl('payroll', 'accounting');
if (!Auth::hasSpecial('manage_payroll')) {
    setFlash('error', 'You need the Manage payroll permission.');
    redirect($back);
    exit;
}
$db = Database::instance();
$me = (int) Auth::id();
$valid = array_map('intval', array_column($db->select('SELECT id FROM tbl_account_terminals'), 'id'));
$terminal = static fn ($v): ?int => in_array((int) $v, $valid, true) ? (int) $v : null;

$row = [];
foreach (['salary_expense_terminal_id', 'employer_contribution_terminal_id', 'staff_payable_terminal_id',
    'tds_payable_terminal_id', 'other_deductions_terminal_id', 'deductions_terminal_id', 'payment_terminal_id'] as $key) {
    $row[$key] = $terminal($_POST[$key] ?? 0);
}
$fundMap = [];
foreach ((array) ($_POST['fund'] ?? []) as $fundId => $tid) {
    $fundMap[(int) $fundId] = $terminal($tid);
}

$db->transaction(function (Database $db) use ($row, $fundMap, $me): void {
    $old = (new PayrollPostingService($db))->settings();
    $db->update('tbl_payroll_settings', $row + ['updated_by' => $me], '`id` = 1');
    foreach ($fundMap as $fundId => $tid) {
        $db->update('tbl_payroll_funds', ['payable_terminal_id' => $tid, 'updated_by' => $me], '`id` = ?', [$fundId]);
    }
    auditLog('payroll', 'accounting_updated', 'payroll_settings', 1, $old, $row + ['funds' => $fundMap]);
});
setFlash('success', 'Accounting setup saved.');
redirect($back);
exit;
