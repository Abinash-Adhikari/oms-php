<?php
/**
 * SB-Tech — Seed data script for first-run experience.
 *
 * Populates the system with essential reference data so the UI is not empty
 * on a fresh install. Run once after migrations:
 *
 *   php scripts/seed_data.php [--force]
 *
 * Safe to run multiple times (checks for existing data before inserting).
 */

define('APP_BOOTSTRAP_SKIP_DB', true);
require __DIR__ . '/../config/setup.php';

$db = Database::instance();
$force = in_array('--force', $argv ?? []);

echo "=== SB-Tech Seed Data ===\n\n";

// ---- 1. Office Profile (if empty) ----
$profile = $db->selectOne('SELECT `id` FROM `tbl_office_profiles` LIMIT 1');
if (!$profile) {
    $db->insert('tbl_office_profiles', [
        'org_name'        => config('organization_name', 'SB-Tech'),
        'org_short_name'  => config('organization_short_name', 'SB-TECH'),
        'email'           => 'info@sb-tech.com',
        'phone1'          => '+977-1-4567890',
        'address1'        => 'Kathmandu, Nepal',
        'website'         => 'https://sb-tech.com',
        'calendar_mode'   => 'AD',
        'leave_year_mode' => 'AD',
        'added_by'        => 1,
    ]);
    echo "✓ Office profile created.\n";
} else {
    echo "○ Office profile already exists.\n";
}

// ---- 2. Departments ----
$departments = [
    ['title' => 'Management', 'position' => 1],
    ['title' => 'Engineering', 'position' => 2],
    ['title' => 'Design', 'position' => 3],
    ['title' => 'Marketing', 'position' => 4],
    ['title' => 'Sales', 'position' => 5],
    ['title' => 'Finance', 'position' => 6],
    ['title' => 'HR & Admin', 'position' => 7],
];
foreach ($departments as $d) {
    $exists = $db->selectOne('SELECT `id` FROM `tbl_office_departments` WHERE `title` = ?', [$d['title']]);
    if (!$exists) {
        $db->insert('tbl_office_departments', array_merge($d, ['added_by' => 1]));
    }
}
echo "✓ Departments seeded.\n";

// ---- 3. Designations ----
$designations = [
    ['title' => 'CEO', 'position' => 1],
    ['title' => 'CTO', 'position' => 2],
    ['title' => 'Senior Developer', 'position' => 3],
    ['title' => 'Developer', 'position' => 4],
    ['title' => 'UI/UX Designer', 'position' => 5],
    ['title' => 'Marketing Manager', 'position' => 6],
    ['title' => 'Sales Executive', 'position' => 7],
    ['title' => 'Accountant', 'position' => 8],
    ['title' => 'HR Officer', 'position' => 9],
    ['title' => 'Office Assistant', 'position' => 10],
];
foreach ($designations as $d) {
    $exists = $db->selectOne('SELECT `id` FROM `tbl_office_designation` WHERE `title` = ?', [$d['title']]);
    if (!$exists) {
        $db->insert('tbl_office_designation', array_merge($d, ['added_by' => 1]));
    }
}
echo "✓ Designations seeded.\n";

// ---- 4. Leave Types ----
$leaveTypes = [
    ['title' => 'Annual Leave', 'max_allowed' => 20, 'requires_approval' => 1, 'carry_forward' => 1, 'max_carry_forward' => 5, 'is_active' => 1],
    ['title' => 'Sick Leave', 'max_allowed' => 10, 'requires_approval' => 1, 'carry_forward' => 0, 'max_carry_forward' => 0, 'is_active' => 1],
    ['title' => 'Casual Leave', 'max_allowed' => 5, 'requires_approval' => 1, 'carry_forward' => 0, 'max_carry_forward' => 0, 'is_active' => 1],
    ['title' => 'Maternity Leave', 'max_allowed' => 60, 'requires_approval' => 1, 'carry_forward' => 0, 'max_carry_forward' => 0, 'is_active' => 1],
    ['title' => 'Paternity Leave', 'max_allowed' => 10, 'requires_approval' => 1, 'carry_forward' => 0, 'max_carry_forward' => 0, 'is_active' => 1],
    ['title' => 'Compensatory Leave', 'max_allowed' => 5, 'requires_approval' => 1, 'carry_forward' => 0, 'max_carry_forward' => 0, 'is_active' => 1],
];
foreach ($leaveTypes as $lt) {
    $exists = $db->selectOne('SELECT `id` FROM `tbl_office_leave_configs` WHERE `title` = ?', [$lt['title']]);
    if (!$exists) {
        $db->insert('tbl_office_leave_configs', array_merge($lt, ['added_by' => 1]));
    }
}
echo "✓ Leave types seeded.\n";

// ---- 5. Fiscal Year (current year) ----
$currentYear = (int) date('Y');
$fy = $db->selectOne("SELECT `id` FROM `tbl_fiscal_years` WHERE `starting_date` = ? AND `ending_date` = ?", [$currentYear . '-01-01', $currentYear . '-12-31']);
if (!$fy) {
    $db->insert('tbl_fiscal_years', [
        'title'         => 'FY ' . $currentYear,
        'starting_date' => $currentYear . '-01-01',
        'ending_date'   => $currentYear . '-12-31',
        'closing'       => 'Open',
        'added_by'      => 1,
    ]);
    echo "✓ Fiscal year {$currentYear} created.\n";
} else {
    echo "○ Fiscal year {$currentYear} already exists.\n";
}

// ---- 6. Admin User (ensure exists with proper password) ----
$admin = $db->selectOne("SELECT `id` FROM `tbl_users_login` WHERE `username` = 'admin'");
if (!$admin) {
    $password = 'admin'; // Default password — user should change on first login
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $db->insert('tbl_users_login', [
        'fullname'              => 'Super Administrator',
        'username'              => 'admin',
        'password'              => $hash,
        'salt'                  => '',
        'email'                 => 'admin@sb-tech.com',
        'phone1'                => '+977-1-4567890',
        'gender'                => 'Male',
        'role'                  => 'Super Admin',
        'status'                => 'Active',
        'permitted_modules'     => 'All',
        'permitted_submodules'  => '{}',
        'special_permission'    => '[]',
        'staff_type'            => 'Admin',
        'join_date'             => date('Y-m-d'),
        'added_by'              => 1,
    ]);
    echo "✓ Admin user created (username: admin, password: admin).\n";
    echo "  ⚠ CHANGE THE DEFAULT PASSWORD IMMEDIATELY!\n";
} else {
    echo "○ Admin user already exists.\n";
}

// ---- 7. Sample Communication Templates ----
$templates = [
    ['name' => 'leave_submitted', 'type' => 'Email', 'subject' => 'Leave Application Submitted', 'body' => 'Dear {{name}},\n\nYour leave application has been submitted successfully.\n\nDetails: {{details}}\n\nBest regards,\n{{org_name}}', 'sms_body' => 'Dear {{name}}, your leave has been submitted. {{details}} - {{org_name}}'],
    ['name' => 'leave_approved', 'type' => 'Email', 'subject' => 'Leave Application Approved', 'body' => 'Dear {{name}},\n\nYour leave application has been approved.\n\nDetails: {{details}}\n\nBest regards,\n{{org_name}}', 'sms_body' => 'Dear {{name}}, your leave has been approved. {{details}} - {{org_name}}'],
    ['name' => 'leave_rejected', 'type' => 'Email', 'subject' => 'Leave Application Rejected', 'body' => 'Dear {{name}},\n\nYour leave application has been rejected.\n\nDetails: {{details}}\n\nBest regards,\n{{org_name}}', 'sms_body' => 'Dear {{name}}, your leave has been rejected. {{details}} - {{org_name}}'],
    ['name' => 'task_assigned', 'type' => 'Email', 'subject' => 'New Task Assigned', 'body' => 'Dear {{name}},\n\nYou have been assigned a new task.\n\nDetails: {{details}}\n\nBest regards,\n{{org_name}}', 'sms_body' => 'Dear {{name}}, new task assigned: {{details}} - {{org_name}}'],
    ['name' => 'new_lead', 'type' => 'Email', 'subject' => 'New Lead Received', 'body' => 'Dear {{name}},\n\nA new lead has been created.\n\nDetails: {{details}}\n\nBest regards,\n{{org_name}}', 'sms_body' => 'New lead: {{details}} - {{org_name}}'],
    ['name' => 'expense_approved', 'type' => 'Email', 'subject' => 'Expense Claim Approved', 'body' => 'Dear {{name}},\n\nYour expense claim has been approved.\n\nDetails: {{details}}\n\nBest regards,\n{{org_name}}', 'sms_body' => 'Dear {{name}}, your expense claim approved. {{details}} - {{org_name}}'],
    ['name' => 'voucher_approved', 'type' => 'Email', 'subject' => 'Voucher Approved', 'body' => 'Dear {{name}},\n\nA voucher has been approved.\n\nDetails: {{details}}\n\nBest regards,\n{{org_name}}', 'sms_body' => 'Dear {{name}}, voucher approved: {{details}} - {{org_name}}'],
];
foreach ($templates as $t) {
    $exists = $db->selectOne('SELECT `id` FROM `tbl_communication_templates` WHERE `name` = ?', [$t['name']]);
    if (!$exists) {
        $db->insert('tbl_communication_templates', array_merge($t, ['is_active' => 1, 'added_by' => 1]));
    }
}
echo "✓ Communication templates seeded.\n";

// ---- 8. Office Holidays (Nepal national holidays for current year) ----
$holidays = [
    ['title' => 'New Year (Bisket)', 'from_date' => $currentYear . '-04-14', 'to_date' => $currentYear . '-04-14', 'remarks' => 'Nepali New Year'],
    ['title' => 'Buddha Jayanti', 'from_date' => $currentYear . '-05-15', 'to_date' => $currentYear . '-05-15', 'remarks' => 'Birth of Lord Buddha'],
    ['title' => 'Dashain', 'from_date' => $currentYear . '-10-10', 'to_date' => $currentYear . '-10-17', 'remarks' => 'Major Hindu festival'],
    ['title' => 'Tihar', 'from_date' => $currentYear . '-11-05', 'to_date' => $currentYear . '-11-09', 'remarks' => 'Festival of lights'],
    ['title' => 'Christmas Day', 'from_date' => $currentYear . '-12-25', 'to_date' => $currentYear . '-12-25', 'remarks' => 'Christmas'],
];
foreach ($holidays as $h) {
    $exists = $db->selectOne('SELECT `id` FROM `tbl_office_holidays` WHERE `title` = ? AND `from_date` = ?', [$h['title'], $h['from_date']]);
    if (!$exists) {
        $db->insert('tbl_office_holidays', array_merge($h, ['added_by' => 1]));
    }
}
echo "✓ Holidays seeded.\n";

// ---- 9. Sample Meeting Halls ----
$halls = [
    ['hall_name' => 'Conference Room A', 'occupancy' => 20],
    ['hall_name' => 'Conference Room B', 'occupancy' => 10],
    ['hall_name' => 'Board Room', 'occupancy' => 30],
    ['hall_name' => 'Training Hall', 'occupancy' => 50],
];
foreach ($halls as $h) {
    $exists = $db->selectOne('SELECT `id` FROM `tbl_office_meeting_hall_setup` WHERE `hall_name` = ?', [$h['hall_name']]);
    if (!$exists) {
        $db->insert('tbl_office_meeting_hall_setup', array_merge($h, ['added_by' => 1]));
    }
}
echo "✓ Meeting halls seeded.\n";

// ---- 10. Inventory Categories ----
$invCategories = [
    ['title' => 'IT Equipment', 'description' => 'Laptops, desktops, monitors, keyboards, mice, etc.', 'position' => 1],
    ['title' => 'Networking', 'description' => 'Routers, switches, access points, cables, etc.', 'position' => 2],
    ['title' => 'Office Supplies', 'description' => 'Paper, pens, folders, toner, etc.', 'position' => 3],
    ['title' => 'Furniture', 'description' => 'Desks, chairs, cabinets, etc.', 'position' => 4],
    ['title' => 'Software Licenses', 'description' => 'Operating systems, applications, subscriptions.', 'position' => 5],
    ['title' => 'Consumables', 'description' => 'Items that get used up: ink, batteries, cleaning supplies.', 'position' => 6],
    ['title' => 'Audio/Visual', 'description' => 'Projectors, speakers, webcams, headphones.', 'position' => 7],
    ['title' => 'Security', 'description' => 'CCTV, access control, locks, safes.', 'position' => 8],
];
foreach ($invCategories as $c) {
    $exists = $db->selectOne('SELECT `id` FROM `tbl_inv_categories` WHERE `title` = ?', [$c['title']]);
    if (!$exists) {
        $db->insert('tbl_inv_categories', array_merge($c, ['added_by' => 1]));
    }
}
echo "\u2713 Inventory categories seeded.\n";

echo "\n=== Seed Complete ===\n";
echo "System is ready for first use.\n";
echo "Default login: admin / admin\n";
echo "⚠ Change the default password immediately!\n";
