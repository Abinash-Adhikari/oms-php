<?php
/**
 * SB-Tech — Leave Management / Applications (US-LV-03).
 * Workflow: Pending → Verified → Approved | Rejected. Verify and Approve are
 * separate actions recording who/when; rejection requires a reason.
 */
$db = Database::instance();
$statusFilter = (string) ($_GET['status'] ?? 'Pending');
$allowedStatus = ['Pending', 'Verified', 'Approved', 'Rejected', 'All'];
if (!in_array($statusFilter, $allowedStatus, true)) {
    $statusFilter = 'Pending';
}

$where = '';
$params = [];
if ($statusFilter !== 'All') {
    $where = 'WHERE l.status = ?';
    $params[] = $statusFilter;
}
$rows = $db->select(
    'SELECT l.*, u.fullname, u.department_id, d.title AS department_title,
            lc.title AS leave_title, f.fullname AS filler_name, v.fullname AS verified_name, ap.fullname AS approved_name
     FROM `tbl_staff_leave_applications` l
     JOIN `tbl_users_login` u ON u.id = l.staff_id
     JOIN `tbl_office_leave_configs` lc ON lc.id = l.leave_type_id
     LEFT JOIN `tbl_office_departments` d ON d.id = u.department_id
     LEFT JOIN `tbl_users_login` f ON f.id = l.absence_filler
     LEFT JOIN `tbl_users_login` v ON v.id = l.verified_by
     LEFT JOIN `tbl_users_login` ap ON ap.id = l.approved_by
     ' . $where . '
     ORDER BY FIELD(l.status, "Pending", "Verified", "Approved", "Rejected"), l.added_on DESC',
    $params
);
?>
<div class="row mb-2">
    <div class="col-md-8">
        <form method="get" class="form-inline">
            <input type="hidden" name="module" value="staff_management">
            <input type="hidden" name="page" value="leave_management">
            <input type="hidden" name="tab" value="leave_application">
            <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                <?php foreach ($allowedStatus as $s): ?>
                    <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= $s === 'All' ? 'All statuses' : $s ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-sm table-striped table-hover">
        <thead>
            <tr>
                <th>#</th><th>Staff</th><th>Type</th><th>Dates</th><th>Days</th><th>Substitute</th><th>Status</th><th class="text-right">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $i => $l): ?>
            <?php
            $stCls = ['Pending' => 'warning', 'Verified' => 'info', 'Approved' => 'success', 'Rejected' => 'danger'][$l['status']] ?? 'secondary';
            $canVerify = $l['status'] === 'Pending';
            $canApprove = in_array($l['status'], ['Pending', 'Verified'], true);
            $canReject = in_array($l['status'], ['Pending', 'Verified'], true);
            ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= e($l['fullname']) ?><br><small class="text-muted"><?= e($l['department_title'] ?? '—') ?></small></td>
                <td><?= e($l['leave_title']) ?><?= $l['half_day'] ? ' <small class="text-muted">(half)</small>' : '' ?></td>
                <td><?= e(formatDateView($l['from_date'])) ?> → <?= e(formatDateView($l['to_date'])) ?></td>
                <td><?= e((float) $l['leave_days']) ?></td>
                <td><?= e($l['filler_name'] ?? '—') ?></td>
                <td>
                    <span class="badge badge-<?= $stCls ?>"><?= e($l['status']) ?></span>
                    <?php if ($l['status'] === 'Rejected' && $l['reject_reason']): ?>
                        <br><small class="text-danger"><?= e($l['reject_reason']) ?></small>
                    <?php endif; ?>
                    <?php if ($l['verified_by']): ?><br><small class="text-muted">Verified by <?= e($l['verified_name'] ?? '') ?></small><?php endif; ?>
                    <?php if ($l['approved_by']): ?><br><small class="text-muted">Approved by <?= e($l['approved_name'] ?? '') ?></small><?php endif; ?>
                </td>
                <td class="text-right">
                    <?php if ($canVerify): ?>
                        <form action="operation.php?module=staff_management&page=leave_management" method="post" class="d-inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                            <input type="hidden" name="status" value="Verified">
                            <button type="submit" class="btn btn-xs btn-outline-info" title="Verify"><i class="fas fa-check-double"></i> Verify</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($canApprove): ?>
                        <form action="operation.php?module=staff_management&page=leave_management" method="post" class="d-inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                            <input type="hidden" name="status" value="Approved">
                            <button type="submit" class="btn btn-xs btn-outline-success" title="Approve"><i class="fas fa-check"></i> Approve</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($canReject): ?>
                        <button type="button" class="btn btn-xs btn-outline-danger" data-toggle="collapse" data-target="#reject-<?= (int) $l['id'] ?>" title="Reject"><i class="fas fa-times"></i> Reject</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if ($canReject): ?>
                <tr class="collapse" id="reject-<?= (int) $l['id'] ?>">
                    <td colspan="8" class="bg-light">
                        <form action="operation.php?module=staff_management&page=leave_management" method="post" class="row align-items-center">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                            <input type="hidden" name="status" value="Rejected">
                            <div class="col-md-8">
                                <input type="text" name="reason" class="form-control form-control-sm" placeholder="Rejection reason (required, shown to staff)" required>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-sm btn-danger">Confirm rejection</button>
                            </div>
                        </form>
                    </td>
                </tr>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
            <tr><td colspan="8" class="text-center text-muted">No <?= e(strtolower($statusFilter === 'All' ? '' : $statusFilter)) ?> applications found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
