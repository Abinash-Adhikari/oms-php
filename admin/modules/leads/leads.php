<?php

/**
 * SB-Tech — Leads Pipeline (US-LE-02/03/04/05).
 * Premium pipeline with KPI cards, Kanban board, activity timeline,
 * detail view, and slide-in drawer for add/edit.
 */
$db = Database::instance();
$me = (int) Auth::id();
$canManage = Auth::isSuperAdmin() || Auth::hasSpecial('manage_leads');
$stages = ['New', 'Contacted', 'Qualified', 'Proposal', 'Won', 'Lost'];
$stageColors = [
    'New'       => ['color' => '#3B82F6', 'bg' => '#EFF6FF', 'icon' => 'fas fa-sparkles',  'kpi' => 'info'],
    'Contacted' => ['color' => '#8B5CF6', 'bg' => '#F5F3FF', 'icon' => 'fas fa-phone',     'kpi' => 'primary'],
    'Qualified' => ['color' => '#F59E0B', 'bg' => '#FFFBEB', 'icon' => 'fas fa-check-circle', 'kpi' => 'warning'],
    'Proposal'  => ['color' => '#6366F1', 'bg' => '#EEF2FF', 'icon' => 'fas fa-file-invoice', 'kpi' => 'secondary'],
    'Won'       => ['color' => '#10B981', 'bg' => '#ECFDF5', 'icon' => 'fas fa-trophy',     'kpi' => 'success'],
    'Lost'      => ['color' => '#EF4444', 'bg' => '#FEF2F2', 'icon' => 'fas fa-times-circle', 'kpi' => 'danger'],
];
$stageBadges = ['New' => 'primary', 'Contacted' => 'info', 'Qualified' => 'warning', 'Proposal' => 'secondary', 'Won' => 'success', 'Lost' => 'danger'];
$priorities = ['Hot', 'Warm', 'Cold'];
$priorityColors = ['Hot' => 'danger', 'Warm' => 'warning', 'Cold' => 'secondary'];

$staffs = $db->select(
    "SELECT u.id, u.fullname, d.title AS department_title
     FROM `tbl_users_login` u
     LEFT JOIN `tbl_office_departments` d ON d.id = u.department_id
     WHERE u.status = 'Active'
     ORDER BY u.fullname"
);

// ── Helper: human time ago ──
function leadTimeAgo($datetime) {
    if (!$datetime) return 'never';
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    $days = floor($diff / 86400);
    if ($days === 1) return 'yesterday';
    if ($days < 7) return $days . 'd ago';
    if ($days < 30) return floor($days / 7) . 'w ago';
    return date('M j', strtotime($datetime));
}

// ── Helper: format currency compact ──
function leadFormatValue($val) {
    if ($val === null || $val == 0) return '—';
    $v = (float) $val;
    if ($v >= 10000000) return 'NPR ' . number_format($v / 10000000, 1) . 'Cr';
    if ($v >= 100000) return 'NPR ' . number_format($v / 100000, 1) . 'L';
    if ($v >= 1000) return 'NPR ' . number_format($v / 1000, 1) . 'K';
    return 'NPR ' . number_format($v, 0);
}

// ================================================================== detail
if (isset($_GET['id'])) {
    // All clients for manual linking in detail view
    $allClients = $db->select(
        'SELECT * FROM `tbl_clients` ORDER BY `name` ASC'
    );
    $lead = $db->selectOne(
        'SELECT l.*, o.fullname AS owner_name, c.name AS client_name
         FROM `tbl_leads` l
         LEFT JOIN `tbl_users_login` o ON o.id = l.assigned_to
         LEFT JOIN `tbl_clients` c ON c.id = l.client_id
         WHERE l.id = ?',
        [(int) $_GET['id']]
    );
    if (!$lead) {
        echo '<div class="callout callout-danger"><h5>Lead not found</h5></div>';
        return;
    }
    $activities = $db->select(
        'SELECT a.*, u.fullname AS actor FROM `tbl_lead_activities` a
         LEFT JOIN `tbl_users_login` u ON u.id = a.added_by
         WHERE a.lead_id = ? ORDER BY a.added_on DESC',
        [(int) $lead['id']]
    );
    $files = $db->select('SELECT * FROM `tbl_lead_files` WHERE lead_id = ? ORDER BY added_on DESC', [(int) $lead['id']]);
    $dup = null;
    if (($lead['email'] || $lead['phone']) && !in_array($lead['stage'], ['Won', 'Lost'], true)) {
        $dup = $db->selectOne(
            "SELECT * FROM `tbl_leads`
             WHERE id != ? AND stage NOT IN ('Won','Lost')
               AND ((? IS NOT NULL AND ? <> '' AND email = ?) OR (? IS NOT NULL AND ? <> '' AND phone = ?))
             ORDER BY added_on ASC LIMIT 1",
            [(int) $lead['id'], $lead['email'], $lead['email'], $lead['email'], $lead['phone'], $lead['phone'], $lead['phone']]
        );
    }
    $aging = $lead['last_activity_on']
        ? (time() - strtotime($lead['last_activity_on'])) / 86400
        : (time() - strtotime((string) $lead['added_on'])) / 86400;

    // Stage progress
    $stageIdx = array_search($lead['stage'], $stages);
    $sc = $stageColors[$lead['stage']] ?? $stageColors['New'];
?>
    <!-- ── Detail View ── -->
    <div class="d-flex align-items-center mb-3">
        <a href="<?= pageUrl('leads', 'leads') ?>" class="btn btn-sm btn-outline-secondary mr-2"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h4 class="mb-0 font-weight-bold"><?= e($lead['company'] ?: $lead['contact_name']) ?></h4>
            <small class="text-muted"><?= e($lead['contact_name'] ?: '—') ?> · <?= e($lead['email'] ?: '—') ?></small>
        </div>
        <div class="ml-auto d-flex align-items-center">
            <span class="badge badge-pill badge-<?= $stageBadges[$lead['stage']] ?? 'secondary' ?> mr-2" style="font-size:.82rem;padding:.4em .8em">
                <i class="<?= $sc['icon'] ?> mr-1"></i><?= e($lead['stage']) ?>
            </span>
            <span class="badge badge-pill badge-<?= $priorityColors[$lead['priority']] ?? 'secondary' ?> mr-2" style="font-size:.82rem;padding:.4em .8em">
                <?= e($lead['priority']) ?>
            </span>
        </div>
    </div>

    <!-- Stage Progress Bar -->
    <div class="mb-3">
        <div class="d-flex justify-content-between mb-1">
            <?php foreach ($stages as $si => $st): ?>
                <div class="text-center" style="flex:1">
                    <small class="font-weight-bold <?= $si <= $stageIdx ? 'text-primary' : 'text-muted' ?>"><?= $st ?></small>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="progress" style="height:6px;border-radius:3px">
            <div class="progress-bar bg-primary" style="width:<?= ($stageIdx / (count($stages) - 1)) * 100 ?>%"></div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <!-- Lead Info Card -->
            <div class="card card-outline">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-info-circle mr-1"></i>Lead Information</h3>
                    <div class="card-tools">
                        <?php if ($aging > 7): ?>
                            <span class="badge badge-danger mr-1"><i class="fas fa-hourglass-end mr-1"></i>Aging <?= (int) $aging ?>d</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($dup): ?>
                        <div class="alert alert-warning d-flex align-items-center">
                            <i class="fas fa-clone mr-2"></i>
                            <div class="flex-grow-1">
                                <strong>Possible duplicate:</strong>
                                <a href="<?= pageUrl('leads', 'leads') ?>&id=<?= (int) $dup['id'] ?>"><?= e($dup['company'] ?: $dup['contact_name']) ?></a>
                                shares the same email/phone.
                            </div>
                            <?php if ($canManage): ?>
                                <form action="operation.php?module=leads&page=leads" method="post" class="d-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="merge_leads">
                                    <input type="hidden" name="keep_id" value="<?= (int) $lead['id'] ?>">
                                    <input type="hidden" name="merge_id" value="<?= (int) $dup['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-warning confirm-submit" data-confirm="Merge duplicate into this lead?"><i class="fas fa-compress-arrows-alt mr-1"></i>Merge</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless mb-0">
                                <tr><th class="text-muted" style="width:45%">Contact</th><td class="font-weight-bold"><?= e($lead['contact_name'] ?: '—') ?></td></tr>
                                <tr><th class="text-muted">Email</th><td><?= $lead['email'] ? '<a href="mailto:' . e($lead['email']) . '">' . e($lead['email']) . '</a>' : '—' ?></td></tr>
                                <tr><th class="text-muted">Phone</th><td><?= $lead['phone'] ? '<a href="tel:' . e($lead['phone']) . '">' . e($lead['phone']) . '</a>' : '—' ?></td></tr>
                                <tr><th class="text-muted">Service Interest</th><td><?= e($lead['service_interest'] ?: '—') ?></td></tr>
                                <tr><th class="text-muted">Source</th><td><span class="badge badge-light border"><?= e($lead['source']) ?></span></td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless mb-0">
                                <tr><th class="text-muted" style="width:45%">Owner</th><td><?= $lead['owner_name'] ? e($lead['owner_name']) : '<span class="text-muted">Unassigned</span>' ?></td></tr>
                                <tr><th class="text-muted">Est. Value</th><td class="font-weight-bold text-primary"><?= leadFormatValue($lead['estimated_value']) ?></td></tr>
                                <tr><th class="text-muted">Created</th><td><?= e(date('M j, Y', strtotime($lead['added_on']))) ?></td></tr>
                                <tr><th class="text-muted">Last Activity</th><td><?= $lead['last_activity_on'] ? e(date('M j, g:i A', strtotime($lead['last_activity_on']))) : '—' ?></td></tr>
                                <?php if ($lead['lost_reason']): ?>
                                    <tr><th class="text-muted">Lost Reason</th><td class="text-danger"><?= e($lead['lost_reason']) ?></td></tr>
                                <?php endif; ?>
                                <?php if ($lead['client_name']): ?>
                                    <tr><th class="text-muted">Client</th><td><a href="<?= pageUrl('leads', 'clients') ?>&id=<?= (int) $lead['client_id'] ?>"><i class="fas fa-link mr-1"></i><?= e($lead['client_name']) ?></a></td></tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                    <?php if ($lead['message']): ?>
                        <hr class="my-2">
                        <p class="mb-0 small"><strong>Message:</strong><br><?= nl2br(e($lead['message'])) ?></p>
                    <?php endif; ?>
                    <?php if ($files): ?>
                        <hr class="my-2">
                        <div class="small">
                            <strong><i class="fas fa-paperclip mr-1"></i>Files:</strong>
                            <?php foreach ($files as $f): ?>
                                <a href="<?= assetUrl('user_uploads/' . $f['file_location']) ?>" class="badge badge-light border ml-1" target="_blank"><?= e($f['file_name']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Activity Timeline -->
            <div class="card card-outline">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-stream mr-1"></i>Activity Timeline</h3>
                    <span class="badge badge-light"><?= count($activities) ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if ($canManage): ?>
                        <form action="operation.php?module=leads&page=leads" method="post" class="d-flex align-items-center p-3 border-bottom">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="add_activity">
                            <input type="hidden" name="id" value="<?= (int) $lead['id'] ?>">
                            <select name="type" class="form-control form-control-sm" style="width:100px">
                                <?php foreach (['Call', 'Email', 'Note', 'Meeting'] as $t): ?>
                                    <option value="<?= $t ?>"><?= $t ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="note" class="form-control form-control-sm mx-2" placeholder="Log an activity..." required>
                            <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i></button>
                        </form>
                    <?php endif; ?>
                    <?php if ($activities): ?>
                        <div class="p-3">
                            <?php foreach ($activities as $a): ?>
                                <div class="d-flex mb-3">
                                    <div class="mr-3">
                                        <?php $tc = ['Call' => 'primary', 'Email' => 'info', 'Meeting' => 'warning', 'Note' => 'secondary']; ?>
                                        <span class="badge badge-<?= $tc[$a['type']] ?? 'light' ?>" style="min-width:56px;justify-content:center"><?= e($a['type']) ?></span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between">
                                            <span class="font-weight-bold small"><?= e($a['actor'] ?? '—') ?></span>
                                            <small class="text-muted"><?= leadTimeAgo($a['added_on']) ?></small>
                                        </div>
                                        <?php if ($a['note']): ?><div class="text-muted small mt-1"><?= nl2br(e($a['note'])) ?></div><?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-4"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>No activity yet</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <?php if ($canManage): ?>
                <!-- Quick Actions -->
                <div class="card card-outline">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-bolt mr-1"></i>Quick Actions</h3>
                    </div>
                    <div class="card-body">
                        <form action="operation.php?module=leads&page=leads" method="post" class="mb-3">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="update_lead">
                            <input type="hidden" name="id" value="<?= (int) $lead['id'] ?>">
                            <div class="form-group mb-2">
                                <label class="small text-muted mb-1">Stage</label>
                                <select name="stage" class="form-control form-control-sm">
                                    <?php foreach ($stages as $st): ?>
                                        <option value="<?= $st ?>" <?= $lead['stage'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group mb-2">
                                <label class="small text-muted mb-1">Priority</label>
                                <select name="priority" class="form-control form-control-sm">
                                    <?php foreach ($priorities as $p): ?>
                                        <option value="<?= $p ?>" <?= $lead['priority'] === $p ? 'selected' : '' ?>><?= $p ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group mb-2">
                                <label class="small text-muted mb-1">Owner</label>
                                <select name="assigned_to" class="form-control form-control-sm">
                                    <option value="">Unassigned</option>
                                    <?php foreach ($staffs as $st): ?>
                                        <option value="<?= (int) $st['id'] ?>" <?= (int) $lead['assigned_to'] === (int) $st['id'] ? 'selected' : '' ?>><?= e($st['fullname']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php if ($lead['stage'] === 'Lost'): ?>
                                <div class="form-group mb-2">
                                    <label class="small text-muted mb-1">Lost Reason</label>
                                    <input type="text" name="lost_reason" class="form-control form-control-sm" value="<?= e($lead['lost_reason']) ?>">
                                </div>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-sm btn-primary btn-block"><i class="fas fa-save mr-1"></i>Save Changes</button>
                        </form>

                        <hr class="my-2">
                        <a href="<?= pageUrl('leads', 'leads') ?>&edit=<?= (int) $lead['id'] ?>" class="btn btn-sm btn-outline-primary btn-block mb-2"><i class="fas fa-edit mr-1"></i>Edit Lead Details</a>

                        <?php if ($lead['stage'] === 'Lost'): ?>
                            <form action="operation.php?module=leads&page=leads" method="post">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="reopen_lead">
                                <input type="hidden" name="id" value="<?= (int) $lead['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-success btn-block mb-2"><i class="fas fa-redo mr-1"></i>Reopen (→ Contacted)</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($lead['stage'] === 'Won' && !$lead['won_client_id']): ?>
                            <div class="border rounded p-3 mt-2 bg-light">
                                <h6 class="mb-2"><i class="fas fa-handshake text-success mr-1"></i>Convert to Client</h6>
                                <form action="operation.php?module=leads&page=leads" method="post">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="convert_lead">
                                    <input type="hidden" name="id" value="<?= (int) $lead['id'] ?>">
                                    <div class="form-group mb-2"><input type="text" name="name" class="form-control form-control-sm" placeholder="Client name *" required value="<?= e($lead['company'] ?: $lead['contact_name']) ?>"></div>
                                    <div class="form-group mb-2"><input type="text" name="contact_person" class="form-control form-control-sm" placeholder="Contact person" value="<?= e($lead['contact_name']) ?>"></div>
                                    <div class="form-group mb-2"><input type="text" name="address" class="form-control form-control-sm" placeholder="Address"></div>
                                    <div class="form-group mb-2"><input type="text" name="pan_num" class="form-control form-control-sm" placeholder="PAN (optional)"></div>
                                    <button type="submit" class="btn btn-sm btn-success btn-block"><i class="fas fa-check mr-1"></i>Create Client</button>
                                </form>
                            </div>
                        <?php endif; ?>

                        <?php if ($lead['client_id']): ?>
                            <div class="border rounded p-3 mt-2 bg-light">
                                <h6 class="mb-2"><i class="fas fa-link text-primary mr-1"></i>Linked Client</h6>
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <a href="<?= pageUrl('leads', 'clients') ?>&id=<?= (int) $lead['client_id'] ?>" class="font-weight-bold">
                                        <i class="fas fa-building mr-1"></i><?= e($lead['client_name']) ?>
                                    </a>
                                    <form action="operation.php?module=leads&page=leads" method="post" class="d-inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="unlink_lead_from_client">
                                        <input type="hidden" name="id" value="<?= (int) $lead['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger confirm-submit" data-confirm="Unlink this lead from the client?"><i class="fas fa-unlink"></i></button>
                                    </form>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="border rounded p-3 mt-2 bg-light">
                                <h6 class="mb-2"><i class="fas fa-link text-primary mr-1"></i>Link to Existing Client</h6>
                                <form action="operation.php?module=leads&page=leads" method="post">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="link_lead_to_client">
                                    <input type="hidden" name="id" value="<?= (int) $lead['id'] ?>">
                                    <div class="form-group mb-2">
                                        <select name="client_id" class="form-control form-control-sm" required>
                                            <option value="">— Select client —</option>
                                            <?php foreach ($allClients as $cl): ?>
                                                <option value="<?= (int) $cl['id'] ?>"><?= e($cl['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-outline-primary btn-block"><i class="fas fa-link mr-1"></i>Link Client</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Follow-up Task -->
                <div class="card card-outline">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-clock mr-1"></i>Follow-up Task</h3>
                    </div>
                    <div class="card-body">
                        <form action="operation.php?module=leads&page=leads" method="post">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="create_followup">
                            <input type="hidden" name="id" value="<?= (int) $lead['id'] ?>">
                            <div class="form-group mb-2"><label class="small text-muted">Deadline</label><input type="datetime-local" name="deadline" class="form-control form-control-sm" required></div>
                            <div class="form-group mb-2"><label class="small text-muted">Assign to</label>
                                <select name="assigned_to" class="form-control form-control-sm">
                                    <option value="">Unassigned</option>
                                    <?php foreach ($staffs as $st): ?>
                                        <option value="<?= (int) $st['id'] ?>" <?= (int) $lead['assigned_to'] === (int) $st['id'] ? 'selected' : '' ?>><?= e($st['fullname']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group mb-2"><label class="small text-muted">Note</label><input type="text" name="note" class="form-control form-control-sm" placeholder="e.g. Send proposal"></div>
                            <button type="submit" class="btn btn-sm btn-outline-primary btn-block"><i class="fas fa-plus mr-1"></i>Create Task</button>
                        </form>
                    </div>
                </div>

                <!-- Attach File -->
                <div class="card card-outline">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-paperclip mr-1"></i>Attach File</h3>
                    </div>
                    <div class="card-body">
                        <form action="operation.php?module=leads&page=leads" method="post" enctype="multipart/form-data">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="add_lead_file">
                            <input type="hidden" name="id" value="<?= (int) $lead['id'] ?>">
                            <div class="custom-file mb-2">
                                <input type="file" class="custom-file-input" id="lead_file" name="lead_file" required>
                                <label class="custom-file-label" for="lead_file">Choose file</label>
                            </div>
                            <button type="submit" class="btn btn-sm btn-outline-secondary btn-block"><i class="fas fa-upload mr-1"></i>Upload</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php
    return;
}

// ============================================================ add / edit (full-page fallback)
if (isset($_GET['add']) || isset($_GET['edit'])) {
    $edit = null;
    if (isset($_GET['edit'])) {
        $edit = $db->selectOne('SELECT * FROM `tbl_leads` WHERE `id` = ?', [(int) $_GET['edit']]);
    }
?>
    <div class="d-flex align-items-center mb-3">
        <a href="<?= pageUrl('leads', 'leads') ?>" class="btn btn-sm btn-outline-secondary mr-2"><i class="fas fa-arrow-left"></i></a>
        <h4 class="mb-0 font-weight-bold"><?= $edit ? 'Edit Lead' : 'New Lead' ?></h4>
    </div>
    <div class="card card-outline">
        <div class="card-body">
            <form action="operation.php?module=leads&page=leads" method="post">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save_lead">
                <input type="hidden" name="id" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group"><label>Company</label><input type="text" name="company" class="form-control" value="<?= $edit ? e($edit['company']) : '' ?>"></div>
                        <div class="form-group"><label>Contact name *</label><input type="text" name="contact_name" class="form-control" required value="<?= $edit ? e($edit['contact_name']) : '' ?>"></div>
                        <div class="row">
                            <div class="col-6 form-group"><label>Email</label><input type="email" name="email" class="form-control" value="<?= $edit ? e($edit['email']) : '' ?>"></div>
                            <div class="col-6 form-group"><label>Phone</label><input type="text" name="phone" class="form-control" value="<?= $edit ? e($edit['phone']) : '' ?>"></div>
                        </div>
                        <div class="form-group"><label>Service interest</label><input type="text" name="service_interest" class="form-control" value="<?= $edit ? e($edit['service_interest']) : '' ?>"></div>
                        <div class="form-group"><label>Message</label><textarea name="message" class="form-control" rows="3"><?= $edit ? e($edit['message']) : '' ?></textarea></div>
                    </div>
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-6 form-group"><label>Source</label>
                                <select name="source" class="form-control"><?php foreach (['Website', 'Phone', 'Email', 'Walk-in', 'Referral', 'Social', 'Other'] as $s): ?><option value="<?= $s ?>" <?= $edit && $edit['source'] === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select>
                            </div>
                            <div class="col-6 form-group"><label>Priority</label>
                                <select name="priority" class="form-control"><?php foreach ($priorities as $p): ?><option value="<?= $p ?>" <?= !$edit || $edit['priority'] === $p ? 'selected' : '' ?>><?= $p ?></option><?php endforeach; ?></select>
                            </div>
                        </div>
                        <div class="form-group"><label>Stage</label>
                            <select name="stage" class="form-control"><?php foreach ($stages as $st): ?><option value="<?= $st ?>" <?= $edit && $edit['stage'] === $st ? 'selected' : '' ?>><?= $st ?></option><?php endforeach; ?></select>
                        </div>
                        <div class="form-group"><label>Estimated value (NPR)</label><input type="number" name="estimated_value" class="form-control" step="0.01" min="0" value="<?= $edit && $edit['estimated_value'] !== null ? e($edit['estimated_value']) : '' ?>"></div>
                        <div class="form-group"><label>Owner</label>
                            <select name="assigned_to" class="form-control"><option value="">Unassigned</option><?php foreach ($staffs as $st): ?><option value="<?= (int) $st['id'] ?>" <?= $edit && (int) $edit['assigned_to'] === (int) $st['id'] ? 'selected' : '' ?>><?= e($st['fullname']) ?></option><?php endforeach; ?></select>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i><?= $edit ? 'Update' : 'Save' ?> Lead</button>
                <a href="<?= pageUrl('leads', 'leads') ?>" class="btn btn-default ml-2">Cancel</a>
            </form>
        </div>
    </div>
<?php
    return;
}

// ========================================================== pipeline list
$stageFilter = (string) ($_GET['stage'] ?? '');
$priorityFilter = (string) ($_GET['priority'] ?? '');
$ownerFilter = (int) ($_GET['owner'] ?? 0);
$keyword = trim((string) ($_GET['keyword'] ?? ''));
$viewMode = (string) ($_GET['view'] ?? 'kanban'); // kanban | table

$where = ['1=1'];
$params = [];
if (in_array($stageFilter, $stages, true)) {
    $where[] = 'l.stage = ?';
    $params[] = $stageFilter;
}
if (in_array($priorityFilter, $priorities, true)) {
    $where[] = 'l.priority = ?';
    $params[] = $priorityFilter;
}
if ($ownerFilter) {
    $where[] = 'l.assigned_to = ?';
    $params[] = $ownerFilter;
}
if ($keyword !== '') {
    $where[] = '(l.company LIKE ? OR l.contact_name LIKE ? OR l.email LIKE ? OR l.phone LIKE ? OR l.service_interest LIKE ?)';
    $kw = '%' . $db->escapeLike($keyword) . '%';
    array_push($params, $kw, $kw, $kw, $kw, $kw);
}
if (!empty($_GET['unassigned'])) {
    $where[] = 'l.assigned_to IS NULL';
}
if (!empty($_GET['aging'])) {
    $where[] = "COALESCE(l.last_activity_on, l.added_on) < DATE_SUB(NOW(), INTERVAL 7 DAY)";
}

$leads = $db->select(
    'SELECT l.*, o.fullname AS owner_name, c.name AS client_name
     FROM `tbl_leads` l
     LEFT JOIN `tbl_users_login` o ON o.id = l.assigned_to
     LEFT JOIN `tbl_clients` c ON c.id = l.client_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY FIELD(l.stage, "New", "Contacted", "Qualified", "Proposal", "Won", "Lost"), l.added_on DESC',
    $params
);

// ── KPI data ──
$today = date('Y-m-d');
$kpis = $db->selectOne("SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN stage = 'New' THEN 1 ELSE 0 END) AS new_count,
    SUM(CASE WHEN stage IN ('New','Contacted','Qualified','Proposal') THEN 1 ELSE 0 END) AS active_pipeline,
    SUM(CASE WHEN stage = 'Won' THEN 1 ELSE 0 END) AS won,
    SUM(CASE WHEN stage = 'Lost' THEN 1 ELSE 0 END) AS lost,
    SUM(CASE WHEN stage IN ('New','Contacted','Qualified','Proposal') THEN COALESCE(estimated_value,0) ELSE 0 END) AS pipeline_value,
    SUM(CASE WHEN stage = 'Won' THEN COALESCE(estimated_value,0) ELSE 0 END) AS won_value,
    SUM(CASE WHEN assigned_to IS NULL AND stage NOT IN ('Won','Lost') THEN 1 ELSE 0 END) AS unassigned,
    SUM(CASE WHEN COALESCE(last_activity_on, added_on) < DATE_SUB(NOW(), INTERVAL 7 DAY) AND stage NOT IN ('Won','Lost') THEN 1 ELSE 0 END) AS aging
FROM tbl_leads") ?: [];

$winRate = 0;
$totalClosed = ($kpis['won'] ?? 0) + ($kpis['lost'] ?? 0);
if ($totalClosed > 0) {
    $winRate = round(($kpis['won'] / $totalClosed) * 100);
}
$avgDealSize = ($kpis['won'] ?? 0) > 0 ? ($kpis['won_value'] ?? 0) / $kpis['won'] : 0;

// Group leads by stage for Kanban
$leadsByStage = [];
foreach ($stages as $st) {
    $leadsByStage[$st] = [];
}
foreach ($leads as $l) {
    $leadsByStage[$l['stage']][] = $l;
}

// ── Build form URL for drawer prefill ──
$pageUrl = pageUrl('leads', 'leads');
?>

<!-- ── KPI Cards ── -->
<div class="tms-kpi-grid" style="grid-template-columns: repeat(5, 1fr);">
    <div class="tms-kpi-card bg-info" style="cursor:default">
        <div class="tms-kpi-icon"><i class="fas fa-funnel-dollar"></i></div>
        <div class="tms-kpi-meta">
            <p class="tms-kpi-label">Total Leads</p>
            <p class="tms-kpi-value"><?= (int) ($kpis['total'] ?? 0) ?></p>
        </div>
    </div>
    <div class="tms-kpi-card bg-primary" style="cursor:default">
        <div class="tms-kpi-icon"><i class="fas fa-bolt"></i></div>
        <div class="tms-kpi-meta">
            <p class="tms-kpi-label">Active Pipeline</p>
            <p class="tms-kpi-value"><?= (int) ($kpis['active_pipeline'] ?? 0) ?></p>
        </div>
    </div>
    <a href="<?= $pageUrl ?>?stage=Won" class="tms-kpi-card bg-success">
        <div class="tms-kpi-icon"><i class="fas fa-trophy"></i></div>
        <div class="tms-kpi-meta">
            <p class="tms-kpi-label">Won</p>
            <p class="tms-kpi-value"><?= (int) ($kpis['won'] ?? 0) ?> <small class="text-muted" style="font-size:.7rem;font-weight:400"><?= $winRate ?>%</small></p>
        </div>
    </a>
    <div class="tms-kpi-card bg-warning" style="cursor:default">
        <div class="tms-kpi-icon"><i class="fas fa-coins"></i></div>
        <div class="tms-kpi-meta">
            <p class="tms-kpi-label">Pipeline Value</p>
            <p class="tms-kpi-value" style="font-size:1.1rem"><?= leadFormatValue($kpis['pipeline_value'] ?? 0) ?></p>
        </div>
    </div>
    <a href="<?= $pageUrl ?>?aging=1" class="tms-kpi-card bg-danger">
        <div class="tms-kpi-icon"><i class="fas fa-fire"></i></div>
        <div class="tms-kpi-meta">
            <p class="tms-kpi-label">Aging (7d+)</p>
            <p class="tms-kpi-value"><?= (int) ($kpis['aging'] ?? 0) ?></p>
        </div>
    </a>
</div>

<!-- ── Toolbar ── -->
<div class="card card-outline mb-3">
    <div class="card-body py-2">
        <form method="get" class="d-flex align-items-center flex-wrap" style="gap:.5rem">
            <input type="hidden" name="module" value="leads">
            <input type="hidden" name="page" value="leads">
            <input type="hidden" name="view" value="<?= e($viewMode) ?>">

            <!-- Search -->
            <div class="input-group input-group-sm" style="width:220px">
                <div class="input-group-prepend"><span class="input-group-text bg-transparent border-right-0"><i class="fas fa-search text-muted"></i></span></div>
                <input type="text" name="keyword" class="form-control border-left-0" placeholder="Search leads..." value="<?= e($keyword) ?>">
            </div>

            <!-- Stage filter -->
            <select name="stage" class="form-control form-control-sm" style="width:130px" onchange="this.form.submit()">
                <option value="">All Stages</option>
                <?php foreach ($stages as $st): ?>
                    <option value="<?= $st ?>" <?= $stageFilter === $st ? 'selected' : '' ?>><?= $st ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Priority filter -->
            <select name="priority" class="form-control form-control-sm" style="width:120px" onchange="this.form.submit()">
                <option value="">All Priority</option>
                <?php foreach ($priorities as $p): ?>
                    <option value="<?= $p ?>" <?= $priorityFilter === $p ? 'selected' : '' ?>><?= $p ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Owner filter -->
            <select name="owner" class="form-control form-control-sm" style="width:140px" onchange="this.form.submit()">
                <option value="0">All Owners</option>
                <?php foreach ($staffs as $st): ?>
                    <option value="<?= (int) $st['id'] ?>" <?= $ownerFilter === (int) $st['id'] ? 'selected' : '' ?>><?= e($st['fullname']) ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Checkboxes -->
            <label class="small mb-0 ml-2">
                <input type="checkbox" name="unassigned" value="1" <?= !empty($_GET['unassigned']) ? 'checked' : '' ?> onchange="this.form.submit()"> Unassigned
            </label>
            <label class="small mb-0 ml-2">
                <input type="checkbox" name="aging" value="1" <?= !empty($_GET['aging']) ? 'checked' : '' ?> onchange="this.form.submit()"> Aging
            </label>

            <div class="ml-auto d-flex align-items-center" style="gap:.5rem">
                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search mr-1"></i>Filter</button>

                <!-- View toggle -->
                <div class="btn-group btn-group-sm">
                    <a href="./show_page.php?module=leads&page=leads&view=kanban<?= $stageFilter ? '&stage=' . urlencode($stageFilter) : '' ?>" class="btn btn-outline-secondary <?= $viewMode === 'kanban' ? 'active' : '' ?>"><i class="fas fa-columns"></i></a>
                    <a href="./show_page.php?module=leads&page=leads&view=table<?= $stageFilter ? '&stage=' . urlencode($stageFilter) : '' ?>" class="btn btn-outline-secondary <?= $viewMode === 'table' ? 'active' : '' ?>"><i class="fas fa-list"></i></a>
                </div>

                <!-- Export -->
                <form action="operation.php?module=leads&page=leads" method="post" class="d-inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="export_leads">
                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Export CSV"><i class="fas fa-file-csv"></i></button>
                </form>

                <!-- Add Lead -->
                <?php if ($canManage): ?>
                    <button type="button" class="btn btn-sm btn-primary" onclick="openLeadDrawer()"><i class="fas fa-plus mr-1"></i>Add Lead</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if ($viewMode === 'kanban'): ?>
<!-- ═══════════════════════════════════════════════ KANBAN PIPELINE ═══ -->
<div class="pipeline-board">
    <?php foreach ($stages as $st): ?>
        <?php
            $sc = $stageColors[$st];
            $stageLeads = $leadsByStage[$st];
            $stageTotal = 0;
            foreach ($stageLeads as $sl) { $stageTotal += (float) ($sl['estimated_value'] ?? 0); }
        ?>
        <div class="pipeline-column">
            <div class="pipeline-col-header" style="border-top:3px solid <?= $sc['color'] ?>">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="font-weight-bold" style="font-size:.85rem;color:<?= $sc['color'] ?>">
                        <i class="<?= $sc['icon'] ?> mr-1"></i><?= $st ?>
                    </span>
                    <span class="badge badge-pill" style="background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;font-size:.75rem;padding:.3em .6em;font-weight:700"><?= count($stageLeads) ?></span>
                </div>
                <small class="text-muted" style="font-size:.72rem"><?= leadFormatValue($stageTotal) ?></small>
            </div>
            <div class="pipeline-col-body">
                <?php foreach ($stageLeads as $l): ?>
                    <?php
                        $lAging = $l['last_activity_on'] ? (time() - strtotime($l['last_activity_on'])) / 86400 : (time() - strtotime((string) $l['added_on'])) / 86400;
                        $isAging = $lAging > 7 && !in_array($l['stage'], ['Won', 'Lost']);
                    ?>
                    <a href="<?= $pageUrl ?>&id=<?= (int) $l['id'] ?>" class="pipeline-card <?= $isAging ? 'pipeline-card-aging' : '' ?>">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <span class="font-weight-bold" style="font-size:.82rem;line-height:1.3"><?= e($l['company'] ?: $l['contact_name']) ?></span>
                            <span class="badge badge-<?= $priorityColors[$l['priority']] ?? 'secondary' ?>" style="font-size:.65rem;padding:.2em .45em;flex-shrink:0;margin-left:4px"><?= e($l['priority']) ?></span>
                        </div>
                        <?php if ($l['contact_name'] && $l['company']): ?>
                            <small class="text-muted" style="font-size:.73rem"><i class="fas fa-user mr-1"></i><?= e($l['contact_name']) ?></small>
                        <?php endif; ?>
                        <?php if ($l['service_interest']): ?>
                            <div class="mt-1"><small class="text-muted" style="font-size:.7rem"><i class="fas fa-cog mr-1"></i><?= e($l['service_interest']) ?></small></div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <?php if ($l['estimated_value']): ?>
                                <span class="font-weight-bold text-primary" style="font-size:.78rem"><?= leadFormatValue($l['estimated_value']) ?></span>
                            <?php else: ?>
                                <span></span>
                            <?php endif; ?>
                            <div class="d-flex align-items-center" style="gap:4px">
                                <?php if ($isAging): ?>
                                    <span class="text-danger" style="font-size:.65rem" title="Aging <?= (int) $lAging ?>d"><i class="fas fa-fire"></i></span>
                                <?php endif; ?>
                                <?php if ($l['owner_name']): ?>
                                    <span class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width:22px;height:22px;font-size:.6rem;font-weight:700;color:var(--text-secondary);border:1px solid var(--border-color)" title="<?= e($l['owner_name']) ?>"><?= strtoupper(substr($l['owner_name'], 0, 1)) ?></span>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:.6rem" title="Unassigned"><i class="fas fa-user-slash"></i></span>
                                <?php endif; ?>
                                <small class="text-muted" style="font-size:.65rem"><?= leadTimeAgo($l['added_on']) ?></small>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
                <?php if (empty($stageLeads)): ?>
                    <div class="pipeline-empty">No leads</div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php else: ?>
<!-- ═══════════════════════════════════════════════ TABLE VIEW ═══════ -->
<div class="card card-outline">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Company / Contact</th>
                        <th>Interest</th>
                        <th>Priority</th>
                        <th>Value</th>
                        <th>Stage</th>
                        <th>Owner</th>
                        <th>Added</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leads as $i => $l): ?>
                        <tr>
                            <td class="text-muted"><?= $i + 1 ?></td>
                            <td>
                                <a href="<?= $pageUrl ?>&id=<?= (int) $l['id'] ?>" class="font-weight-bold text-decoration-none"><?= e($l['company'] ?: $l['contact_name']) ?></a><br>
                                <small class="text-muted"><?= e($l['contact_name'] ?: '—') ?><?= $l['email'] ? ' · ' . e($l['email']) : '' ?></small>
                            </td>
                            <td><small><?= e($l['service_interest'] ?: '—') ?></small></td>
                            <td><span class="badge badge-<?= $priorityColors[$l['priority']] ?? 'secondary' ?>"><?= e($l['priority']) ?></span></td>
                            <td><small class="font-weight-bold"><?= $l['estimated_value'] ? leadFormatValue($l['estimated_value']) : '—' ?></small></td>
                            <td>
                                <?php if ($canManage): ?>
                                    <form action="operation.php?module=leads&page=leads" method="post" class="d-inline">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="update_lead">
                                        <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                                        <select name="stage" class="form-control form-control-sm" style="width:110px;padding:2px 4px;font-size:.75rem" onchange="this.form.submit()">
                                            <?php foreach ($stages as $st): ?>
                                                <option value="<?= $st ?>" <?= $l['stage'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                <?php else: ?>
                                    <span class="badge badge-<?= $stageBadges[$l['stage']] ?? 'secondary' ?>"><?= e($l['stage']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><small><?= $l['owner_name'] ? e($l['owner_name']) : '<span class="text-muted">—</span>' ?></small></td>
                            <td><small class="text-muted"><?= leadTimeAgo($l['added_on']) ?></small></td>
                            <td class="text-right">
                                <a href="<?= $pageUrl ?>&id=<?= (int) $l['id'] ?>" class="btn btn-xs btn-outline-primary" title="View"><i class="fas fa-eye"></i></a>
                                <?php if ($canManage): ?>
                                    <a href="<?= $pageUrl ?>&edit=<?= (int) $l['id'] ?>" class="btn btn-xs btn-outline-secondary" title="Edit"><i class="fas fa-edit"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$leads): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>No leads found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════ ADD/EDIT DRAWER ═══ -->
<div class="cms-drawer-backdrop" id="drawerBackdrop" onclick="closeLeadDrawer()"></div>
<div class="cms-drawer" id="leadDrawer">
    <div class="cms-drawer-header">
        <h3><i class="fas fa-funnel-dollar"></i><span id="drawerTitle">New Lead</span></h3>
        <button type="button" class="cms-drawer-close" onclick="closeLeadDrawer()" aria-label="Close"><i class="fas fa-times"></i></button>
    </div>
    <div class="cms-drawer-body">
        <form action="operation.php?module=leads&page=leads" method="post" id="leadForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_lead">
            <input type="hidden" name="id" id="leadId" value="0">

            <h6 class="text-muted text-uppercase mb-2" style="font-size:.7rem;letter-spacing:.05em">Contact Details</h6>
            <div class="form-group">
                <label class="small font-weight-bold">Company</label>
                <input type="text" name="company" id="leadCompany" class="form-control form-control-sm" placeholder="Company name">
            </div>
            <div class="form-group">
                <label class="small font-weight-bold">Contact Name *</label>
                <input type="text" name="contact_name" id="leadContact" class="form-control form-control-sm" placeholder="Full name" required>
            </div>
            <div class="row">
                <div class="col-6 form-group">
                    <label class="small font-weight-bold">Email</label>
                    <input type="email" name="email" id="leadEmail" class="form-control form-control-sm" placeholder="email@example.com">
                </div>
                <div class="col-6 form-group">
                    <label class="small font-weight-bold">Phone</label>
                    <input type="text" name="phone" id="leadPhone" class="form-control form-control-sm" placeholder="+977-9800000000">
                </div>
            </div>

            <hr class="my-3">
            <h6 class="text-muted text-uppercase mb-2" style="font-size:.7rem;letter-spacing:.05em">Lead Details</h6>
            <div class="form-group">
                <label class="small font-weight-bold">Service Interest</label>
                <input type="text" name="service_interest" id="leadService" class="form-control form-control-sm" placeholder="e.g. Web Development, Mobile App">
            </div>
            <div class="form-group">
                <label class="small font-weight-bold">Message / Notes</label>
                <textarea name="message" id="leadMessage" class="form-control form-control-sm" rows="2" placeholder="Requirements or notes..."></textarea>
            </div>
            <div class="row">
                <div class="col-6 form-group">
                    <label class="small font-weight-bold">Source</label>
                    <select name="source" id="leadSource" class="form-control form-control-sm">
                        <?php foreach (['Website', 'Phone', 'Email', 'Walk-in', 'Referral', 'Social', 'Other'] as $s): ?>
                            <option value="<?= $s ?>"><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 form-group">
                    <label class="small font-weight-bold">Priority</label>
                    <select name="priority" id="leadPriority" class="form-control form-control-sm">
                        <?php foreach ($priorities as $p): ?>
                            <option value="<?= $p ?>"><?= $p ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-6 form-group">
                    <label class="small font-weight-bold">Stage</label>
                    <select name="stage" id="leadStage" class="form-control form-control-sm">
                        <?php foreach ($stages as $st): ?>
                            <option value="<?= $st ?>"><?= $st ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 form-group">
                    <label class="small font-weight-bold">Est. Value (NPR)</label>
                    <input type="number" name="estimated_value" id="leadValue" class="form-control form-control-sm" step="0.01" min="0" placeholder="0.00">
                </div>
            </div>
            <div class="form-group">
                <label class="small font-weight-bold">Owner</label>
                <select name="assigned_to" id="leadOwner" class="form-control form-control-sm">
                    <option value="">Unassigned</option>
                    <?php foreach ($staffs as $st): ?>
                        <option value="<?= (int) $st['id'] ?>"><?= e($st['fullname']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="leadForm" class="btn btn-primary btn-block">
            <i class="fas fa-save mr-1"></i><span id="drawerSubmitText">Save Lead</span>
        </button>
    </div>
</div>

<!-- ═══════════════════════════════════════════════ DRAWER JS ══════════ -->
<script>
var leadsData = <?= json_encode(array_values($leads)) ?>;

function openLeadDrawer(editId) {
    var drawer = document.getElementById('leadDrawer');
    var backdrop = document.getElementById('drawerBackdrop');
    drawer.classList.add('open');
    backdrop.classList.add('active');
    document.body.style.overflow = 'hidden';

    var title = document.getElementById('drawerTitle');
    var submitText = document.getElementById('drawerSubmitText');

    if (editId) {
        var lead = leadsData.find(function(l) { return l.id == editId; });
        if (lead) {
            title.textContent = 'Edit Lead';
            submitText.textContent = 'Update Lead';
            document.getElementById('leadId').value = lead.id;
            document.getElementById('leadCompany').value = lead.company || '';
            document.getElementById('leadContact').value = lead.contact_name || '';
            document.getElementById('leadEmail').value = lead.email || '';
            document.getElementById('leadPhone').value = lead.phone || '';
            document.getElementById('leadService').value = lead.service_interest || '';
            document.getElementById('leadMessage').value = lead.message || '';
            document.getElementById('leadSource').value = lead.source || 'Website';
            document.getElementById('leadPriority').value = lead.priority || 'Warm';
            document.getElementById('leadStage').value = lead.stage || 'New';
            document.getElementById('leadValue').value = lead.estimated_value || '';
            document.getElementById('leadOwner').value = lead.assigned_to || '';
        }
    } else {
        title.textContent = 'New Lead';
        submitText.textContent = 'Save Lead';
        document.getElementById('leadId').value = '0';
        document.getElementById('leadForm').reset();
    }
}

function closeLeadDrawer() {
    document.getElementById('leadDrawer').classList.remove('open');
    document.getElementById('drawerBackdrop').classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeLeadDrawer();
});
</script>
