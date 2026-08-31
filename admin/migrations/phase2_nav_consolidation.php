<?php
/**
 * Phase 2 — navigation consolidation: RBAC submodule-grant migration.
 *
 * Rewrites tbl_users_login.permitted_submodules (module-keyed JSON) so the
 * grants affected by the Phase 2 nav moves land on their canonical homes:
 *
 *   my_office.hr_care          → staff_management.hr_care
 *   inventory.reports          → reports.inventory
 *   settings.office_profile    → office_setup.office_profile
 *   settings.permissions       → staff_management.permissions
 *
 * Idempotent: each map is applied once, in order; 'All' granted users are
 * untouched. Run from the CLI:
 *
 *   php admin/migrations/phase2_nav_consolidation.php [--apply]
 *
 * Without --apply it runs in dry-run mode and prints what WOULD change.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require __DIR__ . '/../../config/setup.php';

$db = Database::instance();

/** @var array<string, array{0:string,1:string}> */
$MAP = [
    'my_office.hr_care'       => ['staff_management', 'hr_care'],
    'inventory.reports'       => ['reports', 'inventory'],
    'settings.office_profile' => ['office_setup', 'office_profile'],
    'settings.permissions'    => ['staff_management', 'permissions'],
];

$apply = in_array('--apply', $argv, true);
$rows = $db->select("SELECT id, username, permitted_submodules FROM tbl_users_login WHERE permitted_submodules <> 'All'");

$changed = 0;
foreach ($rows as $row) {
    $subs = json_decode((string) $row['permitted_submodules'], true);
    if (!is_array($subs)) {
        continue;
    }
    $dirty = false;
    foreach ($MAP as $source => $target) {
        [$srcMod, $srcPage] = explode('.', $source, 2);
        [$dstMod, $dstPage] = $target;
        $srcList = $subs[$srcMod] ?? [];
        $pos = array_search($srcPage, $srcList, true);
        if ($pos === false) {
            continue;
        }
        $dirty = true;
        unset($subs[$srcMod][$pos]);
        $subs[$srcMod] = array_values($subs[$srcMod]);
        if (empty($subs[$srcMod])) {
            unset($subs[$srcMod]);
        }
        $subs[$dstMod] = $subs[$dstMod] ?? [];
        if (!in_array($dstPage, $subs[$dstMod], true)) {
            $subs[$dstMod][] = $dstPage;
        }
    }
    if (!$dirty) {
        continue;
    }
    $changed++;
    echo ($apply ? '[apply] ' : '[dry ] ') . $row['username'] . ' (id ' . (int) $row['id'] . "): submodules rewritten\n";
    if ($apply) {
        $db->update('tbl_users_login', [
            'permitted_submodules' => json_encode($subs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ], 'WHERE id = ?', [(int) $row['id']]);
    }
}

echo $changed === 0 ? "No grants to migrate.\n" : ($changed . " user(s) affected.\n");
echo $apply ? "Migration applied.\n" : "Dry-run only — rerun with --apply to write.\n";