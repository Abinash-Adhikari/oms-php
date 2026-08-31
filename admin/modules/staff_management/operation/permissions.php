<?php
/**
 * SB-Tech — save staff permissions (US-AUTH-02).
 * Included by admin/operation.php (CSRF + permission already verified).
 * Changes take effect on the user's next request (AC-AUTH-02.2) because
 * Auth reads permissions from the DB on every check.
 */
$db = Database::instance();
$userId = (int) ($_POST['user_id'] ?? 0);
$back = pageUrl('staff_management', 'permissions') . '&id=' . $userId;

$user = $db->selectOne('SELECT * FROM `tbl_users_login` WHERE `id` = ?', [$userId]);
if (!$user) {
    setFlash('error', 'Staff not found.');
    redirect($back);
}
if (Auth::isSuperAdmin($user)) {
    setFlash('error', 'Super Admin permissions cannot be edited.');
    redirect($back);
}

// Validate module keys against the nav map (never trust arbitrary input).
$validModules = $GLOBALS['modules'] ?? [];
$moduleKeys = [];
foreach (($_POST['modules'] ?? []) as $m) {
    if (in_array($m, $validModules, true)) {
        $moduleKeys[] = $m;
    }
}

// Submodules: only keys that exist under their module.
$subs = [];
foreach (($_POST['submodules'] ?? []) as $mod => $list) {
    if (!in_array($mod, $validModules, true) || !is_array($list)) {
        continue;
    }
    $validSubs = $GLOBALS['subNavBars'][$mod] ?? [];
    $kept = [];
    foreach ($list as $sub) {
        if (array_key_exists($sub, $validSubs)) {
            $kept[] = $sub;
        }
    }
    if ($kept) {
        $subs[$mod] = $kept;
    }
}

// Special permissions whitelist.
$knownSpecial = [
    'manage_staff_leaves', 'approve_vouchers', 'approve_expense_claims', 'manage_leads',
    'access_private_documents', 'view_all_attendance', 'audit',
];
$special = [];
foreach (($_POST['special'] ?? []) as $key) {
    if (in_array($key, $knownSpecial, true)) {
        $special[] = $key;
    }
}

$oldPerms = [
    'permitted_modules'    => $user['permitted_modules'],
    'permitted_submodules' => $user['permitted_submodules'],
    'special_permission'   => $user['special_permission'],
];
$newPerms = [
    'permitted_modules'    => json_encode(array_values(array_unique($moduleKeys))),
    'permitted_submodules' => json_encode($subs),
    'special_permission'   => json_encode(array_values(array_unique($special))),
];

$db->update('tbl_users_login', array_merge($newPerms, [
    'updated_by' => Auth::id(),
]), '`id` = ?', [$userId]);

// Invalidate cached user row so permission checks reflect the update immediately.
Auth::clearUserCache();

// If the admin is editing their OWN permissions, regenerate the session ID
// to prevent session fixation attacks (the old session may have been
// created with different privilege levels).
if ((int) $userId === (int) Auth::id()) {
    session_regenerate_id(true);
}

auditLog('staff_management', 'update_permissions', 'user', $userId, $oldPerms, $newPerms, 'Permissions updated for ' . $user['fullname']);

setFlash('success', 'Permissions saved for ' . $user['fullname'] . '.');
redirect($back);
