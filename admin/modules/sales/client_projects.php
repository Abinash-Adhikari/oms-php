<?php
/**
 * SB-Tech — Client Projects registry.
 * Won deployments (is_won = 1, created when a lead reaches Won) and
 * demo/trials of leads not won yet (is_won = 0, Start Demo on the lead).
 * Each row links a client + a catalog project: title, package, project
 * url, value, period and status.
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
$wonFilter = (string) ($_GET['won'] ?? '');

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
if ($wonFilter === '1' || $wonFilter === '0') {
    $where[] = 'cp.is_won = ?';
    $params[] = (int) $wonFilter;
}
if ($catalogFilter === -1) {
    $where[] = 'cp.project_id IS NULL';
} elseif ($catalogFilter) {
    $where[] = 'cp.project_id = ?';
    $params[] = $catalogFilter;
}
if ($keyword !== '') {
    $where[] = '(cp.title LIKE ? OR cp.package LIKE ? OR cp.url LIKE ? OR bs.name LIKE ? OR bs.contact_person LIKE ? OR p.name LIKE ?)';
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
     ORDER BY COALESCE(cp.updated_on, cp.added_on) DESC, cp.id DESC',
    $params
);
$sources = $db->select('SELECT id, name FROM `tbl_clients` ORDER BY name ASC');
// Active products, plus inactive ones still linked to a project so editing
// that project never silently clears its product.
$catalog = $db->select(
    "SELECT id, name, status FROM `tbl_projects`
     WHERE `status` = 'Active' OR id IN (SELECT project_id FROM `tbl_client_projects` WHERE project_id IS NOT NULL)
     ORDER BY name ASC"
);
// Current filters, posted back so a save returns to the same filtered list.
$returnQuery = http_build_query(array_filter([
    'keyword' => $keyword,
    'status'  => $statusFilter,
    'won'     => $wonFilter,
    'source'  => $sourceFilter ?: '',
    'catalog' => $catalogFilter ?: '',
], static fn ($v) => $v !== ''));
// A failed save comes back with the submitted values: reopen the drawer with them.
$oldCp = null;
if (old('action') === 'save_client_project') {
    $oldCp = [
        'id'          => (int) old('id', 0),
        'client_id'   => (string) old('client_id'),
        'project_id'  => (string) old('project_id'),
        'is_won'      => (int) old('is_won', 1),
        'title'       => (string) old('title'),
        'package'     => (string) old('package'),
        'url'         => (string) old('url'),
        'value'       => (string) old('value'),
        'start_date'  => (string) old('start_date'),
        'end_date'    => (string) old('end_date'),
        'status'      => (string) old('status', 'Active'),
        'description' => (string) old('description'),
        'app_slug'    => (string) old('app_slug'),
        'city'        => (string) old('city'),
        'accent'      => (string) old('accent'),
        'sort_order'  => (int) old('sort_order', 0),
    ];
}

// Snapshot for client-side drawer prefill.
$rowsJson = [];
foreach ($rows as $p) {
    $rowsJson[] = [
        'id'                 => (int) $p['id'],
        'client_id' => (int) $p['client_id'],
        'project_id'         => (int) $p['project_id'],
        'is_won'             => (int) $p['is_won'],
        'title'              => $p['title'],
        'package'            => $p['package'],
        'url'                => $p['url'],
        'value'              => $p['value'],
        'start_date'         => $p['start_date'],
        'end_date'           => $p['end_date'],
        'status'             => $p['status'],
        'description'        => $p['description'],
        'app_slug'           => $p['app_slug'],
        'city'               => $p['city'],
        'accent'             => $p['accent'],
        'sort_order'         => (int) $p['sort_order'],
    ];
}
?>

<!-- Toolbar -->
<div class="card card-outline mb-3">
    <div class="card-body py-2">
        <div class="d-flex align-items-center flex-wrap" style="gap:.5rem">
            <form method="get" id="cpFilterForm" class="d-flex align-items-center flex-wrap" style="gap:.5rem;flex:1 1 auto;min-width:0">
                <input type="hidden" name="module" value="sales">
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
                <select name="won" class="form-control form-control-sm" style="width:130px">
                    <option value="">Won &amp; not won</option>
                    <option value="1" <?= $wonFilter === '1' ? 'selected' : '' ?>>Won</option>
                    <option value="0" <?= $wonFilter === '0' ? 'selected' : '' ?>>Not won (demo/trial)</option>
                </select>
                <select name="source" class="form-control form-control-sm" style="width:200px">
                    <option value="0">All Clients</option>
                    <option value="-1" <?= $sourceFilter === -1 ? 'selected' : '' ?>>Unassigned</option>
                    <?php foreach ($sources as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= $sourceFilter === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="catalog" class="form-control form-control-sm" style="width:200px">
                    <option value="0">All Products</option>
                    <option value="-1" <?= $catalogFilter === -1 ? 'selected' : '' ?>>Unassigned</option>
                    <?php foreach ($catalog as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= $catalogFilter === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?><?= $c['status'] !== 'Active' ? ' (inactive)' : '' ?></option>
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
                        <th>Client</th>
                        <th>URL</th>
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
                            <?php if (!(int) $p['is_won']): ?><span class="badge badge-info ml-1" title="Lead not won yet">Demo/Trial</span><?php endif; ?>
                            <?php if ($p['app_slug']): ?><span class="badge badge-light border ml-1" title="Listed in the school app"><i class="fas fa-mobile-alt mr-1"></i><?= e($p['app_slug']) ?></span><?php endif; ?>
                            <?php if ($p['catalog_name']): ?><br><small class="text-muted"><i class="fas fa-box mr-1"></i><?= e($p['catalog_name']) ?></small><?php endif; ?>
                            <?php if ($p['package']): ?><br><small class="text-muted"><?= e($p['package']) ?></small><?php endif; ?>
                        </td>
                        <td>
                            <?php if ($p['client_id']): ?>
                                <a href="<?= pageUrl('sales', 'client_detail') ?>&id=<?= (int) $p['client_id'] ?>"><?= e($p['source_name'] ?: ('#' . (int) $p['client_id'])) ?></a>
                                <?php if ($p['source_contact']): ?><br><small class="text-muted"><?= e($p['source_contact']) ?></small><?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $p['url'] ? '<a href="' . e($p['url']) . '" target="_blank" rel="noopener noreferrer">' . e($p['url']) . '</a>' : '—' ?></td>
                        <td class="text-right font-weight-bold"><?= $p['value'] !== null ? 'NPR ' . e(formatMoney($p['value'])) : '—' ?></td>
                        <td>
                            <small><?= $p['start_date'] ? e(formatDateView($p['start_date'])) : '—' ?> → <?= $p['end_date'] ? e(formatDateView($p['end_date'])) : '—' ?></small>
                        </td>
                        <td><span class="badge badge-<?= $statusBadge[$p['status']] ?? 'secondary' ?>"><?= e($p['status']) ?></span>
                            <?php if ($p['end_date'] && $p['end_date'] < date('Y-m-d') && $p['status'] === 'Active'): ?><span class="badge badge-danger">Expired</span><?php endif; ?>
                            <?php if ($p['owner_name']): ?><br><small class="text-muted"><?= e($p['owner_name']) ?></small><?php endif; ?>
                        </td>
                        <td class="text-right">
                            <?php if ($canManage): ?>
                                <button type="button" class="btn btn-xs btn-outline-secondary" title="Edit" onclick="openClientProjectDrawer(<?= (int) $p['id'] ?>)"><i class="fas fa-edit"></i></button>
                                <?php if ($p['app_slug']): ?>
                                    <button type="button" class="btn btn-xs btn-outline-danger" disabled title="Listed in the school app — set status to Cancelled instead"><i class="fas fa-trash"></i></button>
                                <?php else: ?>
                                <form action="operation.php?module=sales&page=client_projects" method="post" class="d-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete_client_project">
                                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                    <input type="hidden" name="return_query" value="<?= e($returnQuery) ?>">
                                    <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete client project '<?= e($p['title']) ?>'? This cannot be undone."><i class="fas fa-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>No client projects yet — they are created when a lead is Won or starts a demo/trial.</td></tr>
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
        <form action="operation.php?module=sales&page=client_projects" method="post" id="cpForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_client_project">
            <input type="hidden" name="id" id="cpId" value="0">
            <input type="hidden" name="return_query" value="<?= e($returnQuery) ?>">
            <div class="form-group">
                <label>Client</label>
                <select name="client_id" id="cpSource" class="form-control">
                    <option value="">— None —</option>
                    <?php foreach ($sources as $s): ?>
                        <option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Product</label>
                <select name="project_id" id="cpCatalog" class="form-control">
                    <option value="">— None —</option>
                    <?php foreach ($catalog as $c): ?>
                        <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?><?= $c['status'] !== 'Active' ? ' (inactive)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" id="cpTitle" class="form-control" required placeholder="e.g. Smart School Pro — Acme Ltd" value="<?= e(old('title')) ?>">
            </div>
            <div class="row">
                <div class="col-6 form-group">
                    <label>Package</label>
                    <input type="text" name="package" id="cpPackage" class="form-control" placeholder="e.g. Smart School Pro" value="<?= e(old('package')) ?>">
                </div>
                <div class="col-6 form-group">
                    <label>URL</label>
                    <input type="text" name="url" id="cpUrl" class="form-control" placeholder="https://project.example.com" value="<?= e(old('url')) ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-6 form-group">
                    <label>Value (NPR)</label>
                    <input type="number" name="value" id="cpValue" class="form-control" step="0.01" min="0" placeholder="0.00" value="<?= e(old('value')) ?>">
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
                    <input type="date" name="start_date" id="cpStart" class="form-control" value="<?= e(old('start_date')) ?>">
                </div>
                <div class="col-6 form-group">
                    <label>End</label>
                    <input type="date" name="end_date" id="cpEnd" class="form-control" value="<?= e(old('end_date')) ?>">
                </div>
            </div>
            <div class="form-group">
                <input type="hidden" name="is_won" value="0">
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" name="is_won" id="cpIsWon" value="1" checked>
                    <label class="custom-control-label" for="cpIsWon">Won deal <small class="text-muted">(untick for a demo/trial of a lead not won yet)</small></label>
                </div>
            </div>
            <fieldset class="border rounded px-3 pt-2 mb-3">
                <legend class="w-auto px-1 small font-weight-bold mb-0"><i class="fas fa-mobile-alt mr-1"></i>App listing</legend>
                <input type="hidden" name="app_listing" value="1">
                <p class="small text-muted mb-2">Set an app id to list this school in the school app (<code>/api/v1/schools</code>). The URL must be <?= config('app_listing_allow_http', false) ? 'http or https (local http allowed on this server)' : 'https' ?>. The id is permanent once saved.</p>
                <div class="row">
                    <div class="col-6 form-group">
                        <label class="small">App id</label>
                        <input type="text" name="app_slug" id="cpAppSlug" class="form-control" maxlength="32" autocomplete="off" oninput="this.value = this.value.toLowerCase()" pattern="[a-z0-9][a-z0-9\-]{1,31}" placeholder="e.g. mlebs">
                    </div>
                    <div class="col-6 form-group">
                        <label class="small">City</label>
                        <input type="text" name="city" id="cpCity" class="form-control" maxlength="100" placeholder="e.g. Kathmandu">
                    </div>
                </div>
                <div class="row">
                    <div class="col-6 form-group">
                        <label class="small">Accent colour</label>
                        <div class="input-group">
                            <div class="input-group-prepend"><input type="color" id="cpAccentPicker" class="form-control p-1" style="width:44px" value="#2563EB" aria-label="Pick accent colour"></div>
                            <input type="text" name="accent" id="cpAccent" class="form-control" maxlength="7" pattern="#[0-9A-Fa-f]{6}" placeholder="#2563EB">
                        </div>
                    </div>
                    <div class="col-6 form-group">
                        <label class="small">Sort order</label>
                        <input type="number" name="sort_order" id="cpSortOrder" class="form-control" step="1" value="0">
                    </div>
                </div>
            </fieldset>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="cpDescription" class="form-control" rows="2"><?= e(old('description')) ?></textarea>
            </div>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="cpForm" class="btn btn-primary btn-block"><i class="fas fa-save mr-1"></i><span id="cpDrawerSubmit">Add Client Project</span></button>
    </div>
</div>

<script>
var cpData = <?= json_encode($rowsJson) ?>;
var cpOld = <?= json_encode($oldCp) ?>;

// p = values to show; saved = the stored row (decides whether the app id is locked).
function fillCpForm(p, saved) {
    document.getElementById('cpId').value = p ? p.id : 0;
    document.getElementById('cpSource').value = p ? (p.client_id || '') : '';
    document.getElementById('cpCatalog').value = p ? (p.project_id || '') : '';
    document.getElementById('cpTitle').value = p ? (p.title || '') : '';
    document.getElementById('cpPackage').value = p ? (p.package || '') : '';
    document.getElementById('cpUrl').value = p ? (p.url || '') : '';
    document.getElementById('cpValue').value = p ? (p.value || '') : '';
    document.getElementById('cpStatus').value = p ? (p.status || 'Active') : 'Active';
    document.getElementById('cpIsWon').checked = p ? p.is_won === 1 : true;
    document.getElementById('cpStart').value = p ? (p.start_date || '') : '';
    document.getElementById('cpEnd').value = p ? (p.end_date || '') : '';
    document.getElementById('cpDescription').value = p ? (p.description || '') : '';
    var slug = document.getElementById('cpAppSlug');
    slug.value = p ? (p.app_slug || '') : '';
    slug.readOnly = !!(saved && saved.app_slug);
    document.getElementById('cpCity').value = p ? (p.city || '') : '';
    document.getElementById('cpAccent').value = p ? (p.accent || '') : '';
    document.getElementById('cpAccentPicker').value = (p && p.accent) ? p.accent : '#2563EB';
    document.getElementById('cpSortOrder').value = p ? (p.sort_order || 0) : 0;
}
document.getElementById('cpAccentPicker').addEventListener('input', function() {
    document.getElementById('cpAccent').value = this.value.toUpperCase();
});
document.getElementById('cpAccent').addEventListener('input', function() {
    if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
        document.getElementById('cpAccentPicker').value = this.value;
    }
});

// override: values from a failed save, shown instead of the stored row.
function openClientProjectDrawer(id, override) {
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
    fillCpForm(override || p, p);
    document.getElementById('cpDrawerTitle').textContent = p ? 'Edit Client Project' : 'Add Client Project';
    document.getElementById('cpDrawerSubmit').textContent = p ? 'Update Client Project' : 'Add Client Project';
}

if (cpOld) {
    openClientProjectDrawer(cpOld.id, cpOld);
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