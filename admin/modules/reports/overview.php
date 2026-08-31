<?php
/**
 * SB-Tech — Reports / Overview.
 * Central report hub with KPIs across all modules and quick-access report cards.
 */
$db = Database::instance();
$today = date('Y-m-d');
$thisMonth = date('Y-m');
$prevMonth = date('Y-m', strtotime('-1 month'));

// === KPI Queries ===
$kpis = [
    'staff' => [
        'icon' => 'fas fa-users', 'color' => 'info', 'label' => 'Active Staff',
        'value' => (int) ($db->selectOne("SELECT COUNT(*) AS c FROM tbl_users_login WHERE status = 'Active'")['c'] ?? 0),
        'link' => pageUrl('reports', 'staff'),
    ],
    'present_today' => [
        'icon' => 'fas fa-user-check', 'color' => 'success', 'label' => 'Present Today',
        'value' => (int) ($db->selectOne("SELECT COUNT(*) AS c FROM tbl_staff_attendances WHERE date = ? AND status = 'present'", [$today])['c'] ?? 0),
        'link' => pageUrl('reports', 'attendance'),
    ],
    'pending_leaves' => [
        'icon' => 'fas fa-calendar-times', 'color' => 'warning', 'label' => 'Pending Leaves',
        'value' => (int) ($db->selectOne("SELECT COUNT(*) AS c FROM tbl_staff_leave_applications WHERE status = 'Pending'")['c'] ?? 0),
        'link' => pageUrl('reports', 'leave'),
    ],
    'open_tasks' => [
        'icon' => 'fas fa-tasks', 'color' => 'primary', 'label' => 'Open Tasks',
        'value' => (int) ($db->selectOne("SELECT COUNT(*) AS c FROM tbl_office_tasks WHERE status IN ('Pending','In Progress')")['c'] ?? 0),
        'link' => pageUrl('reports', 'tasks'),
    ],
    'overdue_tasks' => [
        'icon' => 'fas fa-exclamation-triangle', 'color' => 'danger', 'label' => 'Overdue Tasks',
        'value' => (int) ($db->selectOne("SELECT COUNT(*) AS c FROM tbl_office_tasks WHERE status IN ('Pending','In Progress') AND deadline IS NOT NULL AND deadline < ?", [$today])['c'] ?? 0),
        'link' => pageUrl('reports', 'tasks'),
    ],
    'leads_total' => [
        'icon' => 'fas fa-filter', 'color' => 'teal', 'label' => 'Total Leads',
        'value' => (int) ($db->selectOne("SELECT COUNT(*) AS c FROM tbl_leads")['c'] ?? 0),
        'link' => pageUrl('reports', 'leads'),
    ],
    'leads_won' => [
        'icon' => 'fas fa-trophy', 'color' => 'success', 'label' => 'Leads Won',
        'value' => (int) ($db->selectOne("SELECT COUNT(*) AS c FROM tbl_leads WHERE stage = 'Won'")['c'] ?? 0),
        'link' => pageUrl('reports', 'leads'),
    ],
    'expense_pending' => [
        'icon' => 'fas fa-receipt', 'color' => 'orange', 'label' => 'Claims Pending',
        'value' => (int) ($db->selectOne("SELECT COUNT(*) AS c FROM tbl_expense_claims WHERE status IN ('Submitted','Approved')")['c'] ?? 0),
        'link' => pageUrl('reports', 'finance'),
    ],
];

// Month-over-month comparison
$prevMonthLeads = (int) ($db->selectOne("SELECT COUNT(*) AS c FROM tbl_leads WHERE added_on LIKE ?", [$prevMonth . '%'])['c'] ?? 0);
$thisMonthLeads = (int) ($db->selectOne("SELECT COUNT(*) AS c FROM tbl_leads WHERE added_on LIKE ?", [$thisMonth . '%'])['c'] ?? 0);
$prevMonthTasks = (int) ($db->selectOne("SELECT COUNT(*) AS c FROM tbl_office_tasks WHERE status = 'Done' AND updated_on LIKE ?", [$prevMonth . '%'])['c'] ?? 0);
$thisMonthTasks = (int) ($db->selectOne("SELECT COUNT(*) AS c FROM tbl_office_tasks WHERE status = 'Done' AND updated_on LIKE ?", [$thisMonth . '%'])['c'] ?? 0);
?>

<!-- KPI Cards -->
<div class="row">
    <?php foreach ($kpis as $kpi): ?>
        <div class="col-lg-3 col-md-4 col-6 mb-3">
            <div class="info-box">
                <span class="info-box-icon bg-<?= $kpi['color'] ?>"><i class="<?= $kpi['icon'] ?>"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text"><?= $kpi['label'] ?></span>
                    <span class="info-box-number"><?= $kpi['value'] ?></span>
                </div>
                <a href="<?= e($kpi['link']) ?>" class="info-box-footer">Details <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Month-over-month -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card card-outline card-primary">
            <div class="card-header"><h5 class="card-title"><i class="fas fa-chart-line mr-1"></i>Leads This Month vs Last</h5></div>
            <div class="card-body text-center">
                <div class="row">
                    <div class="col-6">
                        <h3 class="text-muted"><?= $prevMonthLeads ?></h3>
                        <small class="text-muted"><?= date('M Y', strtotime($prevMonth . '-01')) ?></small>
                    </div>
                    <div class="col-6">
                        <h3 class="text-primary"><?= $thisMonthLeads ?></h3>
                        <small class="text-muted"><?= date('M Y') ?></small>
                    </div>
                </div>
                <div class="mt-2">
                    <?php
                    $change = $prevMonthLeads > 0 ? round((($thisMonthLeads - $prevMonthLeads) / $prevMonthLeads) * 100) : ($thisMonthLeads > 0 ? 100 : 0);
                    $arrow = $change >= 0 ? '↑' : '↓';
                    $cls = $change >= 0 ? 'text-success' : 'text-danger';
                    ?>
                    <span class="<?= $cls ?> font-weight-bold"><?= $arrow ?> <?= abs($change) ?>%</span>
                    <small class="text-muted">vs last month</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card card-outline card-success">
            <div class="card-header"><h5 class="card-title"><i class="fas fa-check-double mr-1"></i>Tasks Completed This Month vs Last</h5></div>
            <div class="card-body text-center">
                <div class="row">
                    <div class="col-6">
                        <h3 class="text-muted"><?= $prevMonthTasks ?></h3>
                        <small class="text-muted"><?= date('M Y', strtotime($prevMonth . '-01')) ?></small>
                    </div>
                    <div class="col-6">
                        <h3 class="text-success"><?= $thisMonthTasks ?></h3>
                        <small class="text-muted"><?= date('M Y') ?></small>
                    </div>
                </div>
                <div class="mt-2">
                    <?php
                    $change2 = $prevMonthTasks > 0 ? round((($thisMonthTasks - $prevMonthTasks) / $prevMonthTasks) * 100) : ($thisMonthTasks > 0 ? 100 : 0);
                    $arrow2 = $change2 >= 0 ? '↑' : '↓';
                    $cls2 = $change2 >= 0 ? 'text-success' : 'text-danger';
                    ?>
                    <span class="<?= $cls2 ?> font-weight-bold"><?= $arrow2 ?> <?= abs($change2) ?>%</span>
                    <small class="text-muted">vs last month</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Report Quick Access Cards -->
<div class="row">
    <?php
    $reportCards = [
        ['icon' => 'fas fa-user-clock', 'color' => 'info', 'title' => 'Attendance Report', 'desc' => 'Monthly attendance summary, late/early tracking, working hours.', 'link' => pageUrl('reports', 'attendance')],
        ['icon' => 'fas fa-umbrella-beach', 'color' => 'warning', 'title' => 'Leave Report', 'desc' => 'Leave usage by staff/type, balance summary, pending approvals.', 'link' => pageUrl('reports', 'leave')],
        ['icon' => 'fas fa-tasks', 'color' => 'primary', 'title' => 'Task Report', 'desc' => 'Task completion rates, overdue analysis, workload distribution.', 'link' => pageUrl('reports', 'tasks')],
        ['icon' => 'fas fa-funnel-dollar', 'color' => 'teal', 'title' => 'Lead Pipeline Report', 'desc' => 'Pipeline value, conversion rates, aging, owner performance.', 'link' => pageUrl('reports', 'leads')],
        ['icon' => 'fas fa-calculator', 'color' => 'success', 'title' => 'Finance Report', 'desc' => 'Voucher register, expense breakdown, income vs expense.', 'link' => pageUrl('reports', 'finance')],
        ['icon' => 'fas fa-boxes', 'color' => 'secondary', 'title' => 'Inventory Report', 'desc' => 'Stock value, low stock alerts, asset register.', 'link' => pageUrl('reports', 'inventory')],
        ['icon' => 'fas fa-users-cog', 'color' => 'danger', 'title' => 'Staff Report', 'desc' => 'Department breakdown, designation distribution, headcount.', 'link' => pageUrl('reports', 'staff')],
        ['icon' => 'fas fa-history', 'color' => 'dark', 'title' => 'Audit Log', 'desc' => 'System activity trail — logins, changes, approvals.', 'link' => pageUrl('reports', 'audit')],
    ];
    foreach ($reportCards as $rc):
    ?>
    <div class="col-lg-3 col-md-4 col-6 mb-3">
        <a href="<?= e($rc['link']) ?>" class="text-decoration-none">
            <div class="card card-outline card-hover h-100">
                <div class="card-body text-center">
                    <i class="<?= $rc['icon'] ?> fa-3x mb-2 text-<?= $rc['color'] ?>"></i>
                    <h6 class="font-weight-bold"><?= e($rc['title']) ?></h6>
                    <p class="text-muted small mb-0"><?= e($rc['desc']) ?></p>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>
