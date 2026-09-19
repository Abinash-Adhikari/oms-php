<?php
/**
 * SB-Tech — save staff permissions (US-AUTH-02).
 * Included by admin/operation.php (Auth + CSRF + module route already
 * verified). Guarded here to Super Admin only so the editor can never be
 * used to escalate privileges (AUTH scope). Grants are normalized against
 * the nav catalog by normalize_permission_grants() (never trust form
 * input) and JSON-encoded onto tbl_users_login; Auth.php reads them from
 * the DB on every check, so changes apply on the user's next request
 * (AC-AUTH-02.2).
 */
$db = Database::instance();
$userId = (int) ($_POST['user_id'] ?? 0);
$back = pageUrl('staff_management', 'permissions') . '&id=' . $userId;

if (!Auth::isSuperAdmin()) {
    setFlash('error', 'Only a Super Admin can edit permissions.');
    redirect($back);
}

$user = $db->selectOne('SELECT * FROM `tbl_users_login` WHERE `id` = ?', [$userId]);
if (!$user) {
    setFlash('error', 'Staff not found.');
    redirect($back);
}
if (Auth::isSuperAdmin($user)) {
    setFlash('error', 'Super Admin permissions cannot be edited.');
    redirect($back);
}

// Normalize against the nav/RBAC catalogs — unknown keys are dropped.
$grants = normalize_permission_grants(
    (array) ($_POST['modules'] ?? []),
    (array) ($_POST['submodules'] ?? []),
    (array) ($_POST['special'] ?? []),
    (array) ($GLOBALS['modules'] ?? []),
    (array) ($GLOBALS['subNavBars'] ?? []),
    array_keys($GLOBALS['specialPermissions'] ?? []),
    ['permissions'] // admin-managed submodule — never grantable
);

$oldPerms = [
    'permitted_modules'    => $user['permitted_modules'],
    'permitted_submodules' => $user['permitted_submodules'],
    'special_permission'   => $user['special_permission'],
];
$newPerms = [
    'permitted_modules'    => json_encode($grants['permitted_modules']),
    'permitted_submodules' => json_encode($grants['permitted_submodules']),
    'special_permission'   => json_encode($grants['special_permission']),
];

$db->update('tbl_users_login', array_merge($newPerms, [
    'updated_by' => Auth::id(),
]), '`id` = ?', [$userId]);

// Invalidate the cached user row so permission checks reflect the update immediately.
Auth::clearUserCache();

// If the admin is editing their OWN permissions, regenerate the session ID
// to prevent session fixation (the old session may have been created with
// different privilege levels).
if ((int) $userId === (int) Auth::id()) {
    session_regenerate_id(true);
}

auditLog('staff_management', 'update_permissions', 'user', $userId, $oldPerms, $newPerms, 'Permissions updated for ' . $user['fullname']);

setFlash('success', 'Permissions saved for ' . $user['fullname'] . '.');
redirect($back);