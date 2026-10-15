<?php
/**
 * SB-Tech — Reports / Leave.
 * Utilisation by type and staff, monthly leave trend, balances and the
 * pending-approval queue.
 *
 * utilisation = used ÷ (allocated + carried forward)
 */
$db = Database::instance();
$year = (int) ($_GET['year'] ?? (useBsDates() ? currentLeaveYear() : date('Y')));
$currentYear = useBsDates() ? currentLeaveYear() : (int) date('Y');
$staffFilter = (int) ($_GET['staff_id'] ?? 0);
$staffs = $db->select("SELECT id, fullname FROM tbl_users_login WHERE status != 'Terminated' ORDER BY fullname");
$months12 = month_keys(date('Y-m'), 12);

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
    $r['entitled'] = (float) $r['allocated_days'] + (float) $r['carry_forward_days'];
    $r['remaining'] = round($r['entitled'] - (float) $r['used_days'] - (float) $r['pending_days'], 1);
}
unset($r);

$typeSummary = $db->select(
    "SELECT lc.title,
            COUNT(DISTINCT la.staff_id) AS staff_count,
            SUM(la.allocated_days + la.carry_forward_days) AS total_entitled,
            SUM(la.used_days) AS total_used
     FROM tbl_office_staff_leave_allocation la
     JOIN tbl_office_leave_configs lc ON lc.id = la.leave_id
     WHERE {$where}
     GROUP BY lc.id, lc.title
     ORDER BY lc.title",
    $params
);
$entitled = (float) array_sum(array_column($typeSummary, 'total_entitled'));
$used = (float) array_sum(array_column($typeSummary, 'total_used'));

// Approved leave days per month (by start date), last 12 months.
$trendWhere = "status = 'Approved' AND from_date >= ?";
$trendParams = [$months12[0] . '-01'];
if ($staffFilter) {
    $trendWhere .= ' AND staff_id = ?';
    $trendParams[] = $staffFilter;
}
$monthly = rows_to_map($db->select(
    "SELECT DATE_FORMAT(from_date, '%Y-%m') AS m, SUM(leave_days) AS d FROM tbl_staff_leave_applications WHERE {$trendWhere} GROUP BY m",
    $trendParams
), 'm', 'd');

$pending = $db->select(
    "SELECT l.*, u.fullname, lc.title AS leave_title
     FROM tbl_staff_leave_applications l
     JOIN tbl_users_login u ON u.id = l.staff_id
     JOIN tbl_office_leave_configs lc ON lc.id = l.leave_type_id
     WHERE l.status = 'Pending'
     ORDER BY l.added_on"
);
$pendingDays = (float) array_sum(array_column($pending, 'leave_days'));
// number_format always yields one decimal, so trimming '.0' is safe.
$fmtDays = static fn (float $d): string => rtrim(rtrim(number_format($d, 1), '0'), '.');
$utilTone = static fn (float $u) => $u > 90 ? 'critical' : ($u > 70 ? 'warning' : '');
?>

<div class="ana-toolbar">
    <div>
        <h2 class="ana-toolbar__title">Leave — <?= (int) $year ?> <?= e(date_system_label()) ?></h2>
        <p class="ana-toolbar__sub">Utilisation = used ÷ (allocated + carried forward)</p>
    </div>
    <div class="ana-toolbar__actions">
        <form class="ana-toolbar__actions" method="get">
            <input type="hidden" name="module" value="reports">
            <input type="hidden" name="page" value="leave">
            <select name="staff_id" class="form-control form-control-sm" onchange="this.form.submit()" aria-label="Staff">
                <option value="0">All staff</option>
                <?php foreach ($staffs as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= $staffFilter === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['fullname']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="year" class="form-control form-control-sm" onchange="this.form.submit()" aria-label="Leave year">
                <?php for ($y = $currentYear - 2; $y <= $currentYear + 1; $y++): ?>
                    <option value="<?= $y ?>" <?= $year === $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </form>
        <form action="operation.php?module=reports&page=leave" method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="export_leave">
            <input type="hidden" name="year" value="<?= (int) $year ?>">
            <input type="hidden" name="staff_id" value="<?= (int) $staffFilter ?>">
            <button class="btn btn-success btn-sm"><i class="fas fa-download mr-1"></i>CSV</button>
        </form>
    </div>
</div>

<div class="ana-grid">
    <?= stat_tile(['label' => 'Entitlement', 'icon' => 'fas fa-calendar-plus', 'tone' => 'primary',
        'value' => $fmtDays($entitled) . ' days',
        'sub' => count($usage) . ' allocations']) ?>
    <?= stat_tile(['label' => 'Used', 'icon' => 'fas fa-umbrella-beach', 'tone' => 'warning',
        'value' => $fmtDays($used) . ' days',
        'spark' => fill_series($months12, $monthly)]) ?>
    <?= stat_tile(['label' => 'Utilisation', 'icon' => 'fas fa-percentage', 'tone' => 'info',
        'value' => $entitled > 0 ? percent_of($used, $entitled, 1) . '%' : '—',
        'sub' => $fmtDays(max(0, $entitled - $used)) . ' days still available']) ?>
    <?= stat_tile(['label' => 'Awaiting approval', 'icon' => 'fas fa-hourglass-half', 'tone' => count($pending) > 0 ? 'danger' : 'neutral',
        'value' => number_format(count($pending)),
        'sub' => $fmtDays($pendingDays) . ' days requested',
        'href' => pageUrl('staff_management', 'leave_management') . '&tab=leave_application']) ?>
</div>

<div class="row ana-row">
    <div class="col-lg-6">
        <div class="ana-card">
            <div class="ana-card__head">
                <div>
                    <h3 class="ana-card__title">By leave type</h3>
                    <p class="ana-card__hint">Entitled vs. used days</p>
                </div>
            </div>
            <div class="ana-chart">
                <canvas role="img" aria-label="Bar chart of entitled and used leave days per leave type"
                        data-chart="<?= chart_attr([
                            'type' => 'bar', 'labels' => array_column($typeSummary, 'title'),
                            'series' => [
                                ['label' => 'Entitled', 'data' => array_map('floatval', array_column($typeSummary, 'total_entitled')), 'slot' => 1],
                                ['label' => 'Used', 'data' => array_map('floatval', array_column($typeSummary, 'total_used')), 'slot' => 2],
                            ],
                            'empty' => 'No leave allocated for this year.',
                        ]) ?>"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="ana-card">
            <div class="ana-card__head">
                <div>
                    <h3 class="ana-card__title">Leave taken per month</h3>
                    <p class="ana-card__hint">Approved days by start date, last 12 months</p>
                </div>
            </div>
            <div class="ana-chart">
                <canvas role="img" aria-label="Bar chart of approved leave days per month"
                        data-chart="<?= chart_attr([
                            'type' => 'bar',
                            'labels' => array_map(static fn ($m) => date('M y', strtotime($m . '-01')), $months12),
                            'series' => [['label' => 'Days', 'data' => fill_series($months12, $monthly), 'slot' => 1]],
                            'empty' => 'No approved leave in the last 12 months.',
                        ]) ?>"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row ana-row">
    <div class="col-lg-8">
        <div class="ana-card ana-card--flush">
            <div class="ana-card__head">
                <div>
                    <h3 class="ana-card__title">Balances by staff</h3>
                    <p class="ana-card__hint">Remaining = entitled − used − pending</p>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm ana-table">
                    <thead><tr><th>Staff</th><th>Type</th><th class="text-right">Entitled</th><th class="text-right">Used</th><th class="text-right">Pending</th><th class="text-right">Remaining</th><th style="width:22%">Utilisation</th></tr></thead>
                    <tbody>
                    <?php if (!$usage): ?>
                        <tr><td colspan="7" class="text-muted text-center py-4">No allocations for this year.</td></tr>
                    <?php else: foreach ($usage as $u):
                        $util = percent_of((float) $u['used_days'], $u['entitled']);
                    ?>
                        <tr>
                            <td><strong><?= e($u['fullname']) ?></strong></td>
                            <td class="text-muted"><?= e($u['leave_title']) ?></td>
                            <td class="text-right"><?= e((float) $u['entitled']) ?></td>
                            <td class="text-right"><?= e((float) $u['used_days']) ?></td>
                            <td class="text-right"><?= e((float) $u['pending_days']) ?></td>
                            <td class="text-right"><strong class="<?= $u['remaining'] <= 0 ? 'text-danger' : '' ?>"><?= e($u['remaining']) ?></strong></td>
                            <td><?= $u['entitled'] > 0 ? meter_bar($util, $utilTone($util)) : '<span class="text-muted small">—</span>' ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="ana-card">
            <div class="ana-card__head">
                <div>
                    <h3 class="ana-card__title">Pending approvals</h3>
                    <p class="ana-card__hint">Oldest request first</p>
                </div>
                <a class="ana-card__link" href="<?= pageUrl('staff_management', 'leave_management') ?>&tab=leave_application">Review all</a>
            </div>
            <?php if ($pending): ?>
                <ul class="ana-list">
                    <?php foreach (array_slice($pending, 0, 8) as $p): ?>
                        <li>
                            <div class="ana-list__main">
                                <span class="ana-list__title"><?= e($p['fullname']) ?></span>
                                <span class="ana-list__meta"><?= e($p['leave_title']) ?> · <?= e(formatDateView((string) $p['from_date'])) ?></span>
                            </div>
                            <span class="ana-pill"><?= e((float) $p['leave_days']) ?>d</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="tms-empty-state"><i class="fas fa-check-circle"></i><p>No requests waiting.</p></div>
            <?php endif; ?>
        </div>
    </div>
</div>
