<?php
/**
 * SB-Tech — SSE Notifications Stream
 *
 * Streams real-time notifications to the authenticated user via
 * Server-Sent Events. Supports Last-Event-ID resume.
 *
 * IMPORTANT: For Nginx, add to location block:
 *   fastcgi_buffering off;
 *   proxy_buffering off;
 */

// Bootstrap (session, DB, auth)
require_once __DIR__ . '/../../config/setup.php';

// Auth gate — reject before any SSE headers
if (!Auth::check()) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = (int) Auth::id();

// Release the session lock now — this script runs a long-lived loop below
// and PHP's default file session handler holds an exclusive lock for the
// whole script lifetime, which would block every other request (including
// page navigation) sharing this session cookie until the stream closes.
session_write_close();

// SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Nginx: disable proxy buffering

// Disable output buffering
while (ob_get_level()) {
    ob_end_clean();
}
ini_set('output_buffering', '0');
ini_set('zlib.output_compression', '0');

// Ensure the script terminates when client disconnects
ignore_user_abort(true);
set_time_limit(300); // 5 minutes max — client will reconnect

// Register shutdown to clean up
register_shutdown_function(function () {
    if (connection_aborted()) {
        // Force close the connection
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }
});

// Resume from Last-Event-ID if provided
$lastId = isset($_SERVER['HTTP_LAST_EVENT_ID']) ? (int) $_SERVER['HTTP_LAST_EVENT_ID'] : 0;

// If no Last-Event-ID, only send notifications from "now" (not dump history)
if ($lastId === 0) {
    // Get the max ID at connection time — only send notifications newer than this
    $maxRow = Database::instance()->selectOne(
        'SELECT COALESCE(MAX(`id`), 0) AS max_id FROM `tbl_notifications` WHERE `receiver` = ?',
        [$userId]
    );
    $lastId = (int) ($maxRow['max_id'] ?? 0);
}

$startTime = time();
$maxLifetime = 5 * 60; // 5 minutes — client JS will auto-reconnect

echo "retry: 3000\n"; // Client reconnect interval: 3 seconds
echo "\n";

flush();

while (true) {
    // Check if client disconnected — exit immediately
    if (connection_aborted()) {
        break;
    }

    // Check lifetime ceiling
    if ((time() - $startTime) > $maxLifetime) {
        // Send a closing comment, then exit
        echo ": closing stream (max lifetime reached)\n";
        flush();
        break;
    }

    // Query for new notifications
    try {
        $rows = Database::instance()->select(
            "SELECT `id`, `details`, `ref_id`, `receiver`, `type`, `title`, `url`, `viewed`, `added_on`
             FROM `tbl_notifications`
             WHERE `receiver` = ? AND `id` > ?
             ORDER BY `id` ASC",
            [$userId, $lastId]
        );
    } catch (Throwable $e) {
        // DB error — send a comment and retry
        echo ": db error, retrying\n";
        flush();
        // Short sleep with frequent abort checks
        for ($i = 0; $i < 3; $i++) {
            if (connection_aborted()) break;
            sleep(1);
        }
        continue;
    }

    foreach ($rows as $row) {
        $nid = (int) $row['id'];
        if ($nid > $lastId) {
            $lastId = $nid;
        }

        $payload = json_encode([
            'id'         => $nid,
            'title'      => $row['title'] ?: 'Notification',
            'details'    => $row['details'] ?? '',
            'type'       => $row['type'] ?? 'info',
            'url'        => $row['url'] ?? null,
            'created_at' => $row['added_on'] ?? date('Y-m-d H:i:s'),
            'is_read'    => (int) ($row['viewed'] ?? 0),
        ]);

        echo "id: {$nid}\n";
        echo "data: {$payload}\n\n";
    }

    flush();

    // Short sleep with frequent abort checks (1 second intervals)
    for ($i = 0; $i < 3; $i++) {
        if (connection_aborted()) {
            break 2; // Break out of both loops
        }
        sleep(1);
    }
}

// Final cleanup
exit;
