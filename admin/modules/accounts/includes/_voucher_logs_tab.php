<?php
/**
 * Accounts / Posting — Voucher Logs tab (AC-FIN-09.1).
 * Every create/edit/approve/unapprove/delete is recorded with actor + time.
 */
$db = Database::instance();
$logType = (string) ($_GET['log_type'] ?? '');
$logs = $db->select(
    'SELECT l.*, u.fullname AS actor FROM `tbl_voucher_logs` l
     LEFT JOIN `tbl_users_login` u ON u.id = l.added_by
     WHERE (? = \'\' OR l.voucher_type = ?)
     ORDER BY l.id DESC LIMIT 300',
    [$logType, $logType]
);
$typeCounts = $db->select(
    'SELECT voucher_type, COUNT(*) AS c FROM `tbl_voucher_logs` GROUP BY voucher_type ORDER BY voucher_type'
);
?>
<div class="row">
    <div class="col-md-3">
        <div class="card card-outline">
            <div class="card-header"><h3 class="card-title">Filter by type</h3></div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <a href="<?= pageUrl('accounts', 'postings') ?>&tab=voucher_logs">All types</a>
                        <span class="badge badge-light border"><?= array_sum(array_column($typeCounts, 'c')) ?></span>
                    </li>
                    <?php foreach ($typeCounts as $tc): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <a href="<?= pageUrl('accounts', 'postings') ?>&tab=voucher_logs&log_type=<?= urlencode($tc['voucher_type']) ?>"><?= e($tc['voucher_type']) ?></a>
                            <span class="badge badge-light border"><?= (int) $tc['c'] ?></span>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$typeCounts): ?><li class="list-group-item text-muted">No log entries yet.</li><?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-md-9">
        <div class="card card-outline">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-history mr-1"></i>Voucher audit log</h3></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead><tr><th>When</th><th>Actor</th><th>Voucher</th><th>Action</th><th>Type</th></tr></thead>
                        <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= e($log['added_on']) ?></td>
                                <td><?= e($log['actor'] ?? '#' . $log['added_by']) ?></td>
                                <td><?= e($log['voucher_no'] ?? '—') ?></td>
                                <td><span class="badge badge-<?= $log['action'] === 'Delete' || $log['action'] === 'Void' ? 'danger' : ($log['action'] === 'Approve' ? 'success' : 'light border') ?>"><?= e($log['action']) ?></span></td>
                                <td><?= e($log['voucher_type'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$logs): ?><tr><td colspan="5" class="text-center text-muted">No log entries yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
