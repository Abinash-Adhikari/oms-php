<?php

/**
 * SB-Tech — Clients (companies/individuals registry) list view.
 * Individual or Company rows link to the separate detail page
 * (clients/detail.php?module=clients&page=detail&id=N).
 */
$db = Database::instance();
$canManage = Auth::isSuperAdmin() || Auth::hasSpecial('manage_leads');

$keyword = trim((string) ($_GET['keyword'] ?? ''));
$typeFilter = (string) ($_GET['type'] ?? '');
$where = ['1=1'];
$params = [];
if (in_array($typeFilter, ['Individual', 'Company'], true)) {
    $where[] = 'c.type = ?';
    $params[] = $typeFilter;
}
if ($keyword !== '') {
    $where[] = '(c.name LIKE ? OR c.contact_person LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.pan_num LIKE ?)';
    $kw = '%' . $db->escapeLike($keyword) . '%';
    array_push($params, $kw, $kw, $kw, $kw, $kw);
}
$sources = $db->select(
    'SELECT c.*,
            (SELECT COUNT(*) FROM `tbl_client_contacts` t WHERE t.client_id = c.id) AS contact_count,
            (SELECT COUNT(*) FROM `tbl_client_projects` p WHERE p.client_id = c.id) AS project_count,
            (SELECT COUNT(*) FROM `tbl_leads` lc WHERE lc.client_id = c.id) AS lead_count
     FROM `tbl_clients` c
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY c.name',
    $params
);

// Snapshot for client-side drawer prefill.
$sourcesJson = [];
foreach ($sources as $c) {
    $sourcesJson[] = [
        'id'             => (int) $c['id'],
        'type'           => $c['type'],
        'name'           => $c['name'],
        'contact_person' => $c['contact_person'],
        'email'          => $c['email'],
        'phone'          => $c['phone'],
        'address'        => $c['address'],
        'pan_num'        => $c['pan_num'],
        'notes'          => $c['notes'],
    ];
}
?>
<!-- Clients Table -->
<div class="card card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-handshake mr-1"></i>Clients</h3>
        <div class="card-tools">
            <?php if ($canManage): ?>
                <button type="button" class="btn btn-primary btn-sm" onclick="openSourceDrawer()">
                    <i class="fas fa-plus mr-1"></i>Add Client
                </button>
            <?php endif; ?>
            <form method="get" class="form-inline d-inline ml-2">
                <input type="hidden" name="module" value="clients">
                <input type="hidden" name="page" value="clients">
                <select name="type" class="form-control form-control-sm mr-1" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <?php foreach (['Individual', 'Company'] as $t): ?>
                        <option value="<?= $t ?>" <?= $typeFilter === $t ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="keyword" class="form-control form-control-sm mr-1" placeholder="Search" value="<?= e($keyword) ?>">
                <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-search"></i></button>
            </form>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Primary Contact</th>
                        <th>Email / Phone</th>
                        <th>PAN</th>
                        <th class="text-center">Contacts</th>
                        <th class="text-center">Won Projects</th>
                        <th class="text-center">Leads</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sources as $i => $c): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td>
                                <span class="font-weight-bold"><?= e($c['name']) ?></span>
                                <br><small><span class="badge badge-<?= $c['type'] === 'Individual' ? 'info' : 'secondary' ?>"><?= e($c['type']) ?></span></small>
                            </td>
                            <td><?= e($c['contact_person'] ?: '—') ?></td>
                            <td><?= e($c['email'] ?: '—') ?><br><small><?= e($c['phone'] ?: '—') ?></small></td>
                            <td><?= e($c['pan_num'] ?: '—') ?></td>
                            <td class="text-center"><span class="badge badge-<?= (int) $c['contact_count'] > 0 ? 'info' : 'light' ?> border"><?= (int) $c['contact_count'] ?></span></td>
                            <td class="text-center"><span class="badge badge-<?= (int) $c['project_count'] > 0 ? 'success' : 'light' ?> border"><?= (int) $c['project_count'] ?></span></td>
                            <td class="text-center"><span class="badge badge-light border"><?= (int) $c['lead_count'] ?></span></td>
                            <td class="text-right">
                                <a href="<?= pageUrl('clients', 'detail') ?>&id=<?= (int) $c['id'] ?>" class="btn btn-xs btn-outline-primary" title="View"><i class="fas fa-eye"></i></a>
                                <?php if ($canManage): ?>
                                    <button type="button" class="btn btn-xs btn-outline-secondary" title="Edit" onclick="openSourceDrawer(<?= (int) $c['id'] ?>)"><i class="fas fa-edit"></i></button>
                                    <form action="operation.php?module=clients&page=clients" method="post" class="d-inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="delete_client">
                                        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                        <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete client '<?= e($c['name']) ?>'? Won projects & contacts will be unlinked (kept)."><i class="fas fa-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$sources): ?><tr>
                            <td colspan="9" class="text-center text-muted">No clients yet.</td>
                        </tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Drawer Backdrop -->
<div class="cms-drawer-backdrop" id="drawerBackdrop" onclick="closeAllDrawers()"></div>

<!-- Client Drawer -->
<div class="cms-drawer" id="sourceDrawer">
    <div class="cms-drawer-header">
        <h3><i class="fas fa-handshake"></i><span id="sourceDrawerTitle">Add Client</span></h3>
        <button type="button" class="cms-drawer-close" onclick="closeAllDrawers()" aria-label="Close"><i class="fas fa-times"></i></button>
    </div>
    <div class="cms-drawer-body">
        <form action="operation.php?module=clients&page=clients" method="post" id="sourceForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_client">
            <input type="hidden" name="id" id="sourceId" value="0">
            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name" id="sourceName" class="form-control" required value="">
            </div>
            <div class="form-group">
                <label>Type</label>
                <select name="type" id="sourceType" class="form-control">
                    <?php foreach (['Company', 'Individual'] as $t): ?>
                        <option value="<?= $t ?>"><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Primary contact person</label>
                <input type="text" name="contact_person" id="sourceContact" class="form-control" value="">
                <small class="form-text text-muted">Mirrors the primary Contact Person entry below.</small>
            </div>
            <div class="row">
                <div class="col-6 form-group"><label>Email</label><input type="email" name="email" id="sourceEmail" class="form-control" value=""></div>
                <div class="col-6 form-group"><label>Phone</label><input type="text" name="phone" id="sourcePhone" class="form-control" value=""></div>
            </div>
            <div class="form-group"><label>Address</label><input type="text" name="address" id="sourceAddress" class="form-control" value=""></div>
            <div class="form-group"><label>PAN no</label><input type="text" name="pan_num" id="sourcePan" class="form-control" value=""></div>
            <div class="form-group"><label>Notes</label><textarea name="notes" id="sourceNotes" class="form-control" rows="2"></textarea></div>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="sourceForm" class="btn btn-primary btn-block"><i class="fas fa-save mr-1"></i><span id="sourceDrawerSubmit">Add Client</span></button>
    </div>
</div>

<script>
    var sourcesData = <?= json_encode($sourcesJson) ?>;

    /* ═══ Client Drawer ═══ */
    function fillSourceForm(source) {
        document.getElementById('sourceName').value = source ? (source.name || '') : '';
        document.getElementById('sourceType').value = source ? (source.type || 'Company') : 'Company';
        document.getElementById('sourceContact').value = source ? (source.contact_person || '') : '';
        document.getElementById('sourceEmail').value = source ? (source.email || '') : '';
        document.getElementById('sourcePhone').value = source ? (source.phone || '') : '';
        document.getElementById('sourceAddress').value = source ? (source.address || '') : '';
        document.getElementById('sourcePan').value = source ? (source.pan_num || '') : '';
        document.getElementById('sourceNotes').value = source ? (source.notes || '') : '';
    }

    function openSourceDrawer(sourceId) {
        closeAllDrawers();
        document.getElementById('drawerBackdrop').classList.add('active');
        document.getElementById('sourceDrawer').classList.add('open');
        document.body.style.overflow = 'hidden';

        document.getElementById('sourceId').value = sourceId ? sourceId : 0;
        var source = null;
        if (sourceId) {
            for (var i = 0; i < sourcesData.length; i++) {
                if (sourcesData[i].id == sourceId) {
                    source = sourcesData[i];
                    break;
                }
            }
        }
        fillSourceForm(source);
        document.getElementById('sourceDrawerTitle').textContent = source ? 'Edit Client' : 'Add Client';
        document.getElementById('sourceDrawerSubmit').textContent = source ? 'Update Client' : 'Add Client';
    }

    /* ═══ Shared Drawer Controls ═══ */
    function closeAllDrawers() {
        document.querySelectorAll('.cms-drawer').forEach(function(d) {
            d.classList.remove('open');
        });
        var bd = document.getElementById('drawerBackdrop');
        if (bd) bd.classList.remove('active');
        document.body.style.overflow = '';
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeAllDrawers();
    });
</script>