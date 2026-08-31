<?php
/**
 * SB-Tech — Leave Management / Allocations operation (AC-LV-01.2).
 * Upserts allocated_days + carry_forward_days from the staff × type matrix;
 * used_days is preserved (synced from Approved applications).
 */
$db = Database::instance();
$back = 'show_page.php?module=staff_management&page=leave_management&tab=view_allocations';

try {
    $year = (int) ($_POST['year'] ?? 0);
    if ($year < 2000 || $year > 2100) {
        setFlash('error', 'Invalid leave year.');
        redirect($back);
    }
    $alloc = (array) ($_POST['alloc'] ?? []);
    $carry = (array) ($_POST['carry'] ?? []);

    $validStaff = [];
    foreach ($db->select('SELECT `id` FROM `tbl_users_login` WHERE `status` = ?', ['Active']) as $s) {
        $validStaff[(int) $s['id']] = true;
    }
    $validTypes = [];
    foreach ($db->select('SELECT `id` FROM `tbl_office_leave_configs` WHERE `is_active` = 1') as $t) {
        $validTypes[(int) $t['id']] = true;
    }

    $saved = 0;
    foreach ($alloc as $staffKey => $types) {
        $staffId = (int) $staffKey;
        if (!isset($validStaff[$staffId])) {
            continue;
        }
        foreach ((array) $types as $typeKey => $days) {
            $leaveId = (int) $typeKey;
            if (!isset($validTypes[$leaveId])) {
                continue;
            }
            $allocDays = max(0, round((float) $days, 1));
            $carryDays = max(0, round((float) (($carry[$staffKey][$typeKey] ?? 0)), 1));

            $existing = $db->selectOne(
                'SELECT * FROM `tbl_office_staff_leave_allocation` WHERE `year` = ? AND `leave_id` = ? AND `staff_id` = ?',
                [$year, $leaveId, $staffId]
            );
            if ($existing) {
                $db->update('tbl_office_staff_leave_allocation', [
                    'allocated_days'     => $allocDays,
                    'carry_forward_days' => $carryDays,
                    'updated_by'         => (int) Auth::id(),
                ], '`id` = ?', [(int) $existing['id']]);
            } else {
                $db->insert('tbl_office_staff_leave_allocation', [
                    'year'               => $year,
                    'leave_id'           => $leaveId,
                    'staff_id'           => $staffId,
                    'allocated_days'     => $allocDays,
                    'used_days'          => 0.0,
                    'carry_forward_days' => $carryDays,
                    'added_by'           => (int) Auth::id(),
                ]);
            }
            $saved++;
        }
    }

    setFlash('success', 'Allocations saved (' . $saved . ' rows) for leave year ' . $year . '.');
    redirect($back . '&year=' . $year);
} catch (Throwable $e) {
    setFlash('error', 'Allocation save failed: ' . $e->getMessage());
    redirect($back . '&year=' . (int) ($_POST['year'] ?? 0));
}
