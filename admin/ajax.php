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

// ── Personal to-do shell actions (My Office) ──
// Outstanding + recent to-dos belonging to the caller (or all when Super Admin).

if ($action === 'get_my_todos') {
    $seeAll = Auth::isSuperAdmin();
    if ($seeAll) {
        $rows = $db->select(
            'SELECT `id`, `title`, `todo_date`, `todo_time`, `remarks`, `completed`
             FROM `tbl_office_todos`
             ORDER BY `completed`, `todo_date`, `todo_time`
             LIMIT 30'
        );
    } else {
        $rows = $db->select(
            'SELECT `id`, `title`, `todo_date`, `todo_time`, `remarks`, `completed`
             FROM `tbl_office_todos`
             WHERE `added_by` = ?
             ORDER BY `completed`, `todo_date`, `todo_time`
             LIMIT 30',
            [$userId]
        );
    }
    echo json_encode(['success' => true, 'todos' => $rows]);
    exit;
}

if ($action === 'save_todo') {
    $id = (int) ($_POST['id'] ?? 0);
    $title = trim((string) ($_POST['title'] ?? ''));
    $todoDate = trim((string) ($_POST['todo_date'] ?? date('Y-m-d')));
    $todoTime = trim((string) ($_POST['todo_time'] ?? ''));
    $remarks = trim((string) ($_POST['remarks'] ?? ''));
    if ($title === '') {
        echo json_encode(['success' => false, 'message' => 'Title is required.']);
        exit;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $todoDate)) {
        echo json_encode(['success' => false, 'message' => 'Invalid date.']);
        exit;
    }
    if ($todoTime !== '' && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $todoTime)) {
        $todoTime = '';
    }
    $data = [
        'title'      => $title,
        'todo_date'  => $todoDate,
        'todo_time'  => $todoTime !== '' ? $todoTime : null,
        'remarks'    => $remarks !== '' ? $remarks : null,
        'updated_by' => $userId,
    ];
    if ($id > 0) {
        $todo = $db->selectOne('SELECT * FROM `tbl_office_todos` WHERE `id` = ?', [$id]);
        if (!$todo || (!Auth::isSuperAdmin() && (int) $todo['added_by'] !== $userId)) {
            echo json_encode(['success' => false, 'message' => 'You can only edit your own to-dos.']);
            exit;
        }
        $db->update('tbl_office_todos', $data, '`id` = ?', [$id]);
    } else {
        $db->insert('tbl_office_todos', array_merge($data, ['added_by' => $userId]));
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'toggle_todo') {
    $id = (int) ($_POST['id'] ?? 0);
    $todo = $db->selectOne('SELECT * FROM `tbl_office_todos` WHERE `id` = ?', [$id]);
    if (!$todo || (!Auth::isSuperAdmin() && (int) $todo['added_by'] !== $userId)) {
        echo json_encode(['success' => false, 'message' => 'Todo not found.']);
        exit;
    }
    $db->update('tbl_office_todos', [
        'completed' => $todo['completed'] ? 0 : 1,
        'updated_by' => $userId,
    ], '`id` = ?', [$id]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'delete_todo') {
    $id = (int) ($_POST['id'] ?? 0);
    $todo = $db->selectOne('SELECT * FROM `tbl_office_todos` WHERE `id` = ?', [$id]);
    if ($todo && (Auth::isSuperAdmin() || (int) $todo['added_by'] === $userId)) {
        $db->delete('tbl_office_todos', '`id` = ?', [$id]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Todo not found.']);
    }
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
