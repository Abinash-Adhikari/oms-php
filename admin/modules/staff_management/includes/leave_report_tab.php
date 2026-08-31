<?php
/**
 * SB-Tech — Leave Management / Report (US-LV-04, AC-LV-04.1).
 * Leave usage by staff/type/year, balance summary, pending approvals queue,
 * CSV export.
 */
$db = Database::instance();
$year = (int) ($_GET['year'] ?? currentLeaveYear());
if ($year < 2000 || $year > 2100) {
    $year = currentLeaveYear();
}
$staffFilter = (int) ($_GET['staff_id'] ?? 0);
$years = range(date('Y') - 1, date('Y') + 1);
$staffs = $db->select("SELECT `id`, `fullname` FROM `tbl_users_login` WHERE `status` != 'Terminated' ORDER BY `fullname`");

$where = 'WHERE la.year = ?';
$params = [$year];
if ($staffFilter) {
    $where .= ' AND la.staff_id = ?';
    $params[] = $staffFilter;
}

// Usage: allocated + carry vs approved (used) + pending.
$usage = $db->select(
    'SELECT u.fullname, lc.title AS leave_title, la.allocated_days, la.carry_forward_days, la.used_days,
            COALESCE(p.days, 0) AS pending_days
     FROM `tbl_office_staff_leave_allocation` la
     JOIN `tbl_users_login` u ON u.id = la.staff_id
     JOIN `tbl_office_leave_configs` lc ON lc.id = la.leave_id
     LEFT JOIN (
        SELECT staff_id, leave_type_id, SUM(leave_days) AS days
        FROM `tbl_staff_leave_applications`
        WHERE status IN ("Pending","Verified")
        GROUP BY staff_id, leave_type_id
     ) p ON p.staff_id = la.staff_id AND p.leave_type_id = la.leave_id
     ' . $where . '
     ORDER BY u.fullname, lc.title',
    $params
);
foreach ($usage as &$r) {
    $r['allocated_days'] = (float) $r['allocated_days'];
    $r['carry_forward_days'] = (float) $r['carry_forward_days'];
    $r['used_days'] = (float) $r['used_days'];
    $r['pending_days'] = (float) $r['pending_days'];
    $r['remaining'] = round($r['allocated_days'] + $r['carry_forward_days'] - $r['used_days'] - $r['pending_days'], 1);
}
unset($r);

// Pending approvals queue.
$pending = $db->select(
    'SELECT l.id, u.fullname, lc.title AS leave_title, l.from_date, l.to_date, l.leave_days, l.added_on
     FROM `tbl_staff_leave_applications` l
     JOIN `tbl_users_login` u ON u.id = l.staff_id
     JOIN `tbl_office_leave_configs` lc ON lc.id = l.leave_type_id
     WHERE l.status = "Pending"
     ORDER BY l.added_on'
);
?>
<div class="row mb-2">
    <div class="col-md-9">
        <form method="get" class="form-inline d-inline">
            <input type="hidden" name="module" value="staff_management">
            <input type="hidden" name="page" value="leave_management">
            <input type="hidden" name="tab" value="leave_report">
            <select name="staff_id" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                <option value="0">All staff</option>
                <?php foreach ($staffs as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= $staffFilter === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['fullname']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="year" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                <?php foreach ($years as $y): ?>
                    <option value="<?= $y ?>" <?= $year === $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <form action="operation.php?module=staff_management&page=leave_management" method="post" class="d-inline">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="export_report">
            <input type="hidden" name="year" value="<?= (int) $year ?>">
            <input type="hidden" name="staff_id" value="<?= (int) $staffFilter ?>">
            <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-file-csv mr-1"></i>CSV</button>
        </form>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card card-outline">
            <div class="card-header"><h3 class="card-title">Leave usage — <?= (int) $year ?></h3></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover mb-0">
                        <thead>
                            <tr><th>Staff</th><th>Type</th><th class="text-center">Allocated</th><th class="text-center">Carry</th><th class="text-center">Used</th><th class="text-center">Pending</th><th class="text-center">Remaining</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($usage as $r): ?>
                            <tr>
                                <td><?= e($r['fullname']) ?></td>
                                <td><?= e($r['leave_title']) ?></td>
                                <td class="text-center"><?= e($r['allocated_days']) ?></td>
                                <td class="text-center"><?= e($r['carry_forward_days']) ?></td>
                                <td class="text-center"><?= e($r['used_days']) ?></td>
                                <td class="text-center"><?= e($r['pending_days']) ?></td>
                                <td class="text-center"><strong><?= e($r['remaining']) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$usage): ?><tr><td colspan="7" class="text-center text-muted">No allocations for this year.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-outline">
            <div class="card-header"><h3 class="card-title">Pending approvals (<?= count($pending) ?>)</h3></div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php foreach ($pending as $p): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?= e($p['fullname']) ?></strong><br>
                                <small><?= e($p['leave_title']) ?> · <?= e((float) $p['leave_days']) ?> day(s)<br><?= e(formatDateView($p['from_date'])) ?> → <?= e(formatDateView($p['to_date'])) ?></small>
                            </div>
                            <a href="<?= pageUrl('staff_management', 'leave_management') ?>&tab=leave_application" class="btn btn-xs btn-outline-primary">Review</a>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$pending): ?><li class="list-group-item text-muted text-center">No pending applications.</li><?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
