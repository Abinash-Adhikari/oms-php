<?php
/**
 * SB-Tech — Payroll / Salary Setup writes (PRG).
 * save_structure: upsert the structure for (staff, effective_from), replace
 * its component items, and sync fund enrollments for every active fund.
 */
$list = pageUrl('payroll', 'salary_setup');
if (!Auth::hasSpecial('manage_payroll')) {
    setFlash('error', 'You need the Manage payroll permission.');
    redirect($list);
    exit;
}
$db = Database::instance();
$me = (int) Auth::id();
$action = (string) ($_POST['action'] ?? '');
$staffId = (int) ($_POST['staff_id'] ?? 0);
$back = $list . '&id=' . $staffId;

if ($action !== 'save_structure') {
    setFlash('error', 'Unknown action.');
    redirect($list);
    exit;
}

$staff = $db->selectOne("SELECT id, fullname FROM tbl_users_login WHERE id = ? AND status <> 'Terminated'", [$staffId]);
$effective = (string) ($_POST['effective_from'] ?? '');
$basic = (float) ($_POST['basic'] ?? -1);
if (!$staff || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $effective) || strtotime($effective) === false || $basic < 0) {
    setFlash('error', 'A staff member, a valid effective date and a non-negative amount are required.');
    redirect($staff ? $back : $list);
    exit;
}

$structure = [
    'staff_id'           => $staffId,
    'effective_from'     => $effective,
    'pay_type'           => ($_POST['pay_type'] ?? '') === 'Stipend' ? 'Stipend' : 'Salary',
    'basic'              => round($basic, 2),
    'tax_applicable'     => !empty($_POST['tax_applicable']) ? 1 : 0,
    'deduct_unpaid_days' => !empty($_POST['deduct_unpaid_days']) ? 1 : 0,
    'notes'              => trim((string) ($_POST['notes'] ?? '')) ?: null,
];

// Only active components with a non-zero value are stored.
$activeComponents = array_map('intval', array_column($db->select('SELECT id FROM tbl_payroll_components WHERE is_active = 1'), 'id'));
$items = [];
foreach ((array) ($_POST['component'] ?? []) as $cid => $val) {
    $cid = (int) $cid;
    if (in_array($cid, $activeComponents, true) && trim((string) $val) !== '' && (float) $val > 0) {
        $items[$cid] = round((float) $val, 4);
    }
}

$optionalRate = static function ($v): ?float {
    $v = trim((string) $v);
    return $v === '' ? null : max(0.0, min(100.0, (float) $v));
};
$postedFunds = (array) ($_POST['fund'] ?? []);
$activeFunds = $db->select('SELECT id, code FROM tbl_payroll_funds WHERE is_active = 1');

$db->transaction(function (Database $db) use ($staffId, $structure, $items, $postedFunds, $activeFunds, $optionalRate, $me): void {
    $existing = $db->selectOne(
        'SELECT * FROM tbl_staff_salary_structures WHERE staff_id = ? AND effective_from = ?',
        [$staffId, $structure['effective_from']]
    );
    if ($existing) {
        $structureId = (int) $existing['id'];
        $db->update('tbl_staff_salary_structures', $structure + ['updated_by' => $me], '`id` = ?', [$structureId]);
        $db->delete('tbl_staff_salary_structure_items', '`structure_id` = ?', [$structureId]);
    } else {
        $structureId = $db->insert('tbl_staff_salary_structures', $structure + ['added_by' => $me]);
    }
    foreach ($items as $cid => $value) {
        $db->insert('tbl_staff_salary_structure_items', ['structure_id' => $structureId, 'component_id' => $cid, 'value' => $value]);
    }

    $enrollmentLog = [];
    foreach ($activeFunds as $f) {
        $fid = (int) $f['id'];
        $p = (array) ($postedFunds[$fid] ?? []);
        $current = $db->selectOne('SELECT id FROM tbl_staff_fund_enrollments WHERE staff_id = ? AND fund_id = ?', [$staffId, $fid]);
        if (empty($p['enrolled'])) {
            if ($current) {
                $db->delete('tbl_staff_fund_enrollments', '`id` = ?', [(int) $current['id']]);
            }
            continue;
        }
        $fixed = trim((string) ($p['employee_fixed'] ?? ''));
        $row = [
            'member_no'      => trim((string) ($p['member_no'] ?? '')) ?: null,
            'employee_rate'  => $optionalRate($p['employee_rate'] ?? ''),
            'employee_fixed' => $fixed === '' ? null : max(0.0, round((float) $fixed, 2)),
            'employer_rate'  => $optionalRate($p['employer_rate'] ?? ''),
        ];
        if ($current) {
            $db->update('tbl_staff_fund_enrollments', $row + ['updated_by' => $me], '`id` = ?', [(int) $current['id']]);
        } else {
            $db->insert('tbl_staff_fund_enrollments', $row + ['staff_id' => $staffId, 'fund_id' => $fid, 'added_by' => $me]);
        }
        $enrollmentLog[$f['code']] = $row;
    }

    auditLog('payroll', $existing ? 'salary_updated' : 'salary_created', 'staff', $staffId,
        $existing ?: null, $structure + ['items' => $items, 'funds' => $enrollmentLog]);
});

setFlash('success', 'Pay setup saved for ' . $staff['fullname'] . '.');
redirect($back);
exit;
