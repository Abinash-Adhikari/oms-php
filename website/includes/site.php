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

/**
 * Clients of a public portfolio item (tbl_cms_project_clients), in order.
 * Website clients show their current name and, when active, their id (so the
 * caller can link to /clients#client-<id>); free-text names have id null.
 *
 * @return list<array{id: ?int, name: string}> empty when the item has no clients.
 */
function siteProjectClients(int $projectId): array
{
    $rows = Database::instance()->select(
        'SELECT IF(c.`is_active` = 1, c.`id`, NULL) AS id, COALESCE(c.`name`, pc.`client_name`) AS name
         FROM `tbl_cms_project_clients` pc
         LEFT JOIN `tbl_cms_clients` c ON c.`id` = pc.`cms_client_id`
         WHERE pc.`project_id` = ?
         ORDER BY pc.`position`, pc.`id`',
        [$projectId]
    );
    return array_map(static fn ($r) => [
        'id'   => $r['id'] !== null ? (int) $r['id'] : null,
        'name' => (string) $r['name'],
    ], $rows);
}

/**
 * Active portfolio items per active website client, for the /clients page.
 *
 * @return array<int, list<array{slug: string, title: string}>> keyed by client id.
 */
function siteClientProjects(): array
{
    $rows = Database::instance()->select(
        'SELECT pc.`cms_client_id`, p.`slug`, p.`title`
         FROM `tbl_cms_project_clients` pc
         JOIN `tbl_cms_projects` p ON p.`id` = pc.`project_id` AND p.`is_active` = 1
         WHERE pc.`cms_client_id` IS NOT NULL
         ORDER BY p.`position`, p.`id` DESC'
    );
    $map = [];
    foreach ($rows as $r) {
        $map[(int) $r['cms_client_id']][] = ['slug' => (string) $r['slug'], 'title' => (string) $r['title']];
    }
    return $map;
}

/** Current page key for nav highlighting. */
$sitePage = basename($_SERVER['PHP_SELF'], '.php');
if ($sitePage === 'index') {
    $sitePage = 'home';
}
