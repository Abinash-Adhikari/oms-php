<?php
/**
 * SB-Tech — Notifications AJAX Handler
 *
 * Read-only actions (GET):
 *   unread_count          — Return unread count + last_id
 *   fetch&limit=N         — Return recent notifications
 *
 * State-changing actions (POST, CSRF-protected):
 *   mark_read?id=N        — Mark single notification as read
 *   mark_all_read         — Mark all as read for current user
 */

require_once __DIR__ . '/../../config/setup.php';

header('Content-Type: application/json');

// Auth gate
if (!Auth::check()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = (int) Auth::id();
$action = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['action'] ?? $_GET['action'] ?? '')
    : ($_GET['action'] ?? '');
$db = Database::instance();

// CSRF verification for state-changing actions (X-01).
// All write actions (mark_read, mark_all_read) MUST come via POST.
$writeActions = ['mark_read', 'mark_all_read'];
if (in_array($action, $writeActions, true)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'State-changing actions require POST']);
        exit;
    }
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !hash_equals(csrfToken(), $token)) {
        http_response_code(419);
        echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

try {
    switch ($action) {

        case 'unread_count':
            $row = $db->selectOne(
                "SELECT COUNT(*) AS c, COALESCE(MAX(`id`), 0) AS last_id
                 FROM `tbl_notifications`
                 WHERE `receiver` = ? AND `viewed` = 0",
                [$userId]
            );
            echo json_encode([
                'ok'      => true,
                'count'   => (int) ($row['c'] ?? 0),
                'last_id' => (int) ($row['last_id'] ?? 0),
            ]);
            break;

        case 'mark_read':
            // Accept id from GET (legacy) or POST (new).
            $nid = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
            if ($nid <= 0) {
                echo json_encode(['ok' => false, 'error' => 'Invalid ID']);
                break;
            }
            $db->update(
                'tbl_notifications',
                ['viewed' => 1],
                '`id` = ? AND `receiver` = ? AND `viewed` = 0',
                [$nid, $userId]
            );
            echo json_encode(['ok' => true]);
            break;

        case 'mark_all_read':
            $db->update(
                'tbl_notifications',
                ['viewed' => 1],
                '`receiver` = ? AND `viewed` = 0',
                [$userId]
            );
            echo json_encode(['ok' => true]);
            break;

        case 'fetch':
            $limit = isset($_GET['limit']) ? min((int) $_GET['limit'], 50) : 10;
            $rows = $db->select(
                "SELECT `id`, `details`, `ref_id`, `type`, `title`, `url`, `viewed`, `added_on`
                 FROM `tbl_notifications`
                 WHERE `receiver` = ?
                 ORDER BY `id` DESC
                 LIMIT ?",
                [$userId, $limit]
            );
            // Reverse so newest is last (for prepend rendering)
            $rows = array_reverse($rows);

            $lastId = 0;
            $items = [];
            foreach ($rows as $r) {
                $nid = (int) $r['id'];
                if ($nid > $lastId) {
                    $lastId = $nid;
                }
                $items[] = [
                    'id'         => $nid,
                    'title'      => $r['title'] ?: 'Notification',
                    'details'    => $r['details'] ?? '',
                    'type'       => $r['type'] ?? 'info',
                    'url'        => $r['url'] ?? null,
                    'created_at' => $r['added_on'] ?? '',
                    'is_read'    => (int) ($r['viewed'] ?? 0),
                ];
            }
            echo json_encode([
                'ok'      => true,
                'items'   => $items,
                'last_id' => $lastId,
            ]);
            break;

        default:
            echo json_encode(['ok' => false, 'error' => 'Unknown action']);
            break;
    }
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}
