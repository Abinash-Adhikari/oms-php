<?php

/**
 * SB-Tech — per-client-project module & database entitlements.
 *
 * A won Client Project (tbl_client_projects row) carries its own permission
 * matrix (permitted_modules / permitted_submodules / db_name) configured
 * exactly like staff module permissions — see leads › Client Access. This
 * class reads that matrix the same way Auth reads a staff user's grants, so
 * product writers can ask "can this client project use module X (page Y?)" and
 * "which database does this client project use?" without touching JSON
 * themselves.
 *
 * A row with no entitlement columns grants nothing; one whose permitted_modules
 * is null behaves exactly like a staff user with no permissions (not an admin
 * bypass). Every granted module shares the project's single db_name.
 */

class ClientPermissions
{
    /**
     * Module-level grant for a client project.
     */
    public static function hasModule(?array $project, string $module): bool
    {
        if (!in_array($module, self::grantedModules($project), true)) {
            return false;
        }
        return true;
    }

    /**
     * Submodule-level grant within a module (case-insensitive key match,
     * matching Auth::hasSubmodule semantics).
     */
    public static function hasSubmodule(?array $project, string $module, string $submodule): bool
    {
        $subs = self::decodePermission((string) ($project['permitted_submodules'] ?? ''));
        if (!is_array($subs)) {
            return false;
        }
        $list = null;
        foreach ($subs as $modKey => $subList) {
            if (strcasecmp((string) $modKey, $module) === 0 && is_array($subList)) {
                $list = $subList;
                break;
            }
        }
        if (!is_array($list)) {
            return false;
        }
        foreach ($list as $sub) {
            if (strcasecmp((string) $sub, $submodule) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Combined page access: module grant + (when a submodule page is given)
     * submodule grant. Mirrors Auth::can.
     */
    public static function can(?array $project, string $module, string $page = ''): bool
    {
        if (!self::hasModule($project, $module)) {
            return false;
        }
        $page = (string) $page;
        if ($page === '' || $page === 'home' || strcasecmp($page, $module) === 0) {
            return true;
        }
        return self::hasSubmodule($project, $module, $page);
    }

    /**
     * The deployment database a client project uses for a module — the shared
     * db_name column. Returns null when the module is not granted or no
     * database is assigned.
     */
    public static function databaseFor(?array $project, string $module): ?string
    {
        if (!self::hasModule($project, $module)) {
            return null;
        }
        return self::defaultDatabase($project);
    }

    /**
     * The client project's deployment database.
     */
    public static function defaultDatabase(?array $project): ?string
    {
        $dbName = trim((string) ($project['db_name'] ?? ''));
        return $dbName !== '' ? $dbName : null;
    }

    /**
     * The databases a client project actually uses — its single db_name, if set.
     */
    public static function knownDatabases(?array $project): array
    {
        $own = self::defaultDatabase($project);
        return $own !== null ? [$own] : [];
    }

    /** JSON-decode a permission column; non-JSON → empty array. */
    private static function decodePermission(string $json)
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** The module keys granted explicitly on the project row. */
    private static function grantedModules(?array $project): array
    {
        if (!is_array($project)) {
            return [];
        }
        $modules = self::decodePermission((string) ($project['permitted_modules'] ?? ''));
        return is_array($modules) ? $modules : [];
    }
}
