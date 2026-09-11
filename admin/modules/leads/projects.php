<?php
/**
 * SB-Tech — Project Catalog (independent entity, US-PJ).
 * CRUD for the products/services the business sells (name, code, category,
 * description, status). Leads pursue a catalog project (tbl_leads.project_id →
 * tbl_projects); won deals provision a Client Project (tbl_client_projects).
 */
$db = Database::instance();
$canManage = Auth::isSuperAdmin() || Auth::hasSpecial('manage_leads');

$statuses = ['Active', 'Inactive'];
$statusBadge = ['Active' => 'success', 'Inactive' => 'secondary'];

// ── filters ──
$keyword = trim((string) ($_GET['keyword'] ?? ''));
$statusFilter = (string) ($_GET['status'] ?? '');
$categoryFilter = trim((string) ($_GET['category'] ?? ''));

$where = ['1=1'];
$params = [];
if (in_array($statusFilter, $statuses, true)) {
    $where[] = 'p.status = ?';
    $params[] = $statusFilter;
}
if ($categoryFilter !== '') {
    $where[] = 'p.category = ?';
    $params[] = $categoryFilter;
}
if ($keyword !== '') {
    $where[] = '(p.name LIKE ? OR p.code LIKE ? OR p.category LIKE ?)';
    $kw = '%' . $db->escapeLike($keyword) . '%';
    array_push($params, $kw, $kw, $kw);
}

$projects = $db->select(
    'SELECT p.*,
            (SELECT COUNT(*) FROM `tbl_leads` l WHERE l.project_id = p.id) AS lead_count
     FROM `tbl_projects` p
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY p.status = "Active" DESC, p.name ASC',
    $params
);
$categories = $db->select(
    'SELECT DISTINCT `category` FROM `tbl_projects`
     WHERE `category` IS NOT NULL AND `category` <> \'\' ORDER BY `category`'
);

// Snapshot for client-side drawer prefill.
$projectsJson = [];
foreach ($projects as $p) {
    $projectsJson[] = [
        'id'          => (int) $p['id'],
        'name'        => $p['name'],
        'code'        => $p['code'],
        'category'    => $p['category'],
        'status'      => $p['status'],
        'description' => $p['description'],
    ];
}
?>

<!-- Toolbar -->
<div class="card card-outline mb-3">
    <div class="card-body py-2">
        <div class="d-flex align-items-center flex-wrap" style="gap:.5rem">
            <form method="get" id="projectFilterForm" class="d-flex align-items-center flex-wrap" style="gap:.5rem;flex:1 1 auto;min-width:0">
                <input type="hidden" name="module" value="leads">
                <input type="hidden" name="page" value="projects">
                <div class="input-group input-group-sm" style="width:220px">
                    <div class="input-group-prepend"><span class="input-group-text bg-transparent border-right-0"><i class="fas fa-search text-muted"></i></span></div>
                    <input type="text" name="keyword" class="form-control border-left-0" placeholder="Search projects..." value="<?= e($keyword) ?>">
                </div>
                <select name="status" class="form-control form-control-sm" style="width:130px">
                    <option value="">All Status</option>
                    <?php foreach ($statuses as $s): ?>
                        <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="category" class="form-control form-control-sm" style="width:180px">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= e($cat['category']) ?>" <?= $categoryFilter === $cat['category'] ? 'selected' : '' ?>><?= e($cat['category']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" form="projectFilterForm" class="btn btn-sm btn-primary"><i class="fas fa-search mr-1"></i>Filter</button>
            </form>

            <?php if ($canManage): ?>
                <button type="button" class="btn btn-sm btn-primary" onclick="openProjectDrawer()"><i class="fas fa-plus mr-1"></i>Add Project</button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Projects table -->
<div class="card card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-project-diagram mr-1"></i>Project Catalog</h3>
        <div class="card-tools text-muted small"><?= count($projects) ?> project<?= count($projects) === 1 ? '' : 's' ?></div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Project</th>
                        <th>Code</th>
                        <th>Category</th>
                        <th class="text-center">Leads</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($projects as $i => $p): ?>
                    <tr>
                        <td class="text-muted"><?= $i + 1 ?></td>
                        <td>
                            <a href="<?= pageUrl('leads', 'projects') ?>&edit=<?= (int) $p['id'] ?>" class="font-weight-bold text-decoration-none"><?= e($p['name']) ?></a>
                            <?php if ($p['description']): ?><br><small class="text-muted"><?= e(mb_strimwidth($p['description'], 0, 90, '…')) ?></small><?php endif; ?>
                        </td>
                        <td><?= $p['code'] ? '<code>' . e($p['code']) . '</code>' : '—' ?></td>
                        <td><?= $p['category'] ? e($p['category']) : '—' ?></td>
                        <td class="text-center"><span class="badge badge-<?= (int) $p['lead_count'] > 0 ? 'info' : 'light' ?> border"><?= (int) $p['lead_count'] ?></span></td>
                        <td><span class="badge badge-<?= $statusBadge[$p['status']] ?? 'secondary' ?>"><?= e($p['status']) ?></span></td>
                        <td class="text-right">
                            <?php if ($canManage): ?>
                                <button type="button" class="btn btn-xs btn-outline-secondary" title="Edit" onclick="openProjectDrawer(<?= (int) $p['id'] ?>)"><i class="fas fa-edit"></i></button>
                                <form action="operation.php?module=leads&page=projects" method="post" class="d-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete_project">
                                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                    <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete project '<?= e($p['name']) ?>'? Leads & client projects will keep their history (the links are cleared)."><i class="fas fa-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$projects): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>No projects in the catalog yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Drawer backdrop + project drawer -->
<div class="cms-drawer-backdrop" id="drawerBackdrop" onclick="closeProjectDrawer()"></div>
<div class="cms-drawer" id="projectDrawer">
    <div class="cms-drawer-header">
        <h3><i class="fas fa-project-diagram"></i><span id="projectDrawerTitle">Add Project</span></h3>
        <button type="button" class="cms-drawer-close" onclick="closeProjectDrawer()" aria-label="Close"><i class="fas fa-times"></i></button>
    </div>
    <div class="cms-drawer-body">
        <form action="operation.php?module=leads&page=projects" method="post" id="projectForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_project">
            <input type="hidden" name="id" id="projectId" value="0">
            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name" id="projectName" class="form-control" required placeholder="e.g. Smart School Pro">
            </div>
            <div class="row">
                <div class="col-6 form-group">
                    <label>Code</label>
                    <input type="text" name="code" id="projectCode" class="form-control" placeholder="e.g. SS-PRO">
                </div>
                <div class="col-6 form-group">
                    <label>Category</label>
                    <input type="text" name="category" id="projectCategory" class="form-control" list="projectCategoryList" placeholder="e.g. Education">
                    <datalist id="projectCategoryList">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= e($cat['category']) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" id="projectStatus" class="form-control">
                    <?php foreach ($statuses as $s): ?>
                        <option value="<?= $s ?>"><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="projectDescription" class="form-control" rows="3"></textarea>
            </div>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="projectForm" class="btn btn-primary btn-block"><i class="fas fa-save mr-1"></i><span id="projectDrawerSubmit">Add Project</span></button>
    </div>
</div>

<script>
var projectsData = <?= json_encode($projectsJson) ?>;

function fillProjectForm(project) {
    document.getElementById('projectId').value = project ? project.id : 0;
    document.getElementById('projectName').value = project ? (project.name || '') : '';
    document.getElementById('projectCode').value = project ? (project.code || '') : '';
    document.getElementById('projectCategory').value = project ? (project.category || '') : '';
    document.getElementById('projectStatus').value = project ? (project.status || 'Active') : 'Active';
    document.getElementById('projectDescription').value = project ? (project.description || '') : '';
}

function openProjectDrawer(projectId) {
    closeProjectDrawer();
    var drawer = document.getElementById('projectDrawer');
    document.getElementById('drawerBackdrop').classList.add('active');
    drawer.classList.add('open');
    document.body.style.overflow = 'hidden';

    document.getElementById('projectId').value = projectId ? projectId : 0;
    var project = null;
    if (projectId) {
        for (var i = 0; i < projectsData.length; i++) {
            if (projectsData[i].id == projectId) { project = projectsData[i]; break; }
        }
    }
    fillProjectForm(project);
    document.getElementById('projectDrawerTitle').textContent = project ? 'Edit Project' : 'Add Project';
    document.getElementById('projectDrawerSubmit').textContent = project ? 'Update Project' : 'Add Project';
}

function closeProjectDrawer() {
    document.getElementById('projectDrawer').classList.remove('open');
    document.getElementById('drawerBackdrop').classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeProjectDrawer();
});
</script>