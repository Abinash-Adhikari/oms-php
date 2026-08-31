<?php

/**
 * SB-Tech — application bootstrap.
 *
 * Loads config/setup.php first, then wires: session, timezone, database
 * connection (classes/Database), shared helpers (functions/helpers.php),
 * and derived constants. Do not edit for a new installation; edit
 * config/setup.php instead.
 *
 * Any key added to $APP_CONFIG in setup.php is available globally as:
 *   config('your_key')    — recommended
 *   YOUR_KEY              — auto constant (snake_case key → UPPER_SNAKE_CASE)
 */

if (!isset($APP_CONFIG) || !is_array($APP_CONFIG)) {
    die('Missing $APP_CONFIG. Copy config/setup.sample.php to config/setup.php and set your values.');
}

$GLOBALS['APP_CONFIG'] = $APP_CONFIG;

if (!function_exists('config')) {
    function config(string $key, $default = null)
    {
        return $GLOBALS['APP_CONFIG'][$key] ?? $default;
    }
}

$appRoot = dirname(__DIR__);

if (!isset($base)) {
    $base = '';
}

// --- PHP runtime ---
if (!empty(config('debug'))) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

if (session_status() === PHP_SESSION_NONE) {
    $sessionLifetime = (int) config('session_lifetime_seconds', 28800);
    ini_set('session.gc_maxlifetime', (string) $sessionLifetime);
    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path'     => '/',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

date_default_timezone_set((string) config('timezone', 'Asia/Kathmandu'));

// --- Security headers (X-10) ---
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // Don't set Content-Security-Policy here — it's too restrictive for
    // admin panels that load external JS/CSS from CDNs. Set per-page
    // if needed.
}

// --- Derived constants ---
define('PLAN', strtoupper((string) config('plan', 'PRO')));

// --- Composer autoloader (Dompdf, PhpOffice, etc.) ---
require_once $appRoot . '/vendor/autoload.php';

// --- Classes (single thin DB layer; no dual APIs — anti-pattern fix) ---
require_once $appRoot . '/classes/Database.php';
require_once $appRoot . '/classes/Auth.php';
require_once $appRoot . '/classes/CommunicationService.php';
require_once $appRoot . '/classes/RateLimiter.php';
require_once $appRoot . '/classes/DocumentEngine.php';
require_once $appRoot . '/classes/PdfGenerator.php';
require_once $appRoot . '/classes/DocumentWord.php';

// --- Shared helpers ---
require_once $appRoot . '/functions/helpers.php';
require_once $appRoot . '/functions/hr.php';
require_once $appRoot . '/functions/office.php';
require_once $appRoot . '/functions/accounting.php';
require_once $appRoot . '/functions/inventory.php';
require_once $appRoot . '/functions/documents.php';
require_once $appRoot . '/functions/pdf_helpers.php';

// --- Database connection ---
// CLI tools (artisan) define APP_BOOTSTRAP_SKIP_DB to connect after
// ensuring the database itself exists.
if (!defined('APP_BOOTSTRAP_SKIP_DB')) {
    $objQuery = Database::instance();
}

// --- Auto constants (snake_case config key → UPPER_SNAKE_CASE) ---
foreach ($APP_CONFIG as $k => $v) {
    if (is_string($v) || is_int($v) || is_bool($v) || is_float($v)) {
        $const = strtoupper(preg_replace('/(?<=[a-z0-9])[A-Z]|(?<=[A-Z])[A-Z](?=[a-z])/', '_$0', $k));
        $const = strtoupper(str_replace(' ', '_', $const));
        if (!defined($const)) {
            define($const, $v);
        }
    }
}
