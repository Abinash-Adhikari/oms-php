<?php
/**
 * SB-Tech — public API v1 front controller.
 *
 * GET /api/v1/<resource>  (.htaccess → api/v1/index.php?path=<resource>)
 *
 * Read-only and unauthenticated; responses use the app envelope
 * { ok, data } / { ok: false, error: { code, message } } (functions/api.php).
 * Stateless: no session is started, so responses are publicly cacheable.
 * Each handler returns the `data` array; this file adds ETag/304 handling.
 */
define('SB_STATELESS', true);
define('SB_API', true);
require __DIR__ . '/../../config/setup.php';

/** Emit a JSON response and stop. */
$send = static function (int $status, array $body, array $headers = []): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    foreach ($headers as $name => $value) {
        header($name . ': ' . $value);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD' && $status !== 304) {
        echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    exit;
};

$routes = [
    'schools' => __DIR__ . '/schools.php',
];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET' && $method !== 'HEAD') {
    $send(405, api_error('method_not_allowed', 'Only GET is supported.'), ['Allow' => 'GET, HEAD']);
}

$path = trim((string) ($_GET['path'] ?? ''), '/');
if (!isset($routes[$path])) {
    $send(404, api_error('not_found', 'Unknown endpoint.'));
}

try {
    $data = require $routes[$path];
} catch (Throwable $e) {
    error_log('[api/v1/' . $path . '] ' . $e->getMessage());
    $send(500, api_error('server_error', 'Something went wrong. Please try again later.'));
}

$etag = '"' . api_list_version($data) . '"';
$headers = ['ETag' => $etag, 'Cache-Control' => 'public, max-age=300'];
if (strpos((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''), $etag) !== false) {
    $send(304, [], $headers);
}
$send(200, api_ok($data), $headers);
