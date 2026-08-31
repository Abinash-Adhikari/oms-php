<?php
/**
 * SB-Tech — Clients (AC-LE-06.1/06.2).
 * Client registry + optional project records per client.
 */
$db = Database::instance();
$canManage = Auth::isSuperAdmin() || Auth::hasSpecial('manage_leads');

$stageBadges = ['New' => 'primary', 'Contacted' => 'info', 'Qualified' => 'warning', 'Proposal' => 'secondary', 'Won' => 'success', 'Lost' => 'danger'];
$priorityColors = ['Hot' => 'danger', 'Warm' => 'warning', 'Cold' => 'secondary'];

$edit = null;
$projects = [];
$clientLeads = [];
if (isset($_GET['id'])) {
    $edit = $db->selectOne('SELECT * FROM `tbl_clients` WHERE `id` = ?', [(int) $_GET['id']]);
    if ($edit) {
        $projects = $db->select(
            'SELECT * FROM `tbl_client_projects` WHERE `client_id` = ? ORDER BY start_date DESC',
            [(int) $edit['id']]
        );
        $clientLeads = $db->select(
            'SELECT l.*, o.fullname AS owner_name
             FROM `tbl_leads` l
             LEFT JOIN `tbl_users_login` o ON o.id = l.assigned_to
             WHERE l.client_id = ? ORDER BY l.added_on DESC',
            [(int) $edit['id']]
        );
    }
}

$keyword = trim((string) ($_GET['keyword'] ?? ''));
$where = '1=1';
$params = [];
if ($keyword !== '') {
    $where = '(name LIKE ? OR contact_person LIKE ? OR email LIKE ? OR phone LIKE ?)';
    $kw = '%' . $db->escapeLike($keyword) . '%';
    $params = [$kw, $kw, $kw, $kw];
}
$clients = $db->select(
    'SELECT c.*, l.company AS lead_company,
            (SELECT COUNT(*) FROM `tbl_client_projects` p WHERE p.client_id = c.id) AS project_count,
            (SELECT COUNT(*) FROM `tbl_leads` lc WHERE lc.client_id = c.id) AS lead_count
     FROM `tbl_clients` c
     LEFT JOIN `tbl_leads` l ON l.client_id = c.id
     WHERE ' . $where . '
     ORDER BY c.name',
    $params
);
$clientDrawerOpen = ($edit !== null);
?>

<!-- Client Table (full width) -->
<div class="card card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-users mr-1"></i>Clients</h3>
        <div class="card-tools">
            <?php if ($canManage): ?>
                <button type="button" class="btn btn-primary btn-sm" onclick="openClientDrawer()">
                    <i class="fas fa-plus mr-1"></i>Add Client
                </button>
            <?php endif; ?>
            <form method="get" class="form-inline d-inline ml-2">
                <input type="hidden" name="module" value="leads">
                <input type="hidden" name="page" value="clients">
                <input type="text" name="keyword" class="form-control form-control-sm mr-1" placeholder="Search" value="<?= e($keyword) ?>">
                <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-search"></i></button>
            </form>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover mb-0">
                <thead><tr><th>#</th><th>Name</th><th>Contact</th><th>Email / Phone</th><th>PAN</th><th class="text-center">Leads</th><th class="text-center">Projects</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($clients as $i => $c): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= e($c['name']) ?><?php if ($c['lead_company']): ?><br><small class="text-muted">from <?= e($c['lead_company']) ?></small><?php endif; ?></td>
                        <td><?= e($c['contact_person'] ?: '—') ?></td>
                        <td><?= e($c['email'] ?: '—') ?><br><small><?= e($c['phone'] ?: '—') ?></small></td>
                        <td><?= e($c['pan_num'] ?: '—') ?></td>
                        <td class="text-center"><span class="badge badge-<?= (int) $c['lead_count'] > 0 ? 'info' : 'light' ?> border"><?= (int) $c['lead_count'] ?></span></td>
                        <td class="text-center"><span class="badge badge-light border"><?= (int) $c['project_count'] ?></span></td>
                        <td class="text-right">
                            <button type="button" class="btn btn-xs btn-outline-primary" onclick="openClientDrawer(<?= (int) $c['id'] ?>)"><i class="fas fa-eye"></i></button>
                            <?php if ($canManage): ?>
                                <form action="operation.php?module=leads&page=clients" method="post" class="d-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete_client">
                                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                    <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete client '<?= e($c['name']) ?>' and their projects?"><i class="fas fa-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$clients): ?><tr><td colspan="8" class="text-center text-muted">No clients yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Client Projects (shown below table when a client is selected) -->
<?php if ($edit): ?>
    <div class="card card-outline">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-project-diagram mr-1"></i>Projects — <?= e($edit['name']) ?></h3>
            <div class="card-tools">
                <?php if ($canManage): ?>
                    <button type="button" class="btn btn-primary btn-sm" onclick="openProjectDrawer()">
                        <i class="fas fa-plus mr-1"></i>Add Project
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead><tr><th>#</th><th>Title</th><th>Value</th><th>Period</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($projects as $i => $p): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= e($p['title']) ?></td>
                            <td><?= $p['value'] !== null ? 'NPR ' . e(formatMoney($p['value'])) : '—' ?></td>
                            <td><?= $p['start_date'] ? e(formatDateView($p['start_date'])) : '—' ?> → <?= $p['end_date'] ? e(formatDateView($p['end_date'])) : '—' ?></td>
                            <td><span class="badge badge-<?= $p['status'] === 'Active' ? 'success' : ($p['status'] === 'Completed' ? 'info' : ($p['status'] === 'On Hold' ? 'warning' : 'secondary')) ?>"><?= e($p['status']) ?></span></td>
                            <td class="text-right">
                                <?php if ($canManage): ?>
                                    <form action="operation.php?module=leads&page=clients" method="post" class="d-inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="delete_project">
                                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                        <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete project '<?= e($p['title']) ?>'?"><i class="fas fa-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$projects): ?><tr><td colspan="6" class="text-center text-muted">No projects yet.</td></tr><?php endif; ?>
                    </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Linked Leads (shown below projects when a client is selected) -->
<?php if ($edit): ?>
    <div class="card card-outline">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-link mr-1"></i>Linked Leads — <?= e($edit['name']) ?></h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead><tr><th>#</th><th>Company / Contact</th><th>Service</th><th>Stage</th><th>Priority</th><th>Owner</th><th>Added</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($clientLeads as $i => $l): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= e($l['company'] ?: $l['contact_name']) ?><br><small class="text-muted"><?= e($l['contact_name'] ?: '—') ?> · <?= e($l['email'] ?: '—') ?></small></td>
                            <td><?= e($l['service_interest'] ?: '—') ?></td>
                            <td>
                                <span class="badge badge-<?= $stageBadges[$l['stage']] ?? 'secondary' ?>"><?= e($l['stage']) ?></span>
                            </td>
                            <td><span class="badge badge-<?= $priorityColors[$l['priority']] ?? 'secondary' ?>"><?= e($l['priority']) ?></span></td>
                            <td><?= e($l['owner_name'] ?: '—') ?></td>
                            <td><?= e(date('M j, Y', strtotime($l['added_on']))) ?></td>
                            <td class="text-right">
                                <a href="<?= pageUrl('leads', 'leads') ?>&id=<?= (int) $l['id'] ?>" class="btn btn-xs btn-outline-primary"><i class="fas fa-eye"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$clientLeads): ?><tr><td colspan="8" class="text-center text-muted">No leads linked to this client yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Slide-in Drawer Backdrop -->
<div class="cms-drawer-backdrop" id="drawerBackdrop" onclick="closeAllDrawers()"></div>

<!-- Client Drawer -->
<div class="cms-drawer" id="clientDrawer">
    <div class="cms-drawer-header">
        <h3><i class="fas fa-handshake"></i><span id="clientDrawerTitle"><?= $edit ? 'Edit Client' : 'Add Client' ?></span></h3>
        <button type="button" class="cms-drawer-close" onclick="closeAllDrawers()" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="cms-drawer-body">
        <form action="operation.php?module=leads&page=clients" method="post" id="clientForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_client">
            <input type="hidden" name="id" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name" class="form-control" required value="<?= $edit ? e($edit['name']) : '' ?>">
            </div>
            <div class="form-group">
                <label>Contact person</label>
                <input type="text" name="contact_person" class="form-control" value="<?= $edit ? e($edit['contact_person']) : '' ?>">
            </div>
            <div class="row">
                <div class="col-6 form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" value="<?= $edit ? e($edit['email']) : '' ?>">
                </div>
                <div class="col-6 form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= $edit ? e($edit['phone']) : '' ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Address</label>
                <input type="text" name="address" class="form-control" value="<?= $edit ? e($edit['address']) : '' ?>">
            </div>
            <div class="form-group">
                <label>PAN no</label>
                <input type="text" name="pan_num" class="form-control" value="<?= $edit ? e($edit['pan_num']) : '' ?>">
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" class="form-control" rows="2"><?= $edit ? e($edit['notes']) : '' ?></textarea>
            </div>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="clientForm" class="btn btn-primary btn-block">
            <i class="fas fa-save mr-1"></i><span><?= $edit ? 'Update Client' : 'Add Client' ?></span>
        </button>
    </div>
</div>

<!-- Project Drawer -->
<?php if ($canManage): ?>
<div class="cms-drawer" id="projectDrawer">
    <div class="cms-drawer-header">
        <h3><i class="fas fa-project-diagram"></i>Add Project</h3>
        <button type="button" class="cms-drawer-close" onclick="closeAllDrawers()" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="cms-drawer-body">
        <form action="operation.php?module=leads&page=clients" method="post" id="projectForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_project">
            <input type="hidden" name="client_id" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Value (NPR)</label>
                <input type="number" name="value" class="form-control" step="0.01" min="0">
            </div>
            <div class="row">
                <div class="col-6 form-group">
                    <label>Start</label>
                    <input type="date" name="start_date" class="form-control">
                </div>
                <div class="col-6 form-group">
                    <label>End</label>
                    <input type="date" name="end_date" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <?php foreach (['Active', 'Completed', 'On Hold', 'Cancelled'] as $s): ?>
                        <option value="<?= $s ?>"><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="2"></textarea>
            </div>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="projectForm" class="btn btn-primary btn-block">
            <i class="fas fa-save mr-1"></i>Add Project
        </button>
    </div>
</div>
<?php endif; ?>

<script>
function openClientDrawer(clientId) {
    closeAllDrawers();
    var drawer = document.getElementById('clientDrawer');
    var backdrop = document.getElementById('drawerBackdrop');
    drawer.classList.add('open');
    backdrop.classList.add('active');
    document.body.style.overflow = 'hidden';

    if (clientId) {
        window.location.href = '<?= pageUrl('leads', 'clients') ?>&id=' + clientId;
    }
}

function openProjectDrawer() {
    closeAllDrawers();
    var drawer = document.getElementById('projectDrawer');
    var backdrop = document.getElementById('drawerBackdrop');
    drawer.classList.add('open');
    backdrop.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeAllDrawers() {
    document.querySelectorAll('.cms-drawer').forEach(function(d) { d.classList.remove('open'); });
    document.getElementById('drawerBackdrop').classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeAllDrawers();
});

<?php if ($clientDrawerOpen): ?>
document.addEventListener('DOMContentLoaded', function() {
    openClientDrawer();
});
<?php endif; ?>
</script>
