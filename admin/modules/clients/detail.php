<?php

/**
 * SB-Tech — Client detail page (separate view for a single client).
 * Shows the client's overview, contact persons, won projects and leads.
 * Rendered at ?module=clients&page=detail&id=N.
 */
$db = Database::instance();
$canManage = Auth::isSuperAdmin() || Auth::hasSpecial('manage_leads');

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
foreach ($sourceLeads as $_l) {
    $leadIdsForActivity[] = (int) $_l['id'];
}
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
        'url'           => $p['url'],
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
    if ($_p['value'] !== null) {
        $totalValue += (float) $_p['value'];
    }
    if ($_p['status'] === 'Active') {
        $activeProjects++;
    }
}
$initials = '';
foreach (preg_split('/\s+/', trim($edit['name'] ?? '')) as $_w) {
    if ($_w !== '' && mb_strlen($initials) < 2) {
        $initials .= mb_strtoupper(mb_substr($_w, 0, 1));
    }
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
<div id="cl-detail">
    <!-- Page header -->
    <nav aria-label="breadcrumb" class="mb-2">
        <ol class="breadcrumb bg-transparent p-0 mb-0 small">
            <li class="breadcrumb-item"><a href="<?= pageUrl('clients', 'clients') ?>">Clients</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($edit['name']) ?></li>
        </ol>
    </nav>

    <!-- Hero banner -->
    <div class="cl-hero mb-3">
        <div class="cl-hero-glow cl-hero-glow--a"></div>
        <div class="cl-hero-glow cl-hero-glow--b"></div>
        <div class="cl-hero-inner">
            <a href="<?= pageUrl('clients', 'clients') ?>" class="cl-hero-back" aria-label="Back to clients"><i class="fas fa-arrow-left" aria-hidden="true"></i></a>
            <span class="cl-avatar" aria-hidden="true"><?= e($initials) ?></span>
            <div class="cl-hero-meta">
                <div class="cl-hero-title-row">
                    <h2 class="cl-hero-name"><?= e($edit['name']) ?></h2>
                    <span class="badge-soft badge-soft--light"><i class="fas fa-<?= $edit['type'] === 'Individual' ? 'user' : 'building' ?> mr-1" aria-hidden="true"></i><?= e($edit['type']) ?></span>
                    <?php if ($originLead): ?>
                        <a href="<?= pageUrl('leads', 'leads') ?>&id=<?= (int) $originLead['id'] ?>" class="badge-soft badge-soft--light text-decoration-none" title="Origin lead"><i class="fas fa-funnel-dollar mr-1" aria-hidden="true"></i><?= e($originLead['company'] ?: $originLead['contact_name']) ?></a>
                    <?php endif; ?>
                </div>
                <div class="cl-hero-contact">
                    <i class="fas fa-user-tie mr-1" aria-hidden="true"></i><?= e($edit['contact_person'] ?: '—') ?>
                    <?php if ($edit['email']): ?> · <a href="mailto:<?= e($edit['email']) ?>"><i class="fas fa-envelope mr-1" aria-hidden="true"></i><?= e($edit['email']) ?></a><?php endif; ?>
                    <?php if ($edit['phone']): ?> · <a href="tel:<?= e($edit['phone']) ?>"><i class="fas fa-phone mr-1" aria-hidden="true"></i><?= e($edit['phone']) ?></a><?php endif; ?>
                    <?php if ($edit['pan_num']): ?> · <code style="color:rgba(255,255,255,.9);background:rgba(255,255,255,.14);border-radius:4px;padding:0 .35rem">PAN <?= e($edit['pan_num']) ?></code><?php endif; ?>
                    <?php if ($edit['added_on']): ?> · <i class="fas fa-calendar-alt mr-1" aria-hidden="true"></i>Client since <?= e(date('M j, Y', strtotime($edit['added_on']))) ?><?php endif; ?>
                </div>
            </div>
            <div class="cl-hero-actions">
                <?php if ($canManage): ?>
                    <button type="button" class="btn btn-sm btn-ghost" onclick="openSourceDrawer(<?= (int) $edit['id'] ?>)"><i class="fas fa-edit mr-1" aria-hidden="true"></i>Edit Client</button>
                    <button type="button" class="btn btn-sm btn-ghost btn-soft-warn" onclick="openContactDrawer()"><i class="fas fa-user-plus mr-1" aria-hidden="true"></i>Add Contact</button>
                    <form action="operation.php?module=clients&page=clients" method="post" class="d-inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_client">
                        <input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger-ghost btn-icon-only confirm-submit" data-confirm="Delete client <?= e($edit['name']) ?> and all related data?" aria-label="Delete client"><i class="fas fa-trash" aria-hidden="true"></i></button>
                    </form>
                <?php endif; ?>
                <a href="<?= pageUrl('leads', 'quotations') ?>&add=1&client_id=<?= (int) $edit['id'] ?>" class="btn btn-sm btn-ghost"><i class="fas fa-file-invoice mr-1" aria-hidden="true"></i>New Quotation</a>
                <a href="<?= pageUrl('leads', 'client_projects') ?>&source=<?= (int) $edit['id'] ?>" class="btn btn-sm btn-ghost"><i class="fas fa-folder-open mr-1" aria-hidden="true"></i>Client Projects</a>
            </div>
        </div>
    </div>

    <!-- KPI strip -->
    <div class="tms-kpi-grid">
        <a href="#client-contacts" class="tms-kpi-card bg-info">
            <div class="tms-kpi-icon"><i class="fas fa-id-card" aria-hidden="true"></i></div>
            <div class="tms-kpi-meta">
                <p class="tms-kpi-label">Contact Persons</p>
                <p class="tms-kpi-value"><?= count($contacts) ?></p>
            </div>
        </a>
        <a href="#client-projects" class="tms-kpi-card bg-success">
            <div class="tms-kpi-icon"><i class="fas fa-project-diagram" aria-hidden="true"></i></div>
            <div class="tms-kpi-meta">
                <p class="tms-kpi-label">Won Projects</p>
                <p class="tms-kpi-value"><?= count($wonProjects) ?><small class="tms-kpi-sub"><?= $activeProjects ?> active</small></p>
            </div>
        </a>
        <a href="#client-projects" class="tms-kpi-card bg-primary">
            <div class="tms-kpi-icon"><i class="fas fa-money-bill-wave" aria-hidden="true"></i></div>
            <div class="tms-kpi-meta">
                <p class="tms-kpi-label">Total Project Value</p>
                <p class="tms-kpi-value">NRs. <?= e(formatMoney($totalValue)) ?></p>
            </div>
        </a>
        <a href="#client-leads" class="tms-kpi-card bg-warning">
            <div class="tms-kpi-icon"><i class="fas fa-link" aria-hidden="true"></i></div>
            <div class="tms-kpi-meta">
                <p class="tms-kpi-label">Linked Leads</p>
                <p class="tms-kpi-value"><?= count($sourceLeads) ?></p>
            </div>
        </a>
    </div>

    <div class="row">
        <div class="col-md-8">
            <!-- Client Overview -->
            <div class="card card-outline mb-3">
                <div class="card-header">
                    <h3 class="card-title"><span class="tms-chip tms-chip--info"><i class="fas fa-info-circle" aria-hidden="true"></i></span>Client Overview</h3>
                </div>
                <div class="card-body">
                    <div class="cl-facts">
                        <div class="cl-fact cl-fact--accent">
                            <span class="cl-fact-icon"><i class="fas fa-user-tie" aria-hidden="true"></i></span>
                            <div class="cl-fact-body">
                                <span class="cl-fact-label">Primary Contact</span>
                                <span class="cl-fact-value"><?= e($edit['contact_person'] ?: '—') ?></span>
                            </div>
                        </div>
                        <div class="cl-fact cl-fact--accent">
                            <span class="cl-fact-icon"><i class="fas fa-envelope" aria-hidden="true"></i></span>
                            <div class="cl-fact-body">
                                <span class="cl-fact-label">Email</span>
                                <span class="cl-fact-value"><?= $edit['email'] ? '<a href="mailto:' . e($edit['email']) . '">' . e($edit['email']) . '</a>' : '—' ?></span>
                            </div>
                        </div>
                        <div class="cl-fact cl-fact--accent">
                            <span class="cl-fact-icon"><i class="fas fa-phone-alt" aria-hidden="true"></i></span>
                            <div class="cl-fact-body">
                                <span class="cl-fact-label">Phone</span>
                                <span class="cl-fact-value"><?= $edit['phone'] ? '<a href="tel:' . e($edit['phone']) . '">' . e($edit['phone']) . '</a>' : '—' ?></span>
                            </div>
                        </div>
                        <div class="cl-fact">
                            <span class="cl-fact-icon"><i class="fas fa-map-marker-alt" aria-hidden="true"></i></span>
                            <div class="cl-fact-body">
                                <span class="cl-fact-label">Address</span>
                                <span class="cl-fact-value"><?= e($edit['address'] ?: '—') ?></span>
                            </div>
                        </div>
                    </div>
                    <?php if ($edit['notes']): ?>
                        <div class="border rounded p-3 mt-3 bg-primary-light">
                            <strong class="small text-uppercase text-muted" style="font-size:.68rem;letter-spacing:.05em">Notes</strong>
                            <p class="mb-0 small mt-1"><?= nl2br(e($edit['notes'])) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Contact Persons -->
            <div class="card card-outline mb-3" id="client-contacts">
                <div class="card-header">
                    <h3 class="card-title"><span class="tms-chip tms-chip--success"><i class="fas fa-id-card" aria-hidden="true"></i></span>Contact Persons</h3>
                    <div class="card-tools">
                        <?php if ($canManage): ?>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="openContactDrawer()"><i class="fas fa-plus mr-1"></i>Add Contact</button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table cl-table mb-0">
                            <thead>
                                <tr>
                                    <th>Contact</th>
                                    <th>Email / Phone</th>
                                    <th>Primary</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($contacts as $t): ?>
                                    <?php $ci = '';
                                    foreach (preg_split('/\s+/', trim((string) $t['name'])) as $_w) {
                                        if ($_w !== '' && mb_strlen($ci) < 2) {
                                            $ci .= mb_strtoupper(mb_substr($_w, 0, 1));
                                        }
                                    }
                                    $ci = $ci !== '' ? $ci : '?'; ?>
                                    <tr>
                                        <td>
                                            <span class="cl-person">
                                                <span class="cl-mini-avatar"><?= e($ci) ?></span>
                                                <span><span class="cl-cell-main"><?= e($t['name']) ?></span><?php if ($t['designation']): ?><br><span class="cl-cell-sub"><?= e($t['designation']) ?></span><?php endif; ?></span>
                                            </span>
                                        </td>
                                        <td>
                                            <?= $t['email'] ? '<span class="cl-cell-main">' . e($t['email']) . '</span>' : '—' ?>
                                            <?php if ($t['phone']): ?><br><span class="cl-cell-sub"><?= e($t['phone']) ?></span><?php endif; ?>
                                        </td>
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
                                                <span class="badge-soft badge-soft--success"><i class="fas fa-check" aria-hidden="true"></i>Primary</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-right">
                                            <?php if ($canManage): ?>
                                                <button type="button" class="btn btn-xs btn-outline-secondary" title="Edit" aria-label="Edit <?= e($t['name']) ?>?" onclick="openContactDrawer(<?= (int) $t['id'] ?>)"><i class="fas fa-edit" aria-hidden="true"></i></button>
                                                <form action="operation.php?module=clients&page=clients" method="post" class="d-inline">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="delete_contact">
                                                    <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                                    <input type="hidden" name="client_id" value="<?= (int) $edit['id'] ?>">
                                                    <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" aria-label="Delete contact <?= e($t['name']) ?>?" data-confirm="Delete contact <?= e($t['name']) ?>?"><i class="fas fa-trash" aria-hidden="true"></i></button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$contacts): ?><tr>
                                        <td colspan="4">
                                            <div class="tms-empty-state"><i class="fas fa-users" aria-hidden="true"></i>
                                                <p>No contacts yet. The first contact added becomes the primary contact.</p>
                                            </div>
                                        </td>
                                    </tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Won Projects (detail) -->
            <div class="card card-outline mb-3" id="client-projects">
                <div class="card-header">
                    <h3 class="card-title"><span class="tms-chip tms-chip--primary"><i class="fas fa-project-diagram" aria-hidden="true"></i></span>Won Projects</h3>
                    <div class="card-tools">
                        <a href="<?= pageUrl('leads', 'client_projects') ?>&source=<?= (int) $edit['id'] ?>" class="btn btn-sm btn-outline-secondary mb-0"><i class="fas fa-external-link-alt mr-1"></i>Open Client Projects</a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table cl-table mb-0">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>URL</th>
                                    <th class="text-right">Value</th>
                                    <th>Status</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $projectSoft = ['Active' => 'success', 'Completed' => 'info', 'On Hold' => 'warning', 'Cancelled' => 'muted']; ?>
                                <?php foreach ($wonProjects as $p): ?>
                                    <tr>
                                        <td>
                                            <span class="cl-cell-main"><?= e($p['title']) ?></span>
                                            <?php if ($p['catalog_name']): ?><br><span class="cl-cell-sub"><?= e($p['catalog_name']) ?></span><?php endif; ?>
                                        </td>
                                        <td><?= $p['url'] ? '<a href="' . e($p['url']) . '" target="_blank" rel="noopener noreferrer">' . e($p['url']) . '</a>' : '—' ?></td>
                                        <td class="text-right"><span class="cl-money"><?= $p['value'] !== null ? 'NPR ' . e(formatMoney($p['value'])) : '—' ?></span></td>
                                        <td><span class="badge-soft badge-soft--<?= $projectSoft[$p['status']] ?? 'muted' ?>"><?= e($p['status']) ?></span></td>
                                        <td class="text-right">
                                            <?php if ($canManage): ?>
                                                <button type="button" class="btn btn-xs btn-outline-secondary" title="Edit" aria-label="Edit project <?= e($p['title']) ?>" onclick="openClientProjectDrawer(<?= (int) $p['id'] ?>)"><i class="fas fa-edit" aria-hidden="true"></i></button>
                                                <form action="operation.php?module=leads&page=client_projects" method="post" class="d-inline">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="delete_client_project">
                                                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                                    <input type="hidden" name="from_client_id" value="<?= (int) $edit['id'] ?>">
                                                    <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" aria-label="Delete project <?= e($p['title']) ?>?" data-confirm="Delete project <?= e($p['title']) ?>?"><i class="fas fa-trash" aria-hidden="true"></i></button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$wonProjects): ?><tr>
                                        <td colspan="5">
                                            <div class="tms-empty-state"><i class="fas fa-project-diagram" aria-hidden="true"></i>
                                                <p>This client has no won projects yet.</p>
                                            </div>
                                        </td>
                                    </tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Linked Leads (detail) -->
            <div class="card card-outline mb-3" id="client-leads">
                <div class="card-header">
                    <h3 class="card-title"><span class="tms-chip tms-chip--warning"><i class="fas fa-link" aria-hidden="true"></i></span>Linked Leads</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table cl-table mb-0">
                            <thead>
                                <tr>
                                    <th>Lead</th>
                                    <th>Service</th>
                                    <th>Stage</th>
                                    <th>Priority</th>
                                    <th>Owner</th>
                                    <th>Added</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $stageSoft = ['New' => 'info', 'Contacted' => 'info', 'Qualified' => 'warning', 'Proposal' => 'muted', 'Won' => 'success', 'Lost' => 'danger']; ?>
                                <?php $prioritySoft = ['Hot' => 'danger', 'Warm' => 'warning', 'Cold' => 'muted']; ?>
                                <?php foreach ($sourceLeads as $l): ?>
                                    <tr>
                                        <td>
                                            <span class="cl-cell-main"><?= e($l['company'] ?: $l['contact_name']) ?></span>
                                            <br><span class="cl-cell-sub"><?= e($l['contact_name'] ?: '—') ?> · <?= e($l['email'] ?: '—') ?></span>
                                        </td>
                                        <td><?= e($l['service_interest'] ?: '—') ?></td>
                                        <td><span class="badge-soft badge-soft--<?= $stageSoft[$l['stage']] ?? 'muted' ?>"><?= e($l['stage']) ?></span></td>
                                        <td><span class="badge-soft badge-soft--<?= $prioritySoft[$l['priority']] ?? 'muted' ?>"><?= e($l['priority']) ?></span></td>
                                        <td><?= e($l['owner_name'] ?: '—') ?></td>
                                        <td><?= e(date('M j, Y', strtotime($l['added_on']))) ?></td>
                                        <td class="text-right">
                                            <a href="<?= pageUrl('leads', 'leads') ?>&id=<?= (int) $l['id'] ?>" class="btn btn-xs btn-outline-primary" aria-label="View lead <?= e($l['company'] ?: $l['contact_name']) ?>"><i class="fas fa-eye" aria-hidden="true"></i></a>
                                            <?php if ($canManage): ?>
                                                <form action="operation.php?module=leads&page=leads" method="post" class="d-inline">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="action" value="delete_lead">
                                                    <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                                                    <input type="hidden" name="from_client_id" value="<?= (int) $edit['id'] ?>">
                                                    <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" title="Delete" aria-label="Delete lead <?= e($l['company'] ?: $l['contact_name']) ?>?" data-confirm="Delete this lead? Files and activities are removed."><i class="fas fa-trash" aria-hidden="true"></i></button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$sourceLeads): ?><tr>
                                        <td colspan="7">
                                            <div class="tms-empty-state"><i class="fas fa-link" aria-hidden="true"></i>
                                                <p>This client has no linked leads.</p>
                                            </div>
                                        </td>
                                    </tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Quotations -->
            <div class="card card-outline mb-3" id="client-quotations">
                <div class="card-header">
                    <h3 class="card-title"><span class="tms-chip tms-chip--muted"><i class="fas fa-file-invoice" aria-hidden="true"></i></span>Quotations</h3>
                    <div class="card-tools">
                        <a href="<?= pageUrl('leads', 'quotations') ?>&add=1&client_id=<?= (int) $edit['id'] ?>" class="btn btn-outline-primary btn-sm"><i class="fas fa-plus mr-1"></i>New Quotation</a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table cl-table mb-0">
                            <thead>
                                <tr>
                                    <th>Quotation</th>
                                    <th>Date</th>
                                    <th class="text-right">Total</th>
                                    <th>Status</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $statusSoft = ['Draft' => 'muted', 'Sent' => 'info', 'Accepted' => 'success', 'Rejected' => 'danger', 'Expired' => 'warning']; ?>
                                <?php foreach ($quotations as $q): ?>
                                    <tr>
                                        <td>
                                            <span class="cl-cell-main"><?= e($q['quotation_number']) ?></span>
                                            <?php if ($q['subject']): ?><br><span class="cl-cell-sub"><?= e(mb_strimwidth($q['subject'], 0, 44, '…')) ?></span><?php endif; ?>
                                        </td>
                                        <td><?= e($q['quotation_date']) ?></td>
                                        <td class="text-right"><span class="cl-money">NPR <?= e(formatMoney($q['total'])) ?></span></td>
                                        <td><span class="badge-soft badge-soft--<?= $statusSoft[$q['status']] ?? 'muted' ?>"><?= e($q['status']) ?></span></td>
                                        <td class="text-right">
                                            <a href="<?= pageUrl('leads', 'quotations') ?>&id=<?= (int) $q['id'] ?>" class="btn btn-xs btn-outline-primary" title="View" aria-label="View quotation <?= e($q['quotation_number']) ?>"><i class="fas fa-eye" aria-hidden="true"></i></a>
                                            <a href="<?= pageUrl('leads', 'quotations') ?>&id=<?= (int) $q['id'] ?>&pdf=1" class="btn btn-xs btn-outline-danger" title="PDF" aria-label="Download quotation <?= e($q['quotation_number']) ?> PDF"><i class="fas fa-download" aria-hidden="true"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$quotations): ?><tr>
                                        <td colspan="5">
                                            <div class="tms-empty-state"><i class="fas fa-file-invoice" aria-hidden="true"></i>
                                                <p>No quotations for this client yet.</p>
                                            </div>
                                        </td>
                                    </tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Client Activity Timeline (aggregated across linked leads) -->
            <div class="card card-outline mb-3" id="client-activity">
                <div class="card-header">
                    <h3 class="card-title"><span class="tms-chip tms-chip--warning"><i class="fas fa-stream" aria-hidden="true"></i></span>Activity Timeline</h3>
                    <span class="badge-soft badge-soft--muted"><i class="fas fa-list-ol mr-1" aria-hidden="true"></i><?= count($clientActivities) ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if ($clientActivities): ?>
                        <?php $actDot = ['Call' => 'Call', 'Email' => 'Email', 'Meeting' => 'Meeting', 'Note' => 'Note', 'Status Change' => 'StatusChange', 'Task' => 'Task']; ?>
                        <?php $actSoft = ['Call' => 'info', 'Email' => 'info', 'Meeting' => 'warning', 'Note' => 'muted', 'Status Change' => 'success', 'Task' => 'danger']; ?>
                        <ol class="cl-timeline">
                            <?php foreach ($clientActivities as $a): ?>
                                <li class="cl-timeline-item">
                                    <span class="cl-timeline-dot cl-timeline-dot--<?= $actDot[$a['type']] ?? 'Note' ?>" aria-hidden="true"></span>
                                    <div class="cl-timeline-content">
                                        <div class="cl-timeline-head">
                                            <span class="badge-soft badge-soft--<?= $actSoft[$a['type']] ?? 'muted' ?>"><?= e($a['type']) ?></span>
                                            <span class="cl-timeline-title"><?= e($a['actor'] ?? '—') ?><?= $a['lead_title'] ? ' · ' . e($a['lead_title']) : '' ?></span>
                                            <time class="cl-timeline-time" datetime="<?= e($a['added_on']) ?>"><?= e(date('M j, g:i A', strtotime($a['added_on']))) ?></time>
                                        </div>
                                        <?php if ($a['note']): ?><div class="cl-timeline-note"><?= nl2br(e($a['note'])) ?></div><?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php else: ?>
                        <div class="tms-empty-state"><i class="fas fa-inbox" aria-hidden="true"></i>
                            <p>No activity yet across this client's leads.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- About -->
            <div class="card card-outline mb-3">
                <div class="card-header">
                    <h3 class="card-title"><span class="tms-chip tms-chip--muted"><i class="fas fa-address-card" aria-hidden="true"></i></span>About</h3>
                </div>
                <div class="card-body">
                    <div class="cl-facts cl-facts--stacked">
                        <?php if ($originLead): ?>
                            <div class="cl-fact cl-fact--accent">
                                <span class="cl-fact-icon"><i class="fas fa-route" aria-hidden="true"></i></span>
                                <div class="cl-fact-body">
                                    <span class="cl-fact-label">Origin Lead</span>
                                    <span class="cl-fact-value"><a href="<?= pageUrl('leads', 'leads') ?>&id=<?= (int) $originLead['id'] ?>"><?= e($originLead['company'] ?: $originLead['contact_name']) ?></a></span>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="cl-fact">
                            <span class="cl-fact-icon"><i class="fas fa-calendar-alt" aria-hidden="true"></i></span>
                            <div class="cl-fact-body">
                                <span class="cl-fact-label">Client Since</span>
                                <span class="cl-fact-value"><?= e(date('M j, Y', strtotime($edit['added_on']))) ?><?= $creator ? ' <span class="cl-cell-sub">· ' . e($creator['fullname']) . '</span>' : '' ?></span>
                            </div>
                        </div>
                        <?php if ($edit['updated_on'] && $edit['updated_on'] !== $edit['added_on']): ?>
                            <div class="cl-fact">
                                <span class="cl-fact-icon"><i class="fas fa-history" aria-hidden="true"></i></span>
                                <div class="cl-fact-body">
                                    <span class="cl-fact-label">Last Updated</span>
                                    <span class="cl-fact-value"><?= e(date('M j, g:i A', strtotime($edit['updated_on']))) ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="cl-fact">
                            <span class="cl-fact-icon"><i class="fas fa-hashtag" aria-hidden="true"></i></span>
                            <div class="cl-fact-body">
                                <span class="cl-fact-label">PAN No</span>
                                <span class="cl-fact-value"><?= $edit['pan_num'] ? '<code>' . e($edit['pan_num']) . '</code>' : '—' ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card card-outline mb-3">
                <div class="card-header">
                    <h3 class="card-title"><span class="tms-chip tms-chip--primary"><i class="fas fa-bolt" aria-hidden="true"></i></span>Quick Actions</h3>
                </div>
                <div class="card-body p-0">
                    <ul class="cl-quick">
                        <?php if ($canManage): ?>
                            <li>
                                <button type="button" class="cl-quick-item" onclick="openSourceDrawer(<?= (int) $edit['id'] ?>)">
                                    <span class="tms-chip tms-chip--primary"><i class="fas fa-edit" aria-hidden="true"></i></span>
                                    <span class="cl-quick-item-text"><span class="cl-quick-item-label">Edit Client</span><br><span class="cl-quick-item-sub">Profile, PAN &amp; notes</span></span>
                                    <i class="fas fa-chevron-right cl-quick-item-chevron" aria-hidden="true"></i>
                                </button>
                            </li>
                            <li>
                                <button type="button" class="cl-quick-item" onclick="openContactDrawer()">
                                    <span class="tms-chip tms-chip--success"><i class="fas fa-user-plus" aria-hidden="true"></i></span>
                                    <span class="cl-quick-item-text"><span class="cl-quick-item-label">Add Contact Person</span><br><span class="cl-quick-item-sub">Team member for this client</span></span>
                                    <i class="fas fa-chevron-right cl-quick-item-chevron" aria-hidden="true"></i>
                                </button>
                            </li>
                            <li>
                                <form action="operation.php?module=clients&page=clients" method="post">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete_client">
                                    <input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
                                    <button type="submit" class="cl-quick-item cl-quick-item--danger confirm-submit" data-confirm="Delete client <?= e($edit['name']) ?> and all related data?">
                                        <span class="tms-chip tms-chip--danger"><i class="fas fa-trash" aria-hidden="true"></i></span>
                                        <span class="cl-quick-item-text"><span class="cl-quick-item-label">Delete Client</span><br><span class="cl-quick-item-sub">Removes client &amp; related data</span></span>
                                        <i class="fas fa-chevron-right cl-quick-item-chevron" aria-hidden="true"></i>
                                    </button>
                                </form>
                            </li>
                        <?php endif; ?>
                        <li>
                            <a class="cl-quick-item" href="<?= pageUrl('leads', 'quotations') ?>&add=1&client_id=<?= (int) $edit['id'] ?>">
                                <span class="tms-chip tms-chip--warning"><i class="fas fa-file-invoice" aria-hidden="true"></i></span>
                                <span class="cl-quick-item-text"><span class="cl-quick-item-label">New Quotation</span><br><span class="cl-quick-item-sub">Create a quotation for this client</span></span>
                                <i class="fas fa-chevron-right cl-quick-item-chevron" aria-hidden="true"></i>
                            </a>
                        </li>
                        <li>
                            <a class="cl-quick-item" href="<?= pageUrl('leads', 'client_projects') ?>&source=<?= (int) $edit['id'] ?>">
                                <span class="tms-chip tms-chip--info"><i class="fas fa-folder-open" aria-hidden="true"></i></span>
                                <span class="cl-quick-item-text"><span class="cl-quick-item-label">Client Projects</span><br><span class="cl-quick-item-sub">Modules, DB &amp; access</span></span>
                                <i class="fas fa-chevron-right cl-quick-item-chevron" aria-hidden="true"></i>
                            </a>
                        </li>
                        <li>
                            <a class="cl-quick-item" href="<?= pageUrl('leads', 'leads') ?>">
                                <span class="tms-chip tms-chip--muted"><i class="fas fa-search" aria-hidden="true"></i></span>
                                <span class="cl-quick-item-text"><span class="cl-quick-item-label">Browse All Leads</span><br><span class="cl-quick-item-sub">Full lead pipeline</span></span>
                                <i class="fas fa-chevron-right cl-quick-item-chevron" aria-hidden="true"></i>
                            </a>
                        </li>
                    </ul>
                </div>
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
                        <label class="small font-weight-bold">URL</label>
                        <input type="text" name="url" id="cpDetailUrl" class="form-control form-control-sm" placeholder="https://project.example.com">
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
                if (contactsData[i].id == contactId) {
                    contact = contactsData[i];
                    break;
                }
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
                if (wonProjectsData[i].id == projectId) {
                    p = wonProjectsData[i];
                    break;
                }
            }
        }
        document.getElementById('cpDetailId').value = p ? p.id : 0;
        document.getElementById('cpDetailClientId').value = currentClientId;
        document.getElementById('cpDetailCatalog').value = p ? (p.project_id || '') : '';
        document.getElementById('cpDetailTitle').value = p ? (p.title || '') : '';
        document.getElementById('cpDetailPackage').value = p ? (p.package || '') : '';
        document.getElementById('cpDetailUrl').value = p ? (p.url || '') : '';
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