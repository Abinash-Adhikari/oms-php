<?php
/**
 * SB-Tech — OPcache clearing script.
 * Access this once via browser to force-reload all PHP files.
 * DELETE THIS FILE AFTER USE.
 *
 * Security: requires Super Admin authentication. Never expose this
 * endpoint without auth — it can degrade performance if triggered
 * repeatedly by unauthorized users.
 */
require __DIR__ . '/config/setup.php';

if (!Auth::check() || !Auth::isSuperAdmin()) {
    http_response_code(403);
    die('Access denied. Super Admin login required.');
}

if (function_exists('opcache_reset')) {
    opcache_reset();
    echo 'OPcache cleared successfully.';
} elseif (function_exists('opcache_invalidate')) {
    opcache_invalidate(__DIR__ . '/classes/Auth.php', true);
    echo 'Auth.php OPcache invalidated.';
} else {
    echo 'OPcache not enabled. Restart your web server (Apache/Nginx) instead.';
}
