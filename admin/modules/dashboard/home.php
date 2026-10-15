<?php
/**
 * SB-Tech — Dashboard (US-RPT-01).
 *
 * Answers three questions in reading order:
 *   1. What needs my attention right now?   (attention chips)
 *   2. How is the business trending?        (stat tiles with deltas + sparklines, trend charts)
 *   3. What is coming up / just happened?   (deadlines, activity feed)
 * Every tile and chip links to the underlying list (AC-RPT-01.2: no dead ends).
 * Shaping maths lives in functions/analytics.php (pure, unit-tested).
 */
$db = Database::instance();

$today = date('Y-m-d');
$thisMonth = date('Y-m');
$mtd = month_to_date_windows($today);
$months6 = month_keys($thisMonth, 6);
$days14 = day_keys($today, 14);
$weeks8 = week_keys($today, 8);
$monthLabels = array_map(static fn ($m) => date('M', strtotime($m . '-01')), $months6);

// ── People ───────────────────────────────────────────────────────────────
$activeStaff = (int) ($db->selectOne("SELECT COUNT(*) AS c FROM `tbl_users_login` WHERE `status` = 'Active'")['c'] ?? 0);

$attendanceDaily = [];   // date => status => count
foreach ($db->select(
    'SELECT `date`, `status`, COUNT(*) AS c FROM `tbl_staff_attendances`
     WHERE `date` BETWEEN ? AND ? GROUP BY `date`, `status`',
    [$days14[0], $today]
) as $r) {
    $attendanceDaily[$r['status']][$r['date']] = (int) $r['c'];
}
$presentToday = (int) ($attendanceDaily['present'][$today] ?? 0);
$onLeaveToday = (int) ($attendanceDaily['leave'][$today] ?? 0);
$lateToday = (int) ($db->selectOne(
    "SELECT COUNT(*) AS c FROM `tbl_staff_attendances` WHERE `date` = ? AND `status` = 'present' AND `late_checkin` = 1",
    [$today]
)['c'] ?? 0);
$pendingLeaves = (int) ($db->selectOne("SELECT COUNT(*) AS c FROM `tbl_staff_leave_applications` WHERE `status` = 'Pending'")['c'] ?? 0);

// ── Tasks ────────────────────────────────────────────────────────────────
$taskCounts = $db->selectOne(
    "SELECT
        SUM(`status` IN ('Pending','In Progress')) AS open_count,
        SUM(`status` IN ('Pending','In Progress') AND `deadline` IS NOT NULL AND `deadline` < ?) AS overdue_count
     FROM `tbl_office_tasks`",
    [$today]
) ?: [];
$openTasks = (int) ($taskCounts['open_count'] ?? 0);
$overdueTasks = (int) ($taskCounts['overdue_count'] ?? 0);
$tasksDoneWeekly = rows_to_map($db->select(
    "SELECT DATE_FORMAT(DATE_SUB(DATE(`updated_on`), INTERVAL WEEKDAY(`updated_on`) DAY), '%Y-%m-%d') AS wk, COUNT(*) AS c
     FROM `tbl_office_tasks` WHERE `status` = 'Done' AND `updated_on` >= ? GROUP BY wk",
    [$weeks8[0]]
), 'wk', 'c');

$upcoming = $db->select(
    "SELECT t.`id`, t.`title`, t.`deadline`, t.`status`, u.`fullname` AS author_name
     FROM `tbl_office_tasks` t
     LEFT JOIN `tbl_users_login` u ON u.`id` = t.`author`
     WHERE t.`status` IN ('Pending','In Progress') AND t.`deadline` IS NOT NULL AND t.`deadline` < DATE_ADD(?, INTERVAL 8 DAY)
     ORDER BY t.`deadline` ASC
     LIMIT 7",
    [$today]
);

// ── Sales ────────────────────────────────────────────────────────────────
$stageRows = [];
foreach ($db->select('SELECT `stage`, COUNT(*) AS c, COALESCE(SUM(`estimated_value`), 0) AS v FROM `tbl_leads` GROUP BY `stage`') as $r) {
    $stageRows[$r['stage']] = ['count' => (int) $r['c'], 'value' => (float) $r['v']];
}
$openValueByStage = [];
$openCount = 0;
foreach (LEAD_OPEN_STAGES as $st) {
    $openValueByStage[$st] = $stageRows[$st]['value'] ?? 0.0;
    $openCount += $stageRows[$st]['count'] ?? 0;
}
$openValue = array_sum($openValueByStage);
$forecast = weighted_pipeline($openValueByStage);
$won = $stageRows['Won']['count'] ?? 0;
$lost = $stageRows['Lost']['count'] ?? 0;

$leadFlags = $db->selectOne(
    "SELECT
        SUM(`stage` NOT IN ('Won','Lost') AND COALESCE(`last_activity_on`, `added_on`) < DATE_SUB(NOW(), INTERVAL 7 DAY)) AS aging,
        SUM(`stage` NOT IN ('Won','Lost') AND `assigned_to` IS NULL) AS unassigned,
        SUM(DATE(`added_on`) BETWEEN ? AND ?) AS mtd,
        SUM(DATE(`added_on`) BETWEEN ? AND ?) AS prev_mtd
     FROM `tbl_leads`",
    [$mtd['cur_start'], $mtd['cur_end'], $mtd['prev_start'], $mtd['prev_end']]
) ?: [];
$agingLeads = (int) ($leadFlags['aging'] ?? 0);
$unassignedLeads = (int) ($leadFlags['unassigned'] ?? 0);
$leadsMtd = (int) ($leadFlags['mtd'] ?? 0);
$leadsPrevMtd = (int) ($leadFlags['prev_mtd'] ?? 0);

$leadsCreatedMonthly = rows_to_map($db->select(
    "SELECT DATE_FORMAT(`added_on`, '%Y-%m') AS m, COUNT(*) AS c, COALESCE(SUM(`estimated_value`), 0) AS v
     FROM `tbl_leads` WHERE `added_on` >= ? GROUP BY m",
    [$months6[0] . '-01']
), 'm', 'c');
// No won_on column: the last update of a Won lead is the closest proxy for its close date.
$leadsWonMonthly = rows_to_map($db->select(
    "SELECT DATE_FORMAT(COALESCE(`updated_on`, `added_on`), '%Y-%m') AS m, COUNT(*) AS c
     FROM `tbl_leads` WHERE `stage` = 'Won' AND COALESCE(`updated_on`, `added_on`) >= ? GROUP BY m",
    [$months6[0] . '-01']
), 'm', 'c');

// ── Finance ──────────────────────────────────────────────────────────────
$claims = $db->selectOne(
    "SELECT COUNT(*) AS c, COALESCE(SUM(`amount`), 0) AS v FROM `tbl_expense_claims` WHERE `status` IN ('Submitted','Approved')"
) ?: [];
$claimsPending = (int) ($claims['c'] ?? 0);
$activeFy = $db->selectOne("SELECT `title` FROM `tbl_fiscal_years` WHERE `closing` = 'Open' ORDER BY `id` DESC LIMIT 1")['title'] ?? null;

// ── Recent activity: lead touches + task updates, newest first ───────────
$activity = $db->select(
    "(SELECT 'lead' AS kind, a.`type` AS label, COALESCE(NULLIF(l.`company`, ''), l.`contact_name`) AS title,
             a.`note` AS detail, u.`fullname` AS actor, a.`added_on` AS at, l.`id` AS ref_id
      FROM `tbl_lead_activities` a
      JOIN `tbl_leads` l ON l.`id` = a.`lead_id`
      LEFT JOIN `tbl_users_login` u ON u.`id` = a.`added_by`
      ORDER BY a.`added_on` DESC LIMIT 8)
     UNION ALL
     (SELECT 'task', t.`status`, t.`title`, NULL, u.`fullname`, COALESCE(t.`updated_on`, t.`added_on`), t.`id`
      FROM `tbl_office_tasks` t
      LEFT JOIN `tbl_users_login` u ON u.`id` = t.`author`
      ORDER BY COALESCE(t.`updated_on`, t.`added_on`) DESC LIMIT 8)
     ORDER BY at DESC
     LIMIT 8"
);

// ── People: contracts / internships ending, payroll waiting ──────────────
$contracts = $db->selectOne(
    "SELECT SUM(`contract_end_date` BETWEEN ? AND DATE_ADD(?, INTERVAL 30 DAY)) AS ending,
            SUM(`contract_end_date` < ?) AS overdue
     FROM `tbl_users_login` WHERE `status` <> 'Terminated' AND `contract_end_date` IS NOT NULL",
    [$today, $today, $today]
) ?: [];
$contractsEnding = (int) ($contracts['ending'] ?? 0);
$contractsOverdue = (int) ($contracts['overdue'] ?? 0);
$payrollDrafts = (Auth::hasSpecial('manage_payroll') || Auth::hasSpecial('approve_payroll'))
    ? (int) ($db->selectOne("SELECT COUNT(*) AS c FROM `tbl_payroll_runs` WHERE `status` = 'Draft'")['c'] ?? 0)
    : 0;

// ── Derived display values ───────────────────────────────────────────────
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$firstName = explode(' ', trim((string) ($_SESSION['fullname'] ?? '')))[0] ?? '';

$attention = array_filter([
    ['n' => $overdueTasks,    'label' => 'overdue tasks',          'icon' => 'fas fa-exclamation-circle', 'tone' => 'critical', 'href' => pageUrl('staff_management', 'hr_care') . '&tab=tasks'],
    ['n' => $agingLeads,      'label' => 'leads idle 7+ days',     'icon' => 'fas fa-hourglass-half',     'tone' => 'warning',  'href' => pageUrl('sales', 'leads') . '&aging=1'],
    ['n' => $unassignedLeads, 'label' => 'unassigned leads',       'icon' => 'fas fa-user-slash',         'tone' => 'warning',  'href' => pageUrl('sales', 'leads') . '&unassigned=1'],
    ['n' => $pendingLeaves,   'label' => 'leave requests to review', 'icon' => 'fas fa-calendar-check',   'tone' => 'warning',  'href' => pageUrl('staff_management', 'leave_management')],
    ['n' => $contractsOverdue, 'label' => $contractsOverdue === 1 ? 'contract past its end date' : 'contracts past their end date', 'icon' => 'fas fa-file-contract', 'tone' => 'critical', 'href' => pageUrl('reports', 'staff')],
    ['n' => $contractsEnding, 'label' => $contractsEnding === 1 ? 'contract / internship ending in 30 days' : 'contracts / internships ending in 30 days', 'icon' => 'fas fa-hourglass-end', 'tone' => 'warning', 'href' => pageUrl('reports', 'staff')],
    ['n' => $payrollDrafts,   'label' => $payrollDrafts === 1 ? 'payroll run awaiting approval' : 'payroll runs awaiting approval', 'icon' => 'fas fa-file-invoice-dollar', 'tone' => 'warning', 'href' => pageUrl('payroll', 'runs')],
    ['n' => $claimsPending,   'label' => $claimsPending === 1 ? 'expense claim to settle' : 'expense claims to settle', 'icon' => 'fas fa-receipt',          'tone' => 'warning',  'href' => pageUrl('my_office', 'expense_claims')],
], static fn ($a) => $a['n'] > 0);

$attendanceRateToday = percent_of($presentToday, $activeStaff);
$presentSeries = fill_series($days14, $attendanceDaily['present'] ?? []);

$tiles = [
    [
        'label' => 'Present today', 'icon' => 'fas fa-user-check', 'tone' => 'success',
        'value' => $presentToday . ' / ' . $activeStaff,
        'sub'   => $attendanceRateToday . '% in' . ($lateToday ? ' · ' . $lateToday . ' late' : '') . ($onLeaveToday ? ' · ' . $onLeaveToday . ' on leave' : ''),
        'spark' => $presentSeries,
        'href'  => pageUrl('staff_management', 'hr_care') . '&tab=attendance',
    ],
    [
        'label' => 'Open tasks', 'icon' => 'fas fa-tasks', 'tone' => $overdueTasks > 0 ? 'danger' : 'primary',
        'value' => number_format($openTasks),
        'sub'   => $overdueTasks > 0 ? $overdueTasks . ' overdue' : 'none overdue',
        'spark' => fill_series($weeks8, $tasksDoneWeekly),
        'href'  => pageUrl('staff_management', 'hr_care') . '&tab=tasks',
    ],
    [
        'label' => 'Open pipeline', 'icon' => 'fas fa-coins', 'tone' => 'warning',
        'value' => format_compact_money($openValue, 'NPR', 'NPR 0'),
        'sub'   => $openCount . ' deals · ' . format_compact_money($forecast) . ' weighted',
        'href'  => pageUrl('sales', 'leads'),
    ],
    [
        'label' => 'New leads this month', 'icon' => 'fas fa-filter', 'tone' => 'info',
        'value' => number_format($leadsMtd),
        'delta' => percent_change($leadsPrevMtd, $leadsMtd),
        'delta_label' => 'vs same days last month',
        'spark' => fill_series($months6, $leadsCreatedMonthly),
        'href'  => pageUrl('sales', 'leads'),
    ],
];

$stageMax = max(1, ...array_map(static fn ($s) => $stageRows[$s]['count'] ?? 0, LEAD_OPEN_STAGES));
?>

<div class="ana-hello">
    <div>
        <h2 class="ana-hello__title"><?= e($greeting . ($firstName !== '' ? ', ' . $firstName : '')) ?></h2>
        <span class="ana-hello__date"><?= e(formatDateViewLong($today)) ?><?= $activeFy ? ' · ' . e($activeFy) : '' ?></span>
    </div>
    <div class="ana-chips" aria-label="Needs attention">
        <?php if ($attention): ?>
            <?php foreach ($attention as $a): ?>
                <a href="<?= e($a['href']) ?>" class="ana-chip ana-chip--<?= e($a['tone']) ?>">
                    <i class="<?= e($a['icon']) ?>" aria-hidden="true"></i><strong><?= (int) $a['n'] ?></strong> <?= e($a['label']) ?>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <span class="ana-chip ana-chip--good"><i class="fas fa-check-circle" aria-hidden="true"></i>Nothing needs attention</span>
        <?php endif; ?>
    </div>
</div>

<div class="ana-grid">
    <?php foreach ($tiles as $t): ?>
        <?= stat_tile($t) ?>
    <?php endforeach; ?>
</div>

<div class="row ana-row">
    <div class="col-lg-8">
        <div class="ana-card">
            <div class="ana-card__head">
                <div>
                    <h3 class="ana-card__title">Lead flow</h3>
                    <p class="ana-card__hint">Leads created vs. won per month, last 6 months</p>
                </div>
                <a class="ana-card__link" href="<?= pageUrl('reports', 'leads') ?>">Pipeline report <i class="fas fa-arrow-right ml-1"></i></a>
            </div>
            <div class="ana-chart" style="--h:250px">
                <canvas role="img" aria-label="Bar chart: leads created and won per month for the last six months"
                        data-chart="<?= chart_attr([
                            'type'   => 'bar',
                            'labels' => $monthLabels,
                            'series' => [
                                ['label' => 'Created', 'data' => fill_series($months6, $leadsCreatedMonthly), 'slot' => 1],
                                ['label' => 'Won', 'data' => fill_series($months6, $leadsWonMonthly), 'slot' => 3],
                            ],
                            'empty'  => 'No leads in the last 6 months.',
                        ]) ?>"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="ana-card">
            <div class="ana-card__head">
                <div>
                    <h3 class="ana-card__title">Open pipeline</h3>
                    <p class="ana-card__hint">Deals by stage · value in tooltip</p>
                </div>
            </div>
            <div class="ana-chart" style="--h:190px">
                <canvas role="img" aria-label="Horizontal bar chart of open leads per pipeline stage"
                        data-chart="<?= chart_attr([
                            'type'   => 'hbar',
                            'labels' => LEAD_OPEN_STAGES,
                            'series' => [['label' => 'Deals', 'data' => array_map(static fn ($s) => $stageRows[$s]['count'] ?? 0, LEAD_OPEN_STAGES), 'slot' => 1]],
                            'empty'  => 'No open deals — the pipeline starts here.',
                        ]) ?>"></canvas>
            </div>
            <div class="ana-kv">
                <div><span class="ana-kv__label">Won</span><span class="ana-kv__value"><?= (int) $won ?></span></div>
                <div><span class="ana-kv__label">Lost</span><span class="ana-kv__value"><?= (int) $lost ?></span></div>
                <div>
                    <span class="ana-kv__label">Win rate</span>
                    <span class="ana-kv__value"><?= ($won + $lost) > 0 ? percent_of($won, $won + $lost) . '%' : '—' ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row ana-row">
    <div class="col-lg-8">
        <div class="ana-card">
            <div class="ana-card__head">
                <div>
                    <h3 class="ana-card__title">Attendance</h3>
                    <p class="ana-card__hint">Staff per day by status, last 14 days · <?= (int) $activeStaff ?> active staff</p>
                </div>
                <a class="ana-card__link" href="<?= pageUrl('reports', 'attendance') ?>">Attendance report <i class="fas fa-arrow-right ml-1"></i></a>
            </div>
            <div class="ana-chart" style="--h:240px">
                <canvas role="img" aria-label="Stacked bar chart of daily attendance by status for the last 14 days"
                        data-chart="<?= chart_attr([
                            'type'    => 'bar',
                            'stacked' => true,
                            'labels'  => array_map(static fn ($d) => date('j M', strtotime($d)), $days14),
                            'series'  => [
                                ['label' => 'Present', 'data' => $presentSeries, 'status' => 'good'],
                                ['label' => 'On leave', 'data' => fill_series($days14, $attendanceDaily['leave'] ?? []), 'status' => 'warning'],
                                ['label' => 'Absent', 'data' => fill_series($days14, $attendanceDaily['absent'] ?? []), 'status' => 'critical'],
                            ],
                            'empty'   => 'No attendance recorded in the last 14 days.',
                        ]) ?>"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="ana-card">
            <div class="ana-card__head">
                <div>
                    <h3 class="ana-card__title">Deadlines</h3>
                    <p class="ana-card__hint">Overdue and due in the next 7 days</p>
                </div>
            </div>
            <?php if ($upcoming): ?>
                <ul class="ana-list">
                    <?php foreach ($upcoming as $t):
                        $dueDate = substr((string) $t['deadline'], 0, 10);
                        $daysLeft = (int) floor((strtotime($dueDate) - strtotime($today)) / 86400);
                        [$pillTone, $pillText] = $daysLeft < 0
                            ? ['critical', abs($daysLeft) . 'd overdue']
                            : ($daysLeft === 0 ? ['warning', 'Today'] : ['', 'in ' . $daysLeft . 'd']);
                    ?>
                        <li>
                            <span class="ana-list__icon"><i class="fas fa-flag" aria-hidden="true"></i></span>
                            <div class="ana-list__main">
                                <a class="ana-list__title" href="<?= pageUrl('staff_management', 'hr_care') ?>&tab=tasks"><?= e($t['title']) ?></a>
                                <span class="ana-list__meta"><?= e(formatDateView($dueDate)) ?><?= $t['author_name'] ? ' · ' . e($t['author_name']) : '' ?></span>
                            </div>
                            <span class="ana-pill<?= $pillTone ? ' ana-pill--' . $pillTone : '' ?>"><?= e($pillText) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="tms-empty-state">
                    <i class="fas fa-calendar-check"></i>
                    <p>No deadlines this week.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row ana-row">
    <div class="col-lg-8">
        <div class="ana-card">
            <div class="ana-card__head">
                <div>
                    <h3 class="ana-card__title">Recent activity</h3>
                    <p class="ana-card__hint">Lead touches and task updates across the office</p>
                </div>
            </div>
            <?php if ($activity): ?>
                <ul class="ana-list">
                    <?php foreach ($activity as $a):
                        $isLead = $a['kind'] === 'lead';
                        $href = $isLead ? pageUrl('sales', 'leads') . '&id=' . (int) $a['ref_id'] : pageUrl('staff_management', 'hr_care') . '&tab=tasks';
                        $icon = $isLead
                            ? (['Call' => 'fa-phone', 'Email' => 'fa-envelope', 'Meeting' => 'fa-handshake', 'Status Change' => 'fa-exchange-alt', 'Task' => 'fa-tasks'][$a['label']] ?? 'fa-sticky-note')
                            : 'fa-clipboard-check';
                    ?>
                        <li>
                            <span class="ana-list__icon"><i class="fas <?= e($icon) ?>" aria-hidden="true"></i></span>
                            <div class="ana-list__main">
                                <a class="ana-list__title" href="<?= e($href) ?>"><?= e($a['title'] ?: '—') ?></a>
                                <span class="ana-list__meta">
                                    <?= e($isLead ? $a['label'] . ($a['detail'] ? ' — ' . $a['detail'] : '') : 'Task · ' . $a['label']) ?>
                                </span>
                            </div>
                            <span class="ana-list__meta text-right" style="flex-shrink:0">
                                <?= e($a['actor'] ?: 'System') ?><br><?= $a['at'] ? e(formatDateView((string) $a['at'])) : '' ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="tms-empty-state">
                    <i class="fas fa-stream"></i>
                    <p>No activity yet — lead calls and task updates will appear here.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="ana-card">
            <div class="ana-card__head"><h3 class="ana-card__title">Quick actions</h3></div>
            <div class="d-flex flex-column" style="gap:.5rem">
                <a href="<?= pageUrl('staff_management', 'staff_daily_tasks') ?>" class="btn btn-outline-primary btn-block text-left m-0"><i class="fas fa-plus fa-fw mr-2"></i>New task</a>
                <a href="<?= pageUrl('sales', 'leads') ?>" class="btn btn-outline-primary btn-block text-left m-0"><i class="fas fa-filter fa-fw mr-2"></i>Add lead</a>
                <a href="<?= pageUrl('staff_management', 'leave_management') ?>" class="btn btn-outline-primary btn-block text-left m-0"><i class="fas fa-calendar-check fa-fw mr-2"></i>Review leaves<?= $pendingLeaves ? ' <span class="badge badge-warning ml-1">' . (int) $pendingLeaves . '</span>' : '' ?></a>
                <a href="<?= pageUrl('my_office', 'expense_claims') ?>" class="btn btn-outline-primary btn-block text-left m-0"><i class="fas fa-hand-holding-usd fa-fw mr-2"></i>Expense claims<?= $claimsPending ? ' <span class="badge badge-warning ml-1">' . (int) $claimsPending . '</span>' : '' ?></a>
                <a href="<?= pageUrl('reports', 'overview') ?>" class="btn btn-outline-secondary btn-block text-left m-0"><i class="fas fa-chart-line fa-fw mr-2"></i>All reports</a>
            </div>
        </div>
    </div>
</div>
