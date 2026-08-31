<?php
/**
 * SB-Tech — Setup Wizard (first-run experience).
 * Guides the admin through initial configuration after a fresh install.
 * Checks what's missing and provides step-by-step setup.
 */
include __DIR__ . '/../config/setup.php';

if (!Auth::check()) {
    redirect('login.php');
}

$db = Database::instance();
$step = (int) ($_GET['step'] ?? 1);

// Check what's already configured.
$checks = [
    'profile'      => (bool) $db->selectOne('SELECT `id` FROM `tbl_office_profiles` LIMIT 1'),
    'departments'  => (int) ($db->selectOne('SELECT COUNT(*) AS c FROM `tbl_office_departments`')['c'] ?? 0),
    'designations' => (int) ($db->selectOne('SELECT COUNT(*) AS c FROM `tbl_office_designation`')['c'] ?? 0),
    'leave_types'  => (int) ($db->selectOne('SELECT COUNT(*) AS c FROM `tbl_office_leave_configs`')['c'] ?? 0),
    'fiscal_year'  => (int) ($db->selectOne('SELECT COUNT(*) AS c FROM `tbl_fiscal_years`')['c'] ?? 0),
    'staff_count'  => (int) ($db->selectOne("SELECT COUNT(*) AS c FROM `tbl_users_login` WHERE `status` = 'Active' AND `username` != 'admin'")['c'] ?? 0),
    'templates'    => (int) ($db->selectOne('SELECT COUNT(*) AS c FROM `tbl_communication_templates`')['c'] ?? 0),
    'calendar'     => (int) ($db->selectOne('SELECT COUNT(*) AS c FROM `tbl_calendar`')['c'] ?? 0),
];

$completionPct = 0;
$checksList = [
    ['key' => 'profile', 'label' => 'Office Profile', 'done' => $checks['profile']],
    ['key' => 'departments', 'label' => 'Departments (≥3)', 'done' => $checks['departments'] >= 3],
    ['key' => 'designations', 'label' => 'Designations (≥3)', 'done' => $checks['designations'] >= 3],
    ['key' => 'leave_types', 'label' => 'Leave Types (≥3)', 'done' => $checks['leave_types'] >= 3],
    ['key' => 'fiscal_year', 'label' => 'Fiscal Year', 'done' => $checks['fiscal_year'] >= 1],
    ['key' => 'staff_count', 'label' => 'Staff Members (≥1)', 'done' => $checks['staff_count'] >= 1],
    ['key' => 'templates', 'label' => 'Notification Templates', 'done' => $checks['templates'] >= 3],
    ['key' => 'calendar', 'label' => 'BS Calendar Data', 'done' => $checks['calendar'] > 0],
];
$doneCount = 0;
foreach ($checksList as $c) {
    if ($c['done']) {
        $doneCount++;
    }
}
$completionPct = (int) round(($doneCount / count($checksList)) * 100);

include __DIR__ . '/includes/head.php';
?>
<?php include __DIR__ . '/includes/topNavBar.php'; ?>
<?php include __DIR__ . '/includes/mainSideBar.php'; ?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0"><i class="fas fa-magic mr-2"></i>Setup Wizard</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?= pageUrl('dashboard') ?>">Home</a></li>
                        <li class="breadcrumb-item active">Setup Wizard</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">

            <!-- Progress bar -->
            <div class="card card-outline card-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-1">
                        <span><strong>Setup Progress</strong></span>
                        <span><strong><?= $completionPct ?>%</strong> complete (<?= $doneCount ?>/<?= count($checksList) ?> steps)</span>
                    </div>
                    <div class="progress" style="height: 24px;">
                        <div class="progress-bar bg-<?= $completionPct >= 100 ? 'success' : 'primary' ?>" style="width: <?= $completionPct ?>%"></div>
                    </div>
                </div>
            </div>

            <!-- Checklist -->
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Setup Checklist</h3></div>
                        <div class="card-body p-0">
                            <table class="table table-sm">
                                <tbody>
                                <?php foreach ($checksList as $i => $c): ?>
                                    <tr class="<?= $c['done'] ? 'table-success' : '' ?>">
                                        <td style="width:40px" class="text-center">
                                            <?php if ($c['done']): ?>
                                                <i class="fas fa-check-circle text-success fa-lg"></i>
                                            <?php else: ?>
                                                <i class="fas fa-circle text-muted fa-lg"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?= e($c['label']) ?></strong>
                                            <?php if (!$c['done']): ?>
                                                <br><small class="text-muted">
                                                <?php switch ($c['key']):
                                                    case 'profile': ?>
                                                        Go to <a href="<?= pageUrl('office_setup', 'office_profile') ?>">Office Setup → Profile</a> and fill in your organization details.
                                                    <?php break; case 'departments': ?>
                                                        Go to <a href="<?= pageUrl('office_setup', 'departments') ?>">Office Setup → Departments</a> and add at least 3 departments.
                                                    <?php break; case 'designations': ?>
                                                        Go to <a href="<?= pageUrl('office_setup', 'designations') ?>">Office Setup → Designations</a> and add at least 3 designations.
                                                    <?php break; case 'leave_types': ?>
                                                        Go to <a href="<?= pageUrl('staff_management', 'leave_management') ?>&tab=setup_leave">Staff → Leave Management → Setup</a> and configure leave types.
                                                    <?php break; case 'fiscal_year': ?>
                                                        Go to <a href="<?= pageUrl('accounts', 'fiscal_years') ?>">Accounts → Fiscal Years</a> and create a fiscal year.
                                                    <?php break; case 'staff_count': ?>
                                                        Go to <a href="<?= pageUrl('staff_management', 'add_staff') ?>&add=1">Staff → Add Staff</a> and create at least one non-admin staff member.
                                                    <?php break; case 'templates': ?>
                                                        Go to <a href="<?= pageUrl('communication', 'templates') ?>">Communication → Templates</a> and create notification templates.
                                                    <?php break; case 'calendar': ?>
                                                        Run <code>php scripts/seed_data.php</code> to seed the Nepali calendar data.
                                                    <?php break; endswitch; ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td style="width:80px" class="text-right">
                                            <?php if ($c['done']): ?>
                                                <span class="badge badge-success">Done</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card card-outline card-success">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-bolt mr-1"></i>Quick Setup</h3></div>
                        <div class="card-body">
                            <p class="text-muted">Run the seed script to populate default data:</p>
                            <pre class="bg-dark text-light p-3 rounded"><code>php scripts/seed_data.php</code></pre>
                            <p class="small text-muted mt-2">This creates: office profile, departments, designations, leave types, fiscal year, holidays, meeting halls, notification templates, and the admin user.</p>
                            <hr>
                            <h6>Default Login</h6>
                            <p class="mb-1"><strong>Username:</strong> admin</p>
                            <p class="mb-0"><strong>Password:</strong> admin</p>
                            <p class="text-danger small mt-1"><i class="fas fa-exclamation-triangle mr-1"></i>Change the default password immediately!</p>
                        </div>
                    </div>

                    <?php if ($completionPct >= 100): ?>
                    <div class="card card-outline card-success">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-trophy mr-1 text-success"></i>Setup Complete!</h3></div>
                        <div class="card-body text-center">
                            <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                            <h5>All setup steps are complete!</h5>
                            <p class="text-muted">Your system is ready to use.</p>
                            <a href="<?= pageUrl('dashboard') ?>" class="btn btn-success"><i class="fas fa-tachometer-alt mr-1"></i>Go to Dashboard</a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-book mr-1"></i>Documentation</h3></div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0">
                                <li class="mb-2"><a href="../docs/SYSTEM_MODULES.md" target="_blank"><i class="fas fa-file-alt mr-1"></i>System Modules Reference</a></li>
                                <li class="mb-2"><a href="../docs/PRD.md" target="_blank"><i class="fas fa-clipboard-list mr-1"></i>Product Requirements</a></li>
                                <li class="mb-0"><a href="../docs/SB_TECH_SYSTEM_ANALYSIS.md" target="_blank"><i class="fas fa-sitemap mr-1"></i>Architecture & Design</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
