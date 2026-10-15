<?php
/**
 * SB-Tech — Reports / Staff.
 * Headcount by department and designation, composition (type, gender),
 * hiring over the last 12 months and recent joiners.
 */
$db = Database::instance();
$months12 = month_keys(date('Y-m'), 12);

$statusMap = rows_to_map($db->select('SELECT status, COUNT(*) AS c FROM tbl_users_login GROUP BY status'), 'status', 'c');
$totalActive = (int) ($statusMap['Active'] ?? 0);
$totalAll = (int) array_sum($statusMap);

$byDept = $db->select(
    "SELECT d.title AS dept, COUNT(u.id) AS total,
            SUM(u.status = 'Active') AS active,
            SUM(u.status = 'Block') AS blocked,
            SUM(u.status = 'Terminated') AS terminated_count
     FROM tbl_office_departments d
     LEFT JOIN tbl_users_login u ON u.department_id = d.id
     GROUP BY d.id, d.title
     ORDER BY active DESC, d.title"
);
$noDept = (int) ($db->selectOne("SELECT COUNT(*) AS c FROM tbl_users_login WHERE status = 'Active' AND department_id IS NULL")['c'] ?? 0);

$byDesig = $db->select(
    "SELECT g.title AS designation, COUNT(u.id) AS total, SUM(u.status = 'Active') AS active
     FROM tbl_office_designation g
     LEFT JOIN tbl_users_login u ON u.designation_id = g.id
     GROUP BY g.id, g.title
     ORDER BY active DESC, g.title"
);

$byGender = rows_to_map($db->select(
    "SELECT COALESCE(NULLIF(gender, ''), 'Not specified') AS g, COUNT(*) AS c FROM tbl_users_login WHERE status = 'Active' GROUP BY g"
), 'g', 'c');
$byType = rows_to_map($db->select(
    "SELECT COALESCE(NULLIF(staff_type, ''), 'Not specified') AS t, COUNT(*) AS c FROM tbl_users_login WHERE status = 'Active' GROUP BY t"
), 't', 'c');

$joiners = rows_to_map($db->select(
    "SELECT DATE_FORMAT(join_date, '%Y-%m') AS m, COUNT(*) AS c FROM tbl_users_login WHERE join_date >= ? GROUP BY m",
    [$months12[0] . '-01']
), 'm', 'c');
$leavers = rows_to_map($db->select(
    "SELECT DATE_FORMAT(termination_date, '%Y-%m') AS m, COUNT(*) AS c FROM tbl_users_login WHERE termination_date >= ? GROUP BY m",
    [$months12[0] . '-01']
), 'm', 'c');
$joined12 = (int) array_sum($joiners);
$left12 = (int) array_sum($leavers);

$recentJoiners = $db->select(
    "SELECT u.fullname, u.join_date, d.title AS dept, g.title AS designation
     FROM tbl_users_login u
     LEFT JOIN tbl_office_departments d ON d.id = u.department_id
     LEFT JOIN tbl_office_designation g ON g.id = u.designation_id
     WHERE u.status = 'Active' AND u.join_date IS NOT NULL
     ORDER BY u.join_date DESC LIMIT 8"
);

$contracts = $db->select(
    "SELECT u.fullname, u.employment_type, u.contract_end_date, d.title AS dept, DATEDIFF(u.contract_end_date, CURDATE()) AS days_left
     FROM tbl_users_login u LEFT JOIN tbl_office_departments d ON d.id = u.department_id
     WHERE u.status <> 'Terminated' AND u.contract_end_date IS NOT NULL
     ORDER BY u.contract_end_date"
);
$byEmployment = rows_to_map($db->select(
    "SELECT employment_type AS t, COUNT(*) AS c FROM tbl_users_login WHERE status = 'Active' GROUP BY employment_type"
), 't', 'c');

$deptRows = array_values(array_filter($byDept, static fn ($d) => (int) $d['active'] > 0));
if ($noDept > 0) {
    $deptRows[] = ['dept' => 'No department', 'active' => $noDept];
}
?>

<div class="ana-toolbar">
    <div>
        <h2 class="ana-toolbar__title">Staff</h2>
        <p class="ana-toolbar__sub"><?= $totalAll ?> people on record · hiring trend over the last 12 months</p>
    </div>
    <div class="ana-toolbar__actions">
        <form action="operation.php?module=reports&page=staff" method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="export_staff">
            <button class="btn btn-success btn-sm"><i class="fas fa-download mr-1"></i>CSV</button>
        </form>
    </div>
</div>

<div class="ana-grid">
    <?= stat_tile(['label' => 'Active staff', 'icon' => 'fas fa-users', 'tone' => 'success',
        'value' => number_format($totalActive), 'sub' => percent_of($totalActive, $totalAll) . '% of everyone on record',
        'href' => pageUrl('staff_management', 'add_staff')]) ?>
    <?= stat_tile(['label' => 'Joined (12 months)', 'icon' => 'fas fa-user-plus', 'tone' => 'info',
        'value' => number_format($joined12), 'sub' => $left12 . ' left in the same period',
        'spark' => fill_series($months12, $joiners)]) ?>
    <?= stat_tile(['label' => 'Departments', 'icon' => 'fas fa-sitemap', 'tone' => 'primary',
        'value' => number_format(count(array_filter($byDept, static fn ($d) => (int) $d['active'] > 0))),
        'sub' => $noDept > 0 ? $noDept . ' active staff without a department' : 'everyone is assigned']) ?>
    <?= stat_tile(['label' => 'Blocked / terminated', 'icon' => 'fas fa-user-lock', 'tone' => 'neutral',
        'value' => number_format((int) ($statusMap['Block'] ?? 0) + (int) ($statusMap['Terminated'] ?? 0)),
        'sub' => (int) ($statusMap['Block'] ?? 0) . ' blocked · ' . (int) ($statusMap['Terminated'] ?? 0) . ' terminated']) ?>
</div>

<div class="row ana-row">
    <div class="col-lg-7">
        <div class="ana-card">
            <div class="ana-card__head">
                <div>
                    <h3 class="ana-card__title">Headcount by department</h3>
                    <p class="ana-card__hint">Active staff</p>
                </div>
            </div>
            <div class="ana-chart" style="--h:<?= max(200, 40 * count($deptRows) + 40) ?>px">
                <canvas role="img" aria-label="Horizontal bar chart of active staff per department"
                        data-chart="<?= chart_attr([
                            'type' => 'hbar', 'labels' => array_column($deptRows, 'dept'),
                            'series' => [['label' => 'Active staff', 'data' => array_map('intval', array_column($deptRows, 'active')), 'slot' => 1]],
                            'empty' => 'No active staff yet.',
                        ]) ?>"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="ana-card">
            <div class="ana-card__head">
                <div>
                    <h3 class="ana-card__title">Hiring</h3>
                    <p class="ana-card__hint">Joiners vs. leavers per month</p>
                </div>
            </div>
            <div class="ana-chart">
                <canvas role="img" aria-label="Bar chart of staff joining and leaving per month"
                        data-chart="<?= chart_attr([
                            'type' => 'bar',
                            'labels' => array_map(static fn ($m) => date('M', strtotime($m . '-01')), $months12),
                            'series' => [
                                ['label' => 'Joined', 'data' => fill_series($months12, $joiners), 'slot' => 1],
                                ['label' => 'Left', 'data' => fill_series($months12, $leavers), 'slot' => 2],
                            ],
                            'empty' => 'No joiners or leavers in the last 12 months.',
                        ]) ?>"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row ana-row">
    <div class="col-lg-4">
        <div class="ana-card">
            <div class="ana-card__head"><div><h3 class="ana-card__title">Staff type</h3><p class="ana-card__hint">Active staff</p></div></div>
            <div class="ana-chart" style="--h:200px">
                <canvas role="img" aria-label="Doughnut chart of active staff by type"
                        data-chart="<?= chart_attr([
                            'type' => 'doughnut', 'labels' => array_keys($byType),
                            'series' => [['label' => 'Staff', 'data' => array_values($byType)]],
                            'empty' => 'No active staff yet.',
                        ]) ?>"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="ana-card">
            <div class="ana-card__head"><div><h3 class="ana-card__title">Gender</h3><p class="ana-card__hint">Active staff</p></div></div>
            <div class="ana-chart" style="--h:200px">
                <canvas role="img" aria-label="Doughnut chart of active staff by gender"
                        data-chart="<?= chart_attr([
                            'type' => 'doughnut', 'labels' => array_keys($byGender),
                            'series' => [['label' => 'Staff', 'data' => array_values($byGender)]],
                            'empty' => 'No active staff yet.',
                        ]) ?>"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="ana-card">
            <div class="ana-card__head"><div><h3 class="ana-card__title">Recent joiners</h3></div></div>
            <?php if ($recentJoiners): ?>
                <ul class="ana-list">
                    <?php foreach ($recentJoiners as $j): ?>
                        <li>
                            <div class="ana-list__main">
                                <span class="ana-list__title"><?= e($j['fullname']) ?></span>
                                <span class="ana-list__meta"><?= e(trim(($j['designation'] ?? '') . ' · ' . ($j['dept'] ?? '—'), ' ·')) ?></span>
                            </div>
                            <span class="ana-pill"><?= e(formatDateView((string) $j['join_date'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="tms-empty-state"><i class="fas fa-user-plus"></i><p>No join dates recorded.</p></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row ana-row">
    <div class="col-lg-4">
        <div class="ana-card">
            <div class="ana-card__head"><div><h3 class="ana-card__title">Employment type</h3><p class="ana-card__hint">Active staff</p></div></div>
            <div class="ana-chart" style="--h:200px">
                <canvas role="img" aria-label="Doughnut chart of active staff by employment type"
                        data-chart="<?= chart_attr([
                            'type' => 'doughnut', 'labels' => array_keys($byEmployment),
                            'series' => [['label' => 'Staff', 'data' => array_values($byEmployment)]],
                            'empty' => 'No active staff yet.',
                        ]) ?>"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="ana-card ana-card--flush">
            <div class="ana-card__head"><div><h3 class="ana-card__title">Contracts &amp; internships</h3><p class="ana-card__hint">Staff with an end date, soonest first</p></div></div>
            <div class="table-responsive">
                <table class="table table-sm ana-table">
                    <thead><tr><th>Staff</th><th>Type</th><th>Department</th><th>Ends</th><th class="text-right">Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($contracts as $c):
                        $left = (int) $c['days_left'];
                        [$tone, $text] = $left < 0 ? ['critical', abs($left) . 'd overdue — renew or terminate'] : ($left <= 30 ? ['warning', 'in ' . $left . 'd'] : ['', 'in ' . $left . 'd']);
                    ?>
                        <tr>
                            <td><strong><?= e($c['fullname']) ?></strong></td>
                            <td><?= e($c['employment_type']) ?></td>
                            <td class="text-muted"><?= e($c['dept'] ?? '—') ?></td>
                            <td><?= e(formatDateView((string) $c['contract_end_date'])) ?></td>
                            <td class="text-right"><span class="ana-pill<?= $tone ? ' ana-pill--' . e($tone) : '' ?>"><?= e($text) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$contracts): ?><tr><td colspan="5" class="text-muted text-center py-4">No contract or internship end dates recorded.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row ana-row">
    <div class="col-lg-6">
        <div class="ana-card ana-card--flush">
            <div class="ana-card__head"><div><h3 class="ana-card__title">By department</h3></div></div>
            <div class="table-responsive">
                <table class="table table-sm ana-table">
                    <thead><tr><th>Department</th><th class="text-right">Active</th><th class="text-right">Blocked</th><th class="text-right">Terminated</th><th style="width:30%">Share of active</th></tr></thead>
                    <tbody>
                    <?php foreach ($byDept as $d): ?>
                        <tr>
                            <td><strong><?= e($d['dept'] ?: 'Unassigned') ?></strong></td>
                            <td class="text-right"><?= (int) $d['active'] ?></td>
                            <td class="text-right"><?= (int) $d['blocked'] ?></td>
                            <td class="text-right"><?= (int) $d['terminated_count'] ?></td>
                            <td><?= meter_bar(percent_of((float) $d['active'], $totalActive)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$byDept): ?>
                        <tr><td colspan="5" class="text-muted text-center py-4">No departments set up.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="ana-card ana-card--flush">
            <div class="ana-card__head"><div><h3 class="ana-card__title">By designation</h3></div></div>
            <div class="table-responsive">
                <table class="table table-sm ana-table">
                    <thead><tr><th>Designation</th><th class="text-right">Active</th><th class="text-right">Total</th><th style="width:30%">Share of active</th></tr></thead>
                    <tbody>
                    <?php foreach ($byDesig as $g): ?>
                        <tr>
                            <td><strong><?= e($g['designation'] ?: 'Unassigned') ?></strong></td>
                            <td class="text-right"><?= (int) $g['active'] ?></td>
                            <td class="text-right"><?= (int) $g['total'] ?></td>
                            <td><?= meter_bar(percent_of((float) $g['active'], $totalActive)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$byDesig): ?>
                        <tr><td colspan="4" class="text-muted text-center py-4">No designations set up.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
