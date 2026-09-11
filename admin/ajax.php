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

$action = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($_GET['action'] ?? ($_POST['action'] ?? '')));

if ($action === '') {
    echo json_encode(['success' => false, 'message' => 'Missing action']);
    exit;
}

// ── Global shell actions (theme chrome; no module context required) ──
$userId = (int) Auth::id();
$db = Database::instance();

if ($action === 'get_unread_count') {
    $row = $db->selectOne(
        'SELECT COUNT(*) AS c FROM `tbl_notifications`
         WHERE `receiver` = ? AND (`viewed` = 0 OR `viewed` IS NULL)',
        [$userId]
    );
    echo json_encode(['success' => true, 'count' => (int) ($row['c'] ?? 0)]);
    exit;
}

if ($action === 'mark_notification_read') {
    $nid = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
    $ok = $nid > 0;
    if ($ok) {
        $ok = (bool) $db->update(
            'tbl_notifications',
            ['viewed' => 1],
            '`id` = ? AND `receiver` = ?',
            [$nid, $userId]
        );
    }
    echo json_encode(['success' => $ok]);
    exit;
}

if ($action === 'mark_all_notifications_read') {
    $db->update(
        'tbl_notifications',
        ['viewed' => 1],
        '`receiver` = ? AND (`viewed` = 0 OR `viewed` IS NULL)',
        [$userId]
    );
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'getNepaliDate') {
    // Returns a plain-text B.S. date (YYYY-MM-DD) converted from the A.D.
    // value posted by the shell's getNepaliDate() helper.
    $date = trim((string) ($_POST['date'] ?? ''));
    $bs = (function_exists('adToBs') && $date !== '') ? adToBs($date) : null;
    echo $bs ?? '';
    exit;
}

include __DIR__ . '/includes/route.php';

$moduleFs = (string) $moduleFs;

// Action handlers live in modules/<module>/ajax/<action>.php.
// $action is sanitized to [a-zA-Z0-9_-] to prevent path traversal.
$handler = __DIR__ . '/modules/' . $moduleFs . '/ajax/' . $action . '.php';
if (!is_file($handler)) {
    echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
    exit;
}

include $handler;
