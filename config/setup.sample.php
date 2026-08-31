<?php

/**
 * SB-Tech — local environment settings.
 *
 * Copy this file to config/setup.php and edit the values below.
 * config/setup.php is git-ignored (contains credentials); this sample is committed.
 */

$APP_CONFIG = [

    // --- Database ---
    'db_host'     => 'localhost',
    'db_username' => 'admin',
    'db_password' => 'admin',
    'db_name'     => 'sb_tech',
    'db_socket'   => '/var/run/mysqld/mysqld.sock',

    // --- App ---
    'abs_url'             => 'http://localhost/sb-tech',
    'organization_name'   => 'SB-Tech',
    'organization_short_name' => 'SB-TECH',
    'plan'                => 'PRO',           // PRO (full OMS) | WEBSITE (site-only)
    'timezone'            => 'Asia/Kathmandu',
    'base_currency'       => 'NPR',
    'country_code'        => '+977',
    'debug'               => true,
    'session_lifetime_seconds' => 28800,      // 8 h, N-02
    'upload_max_bytes'    => 10485760,        // 10 MB, X-03
    'pagination'          => 50,              // X-04
    'server_path'         => '',

    // --- PDF rendering (Chromium headless preferred, Dompdf fallback) ---
    // Absolute path to a Chrome/Chromium binary used for PDF generation.
    // Leave empty/null to auto-detect (google-chrome, google-chrome-stable,
    // chromium-browser, chromium) from known paths and PATH.
    'pdf'                 => [
        'chrome_bin' => '',
    ],

    // --- Encryption key (for SMTP password storage) ---
    // Change this to a random 32+ character string in production!
    'app_encryption_key'  => 'CHANGE-ME-to-a-random-secret-key-in-production',

    // --- SMTP (Brevo / Sendinblue defaults) ---
    // SMTP settings are stored in tbl_communication_settings.
    // Configure via Admin → Communication → Email/SMS.
    // Host: smtp-relay.brevo.com | Port: 587
    // Username: your Brevo login email
    // Password: your Brevo SMTP key (not login password)

];

require __DIR__ . '/bootstrap.php';
