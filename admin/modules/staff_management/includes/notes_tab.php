<?php
/**
 * SB-Tech — HR Care / Notes tab.
 * Personal (author-only) or assigned (invited staff + author) notes with a
 * single date and an optional expiry. Notes never use venue/hall/schedules.
 */
$db = Database::instance();
$me = (int) Auth::id();
$seeAll = Auth::isSuperAdmin();
$myUser = $db->selectOne('SELECT * FROM `tbl_users_login` WHERE `id` = ?', [$me]);

// --- Edit context (same 7-day window as meetings) ---
$editNote = null;
$editNoteSchedules = [];
$editNoteAttendees = [];
if (isset($_GET['event_id'])) {
    $editNote = $db->selectOne('SELECT * FROM `tbl_office_events` WHERE `id` = ? AND `type` = ?', [(int) $_GET['event_id'], 'Note']);
    if ($editNote) {
        $window = strtotime((string) $editNote['added_on']) + 7 * 86400;
        $isCreatorOrAdmin = $seeAll || (int) $editNote['added_by'] === $me;
        $privateDeleteOnly = !$seeAll && (int) $editNote['added_by'] !== $me;
        if (!$isCreatorOrAdmin || time() > $window || $privateDeleteOnly) {
            $editNote = null;
        }
    }
}
if ($editNote) {
    $editNoteSchedules = $db->select('SELECT * FROM `tbl_office_event_schedules` WHERE `event_id` = ? ORDER BY date LIMIT 1', [(int) $editNote['id']]);
    foreach (explode(',', (string) $editNote['attendees_staffs']) as $id) {
        if (ctype_digit(trim($id))) {
            $editNoteAttendees[] = (int) trim($id);
        }
    }
}

$departments = $db->select('SELECT * FROM `tbl_office_departments` ORDER BY position, title');
$staffs = $db->select(
    "SELECT u.id, u.fullname, u.department_id, d.title AS department_title
     FROM `tbl_users_login` u
     LEFT JOIN `tbl_office_departments` d ON d.id = u.department_id
     WHERE u.status = 'Active'
     ORDER BY u.fullname"
);

// --- Visible notes ---
[$visSql, $visParams] = eventVisibilitySql($me, $myUser, $seeAll);
$notes = $db->select(
    'SELECT e.*, u.fullname AS creator_name
     FROM `tbl_office_events` e
     JOIN `tbl_users_login` u ON u.id = e.added_by
     WHERE ' . $visSql . "
       AND e.type = 'Note'
     ORDER BY e.added_on DESC",
    $visParams
);
foreach ($notes as &$n) {
    $n['note_date'] = $db->selectOne(
        'SELECT `date` FROM `tbl_office_event_schedules` WHERE `event_id` = ? ORDER BY `date` DESC LIMIT 1',
        [(int) $n['id']]
    )['date'] ?? null;
    $attendeeIds = array_filter(array_map('intval', explode(',', (string) $n['attendees_staffs'])));
    $n['attendee_names'] = [];
    if ($attendeeIds) {
        $ids = implode(',', array_fill(0, count($attendeeIds), '?'));
        foreach ($db->select('SELECT `fullname` FROM `tbl_users_login` WHERE `id` IN (' . $ids . ')', array_values($attendeeIds)) as $an) {
            $n['attendee_names'][] = $an['fullname'];
        }
    }
}
unset($n);

$drawerOpen = ($editNote !== null);
?>

<!-- Notes List -->
<div class="card card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-sticky-note mr-1"></i>My Notes</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary btn-sm" onclick="openNoteDrawer()">
                <i class="fas fa-plus mr-1"></i>Add Note
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <?php foreach ($notes as $n): ?>
            <?php
            $withinWindow = time() <= strtotime((string) $n['added_on']) + 7 * 86400;
            $isCreator = (int) $n['added_by'] === $me;
            $canManage = ($seeAll || $isCreator) && $withinWindow;
            $expired = $n['expire_date'] && (string) $n['expire_date'] < date('Y-m-d');
            ?>
            <div class="card card-outline card-light m-2">
                <div class="card-header py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?= e($n['title']) ?></strong>
                            <?php if ($expired): ?>
                                <span class="badge badge-dark ml-1">expired</span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if ($canManage): ?>
                                <button type="button" class="btn btn-xs btn-outline-primary" onclick="openNoteDrawer(<?= (int) $n['id'] ?>)" title="Edit (7-day window)"><i class="fas fa-edit"></i></button>
                                <form action="operation.php?module=staff_management&page=hr_care" method="post" class="d-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete_event">
                                    <input type="hidden" name="event_id" value="<?= (int) $n['id'] ?>">
                                    <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete this note?"><i class="fas fa-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="card-body py-2">
                    <div class="text-muted small">
                        <div><i class="far fa-calendar mr-1"></i><?= e($n['note_date']) ?><?= $n['expire_date'] ? ' · expires ' . e($n['expire_date']) : '' ?></div>
                        <?php if ($n['attendee_names']): ?>
                            <div><i class="fas fa-users mr-1"></i>Assigned to: <?= e(implode(', ', $n['attendee_names'])) ?></div>
                        <?php else: ?>
                            <div><i class="fas fa-user mr-1"></i>Personal note</div>
                        <?php endif; ?>
                        <?php if ($n['remarks']): ?>
                            <div><i class="fas fa-align-left mr-1"></i><?= nl2br(e($n['remarks'])) ?></div>
                        <?php endif; ?>
                        <div class="mt-1">by <?= e($n['creator_name']) ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$notes): ?>
            <div class="text-center text-muted py-4">No notes yet.</div>
        <?php endif; ?>
    </div>
</div>

<!-- Slide-in Drawer Backdrop -->
<div class="cms-drawer-backdrop" id="noteBackdrop" onclick="closeNoteDrawer()"></div>

<!-- Slide-in Drawer -->
<div class="cms-drawer" id="noteDrawer">
    <div class="cms-drawer-header">
        <h3><i class="fas fa-sticky-note"></i><?= $editNote ? 'Edit Note' : 'Add Note' ?></h3>
        <button type="button" class="cms-drawer-close" onclick="closeNoteDrawer()" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="cms-drawer-body">
        <form action="operation.php?module=staff_management&page=hr_care" method="post" id="noteForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_event">
            <input type="hidden" name="type" value="Note">
            <input type="hidden" name="redirect" value="notes">
            <input type="hidden" name="event_id" id="noteId" value="<?= $editNote ? (int) $editNote['id'] : 0 ?>">
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" class="form-control" id="noteTitle" required value="<?= $editNote ? e($editNote['title']) : '' ?>">
            </div>
            <div class="form-row">
                <div class="col-6 form-group">
                    <label>Date *</label>
                    <input type="date" name="note_date" class="form-control" id="noteDate" required value="<?= $editNote && isset($editNoteSchedules[0]) ? e($editNoteSchedules[0]['date']) : '' ?>">
                </div>
                <div class="col-6 form-group">
                    <label>Expire date</label>
                    <input type="date" name="expire_date" class="form-control" id="noteExpireDate" value="<?= $editNote ? e($editNote['expire_date']) : '' ?>">
                    <small class="text-muted">Leave empty to keep it visible. Notes added from the calendar expire on that date automatically.</small>
                </div>
            </div>
            <div class="form-group">
                <label>Assign to (optional)</label>
                <select class="form-control mb-2" id="noteAttDeptFilter">
                    <option value="">All departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int) $d['id'] ?>"><?= e($d['title']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="custom-control custom-checkbox mb-2">
                    <input type="checkbox" class="custom-control-input" id="noteAttSelectAll">
                    <label class="custom-control-label small" for="noteAttSelectAll">Select all visible staff</label>
                </div>
                <select name="attendees[]" class="form-control" id="noteAttSelect" size="5" multiple>
                    <?php foreach ($staffs as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" data-dept="<?= (int) $s['department_id'] ?>" <?= in_array((int) $s['id'], $editNoteAttendees, true) ? 'selected' : '' ?>><?= e($s['fullname']) ?><?= $s['department_title'] ? ' (' . e($s['department_title']) . ')' : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">Empty = personal note (only you see it). Assigned notes are shared with those staff.</small>
            </div>
            <div class="form-group">
                <label>Remarks</label>
                <textarea name="remarks" class="form-control" id="noteRemarks" rows="3"><?= $editNote ? e($editNote['remarks']) : '' ?></textarea>
            </div>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="noteForm" class="btn btn-primary btn-block">
            <i class="fas fa-save mr-1"></i><?= $editNote ? 'Update' : 'Add Note' ?>
        </button>
    </div>
</div>

<script>
var notesData = <?= json_encode(array_values($notes)) ?>;

function openNoteDrawer(editId) {
    var drawer = document.getElementById('noteDrawer');
    var backdrop = document.getElementById('noteBackdrop');
    drawer.classList.add('open');
    backdrop.classList.add('active');
    document.body.style.overflow = 'hidden';

    var title = drawer.querySelector('.cms-drawer-header h3');
    var attSelect = document.getElementById('noteAttSelect');
    if (editId) {
        var n = notesData.find(function(x) { return x.id == editId; });
        if (n) {
            title.innerHTML = '<i class="fas fa-sticky-note"></i>Edit Note';
            document.getElementById('noteId').value = n.id;
            document.getElementById('noteTitle').value = n.title;
            document.getElementById('noteDate').value = n.note_date || '';
            document.getElementById('noteExpireDate').value = n.expire_date || '';
            document.getElementById('noteRemarks').value = n.remarks || '';
            var invited = (n.attendees_staffs || '').split(',').map(Number).filter(Boolean);
            Array.prototype.forEach.call(attSelect.options, function(o) {
                o.selected = invited.indexOf(Number(o.value)) !== -1;
                o.hidden = false;
            });
        }
    } else {
        title.innerHTML = '<i class="fas fa-sticky-note"></i>Add Note';
        document.getElementById('noteId').value = '0';
        document.getElementById('noteTitle').value = '';
        document.getElementById('noteDate').value = new Date().toISOString().slice(0, 10);
        document.getElementById('noteExpireDate').value = '';
        document.getElementById('noteRemarks').value = '';
        Array.prototype.forEach.call(attSelect.options, function(o) { o.selected = false; o.hidden = false; });
    }
    document.getElementById('noteAttDeptFilter').value = '';
    document.getElementById('noteAttSelectAll').checked = false;
}

function closeNoteDrawer() {
    document.getElementById('noteDrawer').classList.remove('open');
    document.getElementById('noteBackdrop').classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeNoteDrawer(); });

<?php if ($drawerOpen): ?>
document.addEventListener('DOMContentLoaded', function() { openNoteDrawer(<?= (int) $editNote['id'] ?>); });
<?php endif; ?>

// --- Attendee picker: department filter + select-all ---
(function () {
    var attSelect = document.getElementById('noteAttSelect');
    var deptFilter = document.getElementById('noteAttDeptFilter');
    var selectAll = document.getElementById('noteAttSelectAll');

    deptFilter.addEventListener('change', function () {
        var dept = deptFilter.value;
        Array.prototype.forEach.call(attSelect.options, function (o) {
            o.hidden = dept !== '' && o.getAttribute('data-dept') !== String(dept);
            o.selected = false;
        });
        selectAll.checked = false;
    });
    selectAll.addEventListener('change', function () {
        Array.prototype.forEach.call(attSelect.options, function (o) {
            if (!o.hidden) { o.selected = selectAll.checked; }
        });
    });
})();
</script>