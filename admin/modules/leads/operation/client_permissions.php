<?php
/**
 * SB-Tech — save client access grants for a won client project:
 * permitted modules/submodules + the deployment database. Included by
 * admin/operation.php (CSRF verified). The checkbox state on the page lives
 * in the client's deployment DB (tbl_modules/tbl_submodules is_active); this
 * handler validates the posted keys against the DB catalog, mirrors them as
 * JSON on the tbl_client_projects row (for audit + fallback reads), then
 * pushes the on/off state back into the deployment DB's is_active columns.
 */
$db = Database::instance();
if (!(Auth::isSuperAdmin() || Auth::hasSpecial('manage_leads'))) {
    http_response_code(403);
    die('Access denied: you need the manage_leads permission.');
}
$id = (int) ($_POST['id'] ?? 0);
$back = pageUrl('leads', 'client_permissions') . '&id=' . $id;

$project = $db->selectOne('SELECT * FROM `tbl_client_projects` WHERE `id` = ?', [$id]);
if (!$project) {
    setFlash('error', 'Client project not found.');
    redirect($back);
}

// Module keys validated against the DB-driven catalog (never trust input).
// Uses the posted/new db_name if changed, else the project's current one.
$postedDb = trim((string) ($_POST['db_name'] ?? ''));
$catalog = client_module_catalog($project, $postedDb !== '' ? $postedDb : null);
$validModules = [];
$validSubs = [];
foreach ($catalog as $cm) {
    $validModules[] = $cm['key'];
    $validSubs[$cm['key']] = $cm['subs'];
}

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
    $validSubList = $validSubs[$mod] ?? [];
    $kept = [];
    foreach ($list as $sub) {
        if (array_key_exists($sub, $validSubList)) {
            $kept[] = $sub;
        }
    }
    if ($kept) {
        $subs[$mod] = $kept;
    }
}

// Deployment database: optional, safe-identifier rule.
$dbName = trim((string) ($_POST['db_name'] ?? ''));
if ($dbName !== '' && !preg_match('/^[A-Za-z0-9_\-]+$/', $dbName)) {
    setFlash('error', 'Database name may only use letters, numbers, underscores and dashes.');
    redirect($back);
}

$oldPerms = [
    'permitted_modules'    => $project['permitted_modules'],
    'permitted_submodules' => $project['permitted_submodules'],
    'db_name'              => $project['db_name'],
];
$newPerms = [
    'permitted_modules'    => json_encode(array_values(array_unique($moduleKeys))),
    'permitted_submodules' => json_encode($subs),
    'db_name'              => $dbName !== '' ? $dbName : null,
];

$db->update('tbl_client_projects', array_merge($newPerms, [
    'updated_by' => Auth::id(),
]), '`id` = ?', [$id]);

// Push the grants into the deployment DB so the school's sidebar only
// shows the modules granted here (is_active sync on tbl_modules/submodules).
$syncErrors = [];
if ($dbName !== '') {
    try {
        sync_deployment_module_visibility($dbName, $moduleKeys, $subs);
    } catch (Throwable $e) {
        $syncErrors[] = 'Module visibility NOT synced to ' . $dbName . ': ' . $e->getMessage();
    }
} else {
    $syncErrors[] = 'No deployment database set — module visibility not synced to a school DB.';
}

auditLog('leads', 'update_client_access', 'client_project', $id, $oldPerms, $newPerms, 'Client access updated for ' . $project['title']);

setFlash('success', 'Client access saved for ' . $project['title'] . '.');
foreach ($syncErrors as $err) {
    setFlash('warning', $err);
}
redirect($back);