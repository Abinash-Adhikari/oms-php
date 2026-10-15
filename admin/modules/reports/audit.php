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

// Summary — last 30 days vs the 30 before, independent of the table filters.
$days30 = day_keys(date('Y-m-d'), 30);
$sum = $db->selectOne(
    "SELECT SUM(added_on >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS events30,
            SUM(added_on < DATE_SUB(NOW(), INTERVAL 30 DAY) AND added_on >= DATE_SUB(NOW(), INTERVAL 60 DAY)) AS events_prev30,
            SUM(module = 'auth' AND action = 'login' AND added_on >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) AS logins24,
            COUNT(DISTINCT CASE WHEN added_on >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN actor_id END) AS users7,
            SUM(module <> 'auth' AND added_on >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS changes30
     FROM tbl_audit_log"
) ?: [];
$dailyLogins = [];
$dailyChanges = [];
foreach ($db->select(
    "SELECT DATE(added_on) AS d, module = 'auth' AS is_auth, COUNT(*) AS c
     FROM tbl_audit_log WHERE added_on >= ? GROUP BY d, is_auth",
    [$days30[0]]
) as $r) {
    if ((int) $r['is_auth'] === 1) {
        $dailyLogins[$r['d']] = (int) $r['c'];
    } else {
        $dailyChanges[$r['d']] = (int) $r['c'];
    }
}
$topModules = $db->select(
    "SELECT module, COUNT(*) AS c FROM tbl_audit_log
     WHERE added_on >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY module ORDER BY c DESC LIMIT 8"
);
?>

<div class="ana-grid">
    <?= stat_tile(['label' => 'Events (30 days)', 'icon' => 'fas fa-database', 'tone' => 'info',
        'value' => number_format((int) ($sum['events30'] ?? 0)),
        'delta' => percent_change((float) ($sum['events_prev30'] ?? 0), (float) ($sum['events30'] ?? 0)),
        'delta_label' => 'vs previous 30 days']) ?>
    <?= stat_tile(['label' => 'Logins (24 h)', 'icon' => 'fas fa-sign-in-alt', 'tone' => 'success',
        'value' => number_format((int) ($sum['logins24'] ?? 0)), 'sub' => 'trend: sign-ins per day, 30 days',
        'spark' => fill_series($days30, $dailyLogins)]) ?>
    <?= stat_tile(['label' => 'Active users (7 days)', 'icon' => 'fas fa-user-shield', 'tone' => 'primary',
        'value' => number_format((int) ($sum['users7'] ?? 0)), 'sub' => 'distinct people with any logged action']) ?>
    <?= stat_tile(['label' => 'Data changes (30 days)', 'icon' => 'fas fa-pen', 'tone' => 'warning',
        'value' => number_format((int) ($sum['changes30'] ?? 0)), 'sub' => 'everything except sign-in / sign-out',
        'spark' => fill_series($days30, $dailyChanges)]) ?>
</div>

<div class="row ana-row">
    <div class="col-lg-8">
        <div class="ana-card">
            <div class="ana-card__head"><div><h3 class="ana-card__title">Activity</h3><p class="ana-card__hint">Logged events per day, last 30 days</p></div></div>
            <div class="ana-chart" style="--h:220px">
                <canvas role="img" aria-label="Stacked bar chart of sign-ins and data changes per day"
                        data-chart="<?= chart_attr([
                            'type' => 'bar', 'stacked' => true,
                            'labels' => array_map(static fn ($d) => date('j M', strtotime($d)), $days30),
                            'series' => [
                                ['label' => 'Data changes', 'data' => fill_series($days30, $dailyChanges), 'slot' => 1],
                                ['label' => 'Sign-in / out', 'data' => fill_series($days30, $dailyLogins), 'slot' => 3],
                            ],
                            'empty' => 'No activity in the last 30 days.',
                        ]) ?>"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="ana-card">
            <div class="ana-card__head"><div><h3 class="ana-card__title">Busiest modules</h3><p class="ana-card__hint">Last 30 days</p></div></div>
            <div class="ana-chart" style="--h:220px">
                <canvas role="img" aria-label="Horizontal bar chart of logged events per module"
                        data-chart="<?= chart_attr([
                            'type' => 'hbar', 'labels' => array_column($topModules, 'module'),
                            'series' => [['label' => 'Events', 'data' => array_map('intval', array_column($topModules, 'c')), 'slot' => 1]],
                            'empty' => 'No activity in the last 30 days.',
                        ]) ?>"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-history mr-1"></i>Audit Log</h3>
        <div class="card-tools">
            <form action="operation.php?module=reports&page=audit" method="post" style="display:inline">
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
        <table class="table table-sm table-striped ana-table">
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
