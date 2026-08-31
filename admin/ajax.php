<?php
/**
 * SB-Tech — admin AJAX read path (JSON).
 *
 * GET|POST /admin/ajax.php?module=X&action=Y
 * Authentication-gated; each action is responsible for its own
 * permission check via Auth::hasModule / Auth::hasSubmodule.
 * Responds with JSON only.
 */
include __DIR__ . '/../config/setup.php';

header('Content-Type: application/json; charset=utf-8');

if (!Auth::check()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// CSRF verification for state-changing POST requests (X-01).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !hash_equals(csrfToken(), $token)) {
        http_response_code(419);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
}

include __DIR__ . '/includes/route.php';

$action = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($_GET['action'] ?? ($_POST['action'] ?? '')));
$moduleFs = (string) $moduleFs;

if ($action === '') {
    echo json_encode(['success' => false, 'message' => 'Missing action']);
    exit;
}

// Action handlers live in modules/<module>/ajax/<action>.php.
// $action is sanitized to [a-zA-Z0-9_-] to prevent path traversal.
$handler = __DIR__ . '/modules/' . $moduleFs . '/ajax/' . $action . '.php';
if (!is_file($handler)) {
    echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
    exit;
}

include $handler;
