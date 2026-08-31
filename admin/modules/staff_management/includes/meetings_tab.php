<?php
/**
 * SB-Tech — HR Care / Meetings tab (US-MTG-01, AC-MTG-01.x).
 * Create/edit meetings & events with multiple date+time schedules, privacy
 * (Public → all or one department; Private → invited staff), venue (meeting
 * hall or out-of-office), and a free-staff picker that excludes staff
 * already booked at the same slot (validated server-side).
 */
$db = Database::instance();
$me = (int) Auth::id();
$seeAll = Auth::isSuperAdmin();
$myUser = $db->selectOne('SELECT * FROM `tbl_users_login` WHERE `id` = ?', [$me]);

// --- Edit context ---
$editEvent = null;
$editSchedules = [];
$editAttendees = [];
if (isset($_GET['event_id'])) {
    $editEvent = $db->selectOne('SELECT * FROM `tbl_office_events` WHERE `id` = ?', [(int) $_GET['event_id']]);
    if ($editEvent) {
        $window = strtotime((string) $editEvent['added_on']) + 7 * 86400;
        $isCreatorOrAdmin = $seeAll || (int) $editEvent['added_by'] === $me;
        $privateDeleteOnly = $editEvent['privacy'] === 'Private' && !$seeAll && (int) $editEvent['added_by'] !== $me;
        if (!$isCreatorOrAdmin || time() > $window || $privateDeleteOnly) {
            $editEvent = null;
        }
    }
}
if ($editEvent) {
    $editSchedules = $db->select('SELECT * FROM `tbl_office_event_schedules` WHERE `event_id` = ? ORDER BY date, from_time', [(int) $editEvent['id']]);
    foreach (explode(',', (string) $editEvent['attendees_staffs']) as $id) {
        if (ctype_digit(trim($id))) {
            $editAttendees[] = (int) trim($id);
        }
    }
}

$halls = $db->select('SELECT * FROM `tbl_office_meeting_hall_setup` ORDER BY hall_name');
$departments = $db->select('SELECT * FROM `tbl_office_departments` ORDER BY position, title');
$staffs = $db->select(
    "SELECT u.id, u.fullname, u.department_id, d.title AS department_title
     FROM `tbl_users_login` u
     LEFT JOIN `tbl_office_departments` d ON d.id = u.department_id
     WHERE u.status = 'Active'
     ORDER BY u.fullname"
);

// --- Visible upcoming events (AC-MTG-02.2) ---
[$visSql, $visParams] = eventVisibilitySql($me, $myUser, $seeAll);
$events = $db->select(
    'SELECT e.*, u.fullname AS creator_name
     FROM `tbl_office_events` e
     JOIN `tbl_users_login` u ON u.id = e.added_by
     WHERE ' . $visSql . '
       AND EXISTS (
           SELECT 1 FROM `tbl_office_event_schedules` s WHERE s.event_id = e.id AND s.date >= ?
       )
     ORDER BY e.added_on DESC',
    array_merge($visParams, [date('Y-m-d')])
);
foreach ($events as &$ev) {
    $ev['schedules'] = $db->select(
        'SELECT * FROM `tbl_office_event_schedules` WHERE `event_id` = ? ORDER BY date, from_time',
        [(int) $ev['id']]
    );
    $ev['next_date'] = min(array_map(fn ($s) => (string) $s['date'], $ev['schedules']) ?: [date('Y-m-d')]);
    $attendeeIds = array_filter(array_map('intval', explode(',', (string) $ev['attendees_staffs'])));
    $ev['attendee_names'] = [];
    if ($attendeeIds) {
        $ids = implode(',', array_fill(0, count($attendeeIds), '?'));
        foreach ($db->select('SELECT `fullname` FROM `tbl_users_login` WHERE `id` IN (' . $ids . ')', array_values($attendeeIds)) as $an) {
            $ev['attendee_names'][] = $an['fullname'];
        }
    }
}
unset($ev);
usort($events, fn ($a, $b) => strcmp($a['next_date'], $b['next_date']));

$drawerOpen = ($editEvent !== null);
?>

<!-- Events List (full width) -->
<div class="card card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-calendar-alt mr-1"></i>Upcoming Meetings & Events</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary btn-sm" onclick="openDrawer()">
                <i class="fas fa-plus mr-1"></i>Schedule Meeting
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <?php foreach ($events as $ev): ?>
            <?php
            $withinWindow = time() <= strtotime((string) $ev['added_on']) + 7 * 86400;
            $isCreator = (int) $ev['added_by'] === $me;
            $canManage = ($seeAll || $isCreator) && $withinWindow
                && !($ev['privacy'] === 'Private' && !$seeAll && !$isCreator);
            ?>
            <div class="card card-outline card-light m-2">
                <div class="card-header py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?= e($ev['title']) ?></strong>
                            <span class="badge badge-<?= $ev['type'] === 'Meeting' ? 'primary' : 'success' ?> ml-1"><?= e($ev['type']) ?></span>
                            <span class="badge badge-<?= $ev['privacy'] === 'Public' ? 'info' : 'secondary' ?> ml-1"><?= e($ev['privacy']) ?></span>
                        </div>
                        <div>
                            <?php if ($canManage): ?>
                                <button type="button" class="btn btn-xs btn-outline-primary" onclick="openDrawer(<?= (int) $ev['id'] ?>)" title="Edit (7-day window)"><i class="fas fa-edit"></i></button>
                                <form action="operation.php?module=staff_management&page=hr_care" method="post" class="d-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete_event">
                                    <input type="hidden" name="event_id" value="<?= (int) $ev['id'] ?>">
                                    <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete this event?"><i class="fas fa-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="card-body py-2">
                    <div class="text-muted small">
                        <?php foreach ($ev['schedules'] as $sch): ?>
                            <div><i class="far fa-clock mr-1"></i><?= e(scheduleLine($sch)) ?></div>
                        <?php endforeach; ?>
                        <div><i class="fas fa-map-marker-alt mr-1"></i><?= e($ev['venue_type']) ?><?= $ev['venue_location'] ? ' — ' . e($ev['venue_location']) : '' ?></div>
                        <?php if ($ev['attendee_names']): ?>
                            <div><i class="fas fa-users mr-1"></i><?= e(implode(', ', $ev['attendee_names'])) ?></div>
                        <?php endif; ?>
                        <?php if ($ev['other_attendees']): ?>
                            <div><i class="fas fa-user-plus mr-1"></i><?= e($ev['other_attendees']) ?></div>
                        <?php endif; ?>
                        <?php if ($ev['remarks']): ?>
                            <div><i class="fas fa-sticky-note mr-1"></i><?= nl2br(e($ev['remarks'])) ?></div>
                        <?php endif; ?>
                        <div class="mt-1">by <?= e($ev['creator_name']) ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$events): ?>
            <div class="text-center text-muted py-4">No upcoming meetings or events.</div>
        <?php endif; ?>
    </div>
</div>

<!-- Slide-in Drawer Backdrop -->
<div class="cms-drawer-backdrop" id="drawerBackdrop" onclick="closeDrawer()"></div>

<!-- Slide-in Drawer -->
<div class="cms-drawer" id="eventDrawer">
    <div class="cms-drawer-header">
        <h3><i class="fas fa-calendar-plus"></i><?= $editEvent ? 'Edit Event' : 'Schedule Meeting / Event' ?></h3>
        <button type="button" class="cms-drawer-close" onclick="closeDrawer()" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="cms-drawer-body">
        <form action="operation.php?module=staff_management&page=hr_care" method="post" id="eventForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_event">
            <input type="hidden" name="event_id" id="eventId" value="<?= $editEvent ? (int) $editEvent['id'] : 0 ?>">
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" class="form-control" id="eventTitle" required value="<?= $editEvent ? e($editEvent['title']) : '' ?>">
            </div>
            <div class="form-row">
                <div class="col-6 form-group">
                    <label>Type</label>
                    <select name="type" id="event_type" class="form-control">
                        <option value="Meeting" <?= $editEvent && $editEvent['type'] === 'Meeting' ? 'selected' : '' ?>>Meeting</option>
                        <option value="Event" <?= $editEvent && $editEvent['type'] === 'Event' ? 'selected' : '' ?>>Event</option>
                    </select>
                </div>
                <div class="col-6 form-group">
                    <label>Privacy</label>
                    <select name="privacy" id="event_privacy" class="form-control">
                        <option value="Public" <?= !$editEvent || $editEvent['privacy'] === 'Public' ? 'selected' : '' ?>>Public</option>
                        <option value="Private" <?= $editEvent && $editEvent['privacy'] === 'Private' ? 'selected' : '' ?>>Private</option>
                    </select>
                </div>
            </div>
            <div class="form-group" id="dept_group">
                <label>Visible to department (Public only)</label>
                <select name="attendees_department" class="form-control">
                    <option value="">All departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int) $d['id'] ?>" <?= $editEvent && (int) $editEvent['attendees_department'] === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" id="attendee_group">
                <label>Invite staff (Private) *</label>
                <select name="attendees[]" class="form-control" size="4" multiple>
                    <?php foreach ($staffs as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= in_array((int) $s['id'], $editAttendees, true) ? 'selected' : '' ?>><?= e($s['fullname']) ?><?= $s['department_title'] ? ' (' . e($s['department_title']) . ')' : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">Staff already booked at the same slot are excluded from new bookings.</small>
            </div>
            <div class="form-group">
                <label>Other attendees (external)</label>
                <input type="text" name="other_attendees" class="form-control" placeholder="e.g. Client names" value="<?= $editEvent ? e($editEvent['other_attendees']) : '' ?>">
            </div>
            <div class="form-row">
                <div class="col-6 form-group">
                    <label>Venue</label>
                    <select name="venue_type" id="venue_type" class="form-control">
                        <option value="In Office" <?= !$editEvent || $editEvent['venue_type'] === 'In Office' ? 'selected' : '' ?>>In Office</option>
                        <option value="Out of Office" <?= $editEvent && $editEvent['venue_type'] === 'Out of Office' ? 'selected' : '' ?>>Out of Office</option>
                    </select>
                </div>
                <div class="col-6 form-group" id="hall_group">
                    <label>Meeting hall</label>
                    <select name="venue_location" class="form-control">
                        <option value="">—</option>
                        <?php foreach ($halls as $h): ?>
                            <option value="<?= e($h['hall_name']) ?>" <?= $editEvent && $editEvent['venue_location'] === $h['hall_name'] ? 'selected' : '' ?>><?= e($h['hall_name']) ?> (<?= (int) $h['occupancy'] ?> seats)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 form-group" id="location_group" style="display:none">
                    <label>Location</label>
                    <input type="text" name="venue_location_text" class="form-control" placeholder="Address / venue name">
                </div>
            </div>
            <div class="form-group">
                <label>Schedules (one or more) *</label>
                <div id="schedule_rows">
                    <?php if ($editSchedules): ?>
                        <?php foreach ($editSchedules as $i => $sch): ?>
                            <div class="row mb-1 schedule-row">
                                <div class="col-4"><input type="date" name="date[]" class="form-control form-control-sm" required value="<?= e($sch['date']) ?>"></div>
                                <div class="col-3"><input type="time" name="from_time[]" class="form-control form-control-sm" value="<?= e($sch['from_time']) ?>"></div>
                                <div class="col-3"><input type="time" name="to_time[]" class="form-control form-control-sm" value="<?= e($sch['to_time']) ?>"></div>
                                <div class="col-2"><button type="button" class="btn btn-sm btn-outline-danger remove-schedule"><i class="fas fa-times"></i></button></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="row mb-1 schedule-row">
                            <div class="col-4"><input type="date" name="date[]" class="form-control form-control-sm" required></div>
                            <div class="col-3"><input type="time" name="from_time[]" class="form-control form-control-sm"></div>
                            <div class="col-3"><input type="time" name="to_time[]" class="form-control form-control-sm"></div>
                            <div class="col-2"><button type="button" class="btn btn-sm btn-outline-danger remove-schedule"><i class="fas fa-times"></i></button></div>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary mt-1" id="add_schedule"><i class="fas fa-plus mr-1"></i>Add schedule</button>
            </div>
            <div class="form-group">
                <label>Remarks</label>
                <textarea name="remarks" class="form-control" id="eventRemarks" rows="2"><?= $editEvent ? e($editEvent['remarks']) : '' ?></textarea>
            </div>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="eventForm" class="btn btn-primary btn-block">
            <i class="fas fa-save mr-1"></i><?= $editEvent ? 'Update' : 'Schedule' ?>
        </button>
    </div>
</div>

<script>
var eventsData = <?= json_encode(array_values($events)) ?>;

function openDrawer(editId) {
    var drawer = document.getElementById('eventDrawer');
    var backdrop = document.getElementById('drawerBackdrop');
    drawer.classList.add('open');
    backdrop.classList.add('active');
    document.body.style.overflow = 'hidden';

    var title = drawer.querySelector('.cms-drawer-header h3');
    if (editId) {
        var ev = eventsData.find(function(e) { return e.id == editId; });
        if (ev) {
            title.innerHTML = '<i class="fas fa-calendar-plus"></i>Edit Event';
            document.getElementById('eventId').value = ev.id;
            document.getElementById('eventTitle').value = ev.title;
            document.getElementById('event_type').value = ev.type;
            document.getElementById('event_privacy').value = ev.privacy;
            document.getElementById('eventRemarks').value = ev.remarks || '';
            // Trigger refresh for venue type etc.
            document.getElementById('event_type').dispatchEvent(new Event('change'));
            document.getElementById('event_privacy').dispatchEvent(new Event('change'));
            document.getElementById('venue_type').dispatchEvent(new Event('change'));
        }
    } else {
        title.innerHTML = '<i class="fas fa-calendar-plus"></i>Schedule Meeting / Event';
        document.getElementById('eventId').value = '0';
        document.getElementById('eventTitle').value = '';
        document.getElementById('event_type').value = 'Meeting';
        document.getElementById('event_privacy').value = 'Public';
        document.getElementById('eventRemarks').value = '';
        // Reset schedule rows to one
        var rows = document.getElementById('schedule_rows');
        rows.innerHTML = '<div class="row mb-1 schedule-row"><div class="col-4"><input type="date" name="date[]" class="form-control form-control-sm" required></div><div class="col-3"><input type="time" name="from_time[]" class="form-control form-control-sm"></div><div class="col-3"><input type="time" name="to_time[]" class="form-control form-control-sm"></div><div class="col-2"><button type="button" class="btn btn-sm btn-outline-danger remove-schedule"><i class="fas fa-times"></i></button></div></div>';
        document.getElementById('event_type').dispatchEvent(new Event('change'));
        document.getElementById('event_privacy').dispatchEvent(new Event('change'));
        document.getElementById('venue_type').dispatchEvent(new Event('change'));
    }
}

function closeDrawer() {
    document.getElementById('eventDrawer').classList.remove('open');
    document.getElementById('drawerBackdrop').classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeDrawer(); });

<?php if ($drawerOpen): ?>
document.addEventListener('DOMContentLoaded', function() { openDrawer(<?= (int) $editEvent['id'] ?>); });
<?php endif; ?>

// --- Inline JS for venue/privacy toggling + schedule rows ---
(function () {
    var typeSel = document.getElementById('event_type');
    var privSel = document.getElementById('event_privacy');
    var deptGroup = document.getElementById('dept_group');
    var attGroup = document.getElementById('attendee_group');
    var venueType = document.getElementById('venue_type');
    var hallGroup = document.getElementById('hall_group');
    var locGroup = document.getElementById('location_group');

    function refresh() {
        var type = typeSel.value;
        var priv = privSel.value;
        if (type === 'Event') { privSel.value = 'Public'; priv = 'Public'; privSel.disabled = true; }
        else { privSel.disabled = false; }
        deptGroup.style.display = priv === 'Public' ? '' : 'none';
        attGroup.style.display = priv === 'Private' ? '' : 'none';
        var isOffice = venueType.value === 'In Office';
        hallGroup.style.display = isOffice ? '' : 'none';
        locGroup.style.display = isOffice ? 'none' : '';
    }
    [typeSel, privSel, venueType].forEach(function (el) { el.addEventListener('change', refresh); });
    refresh();

    function addRow() {
        var rows = document.getElementById('schedule_rows');
        var div = document.createElement('div');
        div.className = 'row mb-1 schedule-row';
        div.innerHTML = '<div class="col-4"><input type="date" name="date[]" class="form-control form-control-sm" required></div>' +
            '<div class="col-3"><input type="time" name="from_time[]" class="form-control form-control-sm"></div>' +
            '<div class="col-3"><input type="time" name="to_time[]" class="form-control form-control-sm"></div>' +
            '<div class="col-2"><button type="button" class="btn btn-sm btn-outline-danger remove-schedule"><i class="fas fa-times"></i></button></div>';
        rows.appendChild(div);
    }
    document.getElementById('add_schedule').addEventListener('click', addRow);
    document.addEventListener('click', function (e) {
        if (e.target.closest('.remove-schedule')) {
            var rows = document.querySelectorAll('#schedule_rows .schedule-row');
            if (rows.length > 1) { e.target.closest('.schedule-row').remove(); }
        }
    });
})();
</script>
