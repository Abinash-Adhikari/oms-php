<?php
/**
 * SB-Tech — Client Projects operations.
 *   save_client_project / delete_client_project
 * Manually maintains the client-project registry (tbl_client_projects):
 * won deployments (is_won = 1) and demo/trials of open leads (is_won = 0).
 * Rows are normally created from the lead (Won / Start Demo); the project
 * URL is edited here.
 */
$db = Database::instance();
$me = (int) Auth::id();
if (!(Auth::isSuperAdmin() || Auth::hasSpecial('manage_leads'))) {
    http_response_code(403);
    die('Access denied: you need the manage_leads permission.');
}
$action = (string) ($_POST['action'] ?? '');
$fromClientId = (int) ($_POST['from_client_id'] ?? 0);
$back = $fromClientId
    ? pageUrl('sales', 'client_detail') . '&id=' . $fromClientId
    : 'show_page.php?module=sales&page=client_projects';
// Return to the list with the filters the user had (whitelisted keys only).
if (!$fromClientId) {
    parse_str((string) ($_POST['return_query'] ?? ''), $returnQuery);
    $returnQuery = array_intersect_key($returnQuery, array_flip(['keyword', 'status', 'won', 'source', 'catalog']));
    $returnQuery = array_filter($returnQuery, static fn ($v) => is_string($v) && $v !== '' && $v !== '0');
    if ($returnQuery) {
        $back .= '&' . http_build_query($returnQuery);
    }
}

function validateProjectDates(array $post): array
{
    $start = trim((string) ($post['start_date'] ?? ''));
    $end = trim((string) ($post['end_date'] ?? ''));
    if ($start !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
        $start = '';
    }
    if ($end !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
        $end = '';
    }
    return [$start ?: null, $end ?: null];
}

try {
    if ($action === 'save_client_project') {
        $id = (int) ($_POST['id'] ?? 0);
        $businessSourceId = (int) ($_POST['client_id'] ?? 0);
        $catalogProjectId = (int) ($_POST['project_id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            setFlash('error', 'Project title is required.');
            redirect($back);
        }
        if ($businessSourceId && !$db->selectOne('SELECT id FROM `tbl_clients` WHERE `id` = ?', [$businessSourceId])) {
            setFlash('error', 'Client not found.');
            redirect($back);
        }
        if ($catalogProjectId && !$db->selectOne('SELECT id FROM `tbl_projects` WHERE `id` = ?', [$catalogProjectId])) {
            setFlash('error', 'Product not found.');
            redirect($back);
        }
        $status = (string) ($_POST['status'] ?? 'Active');
        if (!in_array($status, ['Active', 'Completed', 'On Hold', 'Cancelled'], true)) {
            $status = 'Active';
        }
        $projectUrl = normalize_project_url((string) ($_POST['url'] ?? ''));
        [$start, $end] = validateProjectDates($_POST);
        $data = [
            'client_id' => $businessSourceId ?: null,
            'project_id'         => $catalogProjectId ?: null,
            'title'              => $title,
            'package'            => trim((string) ($_POST['package'] ?? '')) ?: null,
            'url'                => $projectUrl,
            'description'        => trim((string) ($_POST['description'] ?? '')) ?: null,
            'value'              => ($_POST['value'] ?? '') !== '' ? round((float) $_POST['value'], 4) : null,
            'start_date'         => $start,
            'end_date'           => $end,
            'status'             => $status,
            'updated_by'         => $me,
        ];
        // Forms that don't post is_won leave the flag untouched (new rows default to won).
        if (isset($_POST['is_won'])) {
            $data['is_won'] = (string) $_POST['is_won'] === '1' ? 1 : 0;
        }
        $existing = $id ? $db->selectOne('SELECT `id`, `app_slug` FROM `tbl_client_projects` WHERE `id` = ?', [$id]) : null;
        if ($id && !$existing) {
            setFlash('error', 'Client project not found.');
            redirect($back);
        }

        // School-app listing (GET /api/v1/schools). Only the Client Projects
        // drawer posts these fields; other forms leave them untouched.
        $appSlug = $existing['app_slug'] ?? null;
        if (isset($_POST['app_listing'])) {
            $postedSlug = normalize_app_slug((string) ($_POST['app_slug'] ?? ''));
            if ($appSlug !== null && $postedSlug !== $appSlug) {
                throw new InvalidArgumentException('The app id "' . $appSlug . '" is permanent and cannot be changed or removed.');
            }
            if ($postedSlug !== null && $appSlug === null
                && $db->selectOne('SELECT `id` FROM `tbl_client_projects` WHERE `app_slug` = ?', [$postedSlug])) {
                throw new InvalidArgumentException('The app id "' . $postedSlug . '" is already used by another client project.');
            }
            $appSlug = $postedSlug;
            $city = trim((string) ($_POST['city'] ?? ''));
            $data['app_slug'] = $appSlug;
            $data['city'] = $city !== '' ? mb_substr($city, 0, 100) : null;
            $data['accent'] = normalize_accent((string) ($_POST['accent'] ?? ''));
            $data['sort_order'] = (int) ($_POST['sort_order'] ?? 0);
        }
        $allowHttp = (bool) config('app_listing_allow_http', false);
        if ($appSlug !== null && !app_listing_url_ok($projectUrl, $allowHttp)) {
            throw new InvalidArgumentException($allowHttp
                ? 'A project listed in the school app needs a URL.'
                : 'A project listed in the school app needs an https:// URL.');
        }

        if ($id) {
            $db->update('tbl_client_projects', $data, '`id` = ?', [$id]);
            setFlash('success', 'Client project updated.');
        } else {
            $data['added_by'] = $me;
            $data['is_won'] = $data['is_won'] ?? 1;
            $id = $db->insert('tbl_client_projects', $data);
            setFlash('success', 'Client project added.');
        }
        redirect($back);
    }

    if ($action === 'delete_client_project') {
        $id = (int) ($_POST['id'] ?? 0);
        $p = $db->selectOne('SELECT `id`, `app_slug` FROM `tbl_client_projects` WHERE `id` = ?', [$id]);
        if (!$p) {
            setFlash('error', 'Client project not found.');
            redirect($back);
        }
        // A listed school's app id must never be freed for reuse by another school.
        if ($p['app_slug'] !== null) {
            setFlash('error', 'This project is listed in the school app as "' . $p['app_slug']
                . '" and cannot be deleted. Set its status to Cancelled to remove it from the app.');
            redirect($back);
        }
        $db->delete('tbl_client_projects', '`id` = ?', [$id]);
        setFlash('success', 'Client project deleted.');
        redirect($back);
    }

    setFlash('error', 'Unknown client project action.');
    redirect($back);
} catch (InvalidArgumentException $e) {
    setFlash('error', $e->getMessage());
    redirect($back);
} catch (Throwable $e) {
    error_log('[client_projects] ' . $e->getMessage());
    setFlash('error', 'Client project operation failed. Please try again.');
    redirect($back);
}