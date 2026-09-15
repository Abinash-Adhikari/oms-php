<?php
/**
 * SB-Tech — My Office / Office Calendar (US-MTG-02).
 * Month view with event day-cells, prev/next navigation, today marker and an
 * upcoming panel. Visibility per AC-MTG-02.2 (own + Public-all +
 * Public-my-department + Private-invited). BS toggle falls back to AD while
 * the Nepali calendar table is unseeded.
 */
$db = Database::instance();
$me = (int) Auth::id();
$seeAll = Auth::isSuperAdmin();
$myUser = $db->selectOne('SELECT * FROM `tbl_users_login` WHERE `id` = ?', [$me]);

// Quick-add options on a date cell are permission-gated: events/meetings/notes
// reuse the HR Care grant, personal to-dos reuse the calendar grant, holidays
// reuse the holidays grant.
$canAddEvent = Auth::can('staff_management', 'hr_care');
$canAddTodo = Auth::can('my_office', 'office_calendar');
$canAddHoliday = Auth::can('office_setup', 'holidays');
$canAddAny = $canAddEvent || $canAddTodo || $canAddHoliday;

$calMode = (string) ($_GET['cal'] ?? (useBsDates() ? 'BS' : 'AD'));
if (!in_array($calMode, ['AD', 'BS'], true)) {
    $calMode = 'AD';
}
$today = date('Y-m-d');
$bsAvailable = bsCalendarAvailable();
$isBs = ($calMode === 'BS' && $bsAvailable);

$rawYm = (string) ($_GET['ym'] ?? '');
$ymOk = preg_match('/^\d{4}-\d{2}$/', $rawYm) === 1;

if ($isBs) {
    $todayBs = adToBs($today);
    if (!$ymOk || bsToAd($rawYm . '-01') === null) {
        $ym = $todayBs ? substr($todayBs, 0, 7) : date('Y-m');
    } else {
        $ym = $rawYm;
    }
} else {
    $ym = $ymOk ? $rawYm : date('Y-m');
}
[$year, $month] = array_map('intval', explode('-', $ym));

if ($isBs) {
    // BS month: day count + AD start come from the seeded Nepali calendar.
    $bsMonth = $db->selectOne(
        'SELECT `no_days` AS days, `eng_start_date` AS start_date
         FROM `tbl_calendar`
         WHERE `nepali_year` = ? AND `month_code` = ? LIMIT 1',
        [$year, $month]
    );
    $daysInMonth = $bsMonth ? (int) $bsMonth['days'] : 0;
    $startDow = $bsMonth ? (int) date('w', strtotime($bsMonth['start_date'])) : 0;
    $headerTitle = $year . ' ' . bsMonthName($month);
    [$py, $pm] = $month === 1 ? [$year - 1, 12] : [$year, $month - 1];
    [$ny, $nm] = $month === 12 ? [$year + 1, 1] : [$year, $month + 1];
    $prev = sprintf('%04d-%02d', $py, $pm);
    $next = sprintf('%04d-%02d', $ny, $nm);
    if (bsToAd($prev . '-01') === null) { $prev = $ym; }
    if (bsToAd($next . '-01') === null) { $next = $ym; }
    // AD window this BS month covers — events are stored in AD.
    $cStart = $bsMonth ? $bsMonth['start_date'] : $today;
    $cEnd = date('Y-m-d', strtotime($cStart) + (max(1, $daysInMonth) - 1) * 86400);
} else {
    $firstDay = mktime(0, 0, 0, $month, 1, $year);
    $daysInMonth = (int) date('t', $firstDay);
    $startDow = (int) date('w', $firstDay); // 0 = Sunday
    $headerTitle = date('F Y', $firstDay);
    $prev = date('Y-m', mktime(0, 0, 0, $month - 1, 1, $year));
    $next = date('Y-m', mktime(0, 0, 0, $month + 1, 1, $year));
}

// Toggle friendliness: each calendar button keeps its own month, so switching
// AD ↔ BS jumps to the equivalent month instead of off-range dates.
$adLinkYm = $isBs
    ? substr(bsToAd(sprintf('%04d-%02d-15', $year, $month)) ?? $today, 0, 7)
    : $ym;
$bsLinkYm = $isBs
    ? $ym
    : substr(adToBs(sprintf('%04d-%02d-15', $year, $month)) ?? $today, 0, 7);

// Events visible to this user within the visible month window.
[$visSql, $visParams] = eventVisibilitySql($me, $myUser, $seeAll);
if ($isBs) {
    $dateWhere = 's.date BETWEEN ? AND ?';
    $dateParams = [$cStart, $cEnd];
} else {
    $dateWhere = 's.date LIKE ?';
    $dateParams = [$ym . '%'];
}
$eventRows = $db->select(
    'SELECT e.id AS event_id, e.title, e.type, e.privacy, e.added_by, s.date AS sched_date, s.from_time, s.to_time
     FROM `tbl_office_event_schedules` s
     JOIN `tbl_office_events` e ON e.id = s.event_id
     WHERE ' . $visSql . ' AND ' . $dateWhere . '
     ORDER BY s.date, s.from_time',
    array_merge($visParams, $dateParams)
);
$byDay = [];
foreach ($eventRows as $er) {
    $byDay[$er['sched_date']][] = $er;
}

// ── Day-modal dataset ────────────────────────────────────────────────────────
// Clicking a date shows everything already added that day (events/meetings/
// notes visible to this user, holidays, personal to-dos) plus "add" buttons.
// The event/meeting/note form mirrors the HR Care "Meetings" tab layout.

$halls = $db->select('SELECT `id`, `hall_name` FROM `tbl_office_meeting_hall_setup` ORDER BY `hall_name`');
$departments = $db->select('SELECT `id`, `title` FROM `tbl_office_departments` ORDER BY `position`, `title`');
$staffs = $db->select(
    "SELECT u.`id`, u.`fullname`, u.`department_id`, d.`title` AS department_title
     FROM `tbl_users_login` u
     LEFT JOIN `tbl_office_departments` d ON d.id = u.department_id
     WHERE u.`status` = ? AND u.`fullname` IS NOT NULL AND u.`fullname` <> ?
     ORDER BY u.`fullname`",
    ['Active', '']
);
$staffDepts = [];
foreach ($staffs as $s) {
    if ((int) $s['department_id'] > 0) {
        $staffDepts[(int) $s['department_id']] = (string) $s['department_title'];
    }
}

if ($isBs) {
    $winStart = $cStart;
    $winEnd = $cEnd;
} else {
    $winStart = sprintf('%04d-%02d-01', $year, $month);
    $winEnd = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));
}

// Holidays expanded across the visible window.
$holidayRows = $db->select(
    'SELECT `id`, `title`, `from_date`, `to_date`, `remarks`
     FROM `tbl_office_holidays`
     WHERE `from_date` <= ? AND `to_date` >= ?',
    [$winEnd, $winStart]
);
$holidaysByDay = [];
foreach ($holidayRows as $h) {
    $hStart = ($h['from_date'] > $winStart) ? $h['from_date'] : $winStart;
    $hEnd = ($h['to_date'] < $winEnd) ? $h['to_date'] : $winEnd;
    for ($d = $hStart; $d <= $hEnd; $d = date('Y-m-d', strtotime($d) + 86400)) {
        $holidaysByDay[$d][] = $h;
    }
}

// Personal to-dos — creator or Super Admin only.
if ($seeAll) {
    $todoScope = '1 = 1';
    $todoParams = [$winStart, $winEnd];
} else {
    $todoScope = 't.added_by = ?';
    $todoParams = [$me, $winStart, $winEnd];
}
$todoRows = $db->select(
    'SELECT t.`id`, t.`title`, t.`todo_date`, t.`todo_time`, t.`remarks`, t.`completed`, t.`added_by`
     FROM `tbl_office_todos` t
     WHERE ' . $todoScope . ' AND t.`todo_date` BETWEEN ? AND ?
     ORDER BY t.`todo_date`, t.`todo_time`',
    $todoParams
);
$todosByDay = [];
foreach ($todoRows as $t) {
    $todosByDay[$t['todo_date']][] = $t;
}

$dayData = [];

// Events — visibility already filtered by eventVisibilitySql(); a delete
// button is offered only when this user could actually delete the row.
foreach ($byDay as $date => $evs) {
    $withinWindow = strtotime($date) >= strtotime($today) - 6 * 86400
                 && strtotime($date) <= strtotime($today) + 6 * 86400;
    foreach ($evs as $ev) {
        $isOwn = (int) ($ev['added_by'] ?? -1) === $me;
        $manageable = $seeAll || ($isOwn && $withinWindow && $ev['privacy'] !== 'Private');
        $dayData[$date][] = [
            'kind'    => 'event',
            'id'      => (int) $ev['event_id'],
            'title'   => $ev['title'],
            'type'    => $ev['type'],
            'privacy' => $ev['privacy'],
            'time'    => $ev['from_time'] ? date('g:i A', strtotime($ev['from_time'])) : '',
            'managed' => $canAddEvent && $manageable,
        ];
    }
}

foreach ($holidaysByDay as $date => $hs) {
    foreach ($hs as $h) {
        $dayData[$date][] = [
            'kind'    => 'holiday',
            'id'      => (int) $h['id'],
            'title'   => $h['title'],
            'remarks' => $h['remarks'] ?? '',
            'managed' => $canAddHoliday,
        ];
    }
}

foreach ($todosByDay as $date => $ts) {
    foreach ($ts as $t) {
        $dayData[$date][] = [
            'kind'      => 'todo',
            'id'        => (int) $t['id'],
            'title'     => $t['title'],
            'time'      => $t['todo_time'] ? date('g:i A', strtotime($t['todo_time'])) : '',
            'completed' => (int) $t['completed'] === 1,
            'managed'   => $seeAll || (int) $t['added_by'] === $me,
        ];
    }
}

// Weekly off days selected in the profile — shown red on the calendar.
$offDays = [];
$offProfile = $db->selectOne('SELECT `weekly_off_days` FROM `tbl_office_profiles` WHERE `id` = 1');
if ($offProfile) {
    $decoded = json_decode((string) $offProfile['weekly_off_days'], true);
    if (is_array($decoded)) {
        $offDays = array_values(array_filter($decoded, 'is_string'));
    }
}

// Upcoming panel: next visible events from today onward.
$upcoming = $db->select(
    'SELECT e.id AS event_id, e.title, e.type, e.privacy, s.date AS sched_date, s.from_time, s.to_time,
            u.fullname AS creator_name
     FROM `tbl_office_event_schedules` s
     JOIN `tbl_office_events` e ON e.id = s.event_id
     JOIN `tbl_users_login` u ON u.id = e.added_by
     WHERE ' . $visSql . ' AND s.date >= ?
     ORDER BY s.date, s.from_time
     LIMIT 12',
    array_merge($visParams, [$today])
);

$weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
$bsNote = $calMode === 'BS' && !$bsAvailable ? '<div class="alert alert-warning py-1 small">The Nepali (BS) calendar is not seeded yet — showing AD dates.</div>' : '';
?>

<div class="row">
    <div class="col-md-9">
        <div class="card card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-calendar-alt mr-1"></i><?= e($headerTitle) ?></h3>
                <div class="card-tools">
                    <a href="<?= pageUrl('my_office', 'office_calendar') ?>&ym=<?= $prev ?>&cal=<?= $calMode ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-chevron-left"></i></a>
                    <a href="<?= pageUrl('my_office', 'office_calendar') ?>&cal=<?= $calMode ?>" class="btn btn-sm btn-outline-secondary">Today</a>
                    <a href="<?= pageUrl('my_office', 'office_calendar') ?>&ym=<?= $next ?>&cal=<?= $calMode ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-chevron-right"></i></a>
                    <a href="<?= pageUrl('staff_management', 'hr_care') ?>&tab=meetings" class="btn btn-sm btn-primary ml-1"><i class="fas fa-plus mr-1"></i>New</a>
                </div>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <a href="<?= pageUrl('my_office', 'office_calendar') ?>&ym=<?= $adLinkYm ?>&cal=AD" class="btn btn-xs <?= $calMode === 'AD' || !$bsAvailable ? 'btn-primary' : 'btn-default' ?>">AD</a>
                    <a href="<?= pageUrl('my_office', 'office_calendar') ?>&ym=<?= $bsLinkYm ?>&cal=BS" class="btn btn-xs <?= $calMode === 'BS' && $bsAvailable ? 'btn-primary' : 'btn-default' ?>">BS</a>
                </div>
                <?= $bsNote ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm text-center mb-0">
                        <thead>
                            <tr>
                                <?php foreach ($weekdays as $wd): ?><th class="bg-light"><?= $wd ?></th><?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $cell = 0 - $startDow;
                        $totalCells = (int) (ceil(($startDow + $daysInMonth) / 7) * 7);
                        for ($row = 0; $row * 7 < $totalCells; $row++):
                            echo '<tr>';
                            for ($col = 0; $col < 7; $col++):
                                $dayNum = $cell + 1;
                                $cell++;
                                if ($dayNum < 1 || $dayNum > $daysInMonth):
                                    echo '<td class="bg-light"></td>';
                                    continue;
                                endif;
                                $dateKey = $isBs
                                    ? (string) bsToAd(sprintf('%04d-%02d-%02d', $year, $month, $dayNum))
                                    : sprintf('%04d-%02d-%02d', $year, $month, $dayNum);
                                // Secondary date caption (BS view → AD date, AD view → BS date).
                                // Show the day only; include the month name only on the 1st. Never the year.
                                $altCaption = '';
                                if ($isBs) {
                                    if ($dateKey !== '') {
                                        $altDay = (int) date('j', strtotime($dateKey));
                                        $altCaption = $altDay . ($altDay === 1 ? ' ' . date('M', strtotime($dateKey)) : '');
                                    }
                                } else {
                                    $bsKey = adToBs($dateKey);
                                    if ($bsKey !== null && preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $bsKey, $bm)) {
                                        $altDay = (int) $bm[3];
                                        $altCaption = $altDay . ($altDay === 1 ? ' ' . bsMonthName((int) $bm[2]) : '');
                                    }
                                }
                                $isToday = $dateKey === $today;
                                $dayName = date('l', strtotime($dateKey));
                                $isOffDay = in_array($dayName, $offDays, true);
                                $isClickable = $dateKey !== '';
                                $evs = $byDay[$dateKey] ?? [];
                                ?>
                                <td class="align-top position-relative <?= $isOffDay ? 'table-danger' : ($isToday ? 'bg-primary-light' : '') ?><?= $isClickable ? ' cal-day-clickable' : '' ?>" data-date="<?= $dateKey !== '' ? e($dateKey) : '' ?>" role="<?= $isClickable ? 'button' : '' ?> " style="height:88px;border:1px solid #dee2e6<?= $isToday ? ';border-left:3px solid #2563eb' : '' ?>" title="<?= $isOffDay ? e('Weekly off: ' . $dayName) : ($isClickable ? 'View or add on ' . e($dateKey) : '') ?>">
                                    <span class="d-block text-center <?= $isOffDay ? 'text-danger font-weight-bold' : ($isToday ? 'badge badge-primary' : 'text-muted') ?>" style="font-size:1.5rem;line-height:1.4"><?= $dayNum ?></span>
                                    <?php if ($isToday): ?><small class="badge badge-pill badge-primary" style="font-size:.55rem">Today</small><?php endif; ?>
                                    <?php foreach (array_slice($evs, 0, 3) as $ev): ?>
                                        <div class="text-left small <?= $ev['type'] === 'Note' ? 'text-warning' : ($ev['type'] === 'Meeting' ? 'text-primary' : 'text-success') ?>" title="<?= e($ev['title']) ?>">
                                            <i class="fas fa-<?= $ev['type'] === 'Note' ? 'sticky-note' : ($ev['type'] === 'Meeting' ? 'handshake' : 'calendar-day') ?> mr-1"></i>
                                            <?= e(mb_strimwidth($ev['title'], 0, 14, '…')) ?>
                                            <?php if ($ev['from_time']): ?><br><small class="text-muted"><?= e(date('g:i A', strtotime($ev['from_time']))) ?></small><?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (count($evs) > 3): ?><small class="text-muted">+<?= count($evs) - 3 ?> more</small><?php endif; ?>
                                    <?php if ($altCaption !== ''): ?><small class="text-muted position-absolute" style="font-size:.6rem;right:.25rem;bottom:.15rem"><?= e($altCaption) ?></small><?php endif; ?>
                                </td>
                                <?php
                            endfor;
                            echo '</tr>';
                        endfor;
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card card-outline">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-hourglass-half mr-1"></i>Upcoming</h3></div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php foreach ($upcoming as $u): ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <strong class="text-<?= $u['type'] === 'Meeting' ? 'primary' : ($u['type'] === 'Note' ? 'warning' : 'success') ?>"><?= e($u['type']) ?></strong>
                                <span class="badge badge-<?= $u['privacy'] === 'Public' ? 'info' : 'secondary' ?>"><?= e($u['privacy']) ?></span>
                            </div>
                            <div><?= e($u['title']) ?></div>
                            <small class="text-muted"><i class="far fa-clock mr-1"></i><?= e(scheduleLine($u)) ?></small>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$upcoming): ?><li class="list-group-item text-muted text-center">Nothing scheduled.</li><?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Left slide-in drawer (same design language as the holidays/office_setup drawer) -->
<div class="cms-drawer-backdrop" id="calDrawerBackdrop" onclick="closeCalDrawer()"></div>

<div class="cms-drawer" id="calDrawer">
    <div class="cms-drawer-header">
        <h3><i class="fas fa-calendar-day mr-1"></i><span id="calDrawerTitle"></span></h3>
        <button type="button" class="cms-drawer-close" onclick="closeCalDrawer()" aria-label="Close"><i class="fas fa-times"></i></button>
    </div>
    <div class="cms-drawer-body">
        <!-- Step 1 — what is already on this date + add actions -->
        <div id="calDayPane">
            <label class="small text-muted mb-2 d-block">Already added on this date</label>
            <ul class="list-group list-group-flush" id="calDayItems">
                <li class="list-group-item text-muted text-center small py-3">Select a date.</li>
            </ul>
            <?php if ($canAddAny): ?>
            <div class="mt-3 border-top pt-3" id="calAddButtons" hidden>
                <label class="small text-muted mb-2 d-block">Add to this date</label>
                <div class="d-flex flex-wrap">
                            <?php if ($canAddEvent): ?>
                            <button type="button" class="btn btn-sm btn-primary mr-2 mb-2 cal-add-btn" data-add="event"><i class="fas fa-handshake mr-1"></i>Add meeting / event</button>
                            <button type="button" class="btn btn-sm btn-warning mr-2 mb-2 cal-add-btn" data-add="note"><i class="fas fa-sticky-note mr-1"></i>Add note</button>
                            <?php endif; ?>
                            <?php if ($canAddTodo): ?>
                            <button type="button" class="btn btn-sm btn-info mr-2 mb-2 cal-add-btn" data-add="todo"><i class="fas fa-tasks mr-1"></i>Add todo</button>
                            <?php endif; ?>
                            <?php if ($canAddHoliday): ?>
                            <button type="button" class="btn btn-sm btn-danger mr-2 mb-2 cal-add-btn" data-add="holiday"><i class="fas fa-umbrella-beach mr-1"></i>Add holiday</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Step 2 — event / meeting / note form (mirrors the HR Care "Meetings" tab) -->
                <form id="calEvtForm" action="operation.php?module=staff_management&page=hr_care" method="post" class="cal-pane" hidden>
                <div class="card card-outline card-primary">
                        <div class="card-header py-2">
                            <h5 class="card-title mb-0"><i class="fas fa-handshake mr-1"></i>Add <span id="calEvtFormTitle">Meeting</span></h5>
                        </div>
                        <div class="card-body">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="save_event">
                            <input type="hidden" name="redirect" value="calendar">
                            <div class="form-group">
                            <label>Type</label>
                            <select name="type" id="calEvtType" class="form-control">
                                <option value="Meeting">Meeting</option>
                                <option value="Event">Event</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Title *</label>
                            <input type="text" name="title" class="form-control" required placeholder="e.g. Sprint review">
                        </div>
                        <div class="form-group">
                            <label>Privacy</label>
                            <select name="privacy" id="calEvtPrivacy" class="form-control">
                                <option value="Public">Public</option>
                                <option value="Private">Private</option>
                            </select>
                        </div>
                        <div class="form-group" id="calEvtDeptWrap">
                            <label>Department <span class="text-muted">(Public)</span></label>
                            <select name="attendees_department" id="calEvtDept" class="form-control">
                                <option value="">All departments</option>
                                <?php foreach ($departments as $dep): ?>
                                <option value="<?= (int) $dep['id'] ?>"><?= e($dep['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" id="calEvtAttendeeWrap" hidden>
                            <label>Invite staff <span class="text-muted">(Private)</span></label>
                            <select class="form-control mb-2" id="calEvtAttDeptFilter">
                                <option value="">All departments</option>
                                <?php foreach ($staffDepts as $did => $dtitle): ?>
                                <option value="<?= (int) $did ?>"><?= e($dtitle) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input" id="calEvtAttSelectAll">
                                <label class="custom-control-label small" for="calEvtAttSelectAll">Select all visible staff</label>
                            </div>
                            <select name="attendees[]" id="calEvtAttendees" class="form-control" multiple>
                                <?php foreach ($staffs as $st): ?>
                                <option value="<?= (int) $st['id'] ?>" data-dept="<?= (int) $st['department_id'] ?>"><?= e($st['fullname']) ?><?= $st['department_title'] ? ' (' . e($st['department_title']) . ')' : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Other attendees</label>
                            <input type="text" name="other_attendees" class="form-control" placeholder="Names, emails…">
                        </div>
                        <div class="form-group">
                            <label>Venue</label>
                            <select name="venue_type" id="calEvtVenue" class="form-control">
                                <option value="In Office">In Office</option>
                                <option value="Out of Office">Out of Office</option>
                            </select>
                        </div>
                        <div class="form-group" id="calEvtHallWrap">
                            <label>Hall / Room</label>
                            <select name="venue_location" id="calEvtHall" class="form-control">
                                <option value="">Select</option>
                                <?php foreach ($halls as $hall): ?>
                                <option value="<?= (int) $hall['id'] ?>"><?= e($hall['hall_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" id="calEvtLocWrap" hidden>
                            <label>Location</label>
                            <input type="text" name="venue_location_text" id="calEvtLoc" class="form-control" placeholder="Address…">
                        </div>
                        <label class="d-block text-muted mb-1">Schedule</label>
                        <div id="calEvtSched">
                            <div class="cal-evtsched border-bottom mb-3 pb-2">
                                <div class="row">
                                    <div class="col-12 form-group mb-2">
                                        <label>Date *</label>
                                        <input type="date" name="date[]" class="form-control calEvtDate" required>
                                    </div>
                                    <div class="col-6 form-group mb-2">
                                        <label>From</label>
                                        <input type="time" name="from_time[]" class="form-control">
                                    </div>
                                    <div class="col-6 form-group mb-2">
                                        <label>To</label>
                                        <input type="time" name="to_time[]" class="form-control">
                                    </div>
                                    <div class="col-12 form-group mb-0">
                                        <button type="button" class="btn btn-sm btn-outline-danger cal-sched-rm"><i class="fas fa-trash"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <button type="button" id="calEvtAddSched" class="btn btn-xs btn-outline-primary"><i class="fas fa-plus mr-1"></i>Add schedule</button>
                        </div>
                        <div class="form-group mb-0">
                            <label>Remarks</label>
                            <textarea name="remarks" rows="2" class="form-control" placeholder="Optional details"></textarea>
                        </div>
                        </div>
                    </div>
                </form>

                <!-- Step 3 — note form (notes need no venue/hall/schedules) -->
                <form id="calNoteForm" action="operation.php?module=staff_management&page=hr_care" method="post" class="cal-pane" hidden>
                <div class="card card-outline card-warning">
                        <div class="card-header py-2">
                            <h5 class="card-title mb-0"><i class="fas fa-sticky-note mr-1"></i>Add Note</h5>
                        </div>
                        <div class="card-body">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="save_event">
                            <input type="hidden" name="type" value="Note">
                            <input type="hidden" name="redirect" value="calendar">
                            <input type="hidden" name="note_date" class="calNoteDate">
                            <div class="form-group">
                            <label>Title *</label>
                            <input type="text" name="title" class="form-control" required placeholder="e.g. Follow up with vendor">
                            <small class="text-muted">Notes added from the calendar expire automatically on this date.</small>
                        </div>
                        <div class="form-group">
                            <label>Assign to <span class="text-muted">(optional — empty = personal)</span></label>
                            <select class="form-control mb-2" id="calNoteAttDeptFilter">
                                <option value="">All departments</option>
                                <?php foreach ($staffDepts as $did => $dtitle): ?>
                                <option value="<?= (int) $did ?>"><?= e($dtitle) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" class="custom-control-input" id="calNoteAttSelectAll">
                                <label class="custom-control-label small" for="calNoteAttSelectAll">Select all visible staff</label>
                            </div>
                            <select name="attendees[]" id="calNoteAttendees" class="form-control" size="5" multiple>
                                <?php foreach ($staffs as $st): ?>
                                <option value="<?= (int) $st['id'] ?>" data-dept="<?= (int) $st['department_id'] ?>"><?= e($st['fullname']) ?><?= $st['department_title'] ? ' (' . e($st['department_title']) . ')' : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group mb-0">
                            <label>Remarks</label>
                            <textarea name="remarks" rows="2" class="form-control" placeholder="Optional details"></textarea>
                        </div>
                        </div>
                    </div>
                </form>

                <!-- Step 4 — personal to-do form -->
                <form id="calTodoForm" action="operation.php?module=my_office&page=office_calendar" method="post" class="cal-pane" hidden>
                <div class="card card-outline card-info">
                        <div class="card-header py-2">
                            <h5 class="card-title mb-0"><i class="fas fa-tasks mr-1"></i>Add To-do</h5>
                        </div>
                        <div class="card-body">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="save_todo">
                            <input type="hidden" name="redirect" value="calendar">
                            <input type="hidden" name="todo_date" class="calTodoDate">
                            <div class="form-group">
                            <label>What needs to be done? *</label>
                            <input type="text" name="title" class="form-control" required placeholder="e.g. Submit monthly report">
                        </div>
                        <div class="form-group">
                            <label>Time</label>
                            <input type="time" name="todo_time" class="form-control">
                        </div>
                        <div class="form-group mb-0">
                            <label>Remarks</label>
                            <textarea name="remarks" rows="2" class="form-control" placeholder="Optional details"></textarea>
                        </div>
                        </div>
                    </div>
                </form>

                <!-- Step 5 — holiday form (mirrors office_setup/holidays drawer) -->
                <form id="calHolForm" action="operation.php?module=office_setup&page=holidays" method="post" class="cal-pane" hidden>
                <div class="card card-outline card-danger">
                        <div class="card-header py-2">
                            <h5 class="card-title mb-0"><i class="fas fa-umbrella-beach mr-1"></i>Add Holiday</h5>
                        </div>
                        <div class="card-body">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="save">
                            <input type="hidden" name="redirect" value="calendar">
                            <div class="form-group">
                        <label>Title *</label>
                        <input type="text" name="title" class="form-control" required placeholder="e.g. Dashain holiday">
                    </div>
                    <div class="form-group">
                        <label>From date *</label>
                        <input type="date" name="from_date" class="form-control calHolFrom" required>
                    </div>
                    <div class="form-group">
                        <label>To date *</label>
                        <input type="date" name="to_date" class="form-control calHolTo" required>
                    </div>
                    <div class="form-group">
                        <label>Department scope</label>
                        <select name="department_id" class="form-control">
                            <option value="">All departments</option>
                            <?php foreach ($departments as $d): ?>
                            <option value="<?= (int) $d['id'] ?>"><?= e($d['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Gender scope</label>
                        <select name="gender_to" class="form-control">
                            <?php foreach (['Both', 'Male', 'Female'] as $g): ?>
                            <option value="<?= $g ?>"><?= $g ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mb-0">
                        <label>Remarks</label>
                        <input type="text" name="remarks" class="form-control" placeholder="Optional details">
                    </div>
                        </div>
                    </div>
                </form>
            </div>
    <div class="cms-drawer-footer">
        <div class="d-flex align-items-center justify-content-between">
            <button type="button" class="btn btn-sm btn-default" id="calBackBtn" hidden><i class="fas fa-arrow-left mr-1"></i>Back</button>
            <button type="button" class="btn btn-sm btn-primary" id="calSubmitBtn" hidden><i class="fas fa-check mr-1"></i>Save</button>
        </div>
    </div>
</div>

<script>
// Vanilla event delegation — no jQuery dependency at bind time (the footer
// loads jQuery AFTER module content, so $ isn't available when this runs).
window.__calDayData   = <?= json_encode($dayData) ?>;
window.__calToday     = <?= json_encode($today) ?>;
window.__calHasPerm   = <?= $canAddAny ? 'true' : 'false' ?>;
window.__calCsrf      = <?= json_encode(csrfToken()) ?>;
window.__calEvtAction = 'operation.php?module=staff_management&page=hr_care';
window.__calHolAction = 'operation.php?module=office_setup&page=holidays';
window.__calTodoAction = 'operation.php?module=my_office&page=office_calendar';
window.__calSchedTpl  = <?= json_encode(
    '<div class="cal-evtsched border-bottom mb-3 pb-2">'
    . '<div class="row">'
    . '<div class="col-12 form-group mb-2">'
    . '<label>Date *</label>'
    . '<input type="date" name="date[]" class="form-control calEvtDate" required>'
    . '</div>'
    . '<div class="col-6 form-group mb-2">'
    . '<label>From</label>'
    . '<input type="time" name="from_time[]" class="form-control">'
    . '</div>'
    . '<div class="col-6 form-group mb-2">'
    . '<label>To</label>'
    . '<input type="time" name="to_time[]" class="form-control">'
    . '</div>'
    . '<div class="col-12 form-group mb-0">'
    . '<button type="button" class="btn btn-sm btn-outline-danger cal-sched-rm"><i class="fas fa-trash"></i></button>'
    . '</div>'
    . '</div>'
    . '</div>'
) ?>;

(function () {
    var clickedDate = '';
    var dayPane = document.getElementById('calDayPane');
    var backBtn = document.getElementById('calBackBtn');
    var submitBtn = document.getElementById('calSubmitBtn');
    var allPanes = document.querySelectorAll('.cal-pane');

    function esc(s) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s));
        return d.innerHTML;
    }

    function fmtDate(iso) {
        return new Date(iso + 'T00:00:00').toLocaleDateString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
        });
    }

    // Programmatic POST helper (carries CSRF + redirect=calendar).
    function postCal(extra) {
        var f = document.createElement('form');
        f.method = 'POST';
        f.action = extra._action;
        f.style.display = 'none';
        document.body.appendChild(f);
        Object.keys(extra).forEach(function (k) {
            if (k.charAt(0) === '_') {
                return;
            }
            var inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = k;
            inp.value = extra[k] == null ? '' : extra[k];
            f.appendChild(inp);
        });
        var csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = 'csrf_token';
        csrf.value = window.__calCsrf;
        f.appendChild(csrf);
        f.submit();
    }

    function showPane(el) {
        allPanes.forEach(function (p) { p.hidden = true; });
        el.hidden = false;
        if (el.tagName === 'FORM') {
            el.reset();
        }
        backBtn.hidden = (el === dayPane);
        submitBtn.hidden = (el === dayPane);
    }

    function setEvtDateAll() {
        document.querySelectorAll('.calEvtDate').forEach(function (i) { i.value = clickedDate; });
    }

    function resetEvtSched() {
        var sched = document.getElementById('calEvtSched');
        while (sched.children.length > 1) {
            sched.removeChild(sched.lastElementChild);
        }
    }

    function syncEvtPrivacy() {
        var priv = document.getElementById('calEvtPrivacy');
        document.getElementById('calEvtDeptWrap').hidden = (priv.value !== 'Public');
        document.getElementById('calEvtAttendeeWrap').hidden = (priv.value !== 'Private');
    }

    function syncEvtVenue() {
        var v = document.getElementById('calEvtVenue').value;
        document.getElementById('calEvtHallWrap').hidden = (v !== 'In Office');
        document.getElementById('calEvtLocWrap').hidden = (v !== 'Out of Office');
    }

    function setEvtType(v) {
        var type = document.getElementById('calEvtType');
        var priv = document.getElementById('calEvtPrivacy');
        type.value = v;
        document.getElementById('calEvtFormTitle').textContent = v;
        priv.disabled = (v === 'Event');
        if (v === 'Event') {
            priv.value = 'Public';
        }
        syncEvtPrivacy();
        syncEvtVenue();
    }

    function wirePicker(selId, filterId, allId) {
        var sel = document.getElementById(selId);
        var filter = document.getElementById(filterId);
        var all = document.getElementById(allId);
        if (!sel || !filter || !all) {
            return;
        }
        filter.addEventListener('change', function () {
            var dept = filter.value;
            Array.prototype.forEach.call(sel.options, function (o) {
                o.hidden = dept !== '' && o.getAttribute('data-dept') !== String(dept);
                o.selected = false;
            });
            all.checked = false;
        });
        all.addEventListener('change', function () {
            Array.prototype.forEach.call(sel.options, function (o) {
                if (!o.hidden) { o.selected = all.checked; }
            });
        });
    }
    wirePicker('calEvtAttendees', 'calEvtAttDeptFilter', 'calEvtAttSelectAll');
    wirePicker('calNoteAttendees', 'calNoteAttDeptFilter', 'calNoteAttSelectAll');

    function renderDay(date) {
        var items = window.__calDayData[date] || [];
        var ul = document.getElementById('calDayItems');
        if (!items.length) {
            ul.innerHTML = '<li class="list-group-item text-muted text-center small py-3">Nothing added on this day.</li>';
        } else {
            ul.innerHTML = items.map(function (it) {
                var icon, cls, badge = '', timeHtml = '', sub = '', actions = '';
                if (it.kind === 'event') {
                    icon = it.type === 'Note' ? 'sticky-note' : (it.type === 'Meeting' ? 'handshake' : 'calendar-day');
                    cls = it.type === 'Note' ? 'text-warning' : (it.type === 'Meeting' ? 'text-primary' : 'text-success');
                    badge = '<span class="badge badge-' + (it.privacy === 'Public' ? 'info' : 'secondary') + ' ml-1">' + esc(it.privacy) + '</span>';
                    if (it.time) { timeHtml = ' <span class="text-muted ml-1"><i class="far fa-clock"></i> ' + esc(it.time) + '</span>'; }
                    if (it.managed) { actions = '<a href="#" class="text-danger cal-del" data-kind="event" data-id="' + it.id + '" title="Delete"><i class="fas fa-trash-alt"></i></a>'; }
                } else if (it.kind === 'holiday') {
                    icon = 'umbrella-beach';
                    cls = 'text-danger';
                    badge = '<span class="badge badge-danger ml-1">Holiday</span>';
                    if (it.remarks) { sub = '<div class="small text-muted">' + esc(it.remarks) + '</div>'; }
                    if (it.managed) { actions = '<a href="#" class="text-danger cal-del" data-kind="holiday" data-id="' + it.id + '" title="Delete"><i class="fas fa-trash-alt"></i></a>'; }
                } else {
                    icon = it.completed ? 'check-circle' : 'circle';
                    cls = it.completed ? 'text-success' : 'text-purple';
                    badge = '<span class="badge badge-' + (it.completed ? 'success' : 'secondary') + ' ml-1">' + (it.completed ? 'Done' : 'Todo') + '</span>';
                    if (it.time) { timeHtml = ' <span class="text-muted ml-1"><i class="far fa-clock"></i> ' + esc(it.time) + '</span>'; }
                    if (it.managed) {
                        actions = '<span>'
                            + '<a href="#" class="text-muted mr-2 cal-toggle" data-id="' + it.id + '" title="' + (it.completed ? 'Mark incomplete' : 'Mark done') + '"><i class="fas fa-' + (it.completed ? 'undo' : 'check') + '"></i></a>'
                            + '<a href="#" class="text-danger cal-del" data-kind="todo" data-id="' + it.id + '" title="Delete"><i class="fas fa-trash-alt"></i></a>'
                            + '</span>';
                    }
                }
                return '<li class="list-group-item d-flex justify-content-between align-items-center py-2">'
                    + '<div style="min-width:0"><strong class="' + cls + '"><i class="fas fa-' + icon + ' mr-1"></i>' + esc(it.title) + '</strong>'
                    + timeHtml + badge + sub + '</div>'
                    + actions + '</li>';
            }).join('');
        }
        var addBtns = document.getElementById('calAddButtons');
        if (addBtns) {
            addBtns.hidden = (date < window.__calToday) || !window.__calHasPerm;
        }
    }

    function goDay() {
        showPane(dayPane);
        renderDay(clickedDate);
    }

    // Calendar cell click → open the left drawer with that day's contents.
    document.addEventListener('click', function (e) {
        var td = e.target.closest('.cal-day-clickable');
        if (!td) {
            return;
        }
        if (e.target.closest('a, button, form, input, select, textarea')) {
            return;
        }
        var date = td.getAttribute('data-date');
        if (!date) {
            return;
        }
        clickedDate = date;
        document.getElementById('calDrawerTitle').textContent = fmtDate(date);
        document.querySelectorAll('.calHolFrom').forEach(function (i) { i.value = date; });
        document.querySelectorAll('.calHolTo').forEach(function (i) { i.value = date; });
        setEvtDateAll();
        document.querySelectorAll('.calTodoDate').forEach(function (i) { i.value = date; });
        goDay();
        openCalDrawer();
    });

    // Back to the day list.
    backBtn.addEventListener('click', goDay);

    // Left-side drawer (same design language as the office_setup holidays drawer).
    function openCalDrawer() {
        document.getElementById('calDrawer').classList.add('open');
        document.getElementById('calDrawerBackdrop').classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    window.closeCalDrawer = function () {
        document.getElementById('calDrawer').classList.remove('open');
        document.getElementById('calDrawerBackdrop').classList.remove('active');
        document.body.style.overflow = '';
    };
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            window.closeCalDrawer();
        }
    });

    // "Add …" buttons → open the right form pane.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.cal-add-btn');
        if (!btn) {
            return;
        }
        var kind = btn.getAttribute('data-add');
        if (kind === 'meeting' || kind === 'event') {
            showPane(document.getElementById('calEvtForm'));
            resetEvtSched();
            setEvtType(kind === 'event' ? 'Event' : 'Meeting');
            setEvtDateAll();
        } else if (kind === 'note') {
            showPane(document.getElementById('calNoteForm'));
            document.querySelectorAll('.calNoteDate').forEach(function (i) { i.value = clickedDate; });
        } else if (kind === 'todo') {
            showPane(document.getElementById('calTodoForm'));
            document.querySelectorAll('.calTodoDate').forEach(function (i) { i.value = clickedDate; });
        } else if (kind === 'holiday') {
            showPane(document.getElementById('calHolForm'));
            // showPane() resets the form, so re-apply the clicked date.
            document.querySelectorAll('.calHolFrom').forEach(function (i) { i.value = clickedDate; });
            document.querySelectorAll('.calHolTo').forEach(function (i) { i.value = clickedDate; });
        }
    });

    // Footer Save submits whichever pane form is visible.
    submitBtn.addEventListener('click', function () {
        var visible = document.querySelector('.cal-pane:not([hidden])');
        if (visible && visible.tagName === 'FORM') {
            visible.submit();
        }
    });

    // Event form live toggles (type / privacy / venue).
    document.addEventListener('change', function (e) {
        if (e.target.id === 'calEvtType') { setEvtType(e.target.value); }
        if (e.target.id === 'calEvtPrivacy') { syncEvtPrivacy(); }
        if (e.target.id === 'calEvtVenue') { syncEvtVenue(); }
    });

    // Schedule row add / remove.
    document.getElementById('calEvtAddSched').addEventListener('click', function () {
        var wrap = document.createElement('div');
        wrap.innerHTML = window.__calSchedTpl;
        var row = wrap.firstElementChild;
        row.querySelectorAll('.calEvtDate').forEach(function (i) { i.value = clickedDate; });
        document.getElementById('calEvtSched').appendChild(row);
    });
    document.addEventListener('click', function (e) {
        var rm = e.target.closest('.cal-sched-rm');
        if (!rm) {
            return;
        }
        var sched = document.getElementById('calEvtSched');
        if (sched.children.length > 1) {
            rm.closest('.cal-evtsched').remove();
        }
    });

    // Day-list actions: toggle todo, delete event / holiday / todo.
    document.addEventListener('click', function (e) {
        var del = e.target.closest('.cal-del');
        if (del) {
            e.preventDefault();
            var kind = del.getAttribute('data-kind');
            var id = parseInt(del.getAttribute('data-id'), 10);
            var title = del.closest('li').querySelector('strong').textContent.trim();
            if (kind === 'event') {
                postCal({ action: 'delete_event', event_id: id, redirect: 'calendar', _action: window.__calEvtAction });
            } else if (kind === 'holiday') {
                postCal({ action: 'delete', id: id, redirect: 'calendar', _action: window.__calHolAction });
            } else {
                postCal({ action: 'delete_todo', id: id, redirect: 'calendar', _action: window.__calTodoAction });
            }
            return;
        }
        var tog = e.target.closest('.cal-toggle');
        if (tog) {
            e.preventDefault();
            postCal({ action: 'toggle_todo', id: parseInt(tog.getAttribute('data-id'), 10), redirect: 'calendar', _action: window.__calTodoAction });
        }
    });
})();
</script>
