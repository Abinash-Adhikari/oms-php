<?php
/**
 * SB-Tech — Staff Management / Terminated Staffs (AC-STF-03.3).
 * Terminated staff remain visible here and in historical records.
 */
$db = Database::instance();
$rows = $db->select(
    'SELECT u.*, d.title AS department_title, g.title AS designation_title
     FROM `tbl_users_login` u
     LEFT JOIN `tbl_office_departments` d ON d.id = u.department_id
     LEFT JOIN `tbl_office_designation` g ON g.id = u.designation_id
     WHERE u.status = \'Terminated\'
     ORDER BY u.termination_date DESC'
);
?>
<div class="card">
    <div class="card-header"><h3 class="card-title">Terminated Staff</h3></div>
    <div class="card-body p-0">
        <div class="table-responsive-scroll">
            <table class="table table-sm table-striped table-hover">
                <thead><tr><th>#</th><th>Name</th><th>Department</th><th>Designation</th><th>Join date</th><th>Termination date</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $i => $r): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= e($r['fullname']) ?></td>
                        <td><?= e($r['department_title'] ?? '—') ?></td>
                        <td><?= e($r['designation_title'] ?? '—') ?></td>
                        <td><?= e(formatDateView($r['join_date'])) ?></td>
                        <td><?= e(formatDateView($r['termination_date'])) ?></td>
                        <td class="text-right">
                            <a href="<?= pageUrl('staff_management', 'add_staff') ?>&id=<?= (int) $r['id'] ?>" class="btn btn-xs btn-outline-primary"><i class="fas fa-edit"></i></a>
                            <a href="<?= pageUrl('staff_management', 'staff_history') ?>&id=<?= (int) $r['id'] ?>" class="btn btn-xs btn-outline-info"><i class="fas fa-history"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted">No terminated staff.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
