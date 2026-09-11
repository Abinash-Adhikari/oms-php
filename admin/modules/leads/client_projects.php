<?php
/**
 * SB-Tech — Client Projects (won deployments registry).
 * Records created automatically when a pipeline deal reaches Won. Each row
 * provisions a business source + a catalog project: title, package, deployment
 * database (db_name), value, period and status — plus the module/module
 * grants edited from the Client Access screen.
 */
$db = Database::instance();
$canManage = Auth::isSuperAdmin() || Auth::hasSpecial('manage_leads');

$statuses = ['Active', 'Completed', 'On Hold', 'Cancelled'];
$statusBadge = ['Active' => 'success', 'Completed' => 'info', 'On Hold' => 'warning', 'Cancelled' => 'secondary'];

// ── filters ──
$keyword = trim((string) ($_GET['keyword'] ?? ''));
$statusFilter = (string) ($_GET['status'] ?? '');
$sourceFilter = (int) ($_GET['source'] ?? 0);
$catalogFilter = (int) ($_GET['catalog'] ?? 0);

$where = ['1=1'];
$params = [];
if (in_array($statusFilter, $statuses, true)) {
    $where[] = 'cp.status = ?';
    $params[] = $statusFilter;
}
if ($sourceFilter === -1) {
    $where[] = 'cp.client_id IS NULL';
} elseif ($sourceFilter) {
    $where[] = 'cp.client_id = ?';
    $params[] = $sourceFilter;
}
if ($catalogFilter === -1) {
    $where[] = 'cp.project_id IS NULL';
} elseif ($catalogFilter) {
    $where[] = 'cp.project_id = ?';
    $params[] = $catalogFilter;
}
if ($keyword !== '') {
    $where[] = '(cp.title LIKE ? OR cp.package LIKE ? OR cp.db_name LIKE ? OR bs.name LIKE ? OR bs.contact_person LIKE ? OR p.name LIKE ?)';
    $kw = '%' . $db->escapeLike($keyword) . '%';
    array_push($params, $kw, $kw, $kw, $kw, $kw, $kw);
}

$rows = $db->select(
    'SELECT cp.*, bs.name AS source_name, bs.contact_person AS source_contact, p.name AS catalog_name,
            o.fullname AS owner_name
     FROM `tbl_client_projects` cp
     LEFT JOIN `tbl_clients` bs ON bs.id = cp.client_id
     LEFT JOIN `tbl_projects` p ON p.id = cp.project_id
     LEFT JOIN `tbl_leads` l ON l.id = cp.lead_id
     LEFT JOIN `tbl_users_login` o ON o.id = l.assigned_to
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY cp.updated_on DESC, cp.id DESC',
    $params
);
$sources = $db->select('SELECT id, name FROM `tbl_clients` ORDER BY name ASC');
$catalog = $db->select('SELECT id, name FROM `tbl_projects` WHERE `status` = \'Active\' ORDER BY name ASC');

// Snapshot for client-side drawer prefill.
$rowsJson = [];
foreach ($rows as $p) {
    $rowsJson[] = [
        'id'                 => (int) $p['id'],
        'client_id' => (int) $p['client_id'],
        'project_id'         => (int) $p['project_id'],
        'title'              => $p['title'],
        'package'            => $p['package'],
        'db_name'            => $p['db_name'],
        'value'              => $p['value'],
        'start_date'         => $p['start_date'],
        'end_date'           => $p['end_date'],
        'status'             => $p['status'],
        'description'        => $p['description'],
    ];
}
?>

<!-- Toolbar -->
<div class="card card-outline mb-3">
    <div class="card-body py-2">
        <div class="d-flex align-items-center flex-wrap" style="gap:.5rem">
            <form method="get" id="cpFilterForm" class="d-flex align-items-center flex-wrap" style="gap:.5rem;flex:1 1 auto;min-width:0">
                <input type="hidden" name="module" value="leads">
                <input type="hidden" name="page" value="client_projects">
                <div class="input-group input-group-sm" style="width:220px">
                    <div class="input-group-prepend"><span class="input-group-text bg-transparent border-right-0"><i class="fas fa-search text-muted"></i></span></div>
                    <input type="text" name="keyword" class="form-control border-left-0" placeholder="Search client projects..." value="<?= e($keyword) ?>">
                </div>
                <select name="status" class="form-control form-control-sm" style="width:130px">
                    <option value="">All Status</option>
                    <?php foreach ($statuses as $s): ?>
                        <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="source" class="form-control form-control-sm" style="width:200px">
                    <option value="0">All Sources</option>
                    <option value="-1" <?= $sourceFilter === -1 ? 'selected' : '' ?>>Unassigned</option>
                    <?php foreach ($sources as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= $sourceFilter === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="catalog" class="form-control form-control-sm" style="width:200px">
                    <option value="0">All Catalog Projects</option>
                    <option value="-1" <?= $catalogFilter === -1 ? 'selected' : '' ?>>Unassigned</option>
                    <?php foreach ($catalog as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= $catalogFilter === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" form="cpFilterForm" class="btn btn-sm btn-primary"><i class="fas fa-search mr-1"></i>Filter</button>
            </form>

            <?php if ($canManage): ?>
                <button type="button" class="btn btn-sm btn-primary" onclick="openClientProjectDrawer()"><i class="fas fa-plus mr-1"></i>Add Client Project</button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Client projects table -->
<div class="card card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-project-diagram mr-1"></i>Client Projects</h3>
        <div class="card-tools text-muted small"><?= count($rows) ?> record<?= count($rows) === 1 ? '' : 's' ?></div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Project</th>
                        <th>Business Source</th>
                        <th>DB Name</th>
                        <th class="text-right">Value</th>
                        <th>Start / End</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $i => $p): ?>
                    <tr>
                        <td class="text-muted"><?= $i + 1 ?></td>
                        <td>
                            <span class="font-weight-bold"><?= e($p['title']) ?></span>
                            <?php if ($p['catalog_name']): ?><br><small class="text-muted"><i class="fas fa-box mr-1"></i><?= e($p['catalog_name']) ?></small><?php endif; ?>
                            <?php if ($p['package']): ?><br><small class="text-muted"><?= e($p['package']) ?></small><?php endif; ?>
                        </td>
                        <td>
                            <?php if ($p['client_id']): ?>
                                <a href="<?= pageUrl('clients', 'detail') ?>&id=<?= (int) $p['client_id'] ?>"><?= e($p['source_name'] ?: ('#' . (int) $p['client_id'])) ?></a>
                                <?php if ($p['source_contact']): ?><br><small class="text-muted"><?= e($p['source_contact']) ?></small><?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $p['db_name'] ? '<code>' . e($p['db_name']) . '</code>' : '—' ?></td>
                        <td class="text-right font-weight-bold"><?= $p['value'] !== null ? 'NPR ' . e(formatMoney($p['value'])) : '—' ?></td>
                        <td>
                            <small><?= $p['start_date'] ? e(formatDateView($p['start_date'])) : '—' ?> → <?= $p['end_date'] ? e(formatDateView($p['end_date'])) : '—' ?></small>
                        </td>
                        <td><span class="badge badge-<?= $statusBadge[$p['status']] ?? 'secondary' ?>"><?= e($p['status']) ?></span>
                            <?php if ($p['owner_name']): ?><br><small class="text-muted"><?= e($p['owner_name']) ?></small><?php endif; ?>
                        </td>
                        <td class="text-right">
                            <a href="<?= pageUrl('leads', 'client_permissions') ?>&id=<?= (int) $p['id'] ?>" class="btn btn-xs btn-outline-secondary" title="Client Access / Modules & DB"><i class="fas fa-user-lock"></i></a>
                            <?php if ($canManage): ?>
                                <button type="button" class="btn btn-xs btn-outline-secondary" title="Edit" onclick="openClientProjectDrawer(<?= (int) $p['id'] ?>)"><i class="fas fa-edit"></i></button>
                                <form action="operation.php?module=leads&page=client_projects" method="post" class="d-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete_client_project">
                                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                    <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete client project '<?= e($p['title']) ?>'? The win and its access grants are removed."><i class="fas fa-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>No client projects yet — they are created automatically when a lead is Won.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Drawer backdrop + client project drawer -->
<div class="cms-drawer-backdrop" id="drawerBackdrop" onclick="closeClientProjectDrawer()"></div>
<div class="cms-drawer" id="clientProjectDrawer">
    <div class="cms-drawer-header">
        <h3><i class="fas fa-project-diagram"></i><span id="cpDrawerTitle">Add Client Project</span></h3>
        <button type="button" class="cms-drawer-close" onclick="closeClientProjectDrawer()" aria-label="Close"><i class="fas fa-times"></i></button>
    </div>
    <div class="cms-drawer-body">
        <form action="operation.php?module=leads&page=client_projects" method="post" id="cpForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_client_project">
            <input type="hidden" name="id" id="cpId" value="0">
            <div class="form-group">
                <label>Business source</label>
                <select name="client_id" id="cpSource" class="form-control">
                    <option value="">— None —</option>
                    <?php foreach ($sources as $s): ?>
                        <option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Catalog project</label>
                <select name="project_id" id="cpCatalog" class="form-control">
                    <option value="">— None —</option>
                    <?php foreach ($catalog as $c): ?>
                        <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" id="cpTitle" class="form-control" required placeholder="e.g. Smart School Pro — Acme Ltd">
            </div>
            <div class="row">
                <div class="col-6 form-group">
                    <label>Package</label>
                    <input type="text" name="package" id="cpPackage" class="form-control" placeholder="e.g. Smart School Pro">
                </div>
                <div class="col-6 form-group">
                    <label>Database</label>
                    <input type="text" name="db_name" id="cpDb" class="form-control" placeholder="customer DB (Client Access)">
                </div>
            </div>
            <div class="row">
                <div class="col-6 form-group">
                    <label>Value (NPR)</label>
                    <input type="number" name="value" id="cpValue" class="form-control" step="0.01" min="0" placeholder="0.00">
                </div>
                <div class="col-6 form-group">
                    <label>Status</label>
                    <select name="status" id="cpStatus" class="form-control">
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s ?>"><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-6 form-group">
                    <label>Start</label>
                    <input type="date" name="start_date" id="cpStart" class="form-control">
                </div>
                <div class="col-6 form-group">
                    <label>End</label>
                    <input type="date" name="end_date" id="cpEnd" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="cpDescription" class="form-control" rows="2"></textarea>
            </div>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="cpForm" class="btn btn-primary btn-block"><i class="fas fa-save mr-1"></i><span id="cpDrawerSubmit">Add Client Project</span></button>
    </div>
</div>

<script>
var cpData = <?= json_encode($rowsJson) ?>;

function fillCpForm(p) {
    document.getElementById('cpId').value = p ? p.id : 0;
    document.getElementById('cpSource').value = p ? (p.client_id || '') : '';
    document.getElementById('cpCatalog').value = p ? (p.project_id || '') : '';
    document.getElementById('cpTitle').value = p ? (p.title || '') : '';
    document.getElementById('cpPackage').value = p ? (p.package || '') : '';
    document.getElementById('cpDb').value = p ? (p.db_name || '') : '';
    document.getElementById('cpValue').value = p ? (p.value || '') : '';
    document.getElementById('cpStatus').value = p ? (p.status || 'Active') : 'Active';
    document.getElementById('cpStart').value = p ? (p.start_date || '') : '';
    document.getElementById('cpEnd').value = p ? (p.end_date || '') : '';
    document.getElementById('cpDescription').value = p ? (p.description || '') : '';
}

function openClientProjectDrawer(id) {
    closeClientProjectDrawer();
    var drawer = document.getElementById('clientProjectDrawer');
    document.getElementById('drawerBackdrop').classList.add('active');
    drawer.classList.add('open');
    document.body.style.overflow = 'hidden';

    document.getElementById('cpId').value = id ? id : 0;
    var p = null;
    if (id) {
        for (var i = 0; i < cpData.length; i++) {
            if (cpData[i].id == id) { p = cpData[i]; break; }
        }
    }
    fillCpForm(p);
    document.getElementById('cpDrawerTitle').textContent = p ? 'Edit Client Project' : 'Add Client Project';
    document.getElementById('cpDrawerSubmit').textContent = p ? 'Update Client Project' : 'Add Client Project';
}

function closeClientProjectDrawer() {
    document.getElementById('clientProjectDrawer').classList.remove('open');
    document.getElementById('drawerBackdrop').classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeClientProjectDrawer();
});
</script>