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

$calMode = (string) ($_GET['cal'] ?? 'AD');
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
    'SELECT e.id AS event_id, e.title, e.type, e.privacy, s.date AS sched_date, s.from_time, s.to_time
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
                                $isToday = $dateKey === $today;
                                $evs = $byDay[$dateKey] ?? [];
                                ?>
                                <td class="align-top <?= $isToday ? 'bg-primary-light border-primary' : '' ?>" style="height:88px">
                                    <span class="d-block <?= $isToday ? 'badge badge-primary' : 'text-muted' ?>"><?= $dayNum ?></span>
                                    <?php foreach (array_slice($evs, 0, 3) as $ev): ?>
                                        <div class="text-left small <?= $ev['type'] === 'Meeting' ? 'text-primary' : 'text-success' ?>" title="<?= e($ev['title']) ?>">
                                            <i class="fas fa-<?= $ev['type'] === 'Meeting' ? 'handshake' : 'calendar-day' ?> mr-1"></i>
                                            <?= e(mb_strimwidth($ev['title'], 0, 14, '…')) ?>
                                            <?php if ($ev['from_time']): ?><br><small class="text-muted"><?= e(date('g:i A', strtotime($ev['from_time']))) ?></small><?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (count($evs) > 3): ?><small class="text-muted">+<?= count($evs) - 3 ?> more</small><?php endif; ?>
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
                                <strong class="text-<?= $u['type'] === 'Meeting' ? 'primary' : 'success' ?>"><?= e($u['type']) ?></strong>
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
