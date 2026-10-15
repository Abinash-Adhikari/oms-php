<?php
/**
 * SB-Tech — Reports / Attendance.
 * Monthly attendance: daily status trend, attendance rate, punctuality and
 * hours per person.
 *
 * Definitions (shown in the UI so nobody has to guess):
 *   attendance rate = present ÷ (present + absent) — leave and holidays are excused
 *   punctuality     = present days without a late check-in ÷ present days
 *   avg hours       = working hours ÷ present days
 */
$db = Database::instance();
$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}
$monthStart = $month . '-01';
$prevMonth = date('Y-m', strtotime($monthStart . ' -1 month'));
$nextMonth = date('Y-m', strtotime($monthStart . ' +1 month'));
$isCurrentMonth = $month === date('Y-m');
$lastDay = $isCurrentMonth ? date('Y-m-d') : date('Y-m-t', strtotime($monthStart));
$days = day_keys($lastDay, (int) date('j', strtotime($lastDay)));

$staffFilter = (int) ($_GET['staff_id'] ?? 0);

$where = "u.status != 'Terminated'";
$params = [];
if ($staffFilter) {
    $where .= ' AND u.id = ?';
    $params[] = $staffFilter;
}

$summary = $db->select(
    "SELECT u.id, u.fullname, d.title AS department_title,
            COALESCE(SUM(a.status = 'present'), 0) AS present_days,
            COALESCE(SUM(a.status = 'absent'), 0) AS absent_days,
            COALESCE(SUM(a.status = 'present' AND a.late_checkin = 1), 0) AS late_checkin_count,
            COALESCE(SUM(a.early_checkout), 0) AS early_checkout_count,
            COALESCE(SUM(a.status = 'leave'), 0) AS leave_days,
            COALESCE(SUM(a.status = 'holiday'), 0) AS holiday_days,
            COALESCE(SUM(a.working_hours), 0) AS total_hours
     FROM tbl_users_login u
     LEFT JOIN tbl_office_departments d ON d.id = u.department_id
     LEFT JOIN tbl_staff_attendances a ON a.user_id = u.id AND a.date BETWEEN ? AND ?
     WHERE {$where}
     GROUP BY u.id, u.fullname, d.title
     ORDER BY u.fullname",
    array_merge([$monthStart, date('Y-m-t', strtotime($monthStart))], $params)
);

$sum = static fn (string $col) => (float) array_sum(array_column($summary, $col));
$present = $sum('present_days');
$absent = $sum('absent_days');
$late = $sum('late_checkin_count');
$hours = $sum('total_hours');
$attendanceRate = percent_of($present, $present + $absent, 1);
$punctuality = percent_of($present - $late, $present, 1);
$avgHours = $present > 0 ? round($hours / $present, 1) : 0.0;

// Daily status counts for the trend chart (same staff filter).
$dailyParams = [$monthStart, $lastDay];
$dailyWhere = '';
if ($staffFilter) {
    $dailyWhere = ' AND a.user_id = ?';
    $dailyParams[] = $staffFilter;
}
$daily = [];
foreach ($db->select(
    "SELECT a.date, a.status, COUNT(*) AS c FROM tbl_staff_attendances a
     WHERE a.date BETWEEN ? AND ?{$dailyWhere} GROUP BY a.date, a.status",
    $dailyParams
) as $r) {
    $daily[$r['status']][$r['date']] = (int) $r['c'];
}

$staffs = $db->select("SELECT id, fullname FROM tbl_users_login WHERE status != 'Terminated' ORDER BY fullname");
$navUrl = static fn (string $m) => pageUrl('reports', 'attendance') . '&month=' . $m . ($staffFilter ? '&staff_id=' . $staffFilter : '');
$rateTone = static fn (float $r) => $r >= 90 ? 'good' : ($r >= 75 ? 'warning' : 'critical');
?>

<div class="ana-toolbar">
    <div>
        <h2 class="ana-toolbar__title">Attendance — <?= e(date('F Y', strtotime($monthStart))) ?></h2>
        <p class="ana-toolbar__sub"><?= $isCurrentMonth ? 'Month to date · ' : '' ?>attendance rate excludes leave and holidays</p>
    </div>
    <div class="ana-toolbar__actions">
    <form class="ana-toolbar__actions" method="get">
        <input type="hidden" name="module" value="reports">
        <input type="hidden" name="page" value="attendance">
        <a href="<?= e($navUrl($prevMonth)) ?>" class="btn btn-sm btn-outline-secondary" aria-label="Previous month"><i class="fas fa-chevron-left"></i></a>
        <input type="month" name="month" class="form-control form-control-sm" value="<?= e($month) ?>" onchange="this.form.submit()" aria-label="Month">
        <a href="<?= e($navUrl($nextMonth)) ?>" class="btn btn-sm btn-outline-secondary" aria-label="Next month"><i class="fas fa-chevron-right"></i></a>
        <select name="staff_id" class="form-control form-control-sm" onchange="this.form.submit()" aria-label="Staff">
            <option value="0">All staff</option>
            <?php foreach ($staffs as $s): ?>
                <option value="<?= (int) $s['id'] ?>" <?= $staffFilter === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['fullname']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <form action="operation.php?module=reports&page=attendance" method="post">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="export_attendance">
        <input type="hidden" name="month" value="<?= e($month) ?>">
        <input type="hidden" name="staff_id" value="<?= (int) $staffFilter ?>">
        <button class="btn btn-success btn-sm"><i class="fas fa-download mr-1"></i>CSV</button>
    </form>
    </div>
</div>

<div class="ana-grid">
    <?= stat_tile(['label' => 'Attendance rate', 'icon' => 'fas fa-user-check', 'tone' => 'success',
        'value' => ($present + $absent) > 0 ? $attendanceRate . '%' : '—',
        'sub' => (int) $present . ' present · ' . (int) $absent . ' absent days']) ?>
    <?= stat_tile(['label' => 'Punctuality', 'icon' => 'fas fa-clock', 'tone' => 'info',
        'value' => $present > 0 ? $punctuality . '%' : '—',
        'sub' => (int) $late . ' late check-ins · ' . (int) $sum('early_checkout_count') . ' early check-outs']) ?>
    <?= stat_tile(['label' => 'Average hours / day', 'icon' => 'fas fa-business-time', 'tone' => 'primary',
        'value' => $present > 0 ? number_format($avgHours, 1) . ' h' : '—',
        'sub' => number_format($hours, 1) . ' h logged in total']) ?>
    <?= stat_tile(['label' => 'Leave & holidays', 'icon' => 'fas fa-umbrella-beach', 'tone' => 'warning',
        'value' => number_format($sum('leave_days') + $sum('holiday_days')),
        'sub' => (int) $sum('leave_days') . ' leave · ' . (int) $sum('holiday_days') . ' holiday days']) ?>
</div>

<div class="ana-card">
    <div class="ana-card__head">
        <div>
            <h3 class="ana-card__title">Daily attendance</h3>
            <p class="ana-card__hint">Staff per day by status</p>
        </div>
    </div>
    <div class="ana-chart" style="--h:240px">
        <canvas role="img" aria-label="Stacked bar chart of staff present, on leave, absent and on holiday per day"
                data-chart="<?= chart_attr([
                    'type' => 'bar', 'stacked' => true,
                    'labels' => array_map(static fn ($d) => date('j', strtotime($d)), $days),
                    'series' => [
                        ['label' => 'Present', 'data' => fill_series($days, $daily['present'] ?? []), 'status' => 'good'],
                        ['label' => 'Leave', 'data' => fill_series($days, $daily['leave'] ?? []), 'status' => 'warning'],
                        ['label' => 'Absent', 'data' => fill_series($days, $daily['absent'] ?? []), 'status' => 'critical'],
                        ['label' => 'Holiday', 'data' => fill_series($days, $daily['holiday'] ?? []), 'status' => 'neutral'],
                    ],
                    'empty' => 'No attendance recorded this month.',
                ]) ?>"></canvas>
    </div>
</div>

<div class="ana-card ana-card--flush">
    <div class="ana-card__head">
        <div>
            <h3 class="ana-card__title">By staff member</h3>
            <p class="ana-card__hint">Rate bars: green ≥ 90%, amber ≥ 75%, red below</p>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm ana-table">
            <thead><tr>
                <th>Staff</th><th>Department</th>
                <th class="text-right">Present</th><th class="text-right">Absent</th><th class="text-right">Leave</th>
                <th class="text-right">Late</th><th class="text-right">Early out</th>
                <th class="text-right">Hours</th><th class="text-right">Avg h/day</th>
                <th style="width:15%">Attendance</th><th style="width:15%">Punctuality</th>
            </tr></thead>
            <tbody>
            <?php if (!$summary): ?>
                <tr><td colspan="11" class="text-muted text-center py-4">No staff match this filter.</td></tr>
            <?php else: foreach ($summary as $s):
                $p = (float) $s['present_days'];
                $a = (float) $s['absent_days'];
                $rate = percent_of($p, $p + $a);
                $punct = percent_of($p - (float) $s['late_checkin_count'], $p);
            ?>
                <tr>
                    <td><strong><?= e($s['fullname']) ?></strong></td>
                    <td class="text-muted"><?= e($s['department_title'] ?? '—') ?></td>
                    <td class="text-right"><?= (int) $p ?></td>
                    <td class="text-right<?= $a > 0 ? ' text-danger' : '' ?>"><?= (int) $a ?></td>
                    <td class="text-right"><?= (int) $s['leave_days'] ?></td>
                    <td class="text-right"><?= (int) $s['late_checkin_count'] ?></td>
                    <td class="text-right"><?= (int) $s['early_checkout_count'] ?></td>
                    <td class="text-right"><?= number_format((float) $s['total_hours'], 1) ?></td>
                    <td class="text-right"><?= $p > 0 ? number_format((float) $s['total_hours'] / $p, 1) : '—' ?></td>
                    <td><?= ($p + $a) > 0 ? meter_bar($rate, $rateTone($rate)) : '<span class="text-muted small">No records</span>' ?></td>
                    <td><?= $p > 0 ? meter_bar($punct, $rateTone($punct)) : '<span class="text-muted small">—</span>' ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
