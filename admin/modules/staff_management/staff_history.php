<?php
/**
 * SB-Tech — Staff Management / Staff History (US-STF-03).
 * Employment events timeline (join, changes, termination) with actor + date.
 */
$db = Database::instance();

$viewUser = null;
if (isset($_GET['id'])) {
    $viewUser = $db->selectOne('SELECT * FROM `tbl_users_login` WHERE `id` = ?', [(int) $_GET['id']]);
}
$staff = $db->select('SELECT id, fullname, username, status FROM `tbl_users_login` ORDER BY fullname');
?>
<div class="row">
    <div class="col-md-3">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Staff</h3></div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover">
                    <tbody>
                    <?php foreach ($staff as $s): ?>
                        <tr class="<?= $viewUser && (int) $viewUser['id'] === (int) $s['id'] ? 'table-primary' : '' ?>">
                            <td><a href="<?= pageUrl('staff_management', 'staff_history') ?>&id=<?= (int) $s['id'] ?>"><?= e($s['fullname']) ?></a>
                                <small class="d-block text-muted">@<?= e($s['username']) ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-9">
        <?php if (!$viewUser): ?>
            <div class="callout callout-info"><h5>Select a staff member</h5><p>Pick a staff member to see their employment history.</p></div>
        <?php else: ?>
            <div class="card">
                <div class="card-header"><h3 class="card-title">History — <?= e($viewUser['fullname']) ?></h3></div>
                <div class="card-body">
                    <?php
                    $events = $db->select(
                        'SELECT h.*, u.fullname AS actor_name
                         FROM `tbl_staff_history` h
                         LEFT JOIN `tbl_users_login` u ON u.id = h.actor_id
                         WHERE h.staff_id = ?
                         ORDER BY h.added_on DESC, h.id DESC',
                        [(int) $viewUser['id']]
                    );
                    $badges = [
                        'Joined' => 'success', 'Reinstated' => 'success', 'Terminated' => 'danger',
                        'Designation Changed' => 'info', 'Department Changed' => 'info',
                        'Blocked' => 'warning', 'Status Changed' => 'warning', 'Updated' => 'secondary',
                    ];
                    ?>
                    <ul class="timeline">
                        <?php if (!$events): ?>
                            <li class="text-muted">No history events recorded yet.</li>
                        <?php endif; ?>
                        <?php foreach ($events as $ev): ?>
                            <li>
                                <i class="fas fa-circle bg-<?= $badges[$ev['event_type']] ?? 'secondary' ?>"></i>
                                <div class="timeline-item">
                                    <span class="time"><i class="fas fa-clock mr-1"></i><?= e($ev['added_on']) ?></span>
                                    <h3 class="timeline-header">
                                        <span class="badge badge-<?= $badges[$ev['event_type']] ?? 'secondary' ?>"><?= e($ev['event_type']) ?></span>
                                        <?= $ev['actor_name'] ? 'by ' . e($ev['actor_name']) : '' ?>
                                    </h3>
                                    <?php if ($ev['details']): ?><div class="timeline-body"><?= e($ev['details']) ?></div><?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
