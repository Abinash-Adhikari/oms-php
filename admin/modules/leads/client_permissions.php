<?php

/**
 * SB-Tech — Leads / Client Access.
 * Per-client-project provisioning: module & deployment-database grants for a
 * won client project (tbl_client_projects row), edited exactly like staff
 * module permissions. Each won project gets its own database and module set.
 *
 * Stored as JSON columns on the project row and read back through
 * ClientPermissions::can()/databaseFor(). Super Admin sees everything;
 * everyone else must hold manage_leads.
 */

$db = Database::instance();
$canManage = Auth::isSuperAdmin() || Auth::hasSpecial('manage_leads');

$editProject = null;
if (isset($_GET['id'])) {
    $editProject = $db->selectOne(
        'SELECT cp.*, bs.name AS source_name, p.name AS catalog_name
         FROM `tbl_client_projects` cp
         LEFT JOIN `tbl_clients` bs ON bs.id = cp.client_id
         LEFT JOIN `tbl_projects` p ON p.id = cp.project_id
         WHERE cp.id = ?',
        [(int) $_GET['id']]
    );
}

$projects = $db->select(
    'SELECT cp.id, cp.title, cp.status, cp.db_name, bs.name AS source_name, p.name AS catalog_name
     FROM `tbl_client_projects` cp
     LEFT JOIN `tbl_clients` bs ON bs.id = cp.client_id
     LEFT JOIN `tbl_projects` p ON p.id = cp.project_id
     ORDER BY bs.name ASC, cp.title ASC'
);

// DB-driven module catalog (tbl_modules / tbl_submodules in the client's
// deployment DB, default master php_smart_school_mlebs). The checkbox state
// reflects the deployment DB's is_active (on/off) flags — the store of truth
// for what the client actually sees.
$catalog = $editProject ? client_module_catalog($editProject) : [];
$catalogDb = trim((string) ($editProject['db_name'] ?? '')) ?: 'php_smart_school_mlebs';

$grouped = [];
foreach ($catalog as $m) {
    $grouped[$m['section']][] = $m;
}
ksort($grouped, SORT_STRING);
?>

<?php if ($editProject): ?>
    <div class="d-flex align-items-center mb-3">
        <?php if ($editProject['client_id']): ?>
            <a href="<?= pageUrl('clients', 'detail') ?>&id=<?= (int) $editProject['client_id'] ?>" class="btn btn-sm btn-outline-secondary mr-2" aria-label="Back to client"><i class="fas fa-arrow-left"></i></a>
        <?php else: ?>
            <a href="<?= pageUrl('leads', 'client_projects') ?>" class="btn btn-sm btn-outline-secondary mr-2" aria-label="Back to client projects"><i class="fas fa-arrow-left"></i></a>
        <?php endif; ?>
        <div class="flex-grow-1">
            <h4 class="mb-0 font-weight-bold" style="font-family:'Poppins',sans-serif"><?= e($editProject['title']) ?></h4>
            <?php if ($editProject['source_name']): ?>
                <?php if ($editProject['client_id']): ?>
                    <small class="text-muted"><i class="fas fa-handshake mr-1"></i><a href="<?= pageUrl('clients', 'detail') ?>&id=<?= (int) $editProject['client_id'] ?>"><?= e($editProject['source_name']) ?></a></small>
                <?php else: ?>
                    <small class="text-muted"><i class="fas fa-handshake mr-1"></i><?= e($editProject['source_name']) ?></small>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php if ($editProject['db_name']): ?>
            <span class="badge badge-pill badge-info ml-2" style="font-size:.78rem;padding:.35em .75em"><i class="fas fa-database mr-1"></i><?= e($editProject['db_name']) ?></span>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-project-diagram mr-1"></i>Won Projects</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th class="text-center">DB</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $pj): ?>
                            <tr class="<?= $editProject && (int) $editProject['id'] === (int) $pj['id'] ? 'table-primary' : '' ?>">
                                <td>
                                    <a href="<?= pageUrl('leads', 'client_permissions') ?>&id=<?= (int) $pj['id'] ?>"><?= e($pj['title']) ?></a>
                                    <?php if ($pj['catalog_name']): ?><small class="d-block text-muted"><i class="fas fa-box mr-1"></i><?= e($pj['catalog_name']) ?></small><?php endif; ?>
                                    <?php if ($pj['source_name']): ?><small class="d-block text-muted"><i class="fas fa-handshake mr-1"></i><?= e($pj['source_name']) ?></small><?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($pj['db_name']): ?>
                                        <span class="badge badge-info" title="<?= e($pj['db_name']) ?>"><i class="fas fa-database"></i></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$projects): ?>
                            <tr>
                                <td colspan="2" class="text-center text-muted py-3">No won projects yet — they are created when a lead is Won.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <?php if (!$editProject): ?>
            <div class="callout callout-info">
                <h5>Select a won project</h5>
                <p>Pick a client project from the list to grant modules and assign its deployment database — the same model as staff module permissions.</p>
            </div>
        <?php else: ?>
            <?php if (!$canManage): ?>
                <div class="callout callout-warning">
                    <h5>Read only</h5>
                    <p>You need the <b>manage_leads</b> permission to change client access grants.</p>
                </div>
            <?php endif; ?>

            <form action="operation.php?module=leads&page=client_permissions" method="post" id="clientAccessForm">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= (int) $editProject['id'] ?>">
                <div class="card card-outline">
                    <div class="card-header">
                        <h3 class="card-title">
                            Client Access — <?= e($editProject['title']) ?>
                            <?php if ($editProject['source_name']): ?><small class="text-muted">· <?= e($editProject['source_name']) ?></small><?php endif; ?>
                        </h3>
                        <?php if ($canManage): ?>
                            <button type="submit" class="btn btn-primary btn-sm float-right"><i class="fas fa-save mr-1"></i>Save Grants</button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="clientDbName">Client database (deployment)</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-database"></i></span></div>
                                <input type="text" name="db_name" id="clientDbName" class="form-control"
                                    value="<?= e($editProject['db_name'] ?? '') ?>"
                                    placeholder="customer DB name (module console)"
                                    <?= $canManage ? '' : 'disabled' ?>>
                            </div>
                            <small class="form-text text-muted">The database this won project is entitled to out of the box. Every module shares it.</small>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-shield-alt mr-1"></i>Permitted modules &amp; submodules
                                <small class="text-muted">(catalog: <code><?= e($catalogDb) ?></code>)</small></label>
                            <small class="form-text text-muted">Checkboxes mirror the deployment database's <code>is_active</code> flags — ticking modules here switches them on/off in the client database.</small>
                            <?php if (!$catalog): ?>
                                <div class="callout callout-warning">
                                    <h5>No module catalog</h5>
                                    <p>Run the <code>create_table_cms_modules</code> migration in the deployment database (<code><?= e($catalogDb) ?></code>) first.</p>
                                </div>
                            <?php endif; ?>
                            <?php foreach ($grouped as $section => $sectionModules): ?>
                                <h6 class="text-uppercase font-weight-bold text-muted mt-2 mb-1"><i class="fas fa-layer-group mr-1"></i><?= e($section) ?></h6>
                                <?php foreach ($sectionModules as $m): ?>
                                    <?php if ($m['key'] === 'dashboard') {
                                        continue;
                                    } ?>
                                    <div class="border rounded p-2 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input module-check" type="checkbox" name="modules[]" value="<?= e($m['key']) ?>" id="mod_<?= e($m['key']) ?>"
                                                <?= !empty($m['is_active']) ? 'checked' : '' ?>
                                                <?= $canManage ? '' : 'disabled' ?>>
                                            <label class="form-check-label font-weight-bold" for="mod_<?= e($m['key']) ?>">
                                                <i class="<?= e($m['icon']) ?> mr-1"></i><?= e($m['name']) ?>
                                            </label>
                                        </div>
                                        <?php if ($m['subs']): ?>
                                            <div class="pl-4 mt-1">
                                                <?php foreach ($m['subs'] as $subKey => $subLabel): ?>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="checkbox" name="submodules[<?= e($m['key']) ?>][]" value="<?= e($subKey) ?>"
                                                            id="sub_<?= e($m['key']) ?>_<?= e($subKey) ?>"
                                                            <?= !empty($m['subs_active'][$subKey]) ? 'checked' : '' ?>
                                                            <?= $canManage ? '' : 'disabled' ?>>
                                                        <label class="form-check-label" for="sub_<?= e($m['key']) ?>_<?= e($subKey) ?>"><?= e($subLabel) ?></label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>