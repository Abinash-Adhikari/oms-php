<?php
/**
 * SB-Tech — PHPUnit bootstrap.
 * Loads config, helpers, and service functions without requiring a live DB.
 */

// Fake the config if not present (unit tests don't need a real DB).
if (!file_exists(__DIR__ . '/../config/setup.php')) {
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

// Load helpers (these don't require a DB connection).
require_once __DIR__ . '/../functions/helpers.php';
require_once __DIR__ . '/../functions/permissions.php';
require_once __DIR__ . '/../functions/hr.php';
require_once __DIR__ . '/../functions/office.php';
require_once __DIR__ . '/../functions/accounting.php';
