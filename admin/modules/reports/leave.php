<?php
/**
 * SB-Tech — Reports / Leave.
 * Leave usage by staff/type, balance summary, pending approvals.
 */
$db = Database::instance();
$year = (int) ($_GET['year'] ?? date('Y'));
$staffFilter = (int) ($_GET['staff_id'] ?? 0);
$staffs = $db->select("SELECT id, fullname FROM tbl_users_login WHERE status != 'Terminated' ORDER BY fullname");

$where = 'la.year = ?';
$params = [$year];
if ($staffFilter) {
    $where .= ' AND la.staff_id = ?';
    $params[] = $staffFilter;
}

$usage = $db->select(
    "SELECT u.fullname, lc.title AS leave_title, la.allocated_days, la.carry_forward_days, la.used_days,
            COALESCE(p.pending, 0) AS pending_days
     FROM tbl_office_staff_leave_allocation la
     JOIN tbl_users_login u ON u.id = la.staff_id
     JOIN tbl_office_leave_configs lc ON lc.id = la.leave_id
     LEFT JOIN (
        SELECT staff_id, leave_type_id, SUM(leave_days) AS pending
        FROM tbl_staff_leave_applications WHERE status IN ('Pending','Verified')
        GROUP BY staff_id, leave_type_id
     ) p ON p.staff_id = la.staff_id AND p.leave_type_id = la.leave_id
     WHERE {$where}
     ORDER BY u.fullname, lc.title",
    $params
);
foreach ($usage as &$r) {
    $r['remaining'] = round((float) $r['allocated_days'] + (float) $r['carry_forward_days'] - (float) $r['used_days'] - (float) $r['pending_days'], 1);
}
unset($r);

// Leave type summary
$typeSummary = $db->select(
    "SELECT lc.title, la.year,
            COUNT(DISTINCT la.staff_id) AS staff_count,
            SUM(la.allocated_days) AS total_allocated,
            SUM(la.used_days) AS total_used,
            SUM(la.carry_forward_days) AS total_carry
     FROM tbl_office_staff_leave_allocation la
     JOIN tbl_office_leave_configs lc ON lc.id = la.leave_id
     WHERE la.year = ?
     GROUP BY lc.id, lc.title, la.year
     ORDER BY lc.title",
    [$year]
);

// Pending approvals
$pending = $db->select(
    "SELECT l.*, u.fullname, lc.title AS leave_title
     FROM tbl_staff_leave_applications l
     JOIN tbl_users_login u ON u.id = l.staff_id
     JOIN tbl_office_leave_configs lc ON lc.id = l.leave_type_id
     WHERE l.status = 'Pending'
     ORDER BY l.added_on"
);
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-umbrella-beach mr-1"></i>Leave Report — <?= $year ?></h3>
        <div class="card-tools">
            <form action="operation.php?module=reports&page=leave_operation" method="post" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="export_leave">
                <input type="hidden" name="year" value="<?= (int) $year ?>">
                <input type="hidden" name="staff_id" value="<?= (int) $staffFilter ?>">
                <button class="btn btn-success btn-sm"><i class="fas fa-download mr-1"></i>CSV</button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <form class="form-inline" method="get">
            <input type="hidden" name="module" value="reports">
            <input type="hidden" name="page" value="leave">
            <select name="staff_id" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                <option value="0">All Staff</option>
                <?php foreach ($staffs as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= $staffFilter === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['fullname']) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="mr-2">Year:</label>
            <select name="year" class="form-control form-control-sm" onchange="this.form.submit()">
                <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                    <option value="<?= $y ?>" <?= $year === $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </form>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><h5 class="card-title">Usage by Staff / Type</h5></div>
            <div class="card-body p-0">
                <table class="table table-sm table-striped">
                    <thead><tr><th>Staff</th><th>Type</th><th class="text-center">Allocated</th><th class="text-center">Carry</th><th class="text-center">Used</th><th class="text-center">Pending</th><th class="text-center">Remaining</th></tr></thead>
                    <tbody>
                    <?php if (!$usage): ?>
                        <tr><td colspan="7" class="text-muted text-center">No allocations for this year.</td></tr>
                    <?php else: foreach ($usage as $u): ?>
                        <tr>
                            <td><?= e($u['fullname']) ?></td>
                            <td><?= e($u['leave_title']) ?></td>
                            <td class="text-center"><?= e($u['allocated_days']) ?></td>
                            <td class="text-center"><?= e($u['carry_forward_days']) ?></td>
                            <td class="text-center"><?= e($u['used_days']) ?></td>
                            <td class="text-center"><?= e($u['pending_days']) ?></td>
                            <td class="text-center"><strong class="<?= $u['remaining'] <= 0 ? 'text-danger' : '' ?>"><?= e($u['remaining']) ?></strong></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h5 class="card-title">By Leave Type</h5></div>
            <div class="card-body p-0">
                <table class="table table-sm">
                    <thead><tr><th>Type</th><th class="text-center">Staff</th><th class="text-center">Total Allocated</th><th class="text-center">Total Used</th></tr></thead>
                    <tbody>
                    <?php foreach ($typeSummary as $t): ?>
                        <tr>
                            <td><strong><?= e($t['title']) ?></strong></td>
                            <td class="text-center"><?= (int) $t['staff_count'] ?></td>
                            <td class="text-center"><?= (float) $t['total_allocated'] ?></td>
                            <td class="text-center"><?= (float) $t['total_used'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h5 class="card-title">Pending Approvals (<?= count($pending) ?>)</h5></div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php foreach (array_slice($pending, 0, 10) as $p): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center small">
                            <div><strong><?= e($p['fullname']) ?></strong><br><?= e($p['leave_title']) ?> · <?= (float) $p['leave_days'] ?>d</div>
                            <a href="<?= pageUrl('staff_management', 'leave_management') ?>&tab=leave_application" class="btn btn-xs btn-outline-primary">Review</a>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$pending): ?>
                        <li class="list-group-item text-muted text-center">No pending.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
