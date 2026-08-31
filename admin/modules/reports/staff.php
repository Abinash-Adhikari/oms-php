<?php
/**
 * SB-Tech — Reports / Staff.
 * Department breakdown, designation distribution, headcount trends.
 */
$db = Database::instance();

// By department
$byDept = $db->select(
    "SELECT d.title AS dept, COUNT(u.id) AS total,
            SUM(u.status = 'Active') AS active,
            SUM(u.status = 'Block') AS blocked,
            SUM(u.status = 'Terminated') AS terminated
     FROM tbl_office_departments d
     LEFT JOIN tbl_users_login u ON u.department_id = d.id
     GROUP BY d.id, d.title
     ORDER BY active DESC"
);

// By designation
$byDesig = $db->select(
    "SELECT g.title AS designation, COUNT(u.id) AS total,
            SUM(u.status = 'Active') AS active
     FROM tbl_office_designation g
     LEFT JOIN tbl_users_login u ON u.designation_id = g.id
     GROUP BY g.id, g.title
     ORDER BY active DESC"
);

// By status
$byStatus = $db->select(
    "SELECT status, COUNT(*) AS c FROM tbl_users_login GROUP BY status"
);
$statusMap = [];
foreach ($byStatus as $s) {
    $statusMap[$s['status']] = (int) $s['c'];
}

// By gender
$byGender = $db->select(
    "SELECT gender, COUNT(*) AS c FROM tbl_users_login WHERE status = 'Active' GROUP BY gender"
);

// By staff type
$byType = $db->select(
    "SELECT staff_type, COUNT(*) AS c FROM tbl_users_login WHERE status = 'Active' GROUP BY staff_type"
);

// Recent joiners (last 90 days)
$recentJoiners = $db->select(
    "SELECT u.fullname, u.join_date, d.title AS dept, g.title AS designation
     FROM tbl_users_login u
     LEFT JOIN tbl_office_departments d ON d.id = u.department_id
     LEFT JOIN tbl_office_designation g ON g.id = u.designation_id
     WHERE u.status = 'Active' AND u.join_date IS NOT NULL
     ORDER BY u.join_date DESC LIMIT 10"
);

$totalActive = $statusMap['Active'] ?? 0;
$totalAll = array_sum($statusMap);
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-users-cog mr-1"></i>Staff Report</h3>
        <div class="card-tools">
            <form action="operation.php?module=reports&page=staff_operation" method="post" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="export_staff">
                <button class="btn btn-success btn-sm"><i class="fas fa-download mr-1"></i>CSV</button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-3"><div class="callout callout-info"><h6>Total Staff</h6><h3><?= $totalAll ?></h3></div></div>
            <div class="col-md-3"><div class="callout callout-success"><h6>Active</h6><h3><?= $totalActive ?></h3></div></div>
            <div class="col-md-3"><div class="callout callout-warning"><h6>Blocked</h6><h3><?= $statusMap['Block'] ?? 0 ?></h3></div></div>
            <div class="col-md-3"><div class="callout callout-danger"><h6>Terminated</h6><h3><?= $statusMap['Terminated'] ?? 0 ?></h3></div></div>
        </div>
    </div>
</div>

<div class="row">
    <!-- By Department -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h5 class="card-title">By Department</h5></div>
            <div class="card-body p-0">
                <table class="table table-sm table-striped">
                    <thead><tr><th>Department</th><th class="text-center">Active</th><th class="text-center">Blocked</th><th class="text-center">Terminated</th><th class="text-center">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($byDept as $d): ?>
                        <tr>
                            <td><strong><?= e($d['dept'] ?: 'Unassigned') ?></strong></td>
                            <td class="text-center"><span class="badge badge-success"><?= (int) $d['active'] ?></span></td>
                            <td class="text-center"><?= (int) $d['blocked'] ?></td>
                            <td class="text-center"><?= (int) $d['terminated'] ?></td>
                            <td class="text-center"><?= (int) $d['total'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- By Designation -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h5 class="card-title">By Designation</h5></div>
            <div class="card-body p-0">
                <table class="table table-sm table-striped">
                    <thead><tr><th>Designation</th><th class="text-center">Active</th><th class="text-center">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($byDesig as $g): ?>
                        <tr>
                            <td><strong><?= e($g['designation'] ?: 'Unassigned') ?></strong></td>
                            <td class="text-center"><span class="badge badge-info"><?= (int) $g['active'] ?></span></td>
                            <td class="text-center"><?= (int) $g['total'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- By Gender -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h5 class="card-title">Gender Distribution</h5></div>
            <div class="card-body p-0">
                <table class="table table-sm">
                    <tbody>
                    <?php foreach ($byGender as $g): ?>
                        <tr><td><?= e($g['gender'] ?: 'Not specified') ?></td><td class="text-right"><strong><?= (int) $g['c'] ?></strong></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- By Type -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h5 class="card-title">Staff Type</h5></div>
            <div class="card-body p-0">
                <table class="table table-sm">
                    <tbody>
                    <?php foreach ($byType as $t): ?>
                        <tr><td><?= e($t['staff_type'] ?: 'Not specified') ?></td><td class="text-right"><strong><?= (int) $t['c'] ?></strong></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Joiners -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h5 class="card-title">Recent Joiners</h5></div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php foreach ($recentJoiners as $j): ?>
                        <li class="list-group-item small">
                            <strong><?= e($j['fullname']) ?></strong><br>
                            <span class="text-muted"><?= e($j['join_date']) ?> · <?= e($j['dept'] ?? '—') ?></span>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$recentJoiners): ?>
                        <li class="list-group-item text-muted text-center">No recent joiners.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
