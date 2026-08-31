<?php
/**
 * SB-Tech — Communication / Logs (US-COM-01).
 * View all email/SMS delivery logs with filtering and CSV export.
 */
$db = Database::instance();

$filterType = $_GET['type'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$search = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['p'] ?? 1));

$where = ['1=1'];
$params = [];

if ($filterType && in_array($filterType, ['Email', 'SMS'], true)) {
    $where[] = '`type` = ?';
    $params[] = $filterType;
}
if ($filterStatus && in_array($filterStatus, ['Queued', 'Sent', 'Failed'], true)) {
    $where[] = '`status` = ?';
    $params[] = $filterStatus;
}
if ($search !== '') {
    $where[] = '`recipient` LIKE ?';
    $params[] = '%' . $db->escapeLike($search) . '%';
}

$whereSql = implode(' AND ', $where);
$total = (int) ($db->selectOne('SELECT COUNT(*) AS c FROM `tbl_communication_logs` WHERE ' . $whereSql, $params)['c'] ?? 0);
$pp = paginationParams($total, $page);

$logs = $db->select(
    'SELECT l.*, u.fullname AS actor_name FROM `tbl_communication_logs` l
     LEFT JOIN `tbl_users_login` u ON u.id = l.added_by
     WHERE ' . $whereSql . '
     ORDER BY l.id DESC LIMIT ' . $pp['per_page'] . ' OFFSET ' . $pp['offset'],
    $params
);
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-list mr-1"></i>Delivery Logs (<?= number_format($total) ?>)</h3>
        <div class="card-tools">
            <form action="operation.php?module=communication&page=logs_operation" method="post" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="export_csv">
                <input type="hidden" name="type" value="<?= e($filterType) ?>">
                <input type="hidden" name="status" value="<?= e($filterStatus) ?>">
                <input type="hidden" name="q" value="<?= e($search) ?>">
                <button class="btn btn-success btn-sm"><i class="fas fa-download mr-1"></i>CSV</button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <form class="form-inline mb-3" method="get">
            <input type="hidden" name="module" value="communication">
            <input type="hidden" name="page" value="logs">
            <select name="type" class="form-control form-control-sm mr-2">
                <option value="">All Types</option>
                <option value="Email" <?= $filterType === 'Email' ? 'selected' : '' ?>>Email</option>
                <option value="SMS" <?= $filterType === 'SMS' ? 'selected' : '' ?>>SMS</option>
            </select>
            <select name="status" class="form-control form-control-sm mr-2">
                <option value="">All Statuses</option>
                <option value="Sent" <?= $filterStatus === 'Sent' ? 'selected' : '' ?>>Sent</option>
                <option value="Failed" <?= $filterStatus === 'Failed' ? 'selected' : '' ?>>Failed</option>
                <option value="Queued" <?= $filterStatus === 'Queued' ? 'selected' : '' ?>>Queued</option>
            </select>
            <input type="text" name="q" class="form-control form-control-sm mr-2" placeholder="Search recipient..." value="<?= e($search) ?>">
            <button class="btn btn-sm btn-primary"><i class="fas fa-search"></i></button>
        </form>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm table-striped">
            <thead><tr><th>#</th><th>Type</th><th>Recipient</th><th>Subject/Event</th><th>Campaign</th><th>Status</th><th>Error</th><th>Sent On</th><th>Actor</th></tr></thead>
            <tbody>
            <?php if (!$logs): ?>
                <tr><td colspan="9" class="text-muted text-center">No logs found.</td></tr>
            <?php else: foreach ($logs as $l): ?>
                <tr>
                    <td><?= (int) $l['id'] ?></td>
                    <td><span class="badge badge-<?= $l['type'] === 'Email' ? 'primary' : 'success' ?>"><?= e($l['type']) ?></span></td>
                    <td><?= e($l['recipient']) ?></td>
                    <td><?= e($l['subject']) ?></td>
                    <td><?= $l['campaign_id'] ? '#' . (int) $l['campaign_id'] : '—' ?></td>
                    <td><span class="badge badge-<?= $l['status'] === 'Sent' ? 'success' : ($l['status'] === 'Failed' ? 'danger' : 'warning') ?>"><?= e($l['status']) ?></span></td>
                    <td class="text-danger small" style="max-width:200px;overflow:hidden;text-overflow:ellipsis"><?= e($l['error_message']) ?></td>
                    <td class="small"><?= e($l['sent_on'] ?? '—') ?></td>
                    <td class="small"><?= e($l['actor_name']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pp['pages'] > 1): ?>
    <div class="card-footer">
        <nav><ul class="pagination pagination-sm mb-0">
            <?php for ($i = max(1, $pp['page'] - 3); $i <= min($pp['pages'], $pp['page'] + 3); $i++): ?>
                <li class="page-item<?= $i === $pp['page'] ? ' active' : '' ?>">
                    <a class="page-link" href="?module=communication&page=logs&type=<?= urlencode($filterType) ?>&status=<?= urlencode($filterStatus) ?>&q=<?= urlencode($search) ?>&p=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>
