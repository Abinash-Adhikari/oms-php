<?php
/**
 * SB-Tech — Dashboard (US-RPT-01).
 * KPI cards aggregate over the live tables; each card links through to
 * the underlying module list (AC-RPT-01.2: no dead ends).
 */
$db = Database::instance();

$today = date('Y-m-d');

$kpis = [
    'staff_active' => [
        'label' => 'Active Staff',
        'icon'  => 'fas fa-users',
        'color' => 'info',
        'link'  => pageUrl('staff_management', 'add_staff'),
        'value' => (int) ($db->selectOne("SELECT COUNT(*) AS c FROM `tbl_users_login` WHERE `status` = 'Active'")['c'] ?? 0),
    ],
    'present_today' => [
        'label' => 'Present Today',
        'icon'  => 'fas fa-user-check',
        'color' => 'success',
        'link'  => pageUrl('staff_management', 'hr_care') . '&tab=attendance',
        'value' => (int) ($db->selectOne("SELECT COUNT(*) AS c FROM `tbl_staff_attendances` WHERE `date` = ? AND `status` = 'present'", [$today])['c'] ?? 0),
    ],
    'pending_leaves' => [
        'label' => 'Pending Leaves',
        'icon'  => 'fas fa-calendar-times',
        'color' => 'warning',
        'link'  => pageUrl('staff_management', 'leave_management'),
        'value' => (int) ($db->selectOne("SELECT COUNT(*) AS c FROM `tbl_staff_leave_applications` WHERE `status` = 'Pending'")['c'] ?? 0),
    ],
    'open_tasks' => [
        'label' => 'Open Tasks',
        'icon'  => 'fas fa-tasks',
        'color' => 'primary',
        'link'  => pageUrl('staff_management', 'hr_care') . '&tab=tasks',
        'value' => (int) ($db->selectOne("SELECT COUNT(*) AS c FROM `tbl_office_tasks` WHERE `status` IN ('Pending','In Progress')")['c'] ?? 0),
    ],
    'overdue_tasks' => [
        'label' => 'Overdue Tasks',
        'icon'  => 'fas fa-exclamation-triangle',
        'color' => 'danger',
        'link'  => pageUrl('staff_management', 'hr_care') . '&tab=tasks',
        'value' => (int) ($db->selectOne("SELECT COUNT(*) AS c FROM `tbl_office_tasks` WHERE `status` IN ('Pending','In Progress') AND `deadline` IS NOT NULL AND `deadline` < ?", [$today])['c'] ?? 0),
    ],
    'new_leads' => [
        'label' => 'New Leads',
        'icon'  => 'fas fa-filter',
        'color' => 'teal',
        'link'  => pageUrl('leads', 'leads'),
        'value' => (int) ($db->selectOne("SELECT COUNT(*) AS c FROM `tbl_leads` WHERE `stage` = 'New'")['c'] ?? 0),
    ],
    'claims_pending' => [
        'label' => 'Claims Pending Payment',
        'icon'  => 'fas fa-hand-holding-usd',
        'color' => 'orange',
        'link'  => pageUrl('accounts', 'expense_claims'),
        'value' => (int) ($db->selectOne("SELECT COUNT(*) AS c FROM `tbl_expense_claims` WHERE `status` IN ('Submitted','Approved')")['c'] ?? 0),
    ],
    'active_fy' => [
        'label' => 'Active Fiscal Year',
        'icon'  => 'fas fa-calendar-alt',
        'color' => 'secondary',
        'link'  => pageUrl('accounts', 'fiscal_years'),
        'value' => ($db->selectOne("SELECT `title` AS t FROM `tbl_fiscal_years` WHERE `closing` = 'Open' ORDER BY `id` DESC LIMIT 1")['t'] ?? '—'),
    ],
];
?>
<div class="tms-kpi-grid">
    <?php foreach ($kpis as $kpi): ?>
        <a href="<?= e($kpi['link']) ?>" class="tms-kpi-card bg-<?= e($kpi['color']) ?>">
            <div class="tms-kpi-icon"><i class="<?= e($kpi['icon']) ?>"></i></div>
            <div class="tms-kpi-meta">
                <p class="tms-kpi-label"><?= e($kpi['label']) ?></p>
                <p class="tms-kpi-value"><?= e($kpi['value']) ?></p>
            </div>
        </a>
    <?php endforeach; ?>
</div>

<div class="row">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Leads by stage</h3></div>
            <div class="card-body d-flex align-items-center justify-content-center" style="min-height: 280px;">
                <canvas id="leadsByStageChart" role="img"
                        aria-label="Donut chart of leads grouped by pipeline stage"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Today's attendance</h3></div>
            <div class="card-body" style="min-height: 280px;">
                <canvas id="attendanceChart" role="img"
                        aria-label="Bar chart of today's attendance statuses"></canvas>
            </div>
        </div>
    </div>
</div>

<?php
// Recent activity feed (latest office tasks).
$recentTasks = $db->select(
    'SELECT t.`id`, t.`title`, t.`status`, t.`added_on`, u.`fullname` AS author_name
     FROM `tbl_office_tasks` t
     JOIN `tbl_users_login` u ON u.`id` = t.`author`
     ORDER BY t.`added_on` DESC
     LIMIT 6'
);
$taskStatusTone = [
    'Pending'     => 'badge-warning',
    'In Progress' => 'badge-primary',
    'Done'        => 'badge-success',
    'Rejected'    => 'badge-danger',
    'Cancelled'   => 'badge-secondary',
];
?>
<div class="row">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Recent task activity</h3></div>
            <div class="card-body">
                <?php if ($recentTasks): ?>
                    <div class="tms-activity-feed">
                        <?php foreach ($recentTasks as $t): ?>
                            <div class="tms-activity-item">
                                <span class="tms-activity-dot tms-activity-dot--<?= e(strtolower(str_replace(' ', '-', (string) $t['status']))) ?>"></span>
                                <div class="tms-activity-body">
                                    <p class="tms-activity-title"><?= e($t['title']) ?></p>
                                    <small class="tms-activity-meta">
                                        <?= e($t['author_name']) ?> · <?= $t['added_on'] ? e(formatDateView((string) $t['added_on'])) : '' ?>
                                    </small>
                                </div>
                                <span class="badge <?= $taskStatusTone[$t['status']] ?? 'badge-secondary' ?>"><?= e($t['status']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="tms-empty-state">
                        <i class="fas fa-clipboard-list"></i>
                        <p>No tasks yet — activity will appear here.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header border-bottom-0"><h3 class="card-title">Quick actions</h3></div>
            <div class="card-body pt-0">
                <div class="d-flex flex-column">
                    <a href="<?= pageUrl('staff_management', 'staff_daily_tasks') ?>" class="btn btn-outline-primary btn-block text-left mb-2"><i class="fas fa-plus mr-2"></i>New task</a>
                    <a href="<?= pageUrl('leads', 'leads') ?>" class="btn btn-outline-primary btn-block text-left mb-2"><i class="fas fa-filter mr-2"></i>Add lead</a>
                    <a href="<?= pageUrl('staff_management', 'leave_management') ?>" class="btn btn-outline-primary btn-block text-left mb-2"><i class="fas fa-calendar-check mr-2"></i>Review leaves</a>
                    <a href="<?= pageUrl('accounts', 'expense_claims') ?>" class="btn btn-outline-primary btn-block text-left"><i class="fas fa-hand-holding-usd mr-2"></i>Expense claims</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Chart payloads consumed by assets/js/dashboard-charts.js.
$byStage = [];
foreach ($db->select('SELECT `stage`, COUNT(*) AS c FROM `tbl_leads` GROUP BY `stage`') as $r) {
    $byStage[$r['stage']] = (int) $r['c'];
}
$statuses = ['present', 'absent', 'leave', 'holiday'];
$byStatus = [];
foreach ($db->select('SELECT `status`, COUNT(*) AS c FROM `tbl_staff_attendances` WHERE `date` = ? GROUP BY `status`', [$today]) as $r) {
    $byStatus[$r['status']] = (int) $r['c'];
}

$stageColors = [
    'New'       => '#2563EB',
    'Contacted' => '#0EA5E9',
    'Qualified' => '#8B5CF6',
    'Proposal'  => '#F59E0B',
    'Won'       => '#10B981',
    'Lost'      => '#EF4444',
];
$attColors = ['present' => '#10B981', 'absent' => '#EF4444', 'leave' => '#F59E0B', 'holiday' => '#0EA5E9'];
?>
<script>
window.SB_DASH = {
    leads: {
        labels: <?= json_encode(array_keys($stageColors)) ?>,
        values: <?= json_encode(array_map(static fn ($s) => (int) ($byStage[$s] ?? 0), array_keys($stageColors))) ?>,
        colors: <?= json_encode(array_values($stageColors)) ?>
    },
    attendance: {
        labels: <?= json_encode(array_map('ucfirst', $statuses)) ?>,
        values: <?= json_encode(array_map(static fn ($st) => (int) ($byStatus[$st] ?? 0), $statuses)) ?>,
        keys: <?= json_encode($statuses) ?>,
        colors: <?= json_encode(array_values($attColors)) ?>
    }
};
</script>
