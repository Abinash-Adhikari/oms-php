<?php
/**
 * SB-Tech — Client detail page (separate view for a single client).
 * Shows the client's overview, contact persons, won projects and leads.
 * Rendered at ?module=clients&page=detail&id=N.
 */
$db = Database::instance();
$canManage = Auth::isSuperAdmin() || Auth::hasSpecial('manage_leads');

$stageBadges = ['New' => 'primary', 'Contacted' => 'info', 'Qualified' => 'warning', 'Proposal' => 'secondary', 'Won' => 'success', 'Lost' => 'danger'];
$priorityColors = ['Hot' => 'danger', 'Warm' => 'warning', 'Cold' => 'secondary'];
$clientProjectStatusBadge = ['Active' => 'success', 'Completed' => 'info', 'On Hold' => 'warning', 'Cancelled' => 'secondary'];

$id = (int) ($_GET['id'] ?? 0);
$edit = $id ? $db->selectOne('SELECT * FROM `tbl_clients` WHERE `id` = ?', [$id]) : null;
if (!$edit) {
    echo '<div class="callout callout-warning"><h5>Client not found</h5><p>No client exists with that ID.</p></div>';
    return;
}

$contacts = $db->select(
    'SELECT * FROM `tbl_client_contacts`
     WHERE `client_id` = ? ORDER BY `is_primary` DESC, `id` ASC',
    [(int) $edit['id']]
);
$wonProjects = $db->select(
    'SELECT cp.*, p.name AS catalog_name
     FROM `tbl_client_projects` cp
     LEFT JOIN `tbl_projects` p ON p.id = cp.project_id
     WHERE cp.client_id = ? ORDER BY cp.start_date DESC',
    [(int) $edit['id']]
);
$sourceLeads = $db->select(
    'SELECT l.*, o.fullname AS owner_name
     FROM `tbl_leads` l
     LEFT JOIN `tbl_users_login` o ON o.id = l.assigned_to
     WHERE l.client_id = ? OR l.won_client_id = ? ORDER BY l.added_on DESC',
    [(int) $edit['id'], (int) $edit['id']]
);
$quotations = $db->select(
    'SELECT q.*, u.fullname AS created_by_name
     FROM `tbl_quotations` q
     LEFT JOIN `tbl_users_login` u ON u.id = q.added_by
     WHERE q.client_id = ? ORDER BY q.added_on DESC',
    [(int) $edit['id']]
);
$leadIdsForActivity = [];
foreach ($sourceLeads as $_l) { $leadIdsForActivity[] = (int) $_l['id']; }
$clientActivities = [];
if ($leadIdsForActivity) {
    $in = rtrim(str_repeat('?,', count($leadIdsForActivity)), ',');
    $clientActivities = $db->select(
        'SELECT a.*, u.fullname AS actor, l.company AS lead_title
         FROM `tbl_lead_activities` a
         LEFT JOIN `tbl_users_login` u ON u.id = a.added_by
         LEFT JOIN `tbl_leads` l ON l.id = a.lead_id
         WHERE a.lead_id IN (' . $in . ')
         ORDER BY a.added_on DESC LIMIT 25',
        $leadIdsForActivity
    );
}

// Snapshot for client-side drawer prefill.
$sourcesJson = [[
    'id'             => (int) $edit['id'],
    'type'           => $edit['type'],
    'name'           => $edit['name'],
    'contact_person' => $edit['contact_person'],
    'email'          => $edit['email'],
    'phone'          => $edit['phone'],
    'address'        => $edit['address'],
    'pan_num'        => $edit['pan_num'],
    'notes'          => $edit['notes'],
]];
$contactsJson = [];
foreach ($contacts as $t) {
    $contactsJson[] = [
        'id'                => (int) $t['id'],
        'name'              => $t['name'],
        'designation'       => $t['designation'],
        'email'             => $t['email'],
        'phone'             => $t['phone'],
        'is_primary'        => (int) $t['is_primary'],
        'notes'             => $t['notes'],
    ];
}
$wonProjectsJson = [];
foreach ($wonProjects as $p) {
    $wonProjectsJson[] = [
        'id'            => (int) $p['id'],
        'client_id'     => (int) $p['client_id'],
        'project_id'    => $p['project_id'] ? (int) $p['project_id'] : '',
        'title'         => $p['title'],
        'package'       => $p['package'],
        'db_name'       => $p['db_name'],
        'value'         => $p['value'] !== null ? (float) $p['value'] : '',
        'status'        => $p['status'],
        'start_date'    => $p['start_date'],
        'end_date'      => $p['end_date'],
        'description'   => $p['description'],
    ];
}
$catalogJson = [];
$catalogList = $db->select('SELECT `id`, `name` FROM `tbl_projects` WHERE `status` = ? ORDER BY `name` ASC', ['Active']);
foreach ($catalogList as $c) {
    $catalogJson[] = ['id' => (int) $c['id'], 'name' => $c['name']];
}

$totalValue = 0.0;
$activeProjects = 0;
foreach ($wonProjects as $_p) {
    if ($_p['value'] !== null) { $totalValue += (float) $_p['value']; }
    if ($_p['status'] === 'Active') { $activeProjects++; }
}
$initials = '';
foreach (preg_split('/\s+/', trim($edit['name'] ?? '')) as $_w) {
    if ($_w !== '' && mb_strlen($initials) < 2) { $initials .= mb_strtoupper(mb_substr($_w, 0, 1)); }
}
$initials = $initials !== '' ? $initials : '?';
$creator = null;
if ($edit['added_by']) {
    $creator = $db->selectOne('SELECT fullname FROM `tbl_users_login` WHERE `id` = ?', [(int) $edit['added_by']]);
}
$originLead = null;
if ($edit['lead_id']) {
    $originLead = $db->selectOne('SELECT `id`, `company`, `contact_name` FROM `tbl_leads` WHERE `id` = ?', [(int) $edit['lead_id']]);
}
?>
<!-- Page header -->
<nav aria-label="breadcrumb" class="mb-2">
    <ol class="breadcrumb bg-transparent p-0 mb-0 small">
        <li class="breadcrumb-item"><a href="<?= pageUrl('clients', 'clients') ?>">Clients</a></li>
        <li class="breadcrumb-item active"><?= e($edit['name']) ?></li>
    </ol>
</nav>
<div class="d-flex align-items-center mb-3">
    <a href="<?= pageUrl('clients', 'clients') ?>" class="btn btn-sm btn-outline-secondary mr-3" aria-label="Back to clients"><i class="fas fa-arrow-left"></i></a>
    <div class="d-flex align-items-center justify-content-center font-weight-bold mr-3" style="width:52px;height:52px;border-radius:50%;background:var(--cms-accent-soft, rgba(59,130,246,.12));color:var(--cms-accent, #2563eb);font-size:1.25rem;font-family:'Poppins',sans-serif"><?= e($initials) ?></div>
    <div class="flex-grow-1">
        <div class="d-flex align-items-center flex-wrap">
            <h3 class="mb-0 font-weight-bold" style="font-family:'Poppins',sans-serif"><?= e($edit['name']) ?></h3>
            <span class="badge badge-pill badge-<?= $edit['type'] === 'Individual' ? 'info' : 'secondary' ?> ml-2" style="font-size:.78rem;padding:.35em .75em">
                <i class="fas fa-<?= $edit['type'] === 'Individual' ? 'user' : 'building' ?> mr-1"></i><?= e($edit['type']) ?>
            </span>
        </div>
        <small class="text-muted"><?= e($edit['contact_person'] ?: '—') ?><?= $edit['email'] ? ' · <a href="mailto:' . e($edit['email']) . '">' . e($edit['email']) . '</a>' : '' ?><?= $edit['phone'] ? ' · <a href="tel:' . e($edit['phone']) . '">' . e($edit['phone']) . '</a>' : '' ?></small>
    </div>
    <?php if ($canManage): ?>
        <div class="ml-auto d-flex align-items-center">
            <button type="button" class="btn btn-sm btn-outline-primary mr-2" onclick="openSourceDrawer(<?= (int) $edit['id'] ?>)"><i class="fas fa-edit mr-1"></i>Edit Client</button>
            <button type="button" class="btn btn-sm btn-primary mr-2" onclick="openContactDrawer()"><i class="fas fa-user-plus mr-1"></i>Add Contact</button>
            <form action="operation.php?module=clients&page=clients" method="post" class="d-inline ml-1">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete_client">
                <input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger confirm-submit" data-confirm="Delete client <?= e($edit['name']) ?> and all related data?"><i class="fas fa-trash mr-1"></i>Delete</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<!-- KPI strip -->
<div class="row mb-3">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner"><h3><?= count($contacts) ?></h3><p>Contact Persons</p></div>
            <div class="icon"><i class="fas fa-id-card"></i></div>
            <a href="#client-contacts" class="small-box-footer">View <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner"><h3><?= count($wonProjects) ?><sup style="font-size:14px">/ <?= $activeProjects ?> active</sup></h3><p>Won Projects</p></div>
            <div class="icon"><i class="fas fa-project-diagram"></i></div>
            <a href="#client-projects" class="small-box-footer">View <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-primary">
            <div class="inner"><h3>NRs. <?= e(formatMoney($totalValue)) ?></h3><p>Total Project Value</p></div>
            <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
            <a href="#client-projects" class="small-box-footer">Details <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner"><h3><?= count($sourceLeads) ?></h3><p>Linked Leads</p></div>
            <div class="icon"><i class="fas fa-link"></i></div>
            <a href="#client-leads" class="small-box-footer">View <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <!-- Client Overview -->
        <div class="card card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-info-circle mr-1"></i>Client Overview</h3>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><th class="text-muted" style="width:32%">Primary Contact</th><td class="font-weight-bold"><?= e($edit['contact_person'] ?: '—') ?></td></tr>
                    <tr><th class="text-muted">Email</th><td><?= $edit['email'] ? '<a href="mailto:' . e($edit['email']) . '">' . e($edit['email']) . '</a>' : '—' ?></td></tr>
                    <tr><th class="text-muted">Phone</th><td><?= $edit['phone'] ? '<a href="tel:' . e($edit['phone']) . '">' . e($edit['phone']) . '</a>' : '—' ?></td></tr>
                    <tr><th class="text-muted">Address</th><td><?= e($edit['address'] ?: '—') ?></td></tr>
                    <tr><th class="text-muted">PAN No</th><td><?= $edit['pan_num'] ? '<code>' . e($edit['pan_num']) . '</code>' : '—' ?></td></tr>
                    <?php if ($originLead): ?>
                        <tr><th class="text-muted">Origin Lead</th><td><a href="<?= pageUrl('leads', 'leads') ?>&id=<?= (int) $originLead['id'] ?>"><i class="fas fa-external-link-alt mr-1"></i><?= e($originLead['company'] ?: $originLead['contact_name']) ?></a></td></tr>
                    <?php endif; ?>
                    <tr><th class="text-muted">Client Since</th><td><?= e(date('M j, Y', strtotime($edit['added_on']))) ?><?= $creator ? ' · by ' . e($creator['fullname']) : '' ?></td></tr>
                    <?php if ($edit['updated_on'] && $edit['updated_on'] !== $edit['added_on']): ?>
                        <tr><th class="text-muted">Last Updated</th><td><?= e(date('M j, g:i A', strtotime($edit['updated_on']))) ?></td></tr>
                    <?php endif; ?>
                </table>
                <?php if ($edit['notes']): ?>
                    <hr class="my-2">
                    <p class="mb-0 small"><strong>Notes:</strong><br><?= nl2br(e($edit['notes'])) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Contact Persons -->
        <div class="card card-outline" id="client-contacts">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-id-card mr-1"></i>Contact Persons — <?= e($edit['name']) ?></h3>
                <div class="card-tools">
                    <?php if ($canManage): ?>
                        <button type="button" class="btn btn-primary btn-sm" onclick="openContactDrawer()"><i class="fas fa-plus mr-1"></i>Add Contact</button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead><tr><th>#</th><th>Name</th><th>Designation</th><th>Email / Phone</th><th>Primary</th><th class="text-right">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($contacts as $i => $t): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td class="font-weight-bold"><?= e($t['name']) ?><?= $t['is_primary'] ? ' <span class="badge badge-primary">Primary</span>' : '' ?></td>
                                <td><?= e($t['designation'] ?: '—') ?></td>
                                <td><?= e($t['email'] ?: '—') ?><?= $t['phone'] ? '<br><small>' . e($t['phone']) . '</small>' : '' ?></td>
                                <td>
                                    <?php if ($canManage && !$t['is_primary']): ?>
                                        <form action="operation.php?module=clients&page=clients" method="post" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="set_primary_contact">
                                            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                            <input type="hidden" name="client_id" value="<?= (int) $edit['id'] ?>">
                                            <button type="submit" class="btn btn-xs btn-outline-primary">Make Primary</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge badge-success"><i class="fas fa-check"></i></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <?php if ($canManage): ?>
                                        <button type="button" class="btn btn-xs btn-outline-secondary" title="Edit" onclick="openContactDrawer(<?= (int) $t['id'] ?>)"><i class="fas fa-edit"></i></button>
                                        <form action="operation.php?module=clients&page=clients" method="post" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete_contact">
                                            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                            <input type="hidden" name="client_id" value="<?= (int) $edit['id'] ?>">
                                            <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete contact <?= e($t['name']) ?>?"><i class="fas fa-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$contacts): ?><tr><td colspan="6" class="text-center text-muted">No contacts yet. The first contact added becomes the primary contact.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Won Projects (detail) -->
        <div class="card card-outline" id="client-projects">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-project-diagram mr-1"></i>Won Projects — <?= e($edit['name']) ?></h3>
                <div class="card-tools">
                    <a href="<?= pageUrl('leads', 'client_projects') ?>&source=<?= (int) $edit['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-external-link-alt mr-1"></i>Open Client Projects</a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead><tr><th>#</th><th>Project</th><th>Catalog</th><th>DB Name</th><th>Value</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($wonProjects as $i => $p): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td class="font-weight-bold"><?= e($p['title']) ?></td>
                                <td><?= $p['catalog_name'] ? e($p['catalog_name']) : '—' ?></td>
                                <td><?= $p['db_name'] ? '<code>' . e($p['db_name']) . '</code>' : '—' ?></td>
                                <td><?= $p['value'] !== null ? 'NPR ' . e(formatMoney($p['value'])) : '—' ?></td>
                                <td><span class="badge badge-<?= $clientProjectStatusBadge[$p['status']] ?? 'secondary' ?>"><?= e($p['status']) ?></span></td>
                                <td class="text-right">
                                    <?php if ($canManage): ?>
                                        <button type="button" class="btn btn-xs btn-outline-secondary" title="Edit" onclick="openClientProjectDrawer(<?= (int) $p['id'] ?>)"><i class="fas fa-edit"></i></button>
                                        <form action="operation.php?module=leads&page=client_projects" method="post" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete_client_project">
                                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                            <input type="hidden" name="from_client_id" value="<?= (int) $edit['id'] ?>">
                                            <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete project <?= e($p['title']) ?>?"><i class="fas fa-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                    <a href="<?= pageUrl('leads', 'client_permissions') ?>&id=<?= (int) $p['id'] ?>" class="btn btn-xs btn-outline-secondary" title="Client Access / Modules & DB"><i class="fas fa-user-lock"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$wonProjects): ?><tr><td colspan="7" class="text-center text-muted">This client has no won projects yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Linked Leads (detail) -->
        <div class="card card-outline" id="client-leads">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-link mr-1"></i>Linked Leads — <?= e($edit['name']) ?></h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead><tr><th>#</th><th>Company / Contact</th><th>Service</th><th>Stage</th><th>Priority</th><th>Owner</th><th>Added</th><th class="text-right">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($sourceLeads as $i => $l): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= e($l['company'] ?: $l['contact_name']) ?><br><small class="text-muted"><?= e($l['contact_name'] ?: '—') ?> · <?= e($l['email'] ?: '—') ?></small></td>
                                <td><?= e($l['service_interest'] ?: '—') ?></td>
                                <td><span class="badge badge-<?= $stageBadges[$l['stage']] ?? 'secondary' ?>"><?= e($l['stage']) ?></span></td>
                                <td><span class="badge badge-<?= $priorityColors[$l['priority']] ?? 'secondary' ?>"><?= e($l['priority']) ?></span></td>
                                <td><?= e($l['owner_name'] ?: '—') ?></td>
                                <td><?= e(date('M j, Y', strtotime($l['added_on']))) ?></td>
                                <td class="text-right">
                                    <a href="<?= pageUrl('leads', 'leads') ?>&id=<?= (int) $l['id'] ?>" class="btn btn-xs btn-outline-primary"><i class="fas fa-eye"></i></a>
                                    <?php if ($canManage): ?>
                                        <form action="operation.php?module=leads&page=leads" method="post" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete_lead">
                                            <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                                            <input type="hidden" name="from_client_id" value="<?= (int) $edit['id'] ?>">
                                            <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" title="Delete" data-confirm="Delete this lead? Files and activities are removed."><i class="fas fa-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <!-- Quotations -->
        <div class="card card-outline" id="client-quotations">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-file-invoice mr-1"></i>Quotations — <?= e($edit['name']) ?></h3>
                <div class="card-tools">
                    <a href="<?= pageUrl('leads', 'quotations') ?>&add=1&client_id=<?= (int) $edit['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-plus mr-1"></i>New Quotation</a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead><tr><th>#</th><th>Quotation #</th><th>Subject</th><th>Date</th><th class="text-right">Total</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                        <tbody>
                        <?php $statusBadge = ['Draft' => 'secondary', 'Sent' => 'info', 'Accepted' => 'success', 'Rejected' => 'danger', 'Expired' => 'warning']; ?>
                        <?php foreach ($quotations as $i => $q): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= e($q['quotation_number']) ?></td>
                                <td><?= e(mb_strimwidth($q['subject'] ?: '', 0, 40, '…')) ?></td>
                                <td><?= e($q['quotation_date']) ?></td>
                                <td class="text-right font-weight-bold">NPR <?= e(formatMoney($q['total'])) ?></td>
                                <td><span class="badge badge-<?= $statusBadge[$q['status']] ?? 'secondary' ?>"><?= e($q['status']) ?></span></td>
                                <td class="text-right">
                                    <a href="<?= pageUrl('leads', 'quotations') ?>&id=<?= (int) $q['id'] ?>" class="btn btn-xs btn-outline-primary" title="View"><i class="fas fa-eye"></i></a>
                                    <a href="<?= pageUrl('leads', 'quotations') ?>&id=<?= (int) $q['id'] ?>&pdf=1" class="btn btn-xs btn-outline-danger" title="PDF"><i class="fas fa-download"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$quotations): ?><tr><td colspan="7" class="text-center text-muted">No quotations for this client yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Client Activity Timeline (aggregated across linked leads) -->
        <div class="card card-outline" id="client-activity">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-stream mr-1"></i>Activity Timeline — <?= e($edit['name']) ?></h3>
                <span class="badge badge-light"><?= count($clientActivities) ?></span>
            </div>
            <div class="card-body p-0">
                <?php if ($clientActivities): ?>
                    <div class="p-3">
                        <?php foreach ($clientActivities as $a): ?>
                            <div class="d-flex mb-3">
                                <div class="mr-3">
                                    <?php $tc = ['Call' => 'primary', 'Email' => 'info', 'Meeting' => 'warning', 'Note' => 'secondary', 'Status Change' => 'success', 'Task' => 'danger']; ?>
                                    <span class="badge badge-<?= $tc[$a['type']] ?? 'light' ?>" style="min-width:56px;justify-content:center"><?= e($a['type']) ?></span>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between">
                                        <span class="font-weight-bold small"><?= e($a['actor'] ?? '—') ?><?= $a['lead_title'] ? ' · ' . e($a['lead_title']) : '' ?></span>
                                        <small class="text-muted"><?= e(date('M j, g:i A', strtotime($a['added_on']))) ?></small>
                                    </div>
                                    <?php if ($a['note']): ?><div class="text-muted small mt-1"><?= nl2br(e($a['note'])) ?></div><?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-4"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>No activity yet across this client's leads.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <!-- Quick Actions -->
        <div class="card card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-bolt mr-1"></i>Quick Actions</h3>
            </div>
            <div class="card-body">
                <?php if ($canManage): ?>
                    <button type="button" class="btn btn-outline-primary btn-sm btn-block mb-2" onclick="openSourceDrawer(<?= (int) $edit['id'] ?>)"><i class="fas fa-edit mr-1"></i>Edit Client</button>
                    <button type="button" class="btn btn-outline-primary btn-sm btn-block mb-2" onclick="openContactDrawer()"><i class="fas fa-user-plus mr-1"></i>Add Contact Person</button>
                    <form action="operation.php?module=clients&page=clients" method="post">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_client">
                        <input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
                        <button type="submit" class="btn btn-outline-danger btn-sm btn-block confirm-submit" data-confirm="Delete client <?= e($edit['name']) ?> and all related data?"><i class="fas fa-trash mr-1"></i>Delete Client</button>
                    </form>
                <?php endif; ?>
                <a href="<?= pageUrl('leads', 'client_projects') ?>&source=<?= (int) $edit['id'] ?>" class="btn btn-outline-secondary btn-sm btn-block mb-2"><i class="fas fa-folder-open mr-1"></i>Open Client Projects</a>
                <a href="<?= pageUrl('leads', 'quotations') ?>&add=1&client_id=<?= (int) $edit['id'] ?>" class="btn btn-outline-secondary btn-sm btn-block mb-2"><i class="fas fa-file-invoice mr-1"></i>New Quotation</a>
                <a href="<?= pageUrl('leads', 'leads') ?>" class="btn btn-outline-secondary btn-sm btn-block"><i class="fas fa-search mr-1"></i>Browse All Leads</a>
            </div>
        </div>
    </div>
</div>

<!-- ═══ Project Drawer (detail page) ═══ -->
<?php if ($canManage): ?>
<div class="cms-drawer" id="clientProjectDrawer">
    <div class="cms-drawer-header">
        <h3><i class="fas fa-project-diagram"></i><span id="cpDetailDrawerTitle">Add Client Project</span></h3>
        <button type="button" class="cms-drawer-close" onclick="closeAllDrawers()" aria-label="Close"><i class="fas fa-times"></i></button>
    </div>
    <div class="cms-drawer-body">
        <form action="operation.php?module=leads&page=client_projects" method="post" id="cpDetailForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_client_project">
            <input type="hidden" name="id" id="cpDetailId" value="0">
            <input type="hidden" name="client_id" id="cpDetailClientId" value="<?= (int) $edit['id'] ?>">
            <input type="hidden" name="from_client_id" value="<?= (int) $edit['id'] ?>">

            <h6 class="text-muted text-uppercase mb-2" style="font-size:.7rem;letter-spacing:.05em">Project Details</h6>
            <div class="row">
                <div class="col-6 form-group">
                    <label class="small font-weight-bold">Catalog project</label>
                    <select name="project_id" id="cpDetailCatalog" class="form-control form-control-sm">
                        <option value="">— None —</option>
                        <?php foreach ($catalogJson as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 form-group">
                    <label class="small font-weight-bold">Title *</label>
                    <input type="text" name="title" id="cpDetailTitle" class="form-control form-control-sm" required>
                </div>
            </div>
            <div class="row">
                <div class="col-6 form-group">
                    <label class="small font-weight-bold">Package</label>
                    <input type="text" name="package" id="cpDetailPackage" class="form-control form-control-sm">
                </div>
                <div class="col-6 form-group">
                    <label class="small font-weight-bold">Database</label>
                    <input type="text" name="db_name" id="cpDetailDb" class="form-control form-control-sm" placeholder="customer DB (Client Access)">
                </div>
            </div>

            <hr class="my-3">
            <h6 class="text-muted text-uppercase mb-2" style="font-size:.7rem;letter-spacing:.05em">Financials & Dates</h6>
            <div class="row">
                <div class="col-6 form-group">
                    <label class="small font-weight-bold">Value (NPR)</label>
                    <input type="number" name="value" id="cpDetailValue" class="form-control form-control-sm" step="0.01" min="0" placeholder="0.00">
                </div>
                <div class="col-6 form-group">
                    <label class="small font-weight-bold">Status</label>
                    <select name="status" id="cpDetailStatus" class="form-control form-control-sm">
                        <?php foreach (['Active', 'Completed', 'On Hold', 'Cancelled'] as $s): ?>
                            <option value="<?= $s ?>"><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-6 form-group">
                    <label class="small font-weight-bold">Start</label>
                    <input type="date" name="start_date" id="cpDetailStart" class="form-control form-control-sm">
                </div>
                <div class="col-6 form-group">
                    <label class="small font-weight-bold">End</label>
                    <input type="date" name="end_date" id="cpDetailEnd" class="form-control form-control-sm">
                </div>
            </div>
            <div class="form-group">
                <label class="small font-weight-bold">Description</label>
                <textarea name="description" id="cpDetailDescription" class="form-control form-control-sm" rows="2"></textarea>
            </div>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="cpDetailForm" class="btn btn-primary btn-block"><i class="fas fa-save mr-1"></i><span id="cpDetailSubmitText">Add Client Project</span></button>
    </div>
</div>
<?php endif; ?>

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
            <input type="hidden" name="id" id="sourceId" value="<?= (int) $edit['id'] ?>">
            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name" id="sourceName" class="form-control" required value="<?= e($edit['name']) ?>">
            </div>
            <div class="form-group">
                <label>Type</label>
                <select name="type" id="sourceType" class="form-control">
                    <?php foreach (['Company', 'Individual'] as $t): ?>
                        <option value="<?= $t ?>" <?= $edit['type'] === $t ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Primary contact person</label>
                <input type="text" name="contact_person" id="sourceContact" class="form-control" value="<?= e($edit['contact_person']) ?>">
                <small class="form-text text-muted">Mirrors the primary Contact Person entry below.</small>
            </div>
            <div class="row">
                <div class="col-6 form-group"><label>Email</label><input type="email" name="email" id="sourceEmail" class="form-control" value="<?= e($edit['email']) ?>"></div>
                <div class="col-6 form-group"><label>Phone</label><input type="text" name="phone" id="sourcePhone" class="form-control" value="<?= e($edit['phone']) ?>"></div>
            </div>
            <div class="form-group"><label>Address</label><input type="text" name="address" id="sourceAddress" class="form-control" value="<?= e($edit['address']) ?>"></div>
            <div class="form-group"><label>PAN no</label><input type="text" name="pan_num" id="sourcePan" class="form-control" value="<?= e($edit['pan_num']) ?>"></div>
            <div class="form-group"><label>Notes</label><textarea name="notes" id="sourceNotes" class="form-control" rows="2"><?= e($edit['notes']) ?></textarea></div>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="sourceForm" class="btn btn-primary btn-block"><i class="fas fa-save mr-1"></i><span id="sourceDrawerSubmit">Add Client</span></button>
    </div>
</div>

<!-- Contact Drawer -->
<?php if ($canManage): ?>
<div class="cms-drawer" id="contactDrawer">
    <div class="cms-drawer-header">
        <h3><i class="fas fa-id-card"></i><span id="contactDrawerTitle">Add Contact</span></h3>
        <button type="button" class="cms-drawer-close" onclick="closeAllDrawers()" aria-label="Close"><i class="fas fa-times"></i></button>
    </div>
    <div class="cms-drawer-body">
        <form action="operation.php?module=clients&page=clients" method="post" id="contactForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_contact">
            <input type="hidden" name="id" id="contactId" value="0">
            <input type="hidden" name="client_id" id="contactSourceId" value="<?= (int) $edit['id'] ?>">
            <div class="form-group"><label>Name *</label><input type="text" name="name" id="contactName" class="form-control" required></div>
            <div class="form-group"><label>Designation</label><input type="text" name="designation" id="contactDesignation" class="form-control"></div>
            <div class="row">
                <div class="col-6 form-group"><label>Email</label><input type="email" name="email" id="contactEmail" class="form-control"></div>
                <div class="col-6 form-group"><label>Phone</label><input type="text" name="phone" id="contactPhone" class="form-control"></div>
            </div>
            <div class="form-group"><label>Notes</label><textarea name="notes" id="contactNotes" class="form-control" rows="2"></textarea></div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_primary" id="contactPrimary" value="1">
                <label class="form-check-label" for="contactPrimary">Set as primary contact</label>
            </div>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="contactForm" class="btn btn-primary btn-block"><i class="fas fa-save mr-1"></i><span id="contactDrawerSubmit">Add Contact</span></button>
    </div>
</div>
<?php endif; ?>

<script>
var sourcesData = <?= json_encode($sourcesJson) ?>;
var contactsData = <?= json_encode($contactsJson) ?>;
var wonProjectsData = <?= json_encode($wonProjectsJson) ?>;
var currentClientId = <?= (int) $edit['id'] ?>;

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
            if (sourcesData[i].id == sourceId) { source = sourcesData[i]; break; }
        }
    }
    fillSourceForm(source);
    document.getElementById('sourceDrawerTitle').textContent = source ? 'Edit Client' : 'Add Client';
    document.getElementById('sourceDrawerSubmit').textContent = source ? 'Update Client' : 'Add Client';
}

/* ═══ Contact Drawer ═══ */
function fillContactForm(contact) {
    document.getElementById('contactId').value = contact ? contact.id : 0;
    document.getElementById('contactName').value = contact ? (contact.name || '') : '';
    document.getElementById('contactDesignation').value = contact ? (contact.designation || '') : '';
    document.getElementById('contactEmail').value = contact ? (contact.email || '') : '';
    document.getElementById('contactPhone').value = contact ? (contact.phone || '') : '';
    document.getElementById('contactNotes').value = contact ? (contact.notes || '') : '';
    document.getElementById('contactPrimary').checked = contact ? (contact.is_primary == 1) : false;
}

function openContactDrawer(contactId) {
    closeAllDrawers();
    document.getElementById('drawerBackdrop').classList.add('active');
    document.getElementById('contactDrawer').classList.add('open');
    document.body.style.overflow = 'hidden';

    var contact = null;
    if (contactId) {
        for (var i = 0; i < contactsData.length; i++) {
            if (contactsData[i].id == contactId) { contact = contactsData[i]; break; }
        }
    }
    fillContactForm(contact);
    document.getElementById('contactDrawerTitle').textContent = contact ? 'Edit Contact' : 'Add Contact';
    document.getElementById('contactDrawerSubmit').textContent = contact ? 'Update Contact' : 'Add Contact';
}

/* ═══ Client Project Drawer ═══ */
function openClientProjectDrawer(projectId) {
    closeAllDrawers();
    document.getElementById('drawerBackdrop').classList.add('active');
    document.getElementById('clientProjectDrawer').classList.add('open');
    document.body.style.overflow = 'hidden';

    var p = null;
    if (projectId) {
        for (var i = 0; i < wonProjectsData.length; i++) {
            if (wonProjectsData[i].id == projectId) { p = wonProjectsData[i]; break; }
        }
    }
    document.getElementById('cpDetailId').value = p ? p.id : 0;
    document.getElementById('cpDetailClientId').value = currentClientId;
    document.getElementById('cpDetailCatalog').value = p ? (p.project_id || '') : '';
    document.getElementById('cpDetailTitle').value = p ? (p.title || '') : '';
    document.getElementById('cpDetailPackage').value = p ? (p.package || '') : '';
    document.getElementById('cpDetailDb').value = p ? (p.db_name || '') : '';
    document.getElementById('cpDetailValue').value = p ? (p.value || '') : '';
    document.getElementById('cpDetailStatus').value = p ? (p.status || 'Active') : 'Active';
    document.getElementById('cpDetailStart').value = p ? (p.start_date || '') : '';
    document.getElementById('cpDetailEnd').value = p ? (p.end_date || '') : '';
    document.getElementById('cpDetailDescription').value = p ? (p.description || '') : '';
    document.getElementById('cpDetailDrawerTitle').textContent = p ? 'Edit Client Project' : 'Add Client Project';
    document.getElementById('cpDetailSubmitText').textContent = p ? 'Update Client Project' : 'Add Client Project';
}

/* ═══ Shared Drawer Controls ═══ */
function closeAllDrawers() {
    document.querySelectorAll('.cms-drawer').forEach(function(d) { d.classList.remove('open'); });
    var bd = document.getElementById('drawerBackdrop');
    if (bd) bd.classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeAllDrawers();
});
</script>