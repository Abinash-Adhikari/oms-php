<?php
/**
 * SB-Tech — HR Care / Attendance tab (US-ATT-01, US-ATT-02).
 * Staff see their own records; admins (Super Admin or view_all_attendance
 * special permission) see all staff, can adjust/delete rows, and get the
 * monthly report + CSV export.
 */
$db = Database::instance();
$me = (int) Auth::id();
$canSeeAll = Auth::isSuperAdmin() || Auth::hasSpecial('view_all_attendance');
$today = date('Y-m-d');
$month = (string) ($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}
$filterUser = $canSeeAll ? (int) ($_GET['user_id'] ?? 0) : 0;

$myRow = $db->selectOne(
    'SELECT * FROM `tbl_staff_attendances` WHERE `user_id` = ? AND `date` = ?',
    [$me, $today]
);

// Staff who allow colleagues to record attendance on their behalf.
$proxyStaff = [];
if ($canSeeAll) {
    $proxyStaff = $db->select(
        "SELECT `id`, `fullname` FROM `tbl_users_login`
         WHERE `status` = 'Active' AND `allow_checkin_by_other` = 'Yes'
         ORDER BY `fullname`"
    );
}

// Records for the selected month (all staff for admins, own otherwise).
$records = [];
if ($filterUser) {
    $records = $db->select(
        'SELECT a.*, u.fullname FROM `tbl_staff_attendances` a
         JOIN `tbl_users_login` u ON u.id = a.user_id
         WHERE a.date LIKE ? AND a.user_id = ?
         ORDER BY a.date DESC, u.fullname',
        [$month . '%', $filterUser]
    );
} elseif ($canSeeAll) {
    $records = $db->select(
        'SELECT a.*, u.fullname FROM `tbl_staff_attendances` a
         JOIN `tbl_users_login` u ON u.id = a.user_id
         WHERE a.date LIKE ?
         ORDER BY a.date DESC, u.fullname',
        [$month . '%']
    );
} else {
    $records = $db->select(
        'SELECT a.*, u.fullname FROM `tbl_staff_attendances` a
         JOIN `tbl_users_login` u ON u.id = a.user_id
         WHERE a.date LIKE ? AND a.user_id = ?
         ORDER BY a.date DESC',
        [$month . '%', $me]
    );
}

// Monthly summary rows (per staff) for the report card.
$summary = [];
if ($canSeeAll) {
    $summary = $db->select(
        "SELECT u.id AS user_id, u.fullname,
                COALESCE(SUM(a.status = 'present'), 0) AS present_days,
                COALESCE(SUM(a.status = 'absent'), 0) AS absent_rows,
                COALESCE(SUM(a.status = 'leave'), 0) AS leave_days,
                COALESCE(SUM(a.status = 'holiday'), 0) AS holiday_days,
                COALESCE(SUM(a.late_checkin), 0) AS late_days,
                COALESCE(SUM(a.early_checkout), 0) AS early_days,
                COALESCE(SUM(a.working_hours), 0) AS working_hours
         FROM `tbl_users_login` u
         LEFT JOIN `tbl_staff_attendances` a
           ON a.user_id = u.id AND a.date LIKE ?
         WHERE u.status != 'Terminated'
         GROUP BY u.id, u.fullname
         ORDER BY u.fullname",
        [$month . '%']
    );
} else {
    $summary = $db->select(
        "SELECT u.id AS user_id, u.fullname,
                COALESCE(SUM(a.status = 'present'), 0) AS present_days,
                COALESCE(SUM(a.status = 'absent'), 0) AS absent_rows,
                COALESCE(SUM(a.status = 'leave'), 0) AS leave_days,
                COALESCE(SUM(a.status = 'holiday'), 0) AS holiday_days,
                COALESCE(SUM(a.late_checkin), 0) AS late_days,
                COALESCE(SUM(a.early_checkout), 0) AS early_days,
                COALESCE(SUM(a.working_hours), 0) AS working_hours
         FROM `tbl_users_login` u
         LEFT JOIN `tbl_staff_attendances` a
           ON a.user_id = u.id AND a.date LIKE ?
         WHERE u.id = ?
         GROUP BY u.id, u.fullname",
        [$month . '%', $me]
    );
}
$daysInMonth = (int) date('t', strtotime($month . '-01'));
foreach ($summary as &$s) {
    $s['present_days'] = (int) $s['present_days'];
    $s['leave_days'] = (int) $s['leave_days'];
    $s['holiday_days'] = (int) $s['holiday_days'];
    $s['absent_days'] = max(0, $daysInMonth - $s['present_days'] - $s['leave_days'] - $s['holiday_days']);
    $s['working_hours'] = round((float) $s['working_hours'], 2);
}
unset($s);

$activeStaff = $db->select(
    "SELECT `id`, `fullname` FROM `tbl_users_login` WHERE `status` = 'Active' ORDER BY `fullname`"
);

// Adjustment form data
$adjUser = (int) ($_GET['adjust_user'] ?? 0);
$adjDate = (string) ($_GET['adjust_date'] ?? $today);
$adj = null;
if ($adjUser) {
    $adj = $db->selectOne(
        'SELECT * FROM `tbl_staff_attendances` WHERE `user_id` = ? AND `date` = ?',
        [$adjUser, $adjDate]
    );
}
$adjustDrawerOpen = ($adjUser > 0);
?>

<!-- Quick Actions Row (top) -->
<div class="row mb-3">
    <!-- Today Card -->
    <div class="col-lg-4 col-md-6 mb-2">
        <div class="card card-outline card-light">
            <div class="card-header py-2">
                <h3 class="card-title mb-0"><i class="fas fa-fingerprint mr-1"></i>Today <span class="badge badge-light ml-1" style="font-size:.75rem"><?= e(formatDateView($today)) ?></span></h3>
            </div>
            <div class="card-body py-2">
                <?php if ($myRow): ?>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small">Check-in</span>
                        <strong class="small"><?= $myRow['checkin'] ? e(date('g:i A', strtotime($myRow['checkin']))) : '<span class="text-muted">—</span>' ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small">Check-out</span>
                        <strong class="small"><?= $myRow['checkout'] ? e(date('g:i A', strtotime($myRow['checkout']))) : '<span class="text-muted">—</span>' ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small">Hours</span>
                        <strong class="small"><?= $myRow['working_hours'] !== null ? e(formatMinutes((int) round($myRow['working_hours'] * 60))) : '—' ?></strong>
                    </div>
                    <?php
                    $stCls = ['present' => 'success', 'absent' => 'danger', 'leave' => 'warning', 'holiday' => 'info'][$myRow['status']] ?? 'secondary';
                    ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">Status</span>
                        <span class="badge badge-<?= $stCls ?>"><?= e(ucfirst($myRow['status'])) ?></span>
                    </div>
                    <?php if ($myRow['late_checkin']): ?>
                        <div class="text-warning small mb-1"><i class="fas fa-exclamation-triangle mr-1"></i>Late by <?= e(formatMinutes((int) $myRow['late_checkin_minutes'])) ?></div>
                    <?php endif; ?>
                    <?php if ($myRow['early_checkout']): ?>
                        <div class="text-primary small mb-1"><i class="fas fa-exclamation-triangle mr-1"></i>Early by <?= e(formatMinutes((int) $myRow['checkout_early'])) ?></div>
                    <?php endif; ?>
                    <div class="mt-2">
                        <?php if (!$myRow['checkin']): ?>
                            <form action="operation.php?module=staff_management&page=hr_care" method="post">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="checkin">
                                <button type="submit" class="btn btn-success btn-sm btn-block"><i class="fas fa-sign-in-alt mr-1"></i>Check In</button>
                            </form>
                        <?php elseif (!$myRow['checkout']): ?>
                            <form action="operation.php?module=staff_management&page=hr_care" method="post">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="checkout">
                                <div class="input-group input-group-sm mb-1">
                                    <input type="text" name="reason_checkout" class="form-control" placeholder="Reason for leaving">
                                    <div class="input-group-append">
                                        <button type="submit" class="btn btn-warning"><i class="fas fa-sign-out-alt"></i></button>
                                    </div>
                                </div>
                            </form>
                        <?php else: ?>
                            <p class="text-success mb-0 text-center small"><i class="fas fa-check-circle mr-1"></i>Day complete</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-2 small">No check-in recorded yet today.</p>
                    <form action="operation.php?module=my_office&page=attendance" method="post">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="checkin">
                        <div class="input-group input-group-sm mb-1">
                            <input type="text" name="reason_checkin" class="form-control" placeholder="Reason for late arrival">
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-success"><i class="fas fa-sign-in-alt"></i></button>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($proxyStaff): ?>
    <!-- Proxy Check-in -->
    <div class="col-lg-4 col-md-6 mb-2">
        <div class="card card-outline card-light">
            <div class="card-header py-2">
                <h3 class="card-title mb-0"><i class="fas fa-user-friends mr-1"></i>Record for Colleague</h3>
            </div>
            <div class="card-body py-2">
                <form action="operation.php?module=staff_management&page=hr_care" method="post" class="mb-1">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="checkin">
                    <div class="input-group input-group-sm mb-1">
                        <select name="for_user" class="custom-select custom-select-sm" required>
                            <option value="">Select staff…</option>
                            <?php foreach ($proxyStaff as $ps): ?>
                                <option value="<?= (int) $ps['id'] ?>"><?= e($ps['fullname']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-success" title="Check In"><i class="fas fa-sign-in-alt"></i></button>
                        </div>
                    </div>
                </form>
                <form action="operation.php?module=staff_management&page=hr_care" method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="checkout">
                    <div class="input-group input-group-sm">
                        <select name="for_user" class="custom-select custom-select-sm" required>
                            <option value="">Select staff…</option>
                            <?php foreach ($proxyStaff as $ps): ?>
                                <option value="<?= (int) $ps['id'] ?>"><?= e($ps['fullname']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-warning" title="Check Out"><i class="fas fa-sign-out-alt"></i></button>
                        </div>
                    </div>
                </form>
                <small class="text-muted mt-1 d-block">Staff with "allow check-in by other" enabled.</small>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($canSeeAll): ?>
    <!-- Adjust Attendance -->
    <div class="col-lg-4 col-md-12 mb-2">
        <div class="card card-outline card-light">
            <div class="card-header py-2">
                <h3 class="card-title mb-0"><i class="fas fa-edit mr-1"></i>Adjust Attendance</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-xs btn-primary" onclick="openAdjustDrawer()"><i class="fas fa-sliders-h"></i></button>
                </div>
            </div>
            <div class="card-body py-2">
                <p class="text-muted small mb-0">Click the button to open the adjustment form for any staff member.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Monthly Report (full width) -->
<div class="card card-outline mb-3">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-chart-bar mr-1"></i>Monthly Report — <?= e($month) ?></h3>
        <div class="card-tools">
            <form action="operation.php?module=staff_management&page=hr_care" method="post" class="d-inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="export_monthly">
                <input type="hidden" name="month" value="<?= e($month) ?>">
                <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-file-csv mr-1"></i>CSV</button>
            </form>
            <form action="<?= pageUrl('staff_management', 'hr_care') ?>&tab=attendance" method="get" class="d-inline ml-1">
                <input type="hidden" name="module" value="my_office">
                <input type="hidden" name="page" value="hr_care">
                <input type="hidden" name="tab" value="attendance">
                <input type="month" name="month" value="<?= e($month) ?>" class="form-control form-control-sm d-inline" style="width:150px" onchange="this.form.submit()">
            </form>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>Staff</th>
                        <th class="text-center">Present</th>
                        <th class="text-center">Late</th>
                        <th class="text-center">Early</th>
                        <th class="text-center">Leave</th>
                        <th class="text-center">Holiday</th>
                        <th class="text-center">Absent</th>
                        <th class="text-right">Hours</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($summary as $s): ?>
                    <tr>
                        <td><strong><?= e($s['fullname']) ?></strong></td>
                        <td class="text-center"><span class="badge badge-success"><?= (int) $s['present_days'] ?></span></td>
                        <td class="text-center"><span class="badge badge-warning"><?= (int) $s['late_days'] ?></span></td>
                        <td class="text-center"><span class="badge badge-primary"><?= (int) $s['early_days'] ?></span></td>
                        <td class="text-center"><span class="badge badge-info"><?= (int) $s['leave_days'] ?></span></td>
                        <td class="text-center"><span class="badge badge-secondary"><?= (int) $s['holiday_days'] ?></span></td>
                        <td class="text-center"><span class="badge badge-danger"><?= (int) $s['absent_days'] ?></span></td>
                        <td class="text-right"><?= e(formatMinutes((int) round($s['working_hours'] * 60))) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$summary): ?>
                    <tr><td colspan="8" class="text-center text-muted">No data for this month.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Attendance Records (full width) -->
<div class="card card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-calendar-check mr-1"></i>Attendance Records — <?= e($month) ?></h3>
        <?php if ($canSeeAll && $activeStaff): ?>
            <div class="card-tools">
                <form method="get" class="form-inline">
                    <input type="hidden" name="module" value="my_office">
                    <input type="hidden" name="page" value="hr_care">
                    <input type="hidden" name="tab" value="attendance">
                    <input type="hidden" name="month" value="<?= e($month) ?>">
                    <select name="user_id" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="0">All staff</option>
                        <?php foreach ($activeStaff as $as): ?>
                            <option value="<?= (int) $as['id'] ?>" <?= $filterUser === (int) $as['id'] ? 'selected' : '' ?>><?= e($as['fullname']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <?php if ($canSeeAll): ?><th>Staff</th><?php endif; ?>
                        <th>Date</th>
                        <th>Check-in</th>
                        <th>Check-out</th>
                        <th>Late in</th>
                        <th>Early out</th>
                        <th>Status</th>
                        <th>Hours</th>
                        <?php if ($canSeeAll): ?><th class="text-right">Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($records as $i => $r): ?>
                    <?php
                    $inBadge = !$r['late_checkin'] ? 'success' : ((int) $r['late_checkin_minutes'] > 10 ? 'danger' : 'warning');
                    $outBadge = !$r['early_checkout'] ? 'success' : 'primary';
                    $stCls = ['present' => 'success', 'absent' => 'danger', 'leave' => 'warning', 'holiday' => 'info'][$r['status']] ?? 'secondary';
                    ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <?php if ($canSeeAll): ?><td><?= e($r['fullname']) ?></td><?php endif; ?>
                        <td><?= e(formatDateView($r['date'])) ?></td>
                        <td><?= $r['checkin'] ? e(date('g:i A', strtotime($r['checkin']))) : '—' ?></td>
                        <td><?= $r['checkout'] ? e(date('g:i A', strtotime($r['checkout']))) : '—' ?></td>
                        <td>
                            <span class="badge badge-<?= $inBadge ?>"><?= $r['checkin_delay'] !== null ? e(formatMinutes((int) $r['checkin_delay'])) : '—' ?></span>
                            <?php if ($r['reason_checkin']): ?><br><small class="text-muted"><?= e($r['reason_checkin']) ?></small><?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?= $outBadge ?>"><?= $r['checkout_early'] !== null ? e(formatMinutes((int) $r['checkout_early'])) : '—' ?></span>
                            <?php if ($r['reason_checkout']): ?><br><small class="text-muted"><?= e($r['reason_checkout']) ?></small><?php endif; ?>
                        </td>
                        <td><span class="badge badge-<?= $stCls ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                        <td><?= $r['working_hours'] !== null ? e(formatMinutes((int) round($r['working_hours'] * 60))) : '—' ?></td>
                        <?php if ($canSeeAll): ?>
                            <td class="text-right">
                                <button type="button" class="btn btn-xs btn-outline-primary" onclick="openAdjustDrawer('<?= (int) $r['user_id'] ?>', '<?= e($r['date']) ?>')" title="Adjust"><i class="fas fa-edit"></i></button>
                                <form action="operation.php?module=staff_management&page=hr_care" method="post" class="d-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete this attendance record?"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$records): ?>
                    <tr><td colspan="<?= $canSeeAll ? 10 : 8 ?>" class="text-center text-muted">No attendance records for this month.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($canSeeAll): ?>
<!-- Slide-in Drawer Backdrop -->
<div class="cms-drawer-backdrop" id="drawerBackdrop" onclick="closeAdjustDrawer()"></div>

<!-- Slide-in Drawer (Admin Adjustment) -->
<div class="cms-drawer" id="adjustDrawer">
    <div class="cms-drawer-header">
        <h3><i class="fas fa-edit"></i>Adjust Attendance</h3>
        <button type="button" class="cms-drawer-close" onclick="closeAdjustDrawer()" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="cms-drawer-body">
        <form action="operation.php?module=staff_management&page=hr_care" method="post" id="adjustForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="adjust">
            <div class="form-group">
                <label>Staff *</label>
                <select name="user_id" class="form-control" id="adjUserId" required>
                    <option value="">Select staff…</option>
                    <?php foreach ($activeStaff as $as): ?>
                        <option value="<?= (int) $as['id'] ?>" <?= $adjUser === (int) $as['id'] ? 'selected' : '' ?>><?= e($as['fullname']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Date *</label>
                <input type="date" name="date" class="form-control" id="adjDate" required value="<?= e($adjDate) ?>">
            </div>
            <div class="form-row">
                <div class="col-6 form-group">
                    <label>Check-in</label>
                    <input type="time" name="checkin" class="form-control" id="adjCheckin" value="<?= $adj ? e($adj['checkin']) : '' ?>">
                </div>
                <div class="col-6 form-group">
                    <label>Check-out</label>
                    <input type="time" name="checkout" class="form-control" id="adjCheckout" value="<?= $adj ? e($adj['checkout']) : '' ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="col-6 form-group">
                    <label>Status</label>
                    <select name="status" class="form-control" id="adjStatus">
                        <?php foreach (['present', 'absent', 'leave', 'holiday'] as $st): ?>
                            <option value="<?= $st ?>" <?= $adj && $adj['status'] === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 form-group">
                    <label>Reason (late/early)</label>
                    <input type="text" name="reason" class="form-control" id="adjReason" value="<?= $adj ? e($adj['reason_checkin'] ?: $adj['reason_checkout']) : '' ?>">
                </div>
            </div>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="adjustForm" class="btn btn-primary btn-block">
            <i class="fas fa-save mr-1"></i>Save Adjustment
        </button>
    </div>
</div>

<script>
var activeStaffData = <?= json_encode(array_values($activeStaff)) ?>;

function openAdjustDrawer(userId, date) {
    var drawer = document.getElementById('adjustDrawer');
    var backdrop = document.getElementById('drawerBackdrop');
    drawer.classList.add('open');
    backdrop.classList.add('active');
    document.body.style.overflow = 'hidden';

    if (userId) {
        document.getElementById('adjUserId').value = userId;
    }
    if (date) {
        document.getElementById('adjDate').value = date;
    }
}

function closeAdjustDrawer() {
    document.getElementById('adjustDrawer').classList.remove('open');
    document.getElementById('drawerBackdrop').classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeAdjustDrawer(); });

<?php if ($adjustDrawerOpen): ?>
document.addEventListener('DOMContentLoaded', function() { openAdjustDrawer(); });
<?php endif; ?>
</script>
<?php endif; ?>
