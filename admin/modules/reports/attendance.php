<?php
/**
 * SB-Tech — Reports / Attendance.
 * Monthly attendance summary per staff with late/early/working hours.
 */
$db = Database::instance();
$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}
$prevMonth = date('Y-m', strtotime($month . '-01 -1 month'));
$nextMonth = date('Y-m', strtotime($month . '-01 +1 month'));
$daysInMonth = (int) date('t', strtotime($month . '-01'));

$staffFilter = (int) ($_GET['staff_id'] ?? 0);

$where = "u.status != 'Terminated'";
$params = [];
if ($staffFilter) {
    $where .= ' AND u.id = ?';
    $params[] = $staffFilter;
}

$summary = $db->select(
    "SELECT u.id, u.fullname, d.title AS department_title,
            COUNT(a.id) AS total_records,
            COALESCE(SUM(a.status = 'present'), 0) AS present_days,
            COALESCE(SUM(a.status = 'late'), 0) AS late_days,
            COALESCE(SUM(a.late_checkin), 0) AS late_checkin_count,
            COALESCE(SUM(a.early_checkout), 0) AS early_checkout_count,
            COALESCE(SUM(CASE WHEN a.status = 'leave' THEN 1 ELSE 0 END), 0) AS leave_days,
            COALESCE(SUM(CASE WHEN a.status = 'holiday' THEN 1 ELSE 0 END), 0) AS holiday_days,
            COALESCE(SUM(a.working_hours), 0) AS total_hours,
            ROUND(COALESCE(SUM(a.working_hours), 0) / GREATEST(COUNT(a.id), 1), 1) AS avg_hours
     FROM tbl_users_login u
     LEFT JOIN tbl_office_departments d ON d.id = u.department_id
     LEFT JOIN tbl_staff_attendances a ON a.user_id = u.id AND a.date LIKE ?
     WHERE {$where}
     GROUP BY u.id, u.fullname, d.title
     ORDER BY u.fullname",
    array_merge([$month . '%'], $params)
);

$totals = [
    'present' => array_sum(array_column($summary, 'present_days')),
    'late' => array_sum(array_column($summary, 'late_checkin_count')),
    'early' => array_sum(array_column($summary, 'early_checkout_count')),
    'leave' => array_sum(array_column($summary, 'leave_days')),
    'holiday' => array_sum(array_column($summary, 'holiday_days')),
    'hours' => round(array_sum(array_column($summary, 'total_hours')), 1),
];
$staffs = $db->select("SELECT id, fullname FROM tbl_users_login WHERE status != 'Terminated' ORDER BY fullname");
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-user-clock mr-1"></i>Attendance Report — <?= date('F Y', strtotime($month . '-01')) ?></h3>
        <div class="card-tools">
            <a href="?module=reports&page=attendance&month=<?= $prevMonth ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-chevron-left"></i></a>
            <a href="?module=reports&page=attendance&month=<?= $nextMonth ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-chevron-right"></i></a>
            <form action="operation.php?module=reports&page=attendance_operation" method="post" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="export_attendance">
                <input type="hidden" name="month" value="<?= e($month) ?>">
                <input type="hidden" name="staff_id" value="<?= (int) $staffFilter ?>">
                <button class="btn btn-success btn-sm"><i class="fas fa-download mr-1"></i>CSV</button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <form class="form-inline" method="get">
            <input type="hidden" name="module" value="reports">
            <input type="hidden" name="page" value="attendance">
            <input type="hidden" name="month" value="<?= e($month) ?>">
            <select name="staff_id" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                <option value="0">All Staff</option>
                <?php foreach ($staffs as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= $staffFilter === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['fullname']) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="mr-2">Month:</label>
            <input type="month" name="month" class="form-control form-control-sm mr-2" value="<?= e($month) ?>" onchange="this.form.submit()">
        </form>
    </div>

    <!-- Summary cards -->
    <div class="row mx-3 mb-3">
        <div class="col"><div class="callout callout-success"><h6>Present</h6><h3><?= $totals['present'] ?></h3></div></div>
        <div class="col"><div class="callout callout-warning"><h6>Late Check-in</h6><h3><?= $totals['late'] ?></h3></div></div>
        <div class="col"><div class="callout callout-info"><h6>Leave</h6><h3><?= $totals['leave'] ?></h3></div></div>
        <div class="col"><div class="callout callout-secondary"><h6>Holiday</h6><h3><?= $totals['holiday'] ?></h3></div></div>
        <div class="col"><div class="callout callout-primary"><h6>Total Hours</h6><h3><?= number_format($totals['hours'], 1) ?></h3></div></div>
    </div>

    <div class="card-body p-0">
        <table class="table table-sm table-striped">
            <thead><tr><th>Staff</th><th>Department</th><th class="text-center">Present</th><th class="text-center">Late</th><th class="text-center">Early Out</th><th class="text-center">Leave</th><th class="text-center">Holiday</th><th class="text-center">Total Hrs</th><th class="text-center">Avg Hrs/Day</th></tr></thead>
            <tbody>
            <?php if (!$summary): ?>
                <tr><td colspan="9" class="text-muted text-center">No data for this month.</td></tr>
            <?php else: foreach ($summary as $s): ?>
                <tr>
                    <td><strong><?= e($s['fullname']) ?></strong></td>
                    <td><?= e($s['department_title'] ?? '—') ?></td>
                    <td class="text-center"><span class="badge badge-success"><?= (int) $s['present_days'] ?></span></td>
                    <td class="text-center"><span class="badge badge-<?= $s['late_checkin_count'] > 0 ? 'warning' : 'secondary' ?>"><?= (int) $s['late_checkin_count'] ?></span></td>
                    <td class="text-center"><span class="badge badge-<?= $s['early_checkout_count'] > 0 ? 'info' : 'secondary' ?>"><?= (int) $s['early_checkout_count'] ?></span></td>
                    <td class="text-center"><span class="badge badge-info"><?= (int) $s['leave_days'] ?></span></td>
                    <td class="text-center"><span class="badge badge-secondary"><?= (int) $s['holiday_days'] ?></span></td>
                    <td class="text-center"><?= number_format((float) $s['total_hours'], 1) ?></td>
                    <td class="text-center"><?= number_format((float) $s['avg_hours'], 1) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
