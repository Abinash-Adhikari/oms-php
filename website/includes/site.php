<?php
/**
 * SB-Tech — public website bootstrap.
 * Loads the app bootstrap (session, DB, helpers) plus site-wide data.
 * All public pages start with: require __DIR__ . '/includes/site.php';
 */
require __DIR__ . '/../../config/setup.php';

$db = Database::instance();

/** Site settings record (cached per request). */
function siteSetup(): array
{
    static $setup = null;
    if ($setup === null) {
        $setup = Database::instance()->selectOne('SELECT * FROM `tbl_cms_setup` WHERE `id` = 1') ?: [];
    }
    return $setup;
}

/** Base URL for public assets/links (respects config server_path). */
function siteUrl(string $path = ''): string
{
    $base = rtrim((string) config('server_path', ''), '/');
    return $base . '/' . ltrim($path, '/');
}

/** Public asset URL. */
function siteAsset(string $path): string
{
    return siteUrl('assets/' . ltrim($path, '/'));
}

/** Active (published) rows for a CMS table. Order must use real columns.
 *  Pass $columns to avoid fetching large TEXT/BLOB fields you don't display.
 *
 *  Security: all parameters are validated against a strict whitelist pattern
 *  to prevent SQL injection — no user input should reach this function. */
function siteRows(string $table, string $orderBy = '`id`', string $columns = '*'): array
{
    // Security: validate all parameters are safe SQL identifiers/clauses.
    // All three params are called with hardcoded values from index.php,
    // so this guard exists to prevent future misuse via injection.
    // $table: must match a valid table name (with or without backticks).
    if (!preg_match('/^`?[a-zA-Z0-9_]+`?$/', $table)) {
        throw new InvalidArgumentException('siteRows(): invalid table name.');
    }
    // Strip backticks from table name for consistency
    $table = trim($table, '`');
    // $columns: must be *, or comma-separated backtick-quoted column names.
    $colPattern = '/^(`\*`|`[a-zA-Z0-9_]+`(\s*,\s*`[a-zA-Z0-9_]+`)*|\*)$/';
    if (!preg_match($colPattern, $columns)) {
        throw new InvalidArgumentException('siteRows(): invalid columns parameter.');
    }
    // $orderBy: must be one or more backtick-quoted columns with optional
    // ASC/DESC and comma separation.
    $orderPattern = '/^`[a-zA-Z0-9_]+`(\s+(ASC|DESC))?(\s*,\s*`[a-zA-Z0-9_]+`(\s+(ASC|DESC))?)?$/i';
    if (!preg_match($orderPattern, $orderBy)) {
        throw new InvalidArgumentException('siteRows(): invalid orderBy parameter.');
    }

    return Database::instance()->select(
        'SELECT ' . $columns . ' FROM `' . $table . '` WHERE `is_active` = 1 ORDER BY ' . $orderBy
    );
}

/** Current page key for nav highlighting. */
$sitePage = basename($_SERVER['PHP_SELF'], '.php');
if ($sitePage === 'index') {
    $sitePage = 'home';
}
