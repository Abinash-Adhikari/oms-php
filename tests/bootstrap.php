<?php
/**
 * SB-Tech — PHPUnit bootstrap.
 * Loads config, helpers, and service functions.
 *
 * When config/setup.php exists (local dev), it is loaded so DB-dependent
 * tests run against the real local database instead of being skipped; the
 * DB connection itself stays lazy (APP_BOOTSTRAP_SKIP_DB) so an unreachable
 * database skips those tests rather than failing the run (TEST-03).
 * Without a local config (CI containers) a fake config keeps the pure
 * function tests runnable without any DB.
 */

$appRoot = dirname(__DIR__);
$setupFile = $appRoot . '/config/setup.php';

if (file_exists($setupFile)) {
    $GLOBALS['APP_CONFIG'] = [];
    // Defer the connection: tests reach the DB only through
    // Database::instance() inside try/catch, which converts a down database
    // into skipped tests.
    if (!defined('APP_BOOTSTRAP_SKIP_DB')) {
        define('APP_BOOTSTRAP_SKIP_DB', true);
    }
    // Swallow deprecation noise from legacy vendor files compiled during the
    // app bootstrap (thecodingmachine/safe on PHP 8.4); non-deprecations pass
    // through to PHP's normal handling.
    set_error_handler(static function (int $no): bool {
        return $no === E_DEPRECATED || $no === E_USER_DEPRECATED;
    });
    require $setupFile;   // defines $APP_CONFIG then requires bootstrap.php
    restore_error_handler();
    // setup.php started a session for this request; tests don't need one.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
} else {
    $APP_CONFIG = [
        'db_host' => 'localhost',
        'db_username' => 'test',
        'db_password' => 'test',
        'db_name' => 'sb_tech_test',
        'timezone' => 'Asia/Kathmandu',
        'debug' => true,
        'session_lifetime_seconds' => 28800,
        'upload_max_bytes' => 10485760,
        'pagination' => 50,
        'server_path' => '',
        'organization_name' => 'SB-Tech Test',
    ];
    $GLOBALS['APP_CONFIG'] = $APP_CONFIG;
}

// Environment overrides (CI / disposable DBs): DB_HOST, DB_PORT, DB_USER,
// DB_PASS, DB_NAME. Lets DB-dependent tests target the database built by
// scripts/fresh_test_db.sh without touching config/setup.php.
$envMap = [
    'DB_HOST' => 'db_host',
    'DB_PORT' => 'db_port',
    'DB_USER' => 'db_username',
    'DB_PASS' => 'db_password',
    'DB_NAME' => 'db_name',
];
foreach ($envMap as $envKey => $cfgKey) {
    if (getenv($envKey) !== false) {
        $GLOBALS['APP_CONFIG'][$cfgKey] = getenv($envKey);
    }
}

if (!function_exists('config')) {
    function config(string $key, $default = null)
    {
        return $GLOBALS['APP_CONFIG'][$key] ?? $default;
    }
}

// Load helpers (these don't require a DB connection). No-ops when the local
// config/bootstrap.php already loaded them.
require_once __DIR__ . '/../functions/helpers.php';
require_once __DIR__ . '/../functions/permissions.php';
require_once __DIR__ . '/../functions/api.php';
require_once __DIR__ . '/../functions/hr.php';
require_once __DIR__ . '/../functions/office.php';
require_once __DIR__ . '/../functions/accounting.php';
require_once __DIR__ . '/../functions/analytics.php';
require_once __DIR__ . '/../functions/payroll.php';
require_once __DIR__ . '/../functions/correspondence.php';
require_once __DIR__ . '/../functions/images.php';
