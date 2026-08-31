<?php
/**
 * SB-Tech — HR Care / Profile tab.
 * Read-only view of the logged-in user's own record (editing lives in
 * Staff Management / Staffs).
 */
$db = Database::instance();
$me = (int) Auth::id();
$u = $db->selectOne(
    'SELECT u.*, d.title AS department_title, g.title AS designation_title
     FROM `tbl_users_login` u
     LEFT JOIN `tbl_office_departments` d ON d.id = u.department_id
     LEFT JOIN `tbl_office_designation` g ON g.id = u.designation_id
     WHERE u.id = ?',
    [$me]
);
$profile = $db->selectOne('SELECT * FROM `tbl_user_profiles` WHERE `user_id` = ?', [$me]);
if (!$u) {
    echo '<div class="callout callout-danger"><h5>Profile not found</h5></div>';
    return;
}
?>
<div class="row">
    <div class="col-md-6">
        <div class="card card-outline">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-user mr-1"></i>Personal</h3></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tr><th class="w-40">Full name</th><td><?= e($u['fullname']) ?></td></tr>
                    <tr><th>Username</th><td>@<?= e($u['username']) ?></td></tr>
                    <tr><th>Email</th><td><?= e($u['email'] ?: '—') ?></td></tr>
                    <tr><th>Phone</th><td><?= e($u['phone1'] ?: '—') ?></td></tr>
                    <tr><th>Gender</th><td><?= e($u['gender'] ?: '—') ?></td></tr>
                    <tr><th>Date of birth</th><td><?= e($u['dob'] ? formatDateView($u['dob']) : '—') ?></td></tr>
                    <tr><th>Address</th><td><?= e($u['address'] ?: '—') ?></td></tr>
                    <tr><th>Blood group</th><td><?= e($profile['blood_group'] ?? '—') ?></td></tr>
                    <tr><th>Skills</th><td><?= e($profile['skill'] ?? '—') ?></td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card card-outline">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-briefcase mr-1"></i>Employment</h3></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tr><th class="w-40">Department</th><td><?= e($u['department_title'] ?? '—') ?></td></tr>
                    <tr><th>Designation</th><td><?= e($u['designation_title'] ?? '—') ?></td></tr>
                    <tr><th>Staff type</th><td><?= e($u['staff_type']) ?></td></tr>
                    <tr><th>Join date</th><td><?= e($u['join_date'] ? formatDateView($u['join_date']) : '—') ?></td></tr>
                    <tr><th>Daily working hours</th><td><?= $u['daily_working_hour'] !== null ? e($u['daily_working_hour'] . ' hr') : '—' ?></td></tr>
                    <tr><th>Off day</th><td><?= e($u['off_day'] ?: '—') ?></td></tr>
                    <tr><th>Status</th><td><span class="badge badge-<?= $u['status'] === 'Active' ? 'success' : 'warning' ?>"><?= e($u['status']) ?></span></td></tr>
                </table>
            </div>
        </div>
        <div class="card card-outline">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-phone-alt mr-1"></i>Emergency contact</h3></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tr><th class="w-40">Name</th><td><?= e($profile['emergency_contact_name'] ?? '—') ?></td></tr>
                    <tr><th>Mobile</th><td><?= e($profile['emergency_contact_mobile'] ?? '—') ?></td></tr>
                    <tr><th>Relation</th><td><?= e($profile['emergency_contact_relation'] ?? '—') ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>
