<?php
/**
 * SB-Tech — Staff Management / Staff Detail (US-STF-01).
 * Read-only, single-page view of everything associated with a staff member:
 * personal/employment profile, today's attendance, plus recent activity
 * across attendance, leaves, leads, tasks, events, grievances, expense
 * claims, assets, history, documents, and permissions.
 * Inner page of add_staff (same permission gate; no sidebar entry).
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
$staff = $db->select('SELECT id, fullname, username, role, status FROM `tbl_users_login` ORDER BY fullname');

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
?>
<div class="row">
    <div class="col-md-3">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Staff</h3></div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover">
                    <tbody>
                    <?php foreach ($staff as $s): ?>
                        <tr class="<?= $view && (int) $view['id'] === (int) $s['id'] ? 'table-primary' : '' ?>">
                            <td><a href="<?= pageUrl('staff_management', 'detail') ?>&id=<?= (int) $s['id'] ?>"><?= e($s['fullname']) ?></a>
                                <small class="d-block text-muted">@<?= e($s['username']) ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-9">
        <?php if (!$view): ?>
            <div class="callout callout-info"><h5>Select a staff member</h5><p>Pick a staff member to see their full profile and everything associated with them.</p></div>
        <?php else: ?>
            <?php
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
            $leads = $db->select('SELECT `id`, `company`, `contact_name`, `stage`, `priority`, `estimated_value` FROM `tbl_leads` WHERE `assigned_to` = ? ORDER BY `updated_on` DESC LIMIT 5', [$uid]);

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
            ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-user-circle fa-lg mr-1"></i><?= e($view['fullname']) ?>
                        <small class="text-muted">@<?= e($view['username']) ?><?= $view['role'] ? ' · ' . e($view['role']) : '' ?></small>
                    </h3>
                    <div class="card-tools">
                        <a href="<?= pageUrl('staff_management', 'add_staff') ?>&id=<?= $uid ?>" class="btn btn-xs btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                        <a href="<?= pageUrl('staff_management', 'permissions') ?>&id=<?= $uid ?>" class="btn btn-xs btn-outline-secondary" title="Permissions"><i class="fas fa-user-shield"></i></a>
                        <a href="<?= pageUrl('staff_management', 'staff_history') ?>&id=<?= $uid ?>" class="btn btn-xs btn-outline-info" title="History"><i class="fas fa-history"></i></a>
                        <a href="<?= pageUrl('staff_management', 'staff_documents') ?>&id=<?= $uid ?>" class="btn btn-xs btn-outline-warning" title="Files"><i class="fas fa-folder-open"></i></a>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3 col-sm-6">
                    <div class="small-box bg-<?= !$attendToday ? 'secondary' : (in_array($attendToday['status'], ['Present', 'Half Day'], true) ? 'success' : ($attendToday['status'] === 'Absent' ? 'danger' : 'info')) ?>">
                        <div class="inner"><h3><?= $attendToday ? e(ucfirst($attendToday['status'])) : 'No entry' ?></h3>
                            <p>Attendance today · <?= e(formatDateView($today)) ?></p></div>
                        <div class="icon"><i class="fas fa-fingerprint"></i></div>
                        <a href="<?= pageUrl('staff_management', 'hr_care') ?>&tab=attendance" class="small-box-footer">Open attendance <i class="fas fa-arrow-circle-right"></i></a>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="small-box bg-warning">
                        <div class="inner"><h3><?= $pendingLeaves ?></h3><p>Pending leaves</p></div>
                        <div class="icon"><i class="fas fa-plane-departure"></i></div>
                        <a href="<?= pageUrl('staff_management', 'leave_management') ?>" class="small-box-footer">Leave management <i class="fas fa-arrow-circle-right"></i></a>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="small-box bg-<?= $leadsOpen && (int) $leadsOpen['c'] > 0 ? 'primary' : 'secondary' ?>">
                        <div class="inner"><h3><?= (int) ($leadsOpen['c'] ?? 0) ?></h3><p>Open leads assigned</p></div>
                        <div class="icon"><i class="fas fa-filter"></i></div>
                        <a href="<?= pageUrl('leads', 'leads') ?>" class="small-box-footer">Leads pipeline <i class="fas fa-arrow-circle-right"></i></a>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="small-box bg-<?= (int) ($assetsCount['c'] ?? 0) > 0 ? 'success' : 'secondary' ?>">
                        <div class="inner"><h3><?= (int) ($assetsCount['c'] ?? 0) ?></h3><p>Assets assigned</p></div>
                        <div class="icon"><i class="fas fa-laptop"></i></div>
                        <a href="<?= pageUrl('inventory', 'assets') ?>" class="small-box-footer">Assets <i class="fas fa-arrow-circle-right"></i></a>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="card collapsed-card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-user mr-1"></i>Personal</h3>
                            <div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button></div>
                        </div>
                        <div class="card-body p-0" style="display:none">
                            <table class="table table-sm mb-0">
                                <tr><th class="w-40">Full name</th><td><?= e($view['fullname']) ?></td></tr>
                                <tr><th>Username</th><td>@<?= e($view['username']) ?></td></tr>
                                <tr><th>Email</th><td><?= e($view['email'] ?: '—') ?></td></tr>
                                <tr><th>Phone</th><td><?= e($view['phone1'] ?: '—') ?></td></tr>
                                <tr><th>Gender</th><td><?= e($view['gender'] ?: '—') ?></td></tr>
                                <tr><th>Date of birth</th><td><?= e($view['dob'] ? formatDateView($view['dob']) : '—') ?></td></tr>
                                <tr><th>Address</th><td><?= e($view['address'] ?: '—') ?></td></tr>
                                <tr><th>Citizenship</th><td><?= e($view['citizenship'] ?: '—') ?></td></tr>
                                <tr><th>Marital status</th><td><?= e($view['marital_status'] ?: '—') ?></td></tr>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card collapsed-card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-briefcase mr-1"></i>Employment</h3>
                            <div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button></div>
                        </div>
                        <div class="card-body p-0" style="display:none">
                            <table class="table table-sm mb-0">
                                <tr><th class="w-40">Staff type</th><td><?= e($view['staff_type']) ?></td></tr>
                                <tr><th>Department</th><td><?= e($view['department_title'] ?? '—') ?></td></tr>
                                <tr><th>Designation</th><td><?= e($view['designation_title'] ?? '—') ?></td></tr>
                                <tr><th>Join date</th><td><?= e($view['join_date'] ? formatDateView($view['join_date']) : '—') ?></td></tr>
                                <tr><th>Daily working hours</th><td><?= $view['daily_working_hour'] !== null ? e($view['daily_working_hour'] . ' hr') : '—' ?></td></tr>
                                <tr><th>Off day(s)</th><td><?= e(trim(str_replace(',', ', ', (string) $view['off_day']), ', ') ?: '—') ?></td></tr>
                                <tr><th>Work start</th><td><?= $view['checkin'] ? e(date('g:i A', strtotime($view['checkin']))) : '—' ?></td></tr>
                                <tr><th>Work end</th><td><?= $view['checkout'] ? e(date('g:i A', strtotime($view['checkout']))) : '—' ?></td></tr>
                                <tr><th>PAN no</th><td><?= e($view['pan_num'] ?: '—') ?></td></tr>
                                <tr><th>Bank</th><td><?= e($view['bank'] ?: '—') ?></td></tr>
                                <tr><th>Bank account</th><td><?= e(trim(($view['bank_account_num'] ?: '') . ($view['bank_account_name'] ? ' · ' . $view['bank_account_name'] : '')) ?: '—') ?></td></tr>
                                <tr><th>SSF no</th><td><?= e($view['ssf_number'] ?: '—') ?></td></tr>
                                <tr><th>PF no</th><td><?= e($view['pf_number'] ?: '—') ?></td></tr>
                                <tr><th>CIT no</th><td><?= e($view['cit_number'] ?: '—') ?></td></tr>
                                <tr><th>Status</th><td><span class="badge badge-<?= $badge((string) $view['status']) ?>"><?= e($view['status']) ?></span></td></tr>
                                <tr><th>Added by</th><td><?= e($view['added_by_name'] ?: '—') ?></td></tr>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card collapsed-card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-phone-alt mr-1"></i>Emergency &amp; Profile</h3>
                            <div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button></div>
                        </div>
                        <div class="card-body p-0" style="display:none">
                            <table class="table table-sm mb-0">
                                <tr><th class="w-40">Blood group</th><td><?= e($profile['blood_group'] ?? '—') ?></td></tr>
                                <tr><th>Emergency contact</th><td><?= e($profile['emergency_contact_name'] ?? '—') ?></td></tr>
                                <tr><th>Emergency mobile</th><td><?= e($profile['emergency_contact_mobile'] ?? '—') ?></td></tr>
                                <tr><th>Relation</th><td><?= e($profile['emergency_contact_relation'] ?? '—') ?></td></tr>
                                <tr><th>Work experience</th><td><?= e($profile['work_experience'] ?? '—') ?></td></tr>
                                <tr><th>Skills</th><td><?= e($profile['skill'] ?? '—') ?></td></tr>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card collapsed-card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-fingerprint mr-1"></i>Today's attendance</h3>
                            <div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button></div>
                        </div>
                        <div class="card-body p-0" style="display:none">
                            <?php if (!$attendToday): ?>
                                <p class="text-center text-muted py-3 mb-0">No check-in recorded today.</p>
                            <?php else: ?>
                                <table class="table table-sm mb-0">
                                    <tr><th class="w-40">Check-in</th><td><?= $attendToday['checkin'] ? e(date('g:i A', strtotime($attendToday['checkin']))) : '—' ?></td></tr>
                                    <tr><th>Check-out</th><td><?= $attendToday['checkout'] ? e(date('g:i A', strtotime($attendToday['checkout']))) : '—' ?></td></tr>
                                    <tr><th>Working hours</th><td><?= $attendToday['working_hours'] !== null ? e(formatMinutes((int) round($attendToday['working_hours'] * 60))) : '—' ?></td></tr>
                                    <tr><th>Status</th><td><span class="badge badge-<?= $badge((string) $attendToday['status']) ?>"><?= e(ucfirst($attendToday['status'])) ?></span></td></tr>
                                    <?php if ($attendToday['late_checkin']): ?><tr><th>Late by</th><td class="text-warning"><?= e(formatMinutes((int) $attendToday['late_checkin_minutes'])) ?></td></tr><?php endif; ?>
                                    <?php if ($attendToday['early_checkout']): ?><tr><th>Left early by</th><td class="text-primary"><?= e(formatMinutes((int) $attendToday['checkout_early'])) ?></td></tr><?php endif; ?>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card collapsed-card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-fingerprint mr-1"></i>Attendance history <span class="badge badge-light ml-1"><?= (int) ($attendCount['c'] ?? 0) ?></span></h3>
                    <div class="card-tools">
                        <a href="<?= pageUrl('staff_management', 'hr_care') ?>&tab=attendance" class="btn btn-tool"><i class="fas fa-external-link-alt"></i></a>
                        <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button>
                    </div>
                </div>
                <div class="card-body p-0" style="display:none">
                    <table class="table table-sm table-striped mb-0">
                        <thead><tr><th>Date</th><th>Check-in</th><th>Check-out</th><th>Working hours</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($attendRecent as $r): ?>
                            <tr>
                                <td><?= e(formatDateView($r['date'])) ?></td>
                                <td><?= $r['checkin'] ? e(date('g:i A', strtotime($r['checkin']))) : '—' ?></td>
                                <td><?= $r['checkout'] ? e(date('g:i A', strtotime($r['checkout']))) : '—' ?></td>
                                <td><?= $r['working_hours'] !== null ? e(formatMinutes((int) round($r['working_hours'] * 60))) : '—' ?></td>
                                <td><span class="badge badge-<?= $badge((string) $r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$attendRecent): ?><tr><td colspan="5" class="text-center text-muted">No attendance records.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card collapsed-card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-plane-departure mr-1"></i>Leave applications <span class="badge badge-light ml-1"><?php
                        $leaveTotal = 0; foreach ($leaveSummary as $ls) { $leaveTotal += (int) $ls['c']; } echo $leaveTotal;
                    ?></span></h3>
                    <div class="card-tools">
                        <a href="<?= pageUrl('staff_management', 'leave_management') ?>" class="btn btn-tool"><i class="fas fa-external-link-alt"></i></a>
                        <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button>
                    </div>
                </div>
                <div class="card-body p-0" style="display:none">
                    <?php if ($leaveSummary): ?>
                        <div class="px-3 pt-2">
                            <?php foreach ($leaveSummary as $ls): ?>
                                <span class="badge badge-<?= $badge((string) $ls['status']) ?> mr-1"><?= e($ls['status']) ?>: <?= (int) $ls['c'] ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <table class="table table-sm table-striped mb-0 mt-1">
                        <thead><tr><th>Type</th><th>Dates</th><th>Days</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($leavesRecent as $l): ?>
                            <tr>
                                <td><?= e($l['leave_title'] ?? '—') ?></td>
                                <td><?= e(formatDateView($l['from_date'])) ?> → <?= e(formatDateView($l['to_date'])) ?></td>
                                <td><?= e($l['leave_days']) ?></td>
                                <td><span class="badge badge-<?= $badge((string) $l['status']) ?>"><?= e($l['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$leavesRecent): ?><tr><td colspan="4" class="text-center text-muted">No leave applications.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                    <?php if ($leaveAlloc): ?>
                        <table class="table table-sm table-bordered mb-0">
                            <thead><tr><th>Leave balance</th><th>Allocated</th><th>Used</th><th>Carry forward</th></tr></thead>
                            <tbody>
                            <?php foreach (array_slice($leaveAlloc, 0, 8) as $al): ?>
                                <tr>
                                    <td><?= e($al['leave_title'] ?? 'Leave #' . $al['year']) ?> <small class="text-muted"><?= e($al['year']) ?></small></td>
                                    <td><?= e($al['allocated_days']) ?></td>
                                    <td><?= e($al['used_days']) ?></td>
                                    <td><?= e($al['carry_forward_days']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card collapsed-card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-filter mr-1"></i>Assigned leads <span class="badge badge-light ml-1"><?= (int) ($leadsOpen['c'] ?? 0) ?> open</span></h3>
                    <div class="card-tools">
                        <a href="<?= pageUrl('leads', 'leads') ?>" class="btn btn-tool"><i class="fas fa-external-link-alt"></i></a>
                        <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button>
                    </div>
                </div>
                <div class="card-body p-0" style="display:none">
                    <table class="table table-sm table-striped mb-0">
                        <thead><tr><th>Company</th><th>Contact</th><th>Stage</th><th>Priority</th><th>Est. value</th></tr></thead>
                        <tbody>
                        <?php foreach ($leads as $lead): ?>
                            <tr>
                                <td><a href="<?= pageUrl('leads', 'leads') ?>&lead_id=<?= (int) $lead['id'] ?>"><?= e($lead['company']) ?></a></td>
                                <td><?= e($lead['contact_name'] ?: '—') ?></td>
                                <td><span class="badge badge-<?= $badge((string) $lead['stage']) ?>"><?= e($lead['stage'] ?: 'New') ?></span></td>
                                <td><?= $lead['priority'] ? '<span class="badge badge-' . $badge((string) $lead['priority']) . '">' . e($lead['priority']) . '</span>' : '—' ?></td>
                                <td><?= $lead['estimated_value'] !== null ? e(number_format((float) $lead['estimated_value'], 2)) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$leads): ?><tr><td colspan="5" class="text-center text-muted">No leads assigned.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card collapsed-card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-tasks mr-1"></i>Tasks</h3>
                    <div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button></div>
                </div>
                <div class="card-body p-0" style="display:none">
                    <?php if ($tasksAssigned): ?>
                        <table class="table table-sm table-striped mb-0">
                            <thead><tr><th colspan="3">Assigned tasks</th></tr>
                                <tr><th>Title</th><th>Status</th><th>Deadline</th></tr></thead>
                            <tbody>
                            <?php foreach ($tasksAssigned as $t): ?>
                                <tr>
                                    <td><?= e($t['title']) ?></td>
                                    <td><span class="badge badge-<?= $badge((string) $t['assignee_status']) ?>"><?= e($t['assignee_status'] ?: '—') ?></span></td>
                                    <td><?= $t['deadline'] ? e(date('Y-m-d', strtotime($t['deadline']))) : '—' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    <?php if ($tasksAuthored): ?>
                        <table class="table table-sm table-striped mb-0">
                            <thead><tr><th colspan="3">Created tasks</th></tr>
                                <tr><th>Title</th><th>Status</th><th>Deadline</th></tr></thead>
                            <tbody>
                            <?php foreach ($tasksAuthored as $t): ?>
                                <tr>
                                    <td><?= e($t['title']) ?></td>
                                    <td><span class="badge badge-<?= $badge((string) $t['status']) ?>"><?= e($t['status'] ?: '—') ?></span></td>
                                    <td><?= $t['deadline'] ? e(date('Y-m-d', strtotime($t['deadline']))) : '—' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    <?php if (!$tasksAssigned && !$tasksAuthored): ?><p class="text-center text-muted py-3 mb-0">No tasks.</p><?php endif; ?>
                </div>
            </div>

            <div class="card collapsed-card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-clipboard-list mr-1"></i>Daily tasks</h3>
                    <div class="card-tools">
                        <a href="<?= pageUrl('staff_management', 'staff_daily_tasks') ?>" class="btn btn-tool"><i class="fas fa-external-link-alt"></i></a>
                        <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button>
                    </div>
                </div>
                <div class="card-body p-0" style="display:none">
                    <table class="table table-sm table-striped mb-0">
                        <thead><tr><th>Date</th><th>Work done</th></tr></thead>
                        <tbody>
                        <?php foreach ($dailyTasks as $dt): ?>
                            <tr>
                                <td class="text-nowrap"><?= e(formatDateView($dt['date'])) ?></td>
                                <td><?= e(mb_strimwidth((string) $dt['tasks'], 0, 180, '…')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$dailyTasks): ?><tr><td colspan="2" class="text-center text-muted">No daily task logs.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card collapsed-card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-calendar-alt mr-1"></i>Meetings &amp; events</h3>
                    <div class="card-tools">
                        <a href="<?= pageUrl('staff_management', 'hr_care') ?>&tab=meetings" class="btn btn-tool"><i class="fas fa-external-link-alt"></i></a>
                        <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button>
                    </div>
                </div>
                <div class="card-body p-0" style="display:none">
                    <table class="table table-sm table-striped mb-0">
                        <thead><tr><th>Title</th><th>Dates</th><th>Type</th><th>Privacy</th><th>Venue</th></tr></thead>
                        <tbody>
                        <?php foreach ($events as $ev): ?>
                            <tr>
                                <td><?= e($ev['title']) ?></td>
                                <td class="text-nowrap"><?= e(formatDateView($ev['start_date'])) ?><?= $ev['end_date'] && $ev['end_date'] !== $ev['start_date'] ? ' → ' . e(formatDateView($ev['end_date'])) : '' ?></td>
                                <td><?= e($ev['type']) ?></td>
                                <td><span class="badge badge-<?= $badge((string) $ev['privacy']) ?>"><?= e($ev['privacy']) ?></span></td>
                                <td><?= e($ev['venue_location'] ?: '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$events): ?><tr><td colspan="5" class="text-center text-muted">No events created.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card collapsed-card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-comments mr-1"></i>Grievances (Speak Up)</h3>
                    <div class="card-tools">
                        <a href="<?= pageUrl('staff_management', 'hr_care') ?>&tab=speak_up" class="btn btn-tool"><i class="fas fa-external-link-alt"></i></a>
                        <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button>
                    </div>
                </div>
                <div class="card-body p-0" style="display:none">
                    <table class="table table-sm table-striped mb-0">
                        <thead><tr><th>Title</th><th>Status</th><th>Deadline</th></tr></thead>
                        <tbody>
                        <?php foreach ($grievances as $gr): ?>
                            <tr>
                                <td><?= e($gr['title']) ?></td>
                                <td><span class="badge badge-<?= $badge((string) $gr['status']) ?>"><?= e($gr['status'] ?: 'Open') ?></span></td>
                                <td><?= $gr['deadline'] ? e(date('Y-m-d', strtotime($gr['deadline']))) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$grievances): ?><tr><td colspan="3" class="text-center text-muted">No grievances.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card collapsed-card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-receipt mr-1"></i>Expense claims</h3>
                    <div class="card-tools">
                        <a href="<?= pageUrl('accounts', 'expense_claims') ?>" class="btn btn-tool"><i class="fas fa-external-link-alt"></i></a>
                        <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button>
                    </div>
                </div>
                <div class="card-body p-0" style="display:none">
                    <table class="table table-sm table-striped mb-0">
                        <thead><tr><th>Claim no</th><th>Category</th><th>Date</th><th>Amount</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($claims as $c): ?>
                            <tr>
                                <td><?= e($c['claim_no'] ?: '#' . $c['id']) ?></td>
                                <td><?= e($c['category'] ?: '—') ?></td>
                                <td><?= e($c['expense_date'] ? formatDateView($c['expense_date']) : '—') ?></td>
                                <td><?= e(number_format((float) $c['amount'], 2)) ?></td>
                                <td><span class="badge badge-<?= $badge((string) $c['status']) ?>"><?= e($c['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$claims): ?><tr><td colspan="5" class="text-center text-muted">No expense claims.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card collapsed-card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-laptop mr-1"></i>Assigned assets <span class="badge badge-light ml-1"><?= (int) ($assetsCount['c'] ?? 0) ?></span></h3>
                    <div class="card-tools">
                        <a href="<?= pageUrl('inventory', 'assets') ?>" class="btn btn-tool"><i class="fas fa-external-link-alt"></i></a>
                        <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button>
                    </div>
                </div>
                <div class="card-body p-0" style="display:none">
                    <table class="table table-sm table-striped mb-0">
                        <thead><tr><th>Asset</th><th>Tag</th><th>Assigned on</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($assets as $asset): ?>
                            <tr>
                                <td><?= e($asset['name']) ?></td>
                                <td><?= e($asset['asset_tag'] ?: '—') ?></td>
                                <td><?= $asset['assigned_on'] ? e(formatDateView($asset['assigned_on'])) : '—' ?></td>
                                <td><span class="badge badge-<?= $badge((string) $asset['current_status']) ?>"><?= e($asset['current_status'] ?: '—') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$assets): ?><tr><td colspan="4" class="text-center text-muted">No assets assigned.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card collapsed-card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-folder-open mr-1"></i>Documents</h3>
                    <div class="card-tools">
                        <a href="<?= pageUrl('staff_management', 'staff_documents') ?>&id=<?= $uid ?>" class="btn btn-tool"><i class="fas fa-external-link-alt"></i></a>
                        <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button>
                    </div>
                </div>
                <div class="card-body p-0" style="display:none">
                    <table class="table table-sm table-striped mb-0">
                        <thead><tr><th>Title</th><th>Type</th><th>File</th></tr></thead>
                        <tbody>
                        <?php foreach ($docs as $doc): ?>
                            <tr>
                                <td><?= e($doc['title']) ?></td>
                                <td><?= e($doc['document_type']) ?></td>
                                <td><?= e($doc['document_name']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$docs): ?><tr><td colspan="3" class="text-center text-muted">No documents.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card collapsed-card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-history mr-1"></i>Staff history</h3>
                    <div class="card-tools">
                        <a href="<?= pageUrl('staff_management', 'staff_history') ?>&id=<?= $uid ?>" class="btn btn-tool"><i class="fas fa-external-link-alt"></i></a>
                        <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button>
                    </div>
                </div>
                <div class="card-body p-0" style="display:none">
                    <table class="table table-sm table-striped mb-0">
                        <thead><tr><th>Event</th><th>Details</th><th>Date</th><th>Actor</th></tr></thead>
                        <tbody>
                        <?php foreach ($history as $h): ?>
                            <tr>
                                <td><span class="badge badge-<?= $badge((string) $h['event_type']) ?>"><?= e($h['event_type']) ?></span></td>
                                <td><?= e($h['details'] ?: '—') ?></td>
                                <td class="text-nowrap"><?= e(formatDateView($h['event_date'])) ?></td>
                                <td><?= e($h['actor_name'] ?: '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$history): ?><tr><td colspan="4" class="text-center text-muted">No history events.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card collapsed-card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-user-shield mr-1"></i>Module permissions <span class="badge badge-light ml-1"><?= $moduleCount ?></span></h3>
                    <div class="card-tools">
                        <a href="<?= pageUrl('staff_management', 'permissions') ?>&id=<?= $uid ?>" class="btn btn-tool"><i class="fas fa-external-link-alt"></i></a>
                        <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button>
                    </div>
                </div>
                <div class="card-body" style="display:none">
                    <?php if (!$grantedModules): ?>
                        <p class="text-muted mb-0">No module access granted (<?= e($view['username']) ?> starts with no permissions).</p>
                    <?php else: ?>
                        <?php foreach ($grantedModules as $mod): ?>
                            <span class="badge badge-info mr-1 mb-1"><?= e($navBars[$mod] ?? ucfirst($mod)) ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        <?php endif; ?>
    </div>
</div>