<?php
/**
 * SB-Tech — Payroll / Funds writes (PRG). Actions: save, delete.
 */
$back = pageUrl('payroll', 'funds');
if (!Auth::hasSpecial('manage_payroll')) {
    setFlash('error', 'You need the Manage payroll permission.');
    redirect($back);
    exit;
}
$db = Database::instance();
$action = (string) ($_POST['action'] ?? '');
$me = (int) Auth::id();
$pct = static fn ($v): float => max(0.0, min(100.0, (float) $v));

if ($action === 'save') {
    $id = (int) ($_POST['id'] ?? 0);
    $row = [
        'code'                       => strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($_POST['code'] ?? ''))),
        'name'                       => trim((string) ($_POST['name'] ?? '')),
        'employee_rate'              => $pct($_POST['employee_rate'] ?? 0),
        'employer_rate'              => $pct($_POST['employer_rate'] ?? 0),
        'contribution_base'          => ($_POST['contribution_base'] ?? '') === 'gross' ? 'gross' : 'basic',
        'max_base'                   => (float) ($_POST['max_base'] ?? 0) > 0 ? (float) $_POST['max_base'] : null,
        'tax_deductible'             => !empty($_POST['tax_deductible']) ? 1 : 0,
        'waives_social_security_tax' => !empty($_POST['waives_social_security_tax']) ? 1 : 0,
        'is_active'                  => !empty($_POST['is_active']) ? 1 : 0,
        'notes'                      => trim((string) ($_POST['notes'] ?? '')) ?: null,
    ];
    if ($row['code'] === '' || $row['name'] === '') {
        setFlash('error', 'Code and name are required.');
        redirect($back);
        exit;
    }
    // Payslip lines and postings refer to a fund by its code, so the code is
    // fixed once anyone is enrolled or any payroll has used it.
    if ($id > 0) {
        $current = $db->selectOne('SELECT `code` FROM `tbl_payroll_funds` WHERE `id` = ?', [$id]);
        if ($current && $current['code'] !== $row['code']) {
            $used = (int) ($db->selectOne('SELECT COUNT(*) AS c FROM `tbl_staff_fund_enrollments` WHERE `fund_id` = ?', [$id])['c'] ?? 0)
                + (int) ($db->selectOne("SELECT COUNT(*) AS c FROM `tbl_payroll_run_line_items` WHERE `code` = ? AND `kind` IN ('fund_employee','fund_employer')", [$current['code']])['c'] ?? 0);
            if ($used > 0) {
                setFlash('error', 'The code ' . $current['code'] . ' is already used by enrollments or payroll runs, so it can’t change. Rename the fund instead, or create a new fund.');
                redirect($back);
                exit;
            }
        }
    }
    try {
        $db->transaction(function (Database $db) use ($id, $row, $me): void {
            if ($id > 0) {
                $old = $db->selectOne('SELECT * FROM `tbl_payroll_funds` WHERE `id` = ?', [$id]);
                $db->update('tbl_payroll_funds', $row + ['updated_by' => $me], '`id` = ?', [$id]);
                auditLog('payroll', 'fund_updated', 'payroll_fund', $id, $old, $row);
            } else {
                $newId = $db->insert('tbl_payroll_funds', $row + ['added_by' => $me]);
                auditLog('payroll', 'fund_created', 'payroll_fund', $newId, null, $row);
            }
        });
    } catch (Throwable $e) {
        setFlash('error', str_contains($e->getMessage(), 'Duplicate') ? 'A fund with code ' . $row['code'] . ' already exists.' : 'Could not save the fund.');
        redirect($back);
        exit;
    }
    setFlash('success', 'Fund ' . $row['code'] . ' saved.');
    redirect($back);
    exit;
}

if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    $inUse = (int) ($db->selectOne('SELECT COUNT(*) AS c FROM `tbl_staff_fund_enrollments` WHERE `fund_id` = ?', [$id])['c'] ?? 0);
    if ($inUse > 0) {
        setFlash('error', 'Staff are enrolled in this fund — deactivate it instead.');
        redirect($back);
        exit;
    }
    $old = $db->selectOne('SELECT * FROM `tbl_payroll_funds` WHERE `id` = ?', [$id]);
    if ($old) {
        $db->transaction(function (Database $db) use ($id, $old): void {
            $db->delete('tbl_payroll_funds', '`id` = ?', [$id]);
            auditLog('payroll', 'fund_deleted', 'payroll_fund', $id, $old, null);
        });
        setFlash('success', 'Fund ' . $old['code'] . ' deleted.');
    }
    redirect($back);
    exit;
}

setFlash('error', 'Unknown action.');
redirect($back);
exit;
