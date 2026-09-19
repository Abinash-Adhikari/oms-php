<?php

/**
 * SB-Tech — RBAC permission helpers (pure logic for the staff permissions
 * editor). Loaded by config/bootstrap.php and tests/bootstrap.php.
 *
 * These functions never touch the DB, globals, or the session (RULES §1.2),
 * so they are unit-testable (TEST-05). Grants are stored as JSON on
 * tbl_users_login and evaluated by classes/Auth.php on every request.
 */

/**
 * Normalize posted permission checkboxes into the JSON-safe arrays stored on
 * tbl_users_login (permitted_modules / permitted_submodules /
 * special_permission).
 *
 * Only module keys listed in $validModules, submodule keys that exist inside
 * $validSubmodules[$module], and special keys listed in $validSpecialKeys
 * survive — anything unknown or malformed is dropped (never trust form
 * input). Submodule keys present in $nonGrantableSubmodules (e.g. the
 * super-admin-only 'permissions' editor) are never grantable. A granted
 * submodule implies its module grant, mirroring Auth::hasModule().
 *
 * Output is deterministically sorted so stored JSON produces stable,
 * diffable audit records.
 *
 * @param array $postedModules            user-supplied POST['modules'][] values
 * @param array $postedSubmodules         user-supplied POST['submodules'][module][] map
 * @param array $postedSpecial            user-supplied POST['special'][] values
 * @param array $validModules             list of grantable module keys (nav $modules)
 * @param array $validSubmodules          module => [submodule => label] map (nav $subNavBars)
 * @param array $validSpecialKeys         list of grantable special keys
 * @param array $nonGrantableSubmodules   submodule keys that must never be granted
 *
 * @return array{permitted_modules: list<string>, permitted_submodules: array<string, list<string>>, special_permission: list<string>}
 */
function normalize_permission_grants(
    array $postedModules,
    array $postedSubmodules,
    array $postedSpecial,
    array $validModules,
    array $validSubmodules,
    array $validSpecialKeys,
    array $nonGrantableSubmodules = []
): array {
    $modules = [];
    foreach ($postedModules as $value) {
        if (is_string($value) && in_array($value, $validModules, true) && !in_array($value, $modules, true)) {
            $modules[] = $value;
        }
    }

    $submodules = [];
    foreach ($postedSubmodules as $module => $list) {
        if (!is_string($module) || !in_array($module, $validModules, true) || !is_array($list)) {
            continue;
        }
        $valid = $validSubmodules[$module] ?? [];
        $kept = [];
        foreach ($list as $sub) {
            if (!is_string($sub) || in_array($sub, $nonGrantableSubmodules, true)) {
                continue;
            }
            if (array_key_exists($sub, $valid) && !in_array($sub, $kept, true)) {
                $kept[] = $sub;
            }
        }
        if ($kept !== []) {
            sort($kept);
            $submodules[$module] = $kept;
        }
    }

    $special = [];
    foreach ($postedSpecial as $value) {
        if (is_string($value) && in_array($value, $validSpecialKeys, true) && !in_array($value, $special, true)) {
            $special[] = $value;
        }
    }

    // A granted submodule implies its module grant (Auth::hasModule mirrors this).
    foreach (array_keys($submodules) as $module) {
        if (!in_array($module, $modules, true)) {
            $modules[] = $module;
        }
    }

    sort($modules);
    sort($special);
    ksort($submodules);

    return [
        'permitted_modules'    => $modules,
        'permitted_submodules' => $submodules,
        'special_permission'   => $special,
    ];
}