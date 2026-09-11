<?php

/**
 * Container configuration for docker-compose (read-only mount).
 *
 * The compose file mounts this over config/setup.php inside the app
 * container so host credentials never leak into the image. Matches the
 * db service credentials in docker-compose.yml.
 */

$APP_CONFIG = [

    // --- Database ---
    'db_host'     => 'db',          // compose service name
    'db_username' => 'admin',
    'db_password' => 'admin',
    'db_name'     => 'sb_tech',

    // --- App ---
    'abs_url'             => 'http://localhost:8080',
    'organization_name'   => 'SB-Tech',
    'organization_short_name' => 'SB-TECH',
    'plan'                => 'PRO',
    'timezone'            => 'Asia/Kathmandu',
    'base_currency'       => 'NPR',
    'country_code'        => '+977',
    'debug'               => true,
    'session_lifetime_seconds' => 28800,
    'upload_max_bytes'    => 10485760,
    'pagination'          => 50,
    'server_path'         => '',

    // --- PDF rendering ---
    'pdf'                 => [
        'chrome_bin' => '',           // falls back to dompdf in the container
    ],

    // --- Encryption key (dev only — never use in production) ---
    'app_encryption_key'  => 'dev-only-key-change-me-0123456789abcdef',
];

require __DIR__ . '/bootstrap.php';
