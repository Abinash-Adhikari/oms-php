<?php
/**
 * SB-Tech — Reports / Tasks.
 * Throughput (created vs completed per week), status mix, overdue work and
 * workload per assignee / author.
 *
 * "Completed on" uses updated_on of Done tasks — there is no dedicated
 * completion timestamp, and a Done task is rarely edited afterwards.
 */
$db = Database::instance();
$today = date('Y-m-d');
$weeks12 = week_keys($today, 12);

$statuses = ['Pending', 'In Progress', 'Done', 'Rejected', 'Cancelled'];
$byStatus = rows_to_map($db->select('SELECT status, COUNT(*) AS c FROM tbl_office_tasks GROUP BY status'), 'status', 'c');
$total = (int) array_sum($byStatus);
$done = (int) ($byStatus['Done'] ?? 0);
$open = (int) (($byStatus['Pending'] ?? 0) + ($byStatus['In Progress'] ?? 0));
$overdue = (int) ($db->selectOne(
    "SELECT COUNT(*) AS c FROM tbl_office_tasks WHERE status IN ('Pending','In Progress') AND deadline IS NOT NULL AND deadline < NOW()"
)['c'] ?? 0);
// Of tasks that finished (Done) and had a deadline, how many met it.
$onTime = $db->selectOne(
    "SELECT COUNT(*) AS total, SUM(COALESCE(updated_on, added_on) <= deadline) AS met
     FROM tbl_office_tasks WHERE status = 'Done' AND deadline IS NOT NULL"
) ?: [];
$avgDays = $db->selectOne(
    "SELECT AVG(TIMESTAMPDIFF(HOUR, added_on, COALESCE(updated_on, added_on))) / 24 AS d
     FROM tbl_office_tasks WHERE status = 'Done'"
)['d'] ?? null;

$weekExpr = static fn (string $col) => "DATE_FORMAT(DATE_SUB(DATE({$col}), INTERVAL WEEKDAY({$col}) DAY), '%Y-%m-%d')";
$createdW = rows_to_map($db->select(
    'SELECT ' . $weekExpr('added_on') . ' AS wk, COUNT(*) AS c FROM tbl_office_tasks WHERE added_on >= ? GROUP BY wk',
    [$weeks12[0]]
), 'wk', 'c');
$doneW = rows_to_map($db->select(
    'SELECT ' . $weekExpr('COALESCE(updated_on, added_on)') . " AS wk, COUNT(*) AS c FROM tbl_office_tasks
     WHERE status = 'Done' AND COALESCE(updated_on, added_on) >= ? GROUP BY wk",
    [$weeks12[0]]
), 'wk', 'c');

$byAssignee = $db->select(
    "SELECT u.fullname,
            COUNT(DISTINCT ta.task_id) AS assigned_count,
            SUM(ta.status = 'Done') AS completed,
            SUM(ta.status IN ('Pending','In Progress')) AS pending,
            SUM(ta.status IN ('Pending','In Progress') AND t.deadline < NOW()) AS overdue
     FROM tbl_office_task_assignees ta
     JOIN tbl_users_login u ON u.id = ta.staff_id
     JOIN tbl_office_tasks t ON t.id = ta.task_id
     GROUP BY ta.staff_id, u.fullname
     ORDER BY pending DESC, assigned_count DESC"
);

$byAuthor = $db->select(
    "SELECT u.fullname,
            COUNT(t.id) AS total_tasks,
            SUM(t.status = 'Done') AS completed,
            SUM(t.status IN ('Pending','In Progress')) AS active,
            SUM(t.status IN ('Pending','In Progress') AND t.deadline < NOW()) AS overdue
     FROM tbl_office_tasks t
     JOIN tbl_users_login u ON u.id = t.author
     GROUP BY t.author, u.fullname
     ORDER BY total_tasks DESC"
);

$overdueList = $db->select(
    "SELECT t.title, t.deadline, u.fullname AS author_name
     FROM tbl_office_tasks t LEFT JOIN tbl_users_login u ON u.id = t.author
     WHERE t.status IN ('Pending','In Progress') AND t.deadline IS NOT NULL AND t.deadline < NOW()
     ORDER BY t.deadline ASC LIMIT 8"
);

$statusSlots = ['Pending' => ['status' => 'warning'], 'In Progress' => ['slot' => 1], 'Done' => ['status' => 'good'], 'Rejected' => ['status' => 'critical'], 'Cancelled' => ['status' => 'neutral']];
$rateTone = static fn (float $r) => $r >= 75 ? 'good' : ($r >= 50 ? 'warning' : 'critical');
?>

<div class="ana-toolbar">
    <div>
        <h2 class="ana-toolbar__title">Tasks</h2>
        <p class="ana-toolbar__sub">All-time · <?= number_format($total) ?> tasks</p>
    </div>
    <div class="ana-toolbar__actions">
        <form action="operation.php?module=reports&page=tasks" method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="export_tasks">
            <button class="btn btn-success btn-sm"><i class="fas fa-download mr-1"></i>CSV</button>
        </form>
    </div>
</div>

<div class="ana-grid">
    <?= stat_tile(['label' => 'Completion rate', 'icon' => 'fas fa-check-double', 'tone' => 'success',
        'value' => $total > 0 ? percent_of($done, $total) . '%' : '—',
        'sub' => $done . ' of ' . $total . ' tasks done']) ?>
    <?= stat_tile(['label' => 'Open', 'icon' => 'fas fa-spinner', 'tone' => 'primary',
        'value' => number_format($open),
        'sub' => (int) ($byStatus['In Progress'] ?? 0) . ' in progress · ' . (int) ($byStatus['Pending'] ?? 0) . ' pending',
        'spark' => fill_series($weeks12, $createdW)]) ?>
    <?= stat_tile(['label' => 'Overdue', 'icon' => 'fas fa-exclamation-triangle', 'tone' => $overdue > 0 ? 'danger' : 'neutral',
        'value' => number_format($overdue),
        'sub' => percent_of($overdue, $open) . '% of open work']) ?>
    <?= stat_tile(['label' => 'Delivered on time', 'icon' => 'fas fa-stopwatch', 'tone' => 'info',
        'value' => (int) ($onTime['total'] ?? 0) > 0 ? percent_of((float) $onTime['met'], (float) $onTime['total']) . '%' : '—',
        'sub' => $avgDays !== null ? 'avg ' . number_format((float) $avgDays, 1) . ' days to complete' : 'No completed tasks yet']) ?>
</div>

<div class="row ana-row">
    <div class="col-lg-8">
        <div class="ana-card">
            <div class="ana-card__head">
                <div>
                    <h3 class="ana-card__title">Throughput</h3>
                    <p class="ana-card__hint">Tasks created vs. completed per week, last 12 weeks</p>
                </div>
            </div>
            <div class="ana-chart">
                <canvas role="img" aria-label="Bar chart of tasks created and completed per week"
                        data-chart="<?= chart_attr([
                            'type' => 'bar',
                            'labels' => array_map(static fn ($d) => date('j M', strtotime($d)), $weeks12),
                            'series' => [
                                ['label' => 'Created', 'data' => fill_series($weeks12, $createdW), 'slot' => 1],
                                ['label' => 'Completed', 'data' => fill_series($weeks12, $doneW), 'slot' => 3],
                            ],
                            'empty' => 'No tasks in the last 12 weeks.',
                        ]) ?>"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="ana-card">
            <div class="ana-card__head">
                <div>
                    <h3 class="ana-card__title">Status mix</h3>
                    <p class="ana-card__hint">All tasks</p>
                </div>
            </div>
            <div class="ana-chart">
                <canvas role="img" aria-label="Doughnut chart of tasks by status"
                        data-chart="<?= chart_attr([
                            'type' => 'doughnut', 'labels' => $statuses,
                            'series' => [['label' => 'Tasks', 'data' => fill_series($statuses, $byStatus), 'colors' => array_values($statusSlots)]],
                            'empty' => 'No tasks yet.',
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
                    <h3 class="ana-card__title">Workload by assignee</h3>
                    <p class="ana-card__hint">Sorted by open work · completion = done ÷ assigned</p>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm ana-table">
                    <thead><tr><th>Assignee</th><th class="text-right">Assigned</th><th class="text-right">Open</th><th class="text-right">Overdue</th><th class="text-right">Done</th><th style="width:28%">Completion</th></tr></thead>
                    <tbody>
                    <?php foreach ($byAssignee as $a):
                        $r = percent_of((float) $a['completed'], (float) $a['assigned_count']);
                    ?>
                        <tr>
                            <td><strong><?= e($a['fullname']) ?></strong></td>
                            <td class="text-right"><?= (int) $a['assigned_count'] ?></td>
                            <td class="text-right"><?= (int) $a['pending'] ?></td>
                            <td class="text-right<?= (int) $a['overdue'] > 0 ? ' text-danger font-weight-bold' : '' ?>"><?= (int) $a['overdue'] ?></td>
                            <td class="text-right"><?= (int) $a['completed'] ?></td>
                            <td><?= meter_bar($r, $rateTone($r)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$byAssignee): ?>
                        <tr><td colspan="6" class="text-muted text-center py-4">No task assignments yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="ana-card">
            <div class="ana-card__head">
                <div>
                    <h3 class="ana-card__title">Most overdue</h3>
                    <p class="ana-card__hint">Oldest missed deadlines first</p>
                </div>
            </div>
            <?php if ($overdueList): ?>
                <ul class="ana-list">
                    <?php foreach ($overdueList as $t):
                        $late = (int) floor((time() - strtotime((string) $t['deadline'])) / 86400);
                    ?>
                        <li>
                            <div class="ana-list__main">
                                <span class="ana-list__title"><?= e($t['title']) ?></span>
                                <span class="ana-list__meta"><?= e(formatDateView(substr((string) $t['deadline'], 0, 10))) ?><?= $t['author_name'] ? ' · ' . e($t['author_name']) : '' ?></span>
                            </div>
                            <span class="ana-pill ana-pill--critical"><?= max(1, $late) ?>d late</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="tms-empty-state"><i class="fas fa-check-circle"></i><p>Nothing overdue.</p></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="ana-card ana-card--flush">
    <div class="ana-card__head">
        <div>
            <h3 class="ana-card__title">Tasks created by author</h3>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm ana-table">
            <thead><tr><th>Author</th><th class="text-right">Created</th><th class="text-right">Open</th><th class="text-right">Overdue</th><th class="text-right">Done</th><th style="width:28%">Completion</th></tr></thead>
            <tbody>
            <?php foreach ($byAuthor as $a):
                $r = percent_of((float) $a['completed'], (float) $a['total_tasks']);
            ?>
                <tr>
                    <td><strong><?= e($a['fullname']) ?></strong></td>
                    <td class="text-right"><?= (int) $a['total_tasks'] ?></td>
                    <td class="text-right"><?= (int) $a['active'] ?></td>
                    <td class="text-right<?= (int) $a['overdue'] > 0 ? ' text-danger font-weight-bold' : '' ?>"><?= (int) $a['overdue'] ?></td>
                    <td class="text-right"><?= (int) $a['completed'] ?></td>
                    <td><?= meter_bar($r, $rateTone($r)) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$byAuthor): ?>
                <tr><td colspan="6" class="text-muted text-center py-4">No tasks yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
