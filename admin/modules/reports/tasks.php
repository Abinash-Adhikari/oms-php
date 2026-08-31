<?php
/**
 * SB-Tech — Reports / Tasks.
 * Task completion rates, overdue analysis, workload distribution.
 */
$db = Database::instance();

// Overall stats
$stats = [
    'total'    => (int) ($db->selectOne("SELECT COUNT(*) AS c FROM tbl_office_tasks")['c'] ?? 0),
    'pending'  => (int) ($db->selectOne("SELECT COUNT(*) AS c FROM tbl_office_tasks WHERE status = 'Pending'")['c'] ?? 0),
    'in_progress' => (int) ($db->selectOne("SELECT COUNT(*) AS c FROM tbl_office_tasks WHERE status = 'In Progress'")['c'] ?? 0),
    'done'     => (int) ($db->selectOne("SELECT COUNT(*) AS c FROM tbl_office_tasks WHERE status = 'Done'")['c'] ?? 0),
    'rejected' => (int) ($db->selectOne("SELECT COUNT(*) AS c FROM tbl_office_tasks WHERE status = 'Rejected'")['c'] ?? 0),
    'cancelled'=> (int) ($db->selectOne("SELECT COUNT(*) AS c FROM tbl_office_tasks WHERE status = 'Cancelled'")['c'] ?? 0),
    'overdue'  => (int) ($db->selectOne("SELECT COUNT(*) AS c FROM tbl_office_tasks WHERE status IN ('Pending','In Progress') AND deadline IS NOT NULL AND deadline < NOW()")['c'] ?? 0),
];

// Tasks completed per author
$byAuthor = $db->select(
    "SELECT u.fullname,
            COUNT(t.id) AS total_tasks,
            SUM(t.status = 'Done') AS completed,
            SUM(t.status IN ('Pending','In Progress')) AS active,
            SUM(t.status IN ('Pending','In Progress') AND t.deadline < NOW()) AS overdue
     FROM tbl_office_tasks t
     JOIN tbl_users_login u ON u.id = t.author
     GROUP BY t.author, u.fullname
     ORDER BY completed DESC"
);

// Tasks assigned per person
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
     ORDER BY assigned_count DESC"
);
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-tasks mr-1"></i>Task Report</h3>
        <div class="card-tools">
            <form action="operation.php?module=reports&page=tasks_operation" method="post" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="export_tasks">
                <button class="btn btn-success btn-sm"><i class="fas fa-download mr-1"></i>CSV</button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <!-- Status summary -->
        <div class="row mb-4">
            <div class="col-md-3"><div class="callout callout-secondary"><h6>Total Tasks</h6><h3><?= $stats['total'] ?></h3></div></div>
            <div class="col-md-3"><div class="callout callout-success"><h6>Completed</h6><h3><?= $stats['done'] ?></h3></div></div>
            <div class="col-md-3"><div class="callout callout-warning"><h6>In Progress</h6><h3><?= $stats['in_progress'] ?></h3></div></div>
            <div class="col-md-3"><div class="callout callout-danger"><h6>Overdue</h6><h3><?= $stats['overdue'] ?></h3></div></div>
        </div>

        <!-- Completion rate -->
        <?php $rate = $stats['total'] > 0 ? round(($stats['done'] / $stats['total']) * 100) : 0; ?>
        <div class="mb-4">
            <h6>Overall Completion Rate</h6>
            <div class="progress" style="height: 30px;">
                <div class="progress-bar bg-success" style="width: <?= $rate ?>%"><?= $rate ?>% (<?= $stats['done'] ?>/<?= $stats['total'] ?>)</div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h5 class="card-title">Tasks Created by Author</h5></div>
            <div class="card-body p-0">
                <table class="table table-sm table-striped">
                    <thead><tr><th>Author</th><th class="text-center">Total</th><th class="text-center">Done</th><th class="text-center">Active</th><th class="text-center">Overdue</th></tr></thead>
                    <tbody>
                    <?php foreach ($byAuthor as $a): ?>
                        <tr>
                            <td><strong><?= e($a['fullname']) ?></strong></td>
                            <td class="text-center"><?= (int) $a['total_tasks'] ?></td>
                            <td class="text-center"><span class="badge badge-success"><?= (int) $a['completed'] ?></span></td>
                            <td class="text-center"><span class="badge badge-warning"><?= (int) $a['active'] ?></span></td>
                            <td class="text-center"><span class="badge badge-danger"><?= (int) $a['overdue'] ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$byAuthor): ?>
                        <tr><td colspan="5" class="text-muted text-center">No data.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h5 class="card-title">Tasks Assigned To</h5></div>
            <div class="card-body p-0">
                <table class="table table-sm table-striped">
                    <thead><tr><th>Assignee</th><th class="text-center">Assigned</th><th class="text-center">Done</th><th class="text-center">Pending</th><th class="text-center">Overdue</th></tr></thead>
                    <tbody>
                    <?php foreach ($byAssignee as $a): ?>
                        <tr>
                            <td><strong><?= e($a['fullname']) ?></strong></td>
                            <td class="text-center"><?= (int) $a['assigned_count'] ?></td>
                            <td class="text-center"><span class="badge badge-success"><?= (int) $a['completed'] ?></span></td>
                            <td class="text-center"><span class="badge badge-warning"><?= (int) $a['pending'] ?></span></td>
                            <td class="text-center"><span class="badge badge-danger"><?= (int) $a['overdue'] ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$byAssignee): ?>
                        <tr><td colspan="5" class="text-muted text-center">No data.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
