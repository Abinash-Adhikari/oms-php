<?php
/**
 * SB-Tech — Reports / Overview.
 *
 * Executive scorecard: month-to-date vs the same days of last month (a fair,
 * like-for-like comparison mid-month), 6-month sparklines, two trend charts,
 * and a directory of the detailed reports with one headline metric each.
 */
$db = Database::instance();
$today = date('Y-m-d');
$w = month_to_date_windows($today);
$months6 = month_keys(date('Y-m'), 6);
$months12 = month_keys(date('Y-m'), 12);
$weeks12 = week_keys($today, 12);
$from6 = $months6[0] . '-01';

/**
 * One metric over the current and previous MTD windows plus a monthly series.
 * $dateExpr / $valueExpr / $where are trusted SQL fragments written below,
 * never user input; dates are always bound.
 *
 * @return array{cur: float, prev: float, monthly: array<string, float>}
 */
$windowed = static function (string $table, string $dateExpr, string $valueExpr, string $where) use ($db, $w, $from6): array {
    $row = $db->selectOne(
        "SELECT COALESCE(SUM(CASE WHEN DATE({$dateExpr}) BETWEEN ? AND ? THEN {$valueExpr} END), 0) AS cur,
                COALESCE(SUM(CASE WHEN DATE({$dateExpr}) BETWEEN ? AND ? THEN {$valueExpr} END), 0) AS prev
         FROM `{$table}` WHERE {$where}",
        [$w['cur_start'], $w['cur_end'], $w['prev_start'], $w['prev_end']]
    ) ?: [];
    $monthly = rows_to_map($db->select(
        "SELECT DATE_FORMAT({$dateExpr}, '%Y-%m') AS m, SUM({$valueExpr}) AS v
         FROM `{$table}` WHERE {$where} AND {$dateExpr} >= ? GROUP BY m",
        [$from6]
    ), 'm', 'v');

    return ['cur' => (float) ($row['cur'] ?? 0), 'prev' => (float) ($row['prev'] ?? 0), 'monthly' => $monthly];
};

// Won/Lost have no close timestamp; the last update is the closest proxy.
$leadsCreated = $windowed('tbl_leads', 'added_on', '1', '1=1');
$dealsWon = $windowed('tbl_leads', 'COALESCE(updated_on, added_on)', '1', "stage = 'Won'");
$wonValue = $windowed('tbl_leads', 'COALESCE(updated_on, added_on)', 'COALESCE(estimated_value, 0)', "stage = 'Won'");
$tasksDone = $windowed('tbl_office_tasks', 'COALESCE(updated_on, added_on)', '1', "status = 'Done'");
$expenses = $windowed('tbl_expense_claims', 'expense_date', 'amount', "status IN ('Submitted','Approved','Paid')");

// Attendance rate = present / (present + absent); leave and holidays are excused.
$attRow = $db->selectOne(
    "SELECT SUM(date BETWEEN ? AND ? AND status = 'present') AS cp,
            SUM(date BETWEEN ? AND ? AND status IN ('present','absent')) AS cd,
            SUM(date BETWEEN ? AND ? AND status = 'present') AS pp,
            SUM(date BETWEEN ? AND ? AND status IN ('present','absent')) AS pd
     FROM tbl_staff_attendances",
    [$w['cur_start'], $w['cur_end'], $w['cur_start'], $w['cur_end'], $w['prev_start'], $w['prev_end'], $w['prev_start'], $w['prev_end']]
) ?: [];
$attCur = percent_of((float) ($attRow['cp'] ?? 0), (float) ($attRow['cd'] ?? 0), 1);
$attPrev = percent_of((float) ($attRow['pp'] ?? 0), (float) ($attRow['pd'] ?? 0), 1);
$attHasCur = (float) ($attRow['cd'] ?? 0) > 0;
$attHasPrev = (float) ($attRow['pd'] ?? 0) > 0;
$attMonthly = [];
foreach ($db->select(
    "SELECT DATE_FORMAT(date, '%Y-%m') AS m, SUM(status = 'present') AS p, SUM(status IN ('present','absent')) AS d
     FROM tbl_staff_attendances WHERE date >= ? GROUP BY m",
    [$from6]
) as $r) {
    $attMonthly[$r['m']] = percent_of((float) $r['p'], (float) $r['d'], 1);
}

$periodLabel = date('j M', strtotime($w['cur_start'])) . ' – ' . date('j M', strtotime($w['cur_end']))
    . ' vs ' . date('j M', strtotime($w['prev_start'])) . ' – ' . date('j M', strtotime($w['prev_end']));

$scorecards = [
    [
        'label' => 'Leads created', 'icon' => 'fas fa-filter', 'tone' => 'info',
        'value' => number_format($leadsCreated['cur']),
        'delta' => percent_change($leadsCreated['prev'], $leadsCreated['cur']), 'delta_label' => 'vs ' . (int) $leadsCreated['prev'],
        'spark' => fill_series($months6, $leadsCreated['monthly']), 'href' => pageUrl('reports', 'leads'),
    ],
    [
        'label' => 'Deals won', 'icon' => 'fas fa-trophy', 'tone' => 'success',
        'value' => number_format($dealsWon['cur']),
        'delta' => percent_change($dealsWon['prev'], $dealsWon['cur']), 'delta_label' => 'vs ' . (int) $dealsWon['prev'],
        'spark' => fill_series($months6, $dealsWon['monthly']), 'href' => pageUrl('reports', 'leads'),
    ],
    [
        'label' => 'Won value', 'icon' => 'fas fa-coins', 'tone' => 'warning',
        'value' => format_compact_money($wonValue['cur'], 'NPR', 'NPR 0'),
        'delta' => percent_change($wonValue['prev'], $wonValue['cur']), 'delta_label' => 'vs ' . format_compact_money($wonValue['prev'], 'NPR', 'NPR 0'),
        'spark' => fill_series($months6, $wonValue['monthly']), 'href' => pageUrl('reports', 'leads'),
    ],
    [
        'label' => 'Tasks completed', 'icon' => 'fas fa-check-double', 'tone' => 'primary',
        'value' => number_format($tasksDone['cur']),
        'delta' => percent_change($tasksDone['prev'], $tasksDone['cur']), 'delta_label' => 'vs ' . (int) $tasksDone['prev'],
        'spark' => fill_series($months6, $tasksDone['monthly']), 'href' => pageUrl('reports', 'tasks'),
    ],
    [
        'label' => 'Attendance rate', 'icon' => 'fas fa-user-check', 'tone' => 'teal',
        'value' => $attHasCur ? $attCur . '%' : '—',
        'delta' => $attHasPrev ? round($attCur - $attPrev, 1) : null, 'delta_unit' => ' pp',
        'delta_label' => $attHasPrev ? 'vs ' . $attPrev . '%' : 'no data last month',
        'spark' => fill_series($months6, $attMonthly), 'href' => pageUrl('reports', 'attendance'),
    ],
    [
        'label' => 'Expenses claimed', 'icon' => 'fas fa-receipt', 'tone' => 'danger',
        'value' => format_compact_money($expenses['cur'], 'NPR', 'NPR 0'),
        'delta' => percent_change($expenses['prev'], $expenses['cur']), 'higher_is_better' => false,
        'delta_label' => 'vs ' . format_compact_money($expenses['prev'], 'NPR', 'NPR 0'),
        'spark' => fill_series($months6, $expenses['monthly']), 'href' => pageUrl('reports', 'finance'),
    ],
];

// A rate needs data in both windows to compare; otherwise say so plainly.
if (!$attHasCur) {
    unset($scorecards[4]['delta'], $scorecards[4]['delta_label']);
    $scorecards[4]['sub'] = 'No attendance recorded yet this month';
}

// ── Trend charts ─────────────────────────────────────────────────────────
$leadsCreated12 = rows_to_map($db->select(
    "SELECT DATE_FORMAT(added_on, '%Y-%m') AS m, COUNT(*) AS c FROM tbl_leads WHERE added_on >= ? GROUP BY m",
    [$months12[0] . '-01']
), 'm', 'c');
$leadsWon12 = rows_to_map($db->select(
    "SELECT DATE_FORMAT(COALESCE(updated_on, added_on), '%Y-%m') AS m, COUNT(*) AS c FROM tbl_leads
     WHERE stage = 'Won' AND COALESCE(updated_on, added_on) >= ? GROUP BY m",
    [$months12[0] . '-01']
), 'm', 'c');
$weekExpr = static fn (string $col) => "DATE_FORMAT(DATE_SUB(DATE({$col}), INTERVAL WEEKDAY({$col}) DAY), '%Y-%m-%d')";
$tasksCreatedW = rows_to_map($db->select(
    'SELECT ' . $weekExpr('added_on') . ' AS wk, COUNT(*) AS c FROM tbl_office_tasks WHERE added_on >= ? GROUP BY wk',
    [$weeks12[0]]
), 'wk', 'c');
$tasksDoneW = rows_to_map($db->select(
    'SELECT ' . $weekExpr('COALESCE(updated_on, added_on)') . " AS wk, COUNT(*) AS c FROM tbl_office_tasks
     WHERE status = 'Done' AND COALESCE(updated_on, added_on) >= ? GROUP BY wk",
    [$weeks12[0]]
), 'wk', 'c');

// ── Headline metric per report ───────────────────────────────────────────
$head = $db->selectOne(
    "SELECT
        (SELECT COUNT(*) FROM tbl_users_login WHERE status = 'Active') AS staff,
        (SELECT COUNT(*) FROM tbl_staff_leave_applications WHERE status = 'Pending') AS leave_pending,
        (SELECT COUNT(*) FROM tbl_office_tasks WHERE status IN ('Pending','In Progress') AND deadline IS NOT NULL AND deadline < ?) AS overdue,
        (SELECT COALESCE(SUM(estimated_value), 0) FROM tbl_leads WHERE stage IN ('New','Contacted','Qualified','Proposal')) AS pipeline,
        (SELECT COUNT(*) FROM tbl_expense_claims WHERE status IN ('Submitted','Approved')) AS claims,
        (SELECT COUNT(*) FROM tbl_audit_log WHERE added_on >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) AS audit24",
    [$today]
) ?: [];

$reportCards = [
    ['icon' => 'fas fa-user-clock', 'tone' => '#0EA5E9', 'title' => 'Attendance', 'desc' => 'Daily trend, attendance rate, punctuality and hours per person.', 'metric' => ($attHasCur ? $attCur . '% attendance this month' : 'No attendance this month'), 'link' => pageUrl('reports', 'attendance')],
    ['icon' => 'fas fa-umbrella-beach', 'tone' => '#F59E0B', 'title' => 'Leave', 'desc' => 'Utilisation by type, balances and pending approvals.', 'metric' => (int) ($head['leave_pending'] ?? 0) . ' awaiting approval', 'link' => pageUrl('reports', 'leave')],
    ['icon' => 'fas fa-tasks', 'tone' => 'var(--accent)', 'title' => 'Tasks', 'desc' => 'Throughput, completion rate, overdue work and workload.', 'metric' => (int) ($head['overdue'] ?? 0) . ' overdue', 'link' => pageUrl('reports', 'tasks')],
    ['icon' => 'fas fa-funnel-dollar', 'tone' => '#14B8A6', 'title' => 'Leads & pipeline', 'desc' => 'Funnel, win rate by source and owner, aging.', 'metric' => format_compact_money((float) ($head['pipeline'] ?? 0)) . ' open pipeline', 'link' => pageUrl('reports', 'leads')],
    ['icon' => 'fas fa-calculator', 'tone' => '#10B981', 'title' => 'Finance', 'desc' => 'Monthly cash in vs out, voucher register, claims.', 'metric' => (int) ($head['claims'] ?? 0) . ((int) ($head['claims'] ?? 0) === 1 ? ' claim' : ' claims') . ' awaiting payment', 'link' => pageUrl('reports', 'finance')],
    ['icon' => 'fas fa-boxes', 'tone' => '#64748B', 'title' => 'Inventory', 'desc' => 'Stock value by category, low stock, assets.', 'metric' => 'Stock & asset register', 'link' => pageUrl('reports', 'inventory')],
    ['icon' => 'fas fa-users-cog', 'tone' => '#EF4444', 'title' => 'Staff', 'desc' => 'Headcount by department, designation and type; joiners.', 'metric' => (int) ($head['staff'] ?? 0) . ' active staff', 'link' => pageUrl('reports', 'staff')],
    ['icon' => 'fas fa-history', 'tone' => '#475569', 'title' => 'Audit log', 'desc' => 'Who did what, when — logins, changes, approvals.', 'metric' => (int) ($head['audit24'] ?? 0) . ' events in 24 h', 'link' => pageUrl('reports', 'audit')],
];
?>

<div class="ana-toolbar">
    <div>
        <h2 class="ana-toolbar__title">This month so far</h2>
        <p class="ana-toolbar__sub"><?= e($periodLabel) ?> · same number of days, so the comparison is fair mid-month</p>
    </div>
</div>

<div class="ana-grid" style="--ana-cols:3">
    <?php foreach ($scorecards as $sc): ?>
        <?= stat_tile($sc) ?>
    <?php endforeach; ?>
</div>

<div class="row ana-row">
    <div class="col-lg-7">
        <div class="ana-card">
            <div class="ana-card__head">
                <div>
                    <h3 class="ana-card__title">Leads created vs. won</h3>
                    <p class="ana-card__hint">Per month, last 12 months</p>
                </div>
            </div>
            <div class="ana-chart">
                <canvas role="img" aria-label="Line chart of leads created and won per month over the last year"
                        data-chart="<?= chart_attr([
                            'type'   => 'line',
                            'labels' => array_map(static fn ($m) => date('M y', strtotime($m . '-01')), $months12),
                            'series' => [
                                ['label' => 'Created', 'data' => fill_series($months12, $leadsCreated12), 'slot' => 1],
                                ['label' => 'Won', 'data' => fill_series($months12, $leadsWon12), 'slot' => 3],
                            ],
                            'empty'  => 'No leads in the last 12 months.',
                        ]) ?>"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="ana-card">
            <div class="ana-card__head">
                <div>
                    <h3 class="ana-card__title">Task throughput</h3>
                    <p class="ana-card__hint">Created vs. completed per week, last 12 weeks</p>
                </div>
            </div>
            <div class="ana-chart">
                <canvas role="img" aria-label="Bar chart of tasks created and completed per week"
                        data-chart="<?= chart_attr([
                            'type'   => 'bar',
                            'labels' => array_map(static fn ($d) => date('j M', strtotime($d)), $weeks12),
                            'series' => [
                                ['label' => 'Created', 'data' => fill_series($weeks12, $tasksCreatedW), 'slot' => 1],
                                ['label' => 'Completed', 'data' => fill_series($weeks12, $tasksDoneW), 'slot' => 3],
                            ],
                            'empty'  => 'No tasks in the last 12 weeks.',
                        ]) ?>"></canvas>
            </div>
        </div>
    </div>
</div>

<h3 class="ana-section-title">Detailed reports</h3>
<div class="ana-dir">
    <?php foreach ($reportCards as $rc): ?>
        <a href="<?= e($rc['link']) ?>" class="ana-dir__item" style="--tone:<?= e($rc['tone']) ?>">
            <span class="ana-dir__icon"><i class="<?= e($rc['icon']) ?>" aria-hidden="true"></i></span>
            <span>
                <span class="ana-dir__title"><?= e($rc['title']) ?></span>
                <span class="ana-dir__desc"><?= e($rc['desc']) ?></span>
                <span class="ana-dir__metric"><?= e($rc['metric']) ?></span>
            </span>
        </a>
    <?php endforeach; ?>
</div>
