<?php
/**
 * SB-Tech — Reports / Audit Log.
 * System activity trail — logins, permission changes, staff changes, approvals.
 */
$db = Database::instance();

$filterModule = $_GET['module_filter'] ?? '';
$filterAction = $_GET['action_filter'] ?? '';
$search = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['p'] ?? 1));

$where = ['1=1'];
$params = [];
if ($filterModule) {
    $where[] = 'a.module = ?';
    $params[] = $filterModule;
}
if ($filterAction) {
    $where[] = 'a.action = ?';
    $params[] = $filterAction;
}
if ($search !== '') {
    $where[] = '(a.description LIKE ? OR a.entity_type LIKE ?)';
    $p = '%' . $db->escapeLike($search) . '%';
    $params[] = $p;
    $params[] = $p;
}
$whereSql = implode(' AND ', $where);

$total = (int) ($db->selectOne("SELECT COUNT(*) AS c FROM tbl_audit_log a WHERE {$whereSql}", $params)['c'] ?? 0);
$pp = paginationParams($total, $page);

$logs = $db->select(
    "SELECT a.*, u.fullname AS actor_name
     FROM tbl_audit_log a
     LEFT JOIN tbl_users_login u ON u.id = a.actor_id
     WHERE {$whereSql}
     ORDER BY a.added_on DESC
     LIMIT {$pp['per_page']} OFFSET {$pp['offset']}",
    $params
);

$modules = $db->select("SELECT DISTINCT module FROM tbl_audit_log ORDER BY module");
$actions = $db->select("SELECT DISTINCT action FROM tbl_audit_log ORDER BY action");

// Summary
$moduleCounts = $db->select("SELECT module, COUNT(*) AS c FROM tbl_audit_log GROUP BY module ORDER BY c DESC");
$actionCounts = $db->select("SELECT action, COUNT(*) AS c FROM tbl_audit_log GROUP BY action ORDER BY c DESC");
$recentLogins = (int) ($db->selectOne("SELECT COUNT(*) AS c FROM tbl_audit_log WHERE module = 'auth' AND action = 'login' AND added_on >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")['c'] ?? 0);
?>

<div class="row mb-3">
    <div class="col-md-3">
        <div class="info-box"><span class="info-box-icon bg-info"><i class="fas fa-database"></i></span>
            <div class="info-box-content"><span class="info-box-text">Total Logs</span><span class="info-box-number"><?= number_format($total) ?></span></div></div>
    </div>
    <div class="col-md-3">
        <div class="info-box"><span class="info-box-icon bg-success"><i class="fas fa-sign-in-alt"></i></span>
            <div class="info-box-content"><span class="info-box-text">Logins (24h)</span><span class="info-box-number"><?= $recentLogins ?></span></div></div>
    </div>
    <div class="col-md-3">
        <div class="info-box"><span class="info-box-icon bg-warning"><i class="fas fa-puzzle-piece"></i></span>
            <div class="info-box-content"><span class="info-box-text">Modules Active</span><span class="info-box-number"><?= count($moduleCounts) ?></span></div></div>
    </div>
    <div class="col-md-3">
        <div class="info-box"><span class="info-box-icon bg-primary"><i class="fas fa-bolt"></i></span>
            <div class="info-box-content"><span class="info-box-text">Action Types</span><span class="info-box-number"><?= count($actionCounts) ?></span></div></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-history mr-1"></i>Audit Log</h3>
        <div class="card-tools">
            <form action="operation.php?module=reports&page=audit_operation" method="post" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="export_audit">
                <input type="hidden" name="module_filter" value="<?= e($filterModule) ?>">
                <input type="hidden" name="action_filter" value="<?= e($filterAction) ?>">
                <input type="hidden" name="q" value="<?= e($search) ?>">
                <button class="btn btn-success btn-sm"><i class="fas fa-download mr-1"></i>CSV</button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <form class="form-inline" method="get">
            <input type="hidden" name="module" value="reports">
            <input type="hidden" name="page" value="audit">
            <select name="module_filter" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                <option value="">All Modules</option>
                <?php foreach ($modules as $m): ?>
                    <option value="<?= e($m['module']) ?>" <?= $filterModule === $m['module'] ? 'selected' : '' ?>><?= e($m['module']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="action_filter" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                <option value="">All Actions</option>
                <?php foreach ($actions as $a): ?>
                    <option value="<?= e($a['action']) ?>" <?= $filterAction === $a['action'] ? 'selected' : '' ?>><?= e($a['action']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="q" class="form-control form-control-sm mr-2" placeholder="Search description..." value="<?= e($search) ?>">
            <button class="btn btn-sm btn-primary"><i class="fas fa-search"></i></button>
        </form>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm table-striped">
            <thead><tr><th>Date</th><th>Module</th><th>Action</th><th>Entity</th><th>Actor</th><th>IP</th><th>Description</th></tr></thead>
            <tbody>
            <?php if (!$logs): ?>
                <tr><td colspan="7" class="text-muted text-center">No audit logs found.</td></tr>
            <?php else: foreach ($logs as $l): ?>
                <tr>
                    <td class="small"><?= e($l['added_on']) ?></td>
                    <td><span class="badge badge-info"><?= e($l['module']) ?></span></td>
                    <td><span class="badge badge-secondary"><?= e($l['action']) ?></span></td>
                    <td class="small"><?= e($l['entity_type']) ?> <?= $l['entity_id'] ? '#' . $l['entity_id'] : '' ?></td>
                    <td class="small"><?= e($l['actor_name'] ?? 'System') ?></td>
                    <td class="small"><?= e($l['actor_ip']) ?></td>
                    <td class="small" style="max-width:300px;overflow:hidden;text-overflow:ellipsis"><?= e($l['description']) ?></td>
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
                    <a class="page-link" href="?module=reports&page=audit&module_filter=<?= urlencode($filterModule) ?>&action_filter=<?= urlencode($filterAction) ?>&q=<?= urlencode($search) ?>&p=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>
