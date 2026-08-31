<?php
/**
 * SB-Tech — HR Care / Speak Up tab (US-GRV-01).
 * Staff submit grievances with optional attachments; admins assign + set
 * status/deadline; status changes notify the author and assignee.
 */
$db = Database::instance();
$me = (int) Auth::id();
$seeAll = Auth::isSuperAdmin();

$rows = $db->select(
    'SELECT g.*, a.fullname AS author_name, s.fullname AS assigned_name
     FROM `tbl_office_grievances` g
     LEFT JOIN `tbl_users_login` a ON a.id = g.author
     LEFT JOIN `tbl_users_login` s ON s.id = g.assigned
     WHERE ' . ($seeAll ? '1=1' : '(g.author = ? OR g.assigned = ?)') . '
     ORDER BY FIELD(g.status, "Pending", "In Progress", "Acknowledged", "Done", "Rejected"), g.added_on DESC',
    $seeAll ? [] : [$me, $me]
);

$activeStaff = $db->select(
    "SELECT `id`, `fullname` FROM `tbl_users_login` WHERE `status` = 'Active' ORDER BY `fullname`"
);
$statusBadges = ['Pending' => 'warning', 'In Progress' => 'info', 'Acknowledged' => 'primary', 'Done' => 'success', 'Rejected' => 'danger'];
?>

<!-- Concerns List (full width) -->
<div class="card card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-bullhorn mr-1"></i>Concerns<?= $seeAll ? ' — all staff' : ' — mine' ?></h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary btn-sm" onclick="openDrawer()">
                <i class="fas fa-plus mr-1"></i>Raise Concern
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <?php foreach ($rows as $g): ?>
            <?php
            $updates = $db->select(
                'SELECT f.*, u.fullname AS updater
                 FROM `tbl_office_grievance_files` f
                 LEFT JOIN `tbl_users_login` u ON u.id = f.added_by
                 WHERE f.ref_id = ? AND f.type = ?
                 ORDER BY f.added_on DESC',
                [(int) $g['id'], 'Update']
            );
            $files = $db->select(
                'SELECT * FROM `tbl_office_grievance_files` WHERE ref_id = ? AND type = ? ORDER BY added_on DESC',
                [(int) $g['id'], 'grievance']
            );
            $canUpdate = $seeAll || (int) $g['author'] === $me || (int) $g['assigned'] === $me;
            $canDelete = (int) $g['author'] === $me && $g['status'] === 'Pending';
            ?>
            <div class="card card-outline card-light m-2">
                <div class="card-header py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?= e($g['title']) ?></strong>
                            <span class="badge badge-<?= $statusBadges[$g['status']] ?? 'secondary' ?> ml-1"><?= e($g['status']) ?></span>
                        </div>
                        <div class="text-right">
                            <?php if ($canDelete): ?>
                                <form action="operation.php?module=staff_management&page=hr_care" method="post" class="d-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete_grievance">
                                    <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
                                    <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete this concern?"><i class="fas fa-trash"></i></button>
                                </form>
                            <?php endif; ?>
                            <?php if ($canUpdate): ?>
                                <button type="button" class="btn btn-xs btn-outline-secondary" data-toggle="collapse" data-target="#grv-updates-<?= (int) $g['id'] ?>"><i class="fas fa-comments"></i> <?= count($updates) ?></button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="text-muted small">
                        by <?= e($g['author_name'] ?? '—') ?>
                        <?php if ($g['assigned_name']): ?> · assigned to <span class="badge badge-light border"><?= e($g['assigned_name']) ?></span><?php endif; ?>
                        <?php if ($g['deadline']): ?> · deadline <?= e(date('M j, g:i A', strtotime($g['deadline']))) ?><?php endif; ?>
                    </div>
                </div>
                <div class="card-body py-2">
                    <?php if ($g['description']): ?><p class="mb-1"><?= nl2br(e($g['description'])) ?></p><?php endif; ?>
                    <?php if ($files): ?>
                        <div class="mb-1">
                            <?php foreach ($files as $f): ?>
                                <a href="<?= assetUrl('user_uploads/' . $f['file_location']) ?>" class="badge badge-light border mr-1" target="_blank"><i class="fas fa-paperclip mr-1"></i><?= e($f['filename']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($seeAll): ?>
                        <form action="operation.php?module=staff_management&page=hr_care" method="post" class="row align-items-end bg-light p-2 rounded mt-2">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="admin_update_grievance">
                            <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
                            <div class="col-md-3">
                                <label class="small mb-0">Assignee</label>
                                <select name="assigned" class="form-control form-control-sm">
                                    <option value="">Unassigned</option>
                                    <?php foreach ($activeStaff as $st): ?>
                                        <option value="<?= (int) $st['id'] ?>" <?= (int) $g['assigned'] === (int) $st['id'] ? 'selected' : '' ?>><?= e($st['fullname']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="small mb-0">Status</label>
                                <select name="status" class="form-control form-control-sm">
                                    <?php foreach (array_keys($statusBadges) as $st): ?>
                                        <option value="<?= $st ?>" <?= $g['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="small mb-0">Deadline</label>
                                <input type="datetime-local" name="deadline" class="form-control form-control-sm" value="<?= $g['deadline'] ? e(date('Y-m-d\TH:i', strtotime($g['deadline']))) : '' ?>">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-sm btn-primary btn-block">Save</button>
                            </div>
                        </form>
                    <?php endif; ?>

                    <div class="collapse" id="grv-updates-<?= (int) $g['id'] ?>">
                        <hr class="my-2">
                        <?php if ($canUpdate): ?>
                            <form action="operation.php?module=staff_management&page=hr_care" method="post" enctype="multipart/form-data" class="row align-items-end bg-light p-2 rounded">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="post_grievance_update">
                                <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
                                <div class="col-md-7">
                                    <label class="small mb-0">Update note</label>
                                    <input type="text" name="update_text" class="form-control form-control-sm" placeholder="Progress or response">
                                </div>
                                <div class="col-md-4">
                                    <label class="small mb-0">File (optional)</label>
                                    <input type="file" name="update_file" class="form-control-file form-control-sm">
                                </div>
                                <div class="col-md-1">
                                    <button type="submit" class="btn btn-sm btn-primary btn-block"><i class="fas fa-paper-plane"></i></button>
                                </div>
                            </form>
                        <?php endif; ?>
                        <?php if ($updates): ?>
                            <ul class="list-unstyled mt-2 mb-0">
                                <?php foreach ($updates as $up): ?>
                                    <li class="media mb-1">
                                        <div class="media-body border-left pl-2">
                                            <div class="small">
                                                <strong><?= e($up['updater'] ?? 'Staff') ?></strong>
                                                <span class="text-muted"><?= e(date('M j, g:i A', strtotime($up['added_on']))) ?></span>
                                                <?php if ($up['file_location']): ?>
                                                    <a href="<?= assetUrl('user_uploads/' . $up['file_location']) ?>" target="_blank" class="ml-1"><i class="fas fa-paperclip"></i> <?= e($up['filename']) ?></a>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!$up['file_location'] && $up['filename']): ?><div class="text-muted"><?= e($up['filename']) ?></div><?php endif; ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
            <div class="text-center text-muted py-4">No concerns raised.</div>
        <?php endif; ?>
    </div>
</div>

<!-- Slide-in Drawer Backdrop -->
<div class="cms-drawer-backdrop" id="drawerBackdrop" onclick="closeDrawer()"></div>

<!-- Slide-in Drawer -->
<div class="cms-drawer" id="formDrawer">
    <div class="cms-drawer-header">
        <h3><i class="fas fa-bullhorn"></i>Raise a Concern</h3>
        <button type="button" class="cms-drawer-close" onclick="closeDrawer()" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="cms-drawer-body">
        <form action="operation.php?module=staff_management&page=hr_care" method="post" enctype="multipart/form-data" id="grievanceForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_grievance">
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Description *</label>
                <textarea name="description" class="form-control" rows="5" required></textarea>
            </div>
            <div class="form-group">
                <label>Attachment (optional)</label>
                <div class="custom-file">
                    <input type="file" class="custom-file-input" id="grievance_file" name="grievance_file">
                    <label class="custom-file-label" for="grievance_file">Choose file</label>
                </div>
            </div>
            <p class="text-muted small mb-0">Your concern is recorded with your name and handled by the administration.</p>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="grievanceForm" class="btn btn-primary btn-block">
            <i class="fas fa-paper-plane mr-1"></i>Submit
        </button>
    </div>
</div>

<script>
function openDrawer() {
    document.getElementById('formDrawer').classList.add('open');
    document.getElementById('drawerBackdrop').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeDrawer() {
    document.getElementById('formDrawer').classList.remove('open');
    document.getElementById('drawerBackdrop').classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeDrawer(); });
</script>
