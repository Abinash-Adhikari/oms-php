<?php
/**
 * SB-Tech — GET /api/v1/schools: the school app's directory.
 *
 * Lists client projects that have an app id (`app_slug`), set under
 * Sales → Client Projects → App listing. Completed/Cancelled projects are
 * hidden; status comes from school_api_status(). Demo/trial projects
 * (is_won = 0) appear as active with their trial end as expiryDate.
 * Only the contract fields are exposed (no value, contacts or lead data).
 * Field values are returned exactly as stored (baseUrl = tbl_client_projects.url).
 *
 * Returns the envelope's `data` array to api/v1/index.php.
 */
if (!defined('SB_API')) {
    http_response_code(404);
    exit;
}

$db = Database::instance();
$rows = $db->select(
    'SELECT cp.app_slug, cp.title, cp.city, cp.accent, cp.url, cp.status, cp.end_date,
            COALESCE(cp.updated_on, cp.added_on) AS changed_on, c.name AS client_name
     FROM `tbl_client_projects` cp
     LEFT JOIN `tbl_clients` c ON c.id = cp.client_id
     WHERE cp.app_slug IS NOT NULL AND cp.url IS NOT NULL
     ORDER BY cp.sort_order ASC, COALESCE(c.name, cp.title) ASC, cp.id ASC'
);

$today = date('Y-m-d');
$schools = [];
$lastChange = null;
foreach ($rows as $r) {
    $status = school_api_status((string) $r['status'], $r['end_date'], $today);
    if ($status === null) {
        continue;
    }
    $schools[] = [
        'id'         => $r['app_slug'],
        'schoolName' => $r['client_name'] ?: $r['title'],
        'city'       => $r['city'],
        'accent'     => $r['accent'],
        'baseUrl'    => $r['url'],
        'status'     => $status,
        'expiryDate' => $r['end_date'],
    ];
    if ($r['changed_on'] && ($lastChange === null || $r['changed_on'] > $lastChange)) {
        $lastChange = $r['changed_on'];
    }
}

// Stored times are in the app timezone (Asia/Kathmandu); the API speaks UTC.
$updatedAt = $lastChange
    ? (new DateTimeImmutable($lastChange))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z')
    : null;

return [
    'version'   => api_list_version($schools),
    'updatedAt' => $updatedAt,
    'schools'   => $schools,
];
