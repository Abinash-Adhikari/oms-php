<?php
/**
 * SB-Tech — staff create/update/terminate (US-STF-01, US-STF-03).
 * Included by admin/operation.php (CSRF + permission already verified).
 * Every change records a staff-history event (AC-STF-03.1).
 */
$db = Database::instance();
$back = pageUrl('staff_management', 'add_staff');

$id = (int) ($_POST['id'] ?? 0);
$fullname = trim((string) ($_POST['fullname'] ?? ''));
$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if ($fullname === '' || $username === '') {
    setFlash('error', 'Full name and username are required.');
    redirect($back);
}

// Enums with safe defaults (never read $_POST twice without ?? — a missing
// key in the true branch would emit a warning and break the PRG redirect).
$statusIn  = $_POST['status'] ?? 'Active';
$genderIn  = $_POST['gender'] ?? '';
$maritalIn = $_POST['marital_status'] ?? '';

$loginData = [
    'fullname'          => $fullname,
    'username'          => $username,
    'email'             => trim((string) ($_POST['email'] ?? '')),
    'gender'            => in_array($genderIn, ['Male', 'Female', 'Other'], true) ? $genderIn : null,
    'dob'               => ($_POST['dob'] ?? '') !== '' ? $_POST['dob'] : null,
    'phone1'            => trim((string) ($_POST['phone1'] ?? '')),
    'address'           => trim((string) ($_POST['address'] ?? '')),
    'citizenship'       => trim((string) ($_POST['citizenship'] ?? '')),
    'marital_status'    => in_array($maritalIn, ['Married', 'Unmarried', 'Divorced'], true) ? $maritalIn : '',
    'staff_type'        => ($_POST['staff_type'] ?? 'Admin') === 'Service' ? 'Service' : 'Admin',
    'department_id'     => ($_POST['department_id'] ?? '') !== '' ? (int) $_POST['department_id'] : null,
    'designation_id'    => ($_POST['designation_id'] ?? '') !== '' ? (int) $_POST['designation_id'] : null,
    'join_date'         => ($_POST['join_date'] ?? '') !== '' ? $_POST['join_date'] : null,
    'daily_working_hour'=> ($_POST['daily_working_hour'] ?? '') !== '' ? (int) $_POST['daily_working_hour'] : null,
    'off_day'           => trim((string) ($_POST['off_day'] ?? '')),
    'pan_num'           => trim((string) ($_POST['pan_num'] ?? '')),
    'bank'              => trim((string) ($_POST['bank'] ?? '')),
    'bank_account_num'  => trim((string) ($_POST['bank_account_num'] ?? '')),
    'bank_account_name' => trim((string) ($_POST['bank_account_name'] ?? '')),
    'ssf_number'        => trim((string) ($_POST['ssf_number'] ?? '')),
    'pf_number'         => trim((string) ($_POST['pf_number'] ?? '')),
    'cit_number'        => trim((string) ($_POST['cit_number'] ?? '')),
    'status'            => in_array($statusIn, ['Active', 'Block', 'Terminated'], true) ? $statusIn : 'Active',
];

$profileData = [
    'blood_group'              => trim((string) ($_POST['blood_group'] ?? '')),
    'work_experience'          => trim((string) ($_POST['work_experience'] ?? '')),
    'skill'                    => trim((string) ($_POST['skill'] ?? '')),
    'emergency_contact_name'   => trim((string) ($_POST['emergency_contact_name'] ?? '')),
    'emergency_contact_mobile' => trim((string) ($_POST['emergency_contact_mobile'] ?? '')),
    'emergency_contact_relation' => trim((string) ($_POST['emergency_contact_relation'] ?? '')),
];

try {
    if ($id > 0) {
        $old = $db->selectOne('SELECT * FROM `tbl_users_login` WHERE `id` = ?', [$id]);
        if (!$old) {
            setFlash('error', 'Staff not found.');
            redirect($back);
        }

        // Password change (optional) — bcrypt on every change.
        if ($password !== '') {
            $loginData['password'] = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $loginData['salt'] = '';
        }

        // Termination requires date + reason (AC-STF-03.2).
        if ($loginData['status'] === 'Terminated' && $old['status'] !== 'Terminated') {
            $termDate = ($_POST['termination_date'] ?? '') !== '' ? $_POST['termination_date'] : date('Y-m-d');
            $termReason = trim((string) ($_POST['termination_reason'] ?? ''));
            if ($termReason === '') {
                setFlash('error', 'Termination reason is required.');
                redirect($back);
            }
            $loginData['termination_date'] = $termDate;
            $db->insert('tbl_staff_history', [
                'staff_id' => $id, 'event_type' => 'Terminated', 'details' => $termReason,
                'event_date' => $termDate, 'actor_id' => Auth::id(),
            ]);
            auditLog('staff_management', 'terminate_staff', 'user', $id, ['status' => $old['status']], ['status' => 'Terminated', 'reason' => $termReason, 'date' => $termDate], 'Staff terminated: ' . $fullname);
        } elseif ($old['status'] === 'Terminated' && $loginData['status'] !== 'Terminated') {
            $loginData['termination_date'] = null;
            $db->insert('tbl_staff_history', [
                'staff_id' => $id, 'event_type' => 'Reinstated', 'details' => 'Account reactivated',
                'event_date' => date('Y-m-d'), 'actor_id' => Auth::id(),
            ]);
        } elseif ($old['status'] !== $loginData['status']) {
            $db->insert('tbl_staff_history', [
                'staff_id' => $id, 'event_type' => $loginData['status'] === 'Block' ? 'Blocked' : 'Status Changed',
                'details' => 'Status changed from ' . $old['status'] . ' to ' . $loginData['status'],
                'event_date' => date('Y-m-d'), 'actor_id' => Auth::id(),
            ]);
        }

        // Department / designation change tracking.
        if ((int) $old['department_id'] !== (int) $loginData['department_id']) {
            $db->insert('tbl_staff_history', [
                'staff_id' => $id, 'event_type' => 'Department Changed', 'details' => 'Department changed',
                'event_date' => date('Y-m-d'), 'actor_id' => Auth::id(),
            ]);
        }
        if ((int) $old['designation_id'] !== (int) $loginData['designation_id']) {
            $db->insert('tbl_staff_history', [
                'staff_id' => $id, 'event_type' => 'Designation Changed', 'details' => 'Designation changed',
                'event_date' => date('Y-m-d'), 'actor_id' => Auth::id(),
            ]);
        }

        $loginData['updated_by'] = Auth::id();
        $db->update('tbl_users_login', $loginData, '`id` = ?', [$id]);

        // Audit log (X-08).
        $auditNew = array_diff_key($loginData, array_flip(['password', 'salt']));
        auditLog('staff_management', 'update_staff', 'user', $id, null, $auditNew, 'Staff updated: ' . $fullname);

        // Upsert profile.
        $profileData['user_id'] = $id;
        $profileData['updated_by'] = Auth::id();
        if ($db->selectOne('SELECT `id` FROM `tbl_user_profiles` WHERE `user_id` = ?', [$id])) {
            $db->update('tbl_user_profiles', $profileData, '`user_id` = ?', [$id]);
        } else {
            $profileData['added_by'] = Auth::id();
            $db->insert('tbl_user_profiles', $profileData);
        }

        setFlash('success', 'Staff updated.');
    } else {
        // Create.
        $exists = $db->selectOne('SELECT `id` FROM `tbl_users_login` WHERE `username` = ?', [$username]);
        if ($exists) {
            setFlash('error', 'Username already taken.');
            redirect($back);
        }
        if ($password === '') {
            setFlash('error', 'Password is required for a new staff account.');
            redirect($back);
        }
        $loginData['password'] = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $loginData['salt'] = '';
        $loginData['permitted_modules'] = '[]';
        $loginData['permitted_submodules'] = '{}';
        $loginData['special_permission'] = '[]';
        $loginData['added_by'] = Auth::id();

        $newId = $db->insert('tbl_users_login', $loginData);

        $profileData['user_id'] = $newId;
        $profileData['added_by'] = Auth::id();
        $db->insert('tbl_user_profiles', $profileData);

        $db->insert('tbl_staff_history', [
            'staff_id' => $newId, 'event_type' => 'Joined', 'details' => 'Staff account created',
            'event_date' => $loginData['join_date'] ?? date('Y-m-d'), 'actor_id' => Auth::id(),
        ]);

        auditLog('staff_management', 'create_staff', 'user', $newId, null, ['fullname' => $fullname, 'username' => $username], 'Staff account created');

        setFlash('success', 'Staff created.');
    }
} catch (Throwable $e) {
    setFlash('error', 'Could not save staff: ' . (str_contains($e->getMessage(), 'Duplicate') ? 'Username already taken.' : $e->getMessage()));
}
redirect($back);
