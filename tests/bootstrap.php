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

// Load helpers (these don't require a DB connection).
require_once __DIR__ . '/../functions/helpers.php';
require_once __DIR__ . '/../functions/hr.php';
require_once __DIR__ . '/../functions/office.php';
require_once __DIR__ . '/../functions/accounting.php';
