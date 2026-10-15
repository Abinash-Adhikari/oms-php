<?php
/**
 * SB-Tech — Payroll / Pay Components writes (PRG). Actions: save, delete.
 */
$back = pageUrl('payroll', 'components');
if (!Auth::hasSpecial('manage_payroll')) {
    setFlash('error', 'You need the Manage payroll permission.');
    redirect($back);
    exit;
}
$db = Database::instance();
$action = (string) ($_POST['action'] ?? '');
$me = (int) Auth::id();

if ($action === 'save') {
    $id = (int) ($_POST['id'] ?? 0);
    $kind = ($_POST['kind'] ?? '') === 'deduction' ? 'deduction' : 'earning';
    $row = [
        'code'      => strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($_POST['code'] ?? ''))),
        'name'      => trim((string) ($_POST['name'] ?? '')),
        'kind'      => $kind,
        'calc'      => ($_POST['calc'] ?? '') === 'percent_of_basic' ? 'percent_of_basic' : 'fixed',
        'taxable'   => $kind === 'earning' && !empty($_POST['taxable']) ? 1 : 0,
        'prorate'   => !empty($_POST['prorate']) ? 1 : 0,
        'position'  => (int) ($_POST['position'] ?? 0),
        'is_active' => !empty($_POST['is_active']) ? 1 : 0,
    ];
    if ($row['code'] === '' || $row['name'] === '' || $row['code'] === 'BASIC') {
        setFlash('error', $row['code'] === 'BASIC' ? 'BASIC is reserved for the basic salary.' : 'Code and name are required.');
        redirect($back);
        exit;
    }
    try {
        $db->transaction(function (Database $db) use ($id, $row, $me): void {
            if ($id > 0) {
                $old = $db->selectOne('SELECT * FROM `tbl_payroll_components` WHERE `id` = ?', [$id]);
                $db->update('tbl_payroll_components', $row + ['updated_by' => $me], '`id` = ?', [$id]);
                auditLog('payroll', 'component_updated', 'payroll_component', $id, $old, $row);
            } else {
                $newId = $db->insert('tbl_payroll_components', $row + ['added_by' => $me]);
                auditLog('payroll', 'component_created', 'payroll_component', $newId, null, $row);
            }
        });
    } catch (Throwable $e) {
        setFlash('error', str_contains($e->getMessage(), 'Duplicate') ? 'A component with code ' . $row['code'] . ' already exists.' : 'Could not save the component.');
        redirect($back);
        exit;
    }
    setFlash('success', $row['name'] . ' saved.');
    redirect($back);
    exit;
}

if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    $inUse = (int) ($db->selectOne('SELECT COUNT(*) AS c FROM `tbl_staff_salary_structure_items` WHERE `component_id` = ?', [$id])['c'] ?? 0);
    if ($inUse > 0) {
        setFlash('error', 'This component is used in salary structures — deactivate it instead.');
        redirect($back);
        exit;
    }
    $old = $db->selectOne('SELECT * FROM `tbl_payroll_components` WHERE `id` = ?', [$id]);
    if ($old) {
        $db->transaction(function (Database $db) use ($id, $old): void {
            $db->delete('tbl_payroll_components', '`id` = ?', [$id]);
            auditLog('payroll', 'component_deleted', 'payroll_component', $id, $old, null);
        });
        setFlash('success', $old['name'] . ' deleted.');
    }
    redirect($back);
    exit;
}

setFlash('error', 'Unknown action.');
redirect($back);
exit;
