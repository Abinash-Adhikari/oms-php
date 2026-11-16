<?php
/**
 * SB-Tech — Staff Management / Staff Detail (US-STF-01).
 * Read-only, single-page view of everything associated with a staff member:
 * personal/employment profile, today's attendance, plus recent activity
 * across attendance, leaves, leads, tasks, events, grievances, expense
 * claims, assets, history, documents, and permissions.
 * Inner page of add_staff (same permission gate; no sidebar entry).
 *
 * Layout: searchable staff list · gradient profile hero (shared cl-hero
 * styles with client detail) · KPI tiles (ana-tile) · tabbed sections
 * (Profile / Attendance / Leaves / Work / Activity / Assets & files /
 * History / Access). Styles: assets/css/admin.css "Staff detail (sd-*)".
 */
$db = Database::instance();

$view = null;
if (isset($_GET['id'])) {
    $view = $db->selectOne(
        'SELECT u.*, d.title AS department_title, g.title AS designation_title, a.fullname AS added_by_name
         FROM `tbl_users_login` u
         LEFT JOIN `tbl_office_departments` d ON d.id = u.department_id
         LEFT JOIN `tbl_office_designation` g ON g.id = u.designation_id
         LEFT JOIN `tbl_users_login` a ON a.id = u.added_by
         WHERE u.id = ?',
        [(int) $_GET['id']]
    );
}
$staff = $db->select('SELECT id, fullname, username, role, status, photo FROM `tbl_users_login` ORDER BY fullname');

$badge = static function (string $status): string {
    $map = [
        'Active' => 'success', 'Present' => 'success', 'Approved' => 'success', 'Won' => 'success',
        'Verified' => 'info', 'Leave' => 'info', 'In Progress' => 'info', 'In Repair' => 'warning',
        'Pending' => 'warning', 'New' => 'primary', 'Half Day' => 'warning', 'Assigned' => 'info',
        'Rejected' => 'danger', 'Absent' => 'danger', 'Lost' => 'danger', 'Block' => 'danger',
        'Holiday' => 'secondary', 'Closed' => 'secondary', 'Completed' => 'success',
        'Open' => 'warning', 'Returned' => 'secondary', 'Terminated' => 'danger', 'Deployed' => 'success',
    ];
    return $map[$status] ?? 'secondary';
};

/** Status → ana-pill tone (reuses the $badge colour map). */
$pill = static function (?string $status, ?string $label = null) use ($badge): string {
    $status = (string) $status;
    if ($status === '') {
        return '<span class="text-muted">—</span>';
    }
    $tone = ['success' => 'good', 'warning' => 'warning', 'danger' => 'critical', 'info' => 'info', 'primary' => 'info'][$badge(ucfirst($status))] ?? '';
    return '<span class="ana-pill' . ($tone ? ' ana-pill--' . $tone : '') . '">' . e($label ?? ucfirst($status)) . '</span>';
};
$initials = static function (string $name): string {
    $w = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $first = $w[0] ?? '?';
    $last = count($w) > 1 ? $w[count($w) - 1] : '';
    return mb_strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1));
};
/** Public URL of a staff photo, or '' when none / file missing. */
$photoUrl = static function (?string $path): string {
    $path = ltrim((string) $path, '/');
    return ($path !== '' && is_file(dirname(__DIR__, 3) . '/user_uploads/' . $path)) ? assetUrl('user_uploads/' . $path) : '';
};
$clock = static fn ($t): string => $t ? date('g:i A', (int) strtotime('2000-01-01 ' . $t)) : '—';
$dash = static fn ($v): string => ($v === null || $v === '') ? '—' : (string) $v;
/** One label/value row in a cl-facts grid. $html = pre-escaped value. */
$fact = static function (string $icon, string $label, ?string $value, ?string $html = null): string {
    return '<div class="cl-fact"><span class="cl-fact-icon"><i class="' . e($icon) . '" aria-hidden="true"></i></span>'
        . '<div class="cl-fact-body"><span class="cl-fact-label">' . e($label) . '</span>'
        . '<span class="cl-fact-value">' . ($html ?? e(($value === null || $value === '') ? '—' : $value)) . '</span></div></div>';
};
/** Card header for a flush ana-card section. */
$sectionHead = static function (string $icon, string $title, string $hint = '', string $href = '', string $linkLabel = 'Open'): string {
    return '<div class="ana-card__head"><div class="d-flex align-items-center" style="gap:.65rem">'
        . '<span class="tms-chip tms-chip--primary"><i class="' . e($icon) . '" aria-hidden="true"></i></span>'
        . '<div><h3 class="ana-card__title">' . e($title) . '</h3>' . ($hint !== '' ? '<p class="ana-card__hint">' . e($hint) . '</p>' : '') . '</div></div>'
        . ($href !== '' ? '<a class="ana-card__link" href="' . e($href) . '">' . e($linkLabel) . ' <i class="fas fa-arrow-right ml-1" aria-hidden="true"></i></a>' : '')
        . '</div>';
};
$emptyRow = static fn (int $cols, string $msg): string => '<tr><td colspan="' . $cols . '" class="sd-empty"><i class="far fa-folder-open mr-1" aria-hidden="true"></i>' . e($msg) . '</td></tr>';

if ($view) {
    $uid = (int) $view['id'];
    $today = date('Y-m-d');

    $profile = $db->selectOne('SELECT * FROM `tbl_user_profiles` WHERE `user_id` = ?', [$uid]);
    $profile = is_array($profile) ? $profile : [];

    $attendToday = $db->selectOne('SELECT * FROM `tbl_staff_attendances` WHERE `user_id` = ? AND `date` = ?', [$uid, $today]);
    $attendRecent = $db->select('SELECT `date`, `checkin`, `checkout`, `working_hours`, `status`, `late_checkin`, `early_checkout` FROM `tbl_staff_attendances` WHERE `user_id` = ? ORDER BY `date` DESC LIMIT 7', [$uid]);
    $attendCount = $db->select('SELECT COUNT(*) AS c FROM `tbl_staff_attendances` WHERE `user_id` = ?', [$uid]);

    $leaveSummary = $db->select('SELECT `status`, COUNT(*) AS c FROM `tbl_staff_leave_applications` WHERE `staff_id` = ? GROUP BY `status`', [$uid]);
    $leavesRecent = $db->select('SELECT la.`id`, la.`from_date`, la.`to_date`, la.`leave_days`, la.`status`, la.`added_on`, lc.`title` AS leave_title FROM `tbl_staff_leave_applications` la LEFT JOIN `tbl_office_leave_configs` lc ON lc.id = la.leave_type_id WHERE la.`staff_id` = ? ORDER BY la.`added_on` DESC LIMIT 5', [$uid]);
    $leaveAlloc = $db->select('SELECT a.`year`, a.`allocated_days`, a.`used_days`, a.`carry_forward_days`, lc.`title` AS leave_title FROM `tbl_office_staff_leave_allocation` a LEFT JOIN `tbl_office_leave_configs` lc ON lc.id = a.`leave_id` WHERE a.`staff_id` = ? ORDER BY a.`year` DESC', [$uid]);

    $leadsOpen = $db->selectOne("SELECT COUNT(*) AS c FROM `tbl_leads` WHERE `assigned_to` = ? AND `stage` NOT IN ('Won','Lost')", [$uid]);
    $leads = $db->select('SELECT `id`, `company`, `contact_name`, `referrer_name`, `stage`, `priority`, `estimated_value` FROM `tbl_leads` WHERE `assigned_to` = ? ORDER BY `updated_on` DESC LIMIT 5', [$uid]);

    $tasksAuthored = $db->select('SELECT `id`, `title`, `status`, `deadline` FROM `tbl_office_tasks` WHERE `author` = ? ORDER BY `added_on` DESC LIMIT 5', [$uid]);
    $tasksAssigned = $db->select('SELECT t.`id`, t.`title`, ta.`status` AS assignee_status, t.`deadline` FROM `tbl_office_tasks` t JOIN `tbl_office_task_assignees` ta ON ta.task_id = t.`id` WHERE ta.`staff_id` = ? ORDER BY t.`added_on` DESC, ta.`id` DESC LIMIT 5', [$uid]);

    $dailyTasks = $db->select('SELECT `date`, `tasks` FROM `tbl_daily_tasks` WHERE `staff_id` = ? ORDER BY `date` DESC, `id` DESC LIMIT 5', [$uid]);

    $events = $db->select('SELECT `id`, `title`, `start_date`, `end_date`, `type`, `privacy`, `venue_location` FROM `tbl_office_events` WHERE `added_by` = ? ORDER BY `start_date` DESC LIMIT 5', [$uid]);
    $grievances = $db->select('SELECT `id`, `title`, `status`, `deadline` FROM `tbl_office_grievances` WHERE `author` = ? OR `assigned` = ? ORDER BY `added_on` DESC LIMIT 5', [$uid, $uid]);

    $claims = $db->select('SELECT `id`, `claim_no`, `category`, `expense_date`, `amount`, `status` FROM `tbl_expense_claims` WHERE `staff_id` = ? ORDER BY `added_on` DESC LIMIT 5', [$uid]);

    $assetsCount = $db->selectOne('SELECT COUNT(*) AS c FROM `tbl_inv_assets` WHERE `assigned_to` = ?', [$uid]);
    $assets = $db->select('SELECT `id`, `name`, `asset_tag`, `current_status`, `assigned_on` FROM `tbl_inv_assets` WHERE `assigned_to` = ? ORDER BY `assigned_on` DESC LIMIT 10', [$uid]);

    $history = $db->select('SELECT h.`event_type`, h.`details`, h.`event_date`, u.`fullname` AS actor_name FROM `tbl_staff_history` h LEFT JOIN `tbl_users_login` u ON u.id = h.actor_id WHERE h.`staff_id` = ? ORDER BY h.`event_date` DESC, h.`id` DESC LIMIT 5', [$uid]);
    $docs = $db->select('SELECT `id`, `title`, `document_type`, `document_name` FROM `tbl_staff_documents` WHERE `staff_id` = ? ORDER BY `added_on` DESC LIMIT 5', [$uid]);

    $moduleCount = count(array_filter(json_decode($view['permitted_modules'] ?? '[]', true) ?: [], 'is_string'));
    $grantedModules = array_filter(json_decode((string) $view['permitted_modules'], true) ?: [], 'is_string');
    $pendingLeaves = 0;
    foreach ($leaveSummary as $ls) {
        if ($ls['status'] === 'Pending') {
            $pendingLeaves = (int) $ls['c'];
        }
    }

    $leaveTotal = 0;
    foreach ($leaveSummary as $ls) {
        $leaveTotal += (int) $ls['c'];
    }
    $tenure = '';
    if (!empty($view['join_date'])) {
        try {
            $diff = (new DateTimeImmutable((string) $view['join_date']))->diff(new DateTimeImmutable($today));
            $tenure = $diff->invert ? 'joins soon' : trim(($diff->y ? $diff->y . 'y ' : '') . $diff->m . 'm');
        } catch (Throwable $e) {
            $tenure = '';
        }
    }
    $attStatus = $attendToday ? ucfirst((string) $attendToday['status']) : '';
    $attTone = !$attendToday ? 'neutral' : (in_array($attStatus, ['Present', 'Half Day'], true) ? 'success' : ($attStatus === 'Absent' ? 'danger' : 'info'));
    $openLeads = (int) ($leadsOpen['c'] ?? 0);
    $assetTotal = (int) ($assetsCount['c'] ?? 0);
    $taskTotal = count($tasksAssigned) + count($tasksAuthored);
    $activityTotal = count($events) + count($grievances) + count($claims);
}
?>
<div class="row sd-page">
    <!-- Staff directory -->
    <div class="col-lg-3 mb-3">
        <div class="ana-card ana-card--flush sd-dir">
            <div class="sd-dir__head">
                <h3 class="ana-card__title">Staff <span class="sd-count"><?= count($staff) ?></span></h3>
                <div class="sd-dir__search">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input type="search" id="sdStaffSearch" class="form-control form-control-sm" placeholder="Search staff…" aria-label="Search staff" autocomplete="off">
                </div>
            </div>
            <ul class="sd-dir__list" id="sdStaffList">
                <?php foreach ($staff as $s): ?>
                    <?php $isActive = $view && (int) $view['id'] === (int) $s['id']; ?>
                    <li data-search="<?= e(mb_strtolower($s['fullname'] . ' ' . $s['username'])) ?>">
                        <a href="<?= pageUrl('staff_management', 'detail') ?>&id=<?= (int) $s['id'] ?>" class="sd-dir__item<?= $isActive ? ' is-active' : '' ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                            <?php $sPhoto = $photoUrl($s['photo'] ?? null); ?>
                            <span class="sd-avatar sd-avatar--sm"><?php if ($sPhoto !== ''): ?><img src="<?= e($sPhoto) ?>" alt="" loading="lazy"><?php else: ?><?= e($initials((string) $s['fullname'])) ?><?php endif; ?></span>
                            <span class="sd-dir__text">
                                <span class="sd-dir__name"><?= e($s['fullname']) ?></span>
                                <span class="sd-dir__meta">@<?= e($s['username']) ?></span>
                            </span>
                            <span class="sd-dot sd-dot--<?= e(strtolower((string) $s['status'])) ?>" title="<?= e($s['status']) ?>"></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="sd-empty mb-0 d-none" id="sdStaffNoMatch">No staff match your search.</p>
        </div>
    </div>

    <div class="col-lg-9">
        <?php if (!$view): ?>
            <div class="ana-card sd-blank">
                <span class="tms-chip tms-chip--primary sd-blank__icon"><i class="fas fa-user-friends" aria-hidden="true"></i></span>
                <h3 class="ana-card__title">Select a staff member</h3>
                <p class="ana-card__hint">Pick someone from the list to see their profile, attendance, leaves, work and everything associated with them.</p>
            </div>
        <?php else: ?>
            <!-- Hero -->
            <section class="cl-hero mb-3">
                <span class="cl-hero-glow cl-hero-glow--a"></span>
                <span class="cl-hero-glow cl-hero-glow--b"></span>
                <div class="cl-hero-inner">
                    <?php $heroPhoto = $photoUrl($view['photo'] ?? null); ?>
                    <?php if ($heroPhoto !== ''): ?>
                        <img src="<?= e($heroPhoto) ?>" alt="<?= e($view['fullname']) ?>" class="cl-avatar sd-avatar-photo">
                    <?php else: ?>
                        <span class="cl-avatar"><?= e($initials((string) $view['fullname'])) ?></span>
                    <?php endif; ?>
                    <div class="cl-hero-meta">
                        <div class="cl-hero-title-row">
                            <h2 class="cl-hero-name"><?= e($view['fullname']) ?></h2>
                            <span class="sd-hero-badge sd-hero-badge--<?= e(strtolower((string) $view['status'])) ?>"><?= e($view['status']) ?></span>
                            <?php if (!empty($view['role'])): ?><span class="sd-hero-badge"><?= e($view['role']) ?></span><?php endif; ?>
                        </div>
                        <div class="cl-hero-contact">
                            <?= e(implode(' · ', array_filter([(string) ($view['designation_title'] ?? ''), (string) ($view['department_title'] ?? ''), (string) ($view['staff_type'] ?? '')]))) ?: '@' . e($view['username']) ?>
                        </div>
                        <div class="sd-hero-facts">
                            <span><i class="fas fa-at" aria-hidden="true"></i><?= e($view['username']) ?></span>
                            <?php if (!empty($view['email'])): ?><span><i class="far fa-envelope" aria-hidden="true"></i><a href="mailto:<?= e($view['email']) ?>"><?= e($view['email']) ?></a></span><?php endif; ?>
                            <?php if (!empty($view['phone1'])): ?><span><i class="fas fa-phone-alt" aria-hidden="true"></i><a href="tel:<?= e($view['phone1']) ?>"><?= e($view['phone1']) ?></a></span><?php endif; ?>
                            <?php if (!empty($view['join_date'])): ?><span><i class="far fa-calendar-check" aria-hidden="true"></i>Joined <?= e(formatDateView($view['join_date'])) ?><?= $tenure !== '' ? ' · ' . e($tenure) : '' ?></span><?php endif; ?>
                        </div>
                    </div>
                    <div class="cl-hero-actions">
                        <a href="<?= pageUrl('staff_management', 'add_staff') ?>&id=<?= $uid ?>" class="btn btn-sm btn-ghost"><i class="fas fa-pen mr-1" aria-hidden="true"></i>Edit</a>
                        <a href="<?= pageUrl('staff_management', 'staff_history') ?>&id=<?= $uid ?>" class="btn btn-sm btn-ghost"><i class="fas fa-history mr-1" aria-hidden="true"></i>History</a>
                        <a href="<?= pageUrl('staff_management', 'staff_documents') ?>&id=<?= $uid ?>" class="btn btn-sm btn-ghost"><i class="fas fa-folder-open mr-1" aria-hidden="true"></i>Files</a>
                    </div>
                </div>
            </section>

            <!-- KPI tiles -->
            <div class="ana-grid">
                <a href="<?= pageUrl('staff_management', 'hr_care') ?>&tab=attendance" class="ana-tile ana-tone-<?= e($attTone) ?>">
                    <div class="ana-tile__head">
                        <span class="ana-tile__icon"><i class="fas fa-fingerprint" aria-hidden="true"></i></span>
                        <span class="ana-tile__label">Attendance today</span>
                        <i class="fas fa-arrow-right ana-tile__go" aria-hidden="true"></i>
                    </div>
                    <div class="ana-tile__value"><?= $attendToday ? e($attStatus) : 'Not in' ?></div>
                    <div class="ana-tile__foot"><span class="ana-tile__sub">
                        <?php if ($attendToday && $attendToday['checkin']): ?>
                            In <?= e($clock($attendToday['checkin'])) ?><?= $attendToday['checkout'] ? ' · Out ' . e($clock($attendToday['checkout'])) : '' ?>
                        <?php else: ?>
                            <?= ($view['checkin'] && $view['checkout']) ? 'Shift ' . e($clock($view['checkin'])) . ' – ' . e($clock($view['checkout'])) : 'No check-in today' ?>
                        <?php endif; ?>
                    </span>
                    <?php if ($attendToday && $attendToday['late_checkin']): ?><span class="ana-pill ana-pill--warning"><?= e(formatMinutes((int) $attendToday['late_checkin_minutes'])) ?> late</span><?php endif; ?>
                    </div>
                </a>
                <a href="<?= pageUrl('staff_management', 'leave_management') ?>" class="ana-tile ana-tone-<?= $pendingLeaves > 0 ? 'warning' : 'neutral' ?>">
                    <div class="ana-tile__head">
                        <span class="ana-tile__icon"><i class="fas fa-plane-departure" aria-hidden="true"></i></span>
                        <span class="ana-tile__label">Pending leaves</span>
                        <i class="fas fa-arrow-right ana-tile__go" aria-hidden="true"></i>
                    </div>
                    <div class="ana-tile__value"><?= (int) $pendingLeaves ?></div>
                    <div class="ana-tile__foot"><span class="ana-tile__sub"><?= (int) $leaveTotal ?> application<?= $leaveTotal === 1 ? '' : 's' ?> in total</span></div>
                </a>
                <a href="<?= pageUrl('sales', 'leads') ?>" class="ana-tile ana-tone-<?= $openLeads > 0 ? 'info' : 'neutral' ?>">
                    <div class="ana-tile__head">
                        <span class="ana-tile__icon"><i class="fas fa-filter" aria-hidden="true"></i></span>
                        <span class="ana-tile__label">Open leads</span>
                        <i class="fas fa-arrow-right ana-tile__go" aria-hidden="true"></i>
                    </div>
                    <div class="ana-tile__value"><?= $openLeads ?></div>
                    <div class="ana-tile__foot"><span class="ana-tile__sub">assigned, not won or lost</span></div>
                </a>
                <a href="<?= pageUrl('inventory', 'assets') ?>" class="ana-tile ana-tone-<?= $assetTotal > 0 ? 'teal' : 'neutral' ?>">
                    <div class="ana-tile__head">
                        <span class="ana-tile__icon"><i class="fas fa-laptop" aria-hidden="true"></i></span>
                        <span class="ana-tile__label">Assets assigned</span>
                        <i class="fas fa-arrow-right ana-tile__go" aria-hidden="true"></i>
                    </div>
                    <div class="ana-tile__value"><?= $assetTotal ?></div>
                    <div class="ana-tile__foot"><span class="ana-tile__sub">company equipment held</span></div>
                </a>
            </div>

            <!-- Tabs -->
            <?php
            $sdTabs = [
                'profile'    => ['Profile', 'fas fa-id-card', null],
                'attendance' => ['Attendance', 'fas fa-fingerprint', (int) ($attendCount['c'] ?? 0)],
                'leaves'     => ['Leaves', 'fas fa-plane-departure', $leaveTotal],
                'work'       => ['Work', 'fas fa-briefcase', $openLeads + $taskTotal],
                'activity'   => ['Activity', 'fas fa-stream', $activityTotal],
                'assets'     => ['Assets & files', 'fas fa-laptop', $assetTotal + count($docs)],
                'history'    => ['History', 'fas fa-history', count($history)],
                'access'     => ['Access', 'fas fa-user-shield', $moduleCount],
            ];
            ?>
            <ul class="nav sd-tabs" role="tablist">
                <?php foreach ($sdTabs as $tk => [$tLabel, $tIcon, $tCount]): ?>
                    <li class="nav-item">
                        <a class="nav-link<?= $tk === 'profile' ? ' active' : '' ?>" id="sd-tab-<?= e($tk) ?>" data-toggle="tab" href="#sd-<?= e($tk) ?>" role="tab" aria-controls="sd-<?= e($tk) ?>" aria-selected="<?= $tk === 'profile' ? 'true' : 'false' ?>">
                            <i class="<?= e($tIcon) ?>" aria-hidden="true"></i><?= e($tLabel) ?>
                            <?php if ($tCount): ?><span class="sd-count"><?= (int) $tCount ?></span><?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="tab-content sd-panes">
                <!-- Profile -->
                <div class="tab-pane fade show active" id="sd-profile" role="tabpanel" aria-labelledby="sd-tab-profile">
                    <div class="row ana-row">
                        <div class="col-md-6">
                            <div class="ana-card">
                                <?= $sectionHead('fas fa-user', 'Personal') ?>
                                <div class="cl-facts cl-facts--stacked">
                                    <?= $fact('fas fa-envelope', 'Email', null, !empty($view['email']) ? '<a href="mailto:' . e($view['email']) . '">' . e($view['email']) . '</a>' : null) ?>
                                    <?= $fact('fas fa-phone-alt', 'Phone', $view['phone1']) ?>
                                    <?= $fact('fas fa-venus-mars', 'Gender', $view['gender']) ?>
                                    <?= $fact('fas fa-birthday-cake', 'Date of birth', $view['dob'] ? formatDateView($view['dob']) : '') ?>
                                    <?= $fact('fas fa-map-marker-alt', 'Address', $view['address']) ?>
                                    <?= $fact('fas fa-id-badge', 'Citizenship', $view['citizenship']) ?>
                                    <?= $fact('fas fa-ring', 'Marital status', $view['marital_status']) ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="ana-card">
                                <?= $sectionHead('fas fa-briefcase', 'Employment') ?>
                                <div class="cl-facts cl-facts--stacked">
                                    <?= $fact('fas fa-user-tag', 'Staff type', $view['staff_type']) ?>
                                    <?= $fact('fas fa-layer-group', 'Department', $view['department_title'] ?? '') ?>
                                    <?= $fact('fas fa-id-card', 'Designation', $view['designation_title'] ?? '') ?>
                                    <?= $fact('far fa-calendar-check', 'Join date', $view['join_date'] ? formatDateView($view['join_date']) . ($tenure !== '' ? ' (' . $tenure . ')' : '') : '') ?>
                                    <?= $fact('far fa-clock', 'Working hours', implode(' · ', array_filter([
                                        ($view['checkin'] && $view['checkout']) ? $clock($view['checkin']) . ' – ' . $clock($view['checkout']) : '',
                                        $view['daily_working_hour'] !== null ? rtrim(rtrim((string) $view['daily_working_hour'], '0'), '.') . ' hr/day' : '',
                                    ]))) ?>
                                    <?= $fact('fas fa-bed', 'Off day(s)', trim(str_replace(',', ', ', (string) $view['off_day']), ', ')) ?>
                                    <?= $fact('fas fa-user-plus', 'Added by', $view['added_by_name'] ?? '') ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="ana-card">
                                <?= $sectionHead('fas fa-university', 'Payroll & bank') ?>
                                <div class="cl-facts cl-facts--stacked">
                                    <?= $fact('fas fa-file-invoice', 'PAN no', $view['pan_num']) ?>
                                    <?= $fact('fas fa-university', 'Bank', $view['bank']) ?>
                                    <?= $fact('fas fa-credit-card', 'Bank account', trim(($view['bank_account_num'] ?: '') . ($view['bank_account_name'] ? ' · ' . $view['bank_account_name'] : ''))) ?>
                                    <?= $fact('fas fa-shield-alt', 'SSF no', $view['ssf_number']) ?>
                                    <?= $fact('fas fa-piggy-bank', 'PF no', $view['pf_number']) ?>
                                    <?= $fact('fas fa-coins', 'CIT no', $view['cit_number']) ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="ana-card">
                                <?= $sectionHead('fas fa-first-aid', 'Emergency & profile') ?>
                                <div class="cl-facts cl-facts--stacked">
                                    <?= $fact('fas fa-tint', 'Blood group', $profile['blood_group'] ?? '') ?>
                                    <?= $fact('fas fa-user-friends', 'Emergency contact', trim(($profile['emergency_contact_name'] ?? '') . (!empty($profile['emergency_contact_relation']) ? ' (' . $profile['emergency_contact_relation'] . ')' : ''))) ?>
                                    <?= $fact('fas fa-mobile-alt', 'Emergency mobile', null, !empty($profile['emergency_contact_mobile']) ? '<a href="tel:' . e($profile['emergency_contact_mobile']) . '">' . e($profile['emergency_contact_mobile']) . '</a>' : null) ?>
                                    <?= $fact('fas fa-chart-line', 'Work experience', $profile['work_experience'] ?? '') ?>
                                    <?= $fact('fas fa-tools', 'Skills', $profile['skill'] ?? '') ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attendance -->
                <div class="tab-pane fade" id="sd-attendance" role="tabpanel" aria-labelledby="sd-tab-attendance">
                    <div class="ana-card ana-card--flush">
                        <?= $sectionHead('fas fa-fingerprint', 'Recent attendance', 'Last 7 records · ' . (int) ($attendCount['c'] ?? 0) . ' in total', pageUrl('staff_management', 'hr_care') . '&tab=attendance', 'Open attendance') ?>
                        <div class="table-responsive">
                            <table class="table ana-table">
                                <thead><tr><th>Date</th><th>Check-in</th><th>Check-out</th><th>Worked</th><th>Status</th></tr></thead>
                                <tbody>
                                <?php foreach ($attendRecent as $r): ?>
                                    <tr>
                                        <td class="text-nowrap"><?= e(formatDateView($r['date'])) ?></td>
                                        <td><?= e($clock($r['checkin'])) ?><?= $r['late_checkin'] ? ' <span class="ana-pill ana-pill--warning">late</span>' : '' ?></td>
                                        <td><?= e($clock($r['checkout'])) ?><?= $r['early_checkout'] ? ' <span class="ana-pill ana-pill--info">early</span>' : '' ?></td>
                                        <td><?= $r['working_hours'] !== null ? e(formatMinutes((int) round($r['working_hours'] * 60))) : '—' ?></td>
                                        <td><?= $pill((string) $r['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$attendRecent): ?><?= $emptyRow(5, 'No attendance records yet.') ?><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Leaves -->
                <div class="tab-pane fade" id="sd-leaves" role="tabpanel" aria-labelledby="sd-tab-leaves">
                    <?php if ($leaveAlloc): ?>
                        <div class="ana-card ana-card--flush">
                            <?= $sectionHead('fas fa-balance-scale', 'Leave balance', 'Allocated vs used, by leave type') ?>
                            <div class="table-responsive">
                                <table class="table ana-table">
                                    <thead><tr><th>Leave type</th><th>Year</th><th>Used / allocated</th><th class="text-right">Carry fwd</th></tr></thead>
                                    <tbody>
                                    <?php foreach (array_slice($leaveAlloc, 0, 8) as $al): ?>
                                        <?php
                                        $alloc = (float) $al['allocated_days'];
                                        $used = (float) $al['used_days'];
                                        $usePct = $alloc > 0 ? min(100, round($used / $alloc * 100)) : 0;
                                        ?>
                                        <tr>
                                            <td><?= e($al['leave_title'] ?? 'Leave') ?></td>
                                            <td><?= e($al['year']) ?></td>
                                            <td>
                                                <div class="ana-meter ana-meter--<?= $usePct >= 90 ? 'critical' : ($usePct >= 70 ? 'warning' : 'good') ?>">
                                                    <div class="ana-meter__track"><div class="ana-meter__fill" style="width:<?= (int) $usePct ?>%"></div></div>
                                                    <span class="ana-meter__val"><?= e(rtrim(rtrim(number_format($used, 1), '0'), '.')) ?> / <?= e(rtrim(rtrim(number_format($alloc, 1), '0'), '.')) ?></span>
                                                </div>
                                            </td>
                                            <td class="text-right"><?= e($al['carry_forward_days']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="ana-card ana-card--flush">
                        <?= $sectionHead('fas fa-plane-departure', 'Leave applications', 'Most recent 5', pageUrl('staff_management', 'leave_management'), 'Leave management') ?>
                        <?php if ($leaveSummary): ?>
                            <div class="sd-pillbar">
                                <?php foreach ($leaveSummary as $ls): ?>
                                    <?= $pill((string) $ls['status'], $ls['status'] . ' · ' . (int) $ls['c']) ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div class="table-responsive">
                            <table class="table ana-table">
                                <thead><tr><th>Type</th><th>Dates</th><th>Days</th><th>Status</th></tr></thead>
                                <tbody>
                                <?php foreach ($leavesRecent as $l): ?>
                                    <tr>
                                        <td><?= e($l['leave_title'] ?? '—') ?></td>
                                        <td class="text-nowrap"><?= e(formatDateView($l['from_date'])) ?> → <?= e(formatDateView($l['to_date'])) ?></td>
                                        <td><?= e($l['leave_days']) ?></td>
                                        <td><?= $pill((string) $l['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$leavesRecent): ?><?= $emptyRow(4, 'No leave applications.') ?><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Work -->
                <div class="tab-pane fade" id="sd-work" role="tabpanel" aria-labelledby="sd-tab-work">
                    <div class="ana-card ana-card--flush">
                        <?= $sectionHead('fas fa-filter', 'Assigned leads', $openLeads . ' open · latest 5', pageUrl('sales', 'leads'), 'Leads pipeline') ?>
                        <div class="table-responsive">
                            <table class="table ana-table">
                                <thead><tr><th>Company</th><th>Contact</th><th>Stage</th><th>Priority</th><th class="text-right">Est. value</th></tr></thead>
                                <tbody>
                                <?php foreach ($leads as $lead): ?>
                                    <tr>
                                        <td><a href="<?= pageUrl('sales', 'leads') ?>&lead_id=<?= (int) $lead['id'] ?>" class="font-weight-bold"><?= e($lead['company'] ?: '—') ?></a></td>
                                        <td><?= e($lead['contact_name'] ?: $lead['referrer_name'] ?: '—') ?></td>
                                        <td><?= $pill((string) ($lead['stage'] ?: 'New')) ?></td>
                                        <td><?= $pill((string) $lead['priority']) ?></td>
                                        <td class="text-right"><?= $lead['estimated_value'] !== null ? e(number_format((float) $lead['estimated_value'], 2)) : '—' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$leads): ?><?= $emptyRow(5, 'No leads assigned.') ?><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="row ana-row">
                        <div class="col-md-6">
                            <div class="ana-card">
                                <?= $sectionHead('fas fa-tasks', 'Assigned tasks', 'Latest 5', pageUrl('staff_management', 'hr_care') . '&tab=tasks') ?>
                                <ul class="ana-list">
                                    <?php foreach ($tasksAssigned as $t): ?>
                                        <li>
                                            <span class="ana-list__icon"><i class="fas fa-check" aria-hidden="true"></i></span>
                                            <div class="ana-list__main"><span class="ana-list__title"><?= e($t['title']) ?></span><span class="ana-list__meta"><?= $t['deadline'] ? 'Due ' . e(formatDateView(date('Y-m-d', (int) strtotime($t['deadline'])))) : 'No deadline' ?></span></div>
                                            <?= $pill((string) $t['assignee_status']) ?>
                                        </li>
                                    <?php endforeach; ?>
                                    <?php if (!$tasksAssigned): ?><li class="sd-empty">No tasks assigned.</li><?php endif; ?>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="ana-card">
                                <?= $sectionHead('fas fa-pen-square', 'Created tasks', 'Latest 5') ?>
                                <ul class="ana-list">
                                    <?php foreach ($tasksAuthored as $t): ?>
                                        <li>
                                            <span class="ana-list__icon"><i class="fas fa-pen" aria-hidden="true"></i></span>
                                            <div class="ana-list__main"><span class="ana-list__title"><?= e($t['title']) ?></span><span class="ana-list__meta"><?= $t['deadline'] ? 'Due ' . e(formatDateView(date('Y-m-d', (int) strtotime($t['deadline'])))) : 'No deadline' ?></span></div>
                                            <?= $pill((string) $t['status']) ?>
                                        </li>
                                    <?php endforeach; ?>
                                    <?php if (!$tasksAuthored): ?><li class="sd-empty">No tasks created.</li><?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="ana-card">
                        <?= $sectionHead('fas fa-clipboard-list', 'Daily task log', 'Latest 5 entries', pageUrl('staff_management', 'staff_daily_tasks'), 'Daily tasks') ?>
                        <?php if ($dailyTasks): ?>
                            <div class="sd-log">
                                <?php foreach ($dailyTasks as $dt): ?>
                                    <div class="sd-log__item">
                                        <span class="sd-log__date"><?= e(formatDateView($dt['date'])) ?></span>
                                        <div class="sd-log__body"><?= renderRichText((string) $dt['tasks']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="sd-empty mb-0">No daily task logs.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Activity -->
                <div class="tab-pane fade" id="sd-activity" role="tabpanel" aria-labelledby="sd-tab-activity">
                    <div class="ana-card ana-card--flush">
                        <?= $sectionHead('fas fa-calendar-alt', 'Meetings & events', 'Created by this staff member', pageUrl('staff_management', 'hr_care') . '&tab=meetings') ?>
                        <div class="table-responsive">
                            <table class="table ana-table">
                                <thead><tr><th>Title</th><th>Dates</th><th>Type</th><th>Privacy</th><th>Venue</th></tr></thead>
                                <tbody>
                                <?php foreach ($events as $ev): ?>
                                    <tr>
                                        <td class="font-weight-bold"><?= e($ev['title']) ?></td>
                                        <td class="text-nowrap"><?= e(formatDateView($ev['start_date'])) ?><?= $ev['end_date'] && $ev['end_date'] !== $ev['start_date'] ? ' → ' . e(formatDateView($ev['end_date'])) : '' ?></td>
                                        <td><?= e($dash($ev['type'])) ?></td>
                                        <td><?= $pill((string) $ev['privacy']) ?></td>
                                        <td><?= e($dash($ev['venue_location'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$events): ?><?= $emptyRow(5, 'No events created.') ?><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="row ana-row">
                        <div class="col-md-6">
                            <div class="ana-card">
                                <?= $sectionHead('fas fa-comments', 'Speak Up', 'Raised by or assigned to', pageUrl('staff_management', 'hr_care') . '&tab=speak_up') ?>
                                <ul class="ana-list">
                                    <?php foreach ($grievances as $gr): ?>
                                        <li>
                                            <span class="ana-list__icon"><i class="fas fa-comment-dots" aria-hidden="true"></i></span>
                                            <div class="ana-list__main"><span class="ana-list__title"><?= e($gr['title']) ?></span><span class="ana-list__meta"><?= $gr['deadline'] ? 'Due ' . e(formatDateView(date('Y-m-d', (int) strtotime($gr['deadline'])))) : 'No deadline' ?></span></div>
                                            <?= $pill((string) ($gr['status'] ?: 'Open')) ?>
                                        </li>
                                    <?php endforeach; ?>
                                    <?php if (!$grievances): ?><li class="sd-empty">No grievances.</li><?php endif; ?>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="ana-card">
                                <?= $sectionHead('fas fa-receipt', 'Expense claims', 'Latest 5', pageUrl('my_office', 'expense_claims')) ?>
                                <ul class="ana-list">
                                    <?php foreach ($claims as $c): ?>
                                        <li>
                                            <span class="ana-list__icon"><i class="fas fa-receipt" aria-hidden="true"></i></span>
                                            <div class="ana-list__main"><span class="ana-list__title"><?= e($c['claim_no'] ?: '#' . $c['id']) ?> · <?= e(number_format((float) $c['amount'], 2)) ?></span><span class="ana-list__meta"><?= e($dash($c['category'])) ?><?= $c['expense_date'] ? ' · ' . e(formatDateView($c['expense_date'])) : '' ?></span></div>
                                            <?= $pill((string) $c['status']) ?>
                                        </li>
                                    <?php endforeach; ?>
                                    <?php if (!$claims): ?><li class="sd-empty">No expense claims.</li><?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Assets & files -->
                <div class="tab-pane fade" id="sd-assets" role="tabpanel" aria-labelledby="sd-tab-assets">
                    <div class="ana-card ana-card--flush">
                        <?= $sectionHead('fas fa-laptop', 'Assigned assets', $assetTotal . ' held', pageUrl('inventory', 'assets'), 'Assets') ?>
                        <div class="table-responsive">
                            <table class="table ana-table">
                                <thead><tr><th>Asset</th><th>Tag</th><th>Assigned on</th><th>Status</th></tr></thead>
                                <tbody>
                                <?php foreach ($assets as $asset): ?>
                                    <tr>
                                        <td class="font-weight-bold"><?= e($asset['name']) ?></td>
                                        <td><code><?= e($dash($asset['asset_tag'])) ?></code></td>
                                        <td><?= $asset['assigned_on'] ? e(formatDateView($asset['assigned_on'])) : '—' ?></td>
                                        <td><?= $pill((string) $asset['current_status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$assets): ?><?= $emptyRow(4, 'No assets assigned.') ?><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="ana-card">
                        <?= $sectionHead('fas fa-folder-open', 'Documents', 'Latest 5', pageUrl('staff_management', 'staff_documents') . '&id=' . $uid, 'All files') ?>
                        <ul class="ana-list">
                            <?php foreach ($docs as $doc): ?>
                                <li>
                                    <span class="ana-list__icon"><i class="far fa-file-alt" aria-hidden="true"></i></span>
                                    <div class="ana-list__main"><span class="ana-list__title"><?= e($doc['title']) ?></span><span class="ana-list__meta"><?= e($doc['document_name']) ?></span></div>
                                    <span class="ana-pill"><?= e($dash($doc['document_type'])) ?></span>
                                </li>
                            <?php endforeach; ?>
                            <?php if (!$docs): ?><li class="sd-empty">No documents uploaded.</li><?php endif; ?>
                        </ul>
                    </div>
                </div>

                <!-- History -->
                <div class="tab-pane fade" id="sd-history" role="tabpanel" aria-labelledby="sd-tab-history">
                    <div class="ana-card">
                        <?= $sectionHead('fas fa-history', 'Staff history', 'Latest 5 events', pageUrl('staff_management', 'staff_history') . '&id=' . $uid, 'Full history') ?>
                        <?php if ($history): ?>
                            <ol class="sd-timeline">
                                <?php foreach ($history as $h): ?>
                                    <li>
                                        <div class="sd-timeline__head"><?= $pill((string) $h['event_type']) ?><span class="sd-timeline__date"><?= e(formatDateView($h['event_date'])) ?><?= $h['actor_name'] ? ' · by ' . e($h['actor_name']) : '' ?></span></div>
                                        <?php if (!empty($h['details'])): ?><p class="sd-timeline__text"><?= e($h['details']) ?></p><?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php else: ?>
                            <p class="sd-empty mb-0">No history events.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Access -->
                <div class="tab-pane fade" id="sd-access" role="tabpanel" aria-labelledby="sd-tab-access">
                    <div class="ana-card">
                        <?= $sectionHead('fas fa-user-shield', 'Module permissions', $moduleCount . ' module' . ($moduleCount === 1 ? '' : 's') . ' granted', Auth::isSuperAdmin() ? pageUrl('staff_management', 'permissions') . '&id=' . $uid : '', 'Edit permissions') ?>
                        <?php if (!$grantedModules): ?>
                            <p class="sd-empty mb-0">No module access granted — <?= e($view['username']) ?> starts with no permissions.</p>
                        <?php else: ?>
                            <div class="sd-modules">
                                <?php foreach ($grantedModules as $mod): ?>
                                    <span class="sd-module"><i class="<?= e(str_replace('nav-icon ', '', $icons[$mod] ?? 'fas fa-cube')) ?>" aria-hidden="true"></i><?= e($navBars[$mod] ?? ucfirst($mod)) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    // Staff directory filter.
    var input = document.getElementById('sdStaffSearch');
    var list = document.getElementById('sdStaffList');
    var none = document.getElementById('sdStaffNoMatch');
    if (input && list) {
        input.addEventListener('input', function () {
            var q = input.value.trim().toLowerCase(), shown = 0;
            list.querySelectorAll('li').forEach(function (li) {
                var hit = !q || li.getAttribute('data-search').indexOf(q) !== -1;
                li.style.display = hit ? '' : 'none';
                if (hit) { shown++; }
            });
            none.classList.toggle('d-none', shown > 0);
        });
        var active = list.querySelector('.is-active');
        if (active) { active.scrollIntoView({ block: 'nearest' }); }
    }
    // Remember the open tab in the URL hash (survives reload / back).
    if (window.jQuery) {
        jQuery(function ($) {
            var hash = window.location.hash;
            if (hash && $('.sd-tabs a[href="' + hash + '"]').length) {
                $('.sd-tabs a[href="' + hash + '"]').tab('show');
            }
            $('.sd-tabs a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
                history.replaceState(null, '', e.target.getAttribute('href'));
            });
        });
    }
})();
</script>
