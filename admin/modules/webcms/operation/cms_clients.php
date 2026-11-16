<?php
/**
 * SB-Tech — Website CMS / Clients operations.
 * 'link_projects' sets which portfolio items a website client is linked to
 * (tbl_cms_project_clients); everything else falls through to the shared
 * section CRUD (_dispatch.php).
 */
require_once __DIR__ . '/../includes/cms_config.php';
$db = Database::instance();
$action = (string) ($_POST['action'] ?? '');
$back = 'show_page.php?module=webcms&page=cms_clients&section=client';

if ($action === 'link_projects') {
    try {
        $clientId = (int) ($_POST['id'] ?? 0);
        $client = $db->selectOne('SELECT `id`, `name` FROM `tbl_cms_clients` WHERE `id` = ?', [$clientId]);
        if (!$client) {
            setFlash('error', 'Client not found.');
            redirect($back);
        }
        $valid = array_map(static fn ($r) => (int) $r['id'], $db->select('SELECT `id` FROM `tbl_cms_projects`'));
        $posted = $_POST['project_ids'] ?? [];
        $wanted = array_values(array_intersect(
            array_unique(array_map('intval', is_array($posted) ? $posted : [])),
            $valid
        ));

        $db->transaction(function (Database $db) use ($clientId, $client, $wanted): void {
            $current = array_map(
                static fn ($r) => (int) $r['project_id'],
                $db->select('SELECT `project_id` FROM `tbl_cms_project_clients` WHERE `cms_client_id` = ?', [$clientId])
            );
            foreach (array_diff($current, $wanted) as $pid) {
                $db->delete('tbl_cms_project_clients', '`cms_client_id` = ? AND `project_id` = ?', [$clientId, $pid]);
            }
            foreach (array_diff($wanted, $current) as $pid) {
                $next = $db->selectOne('SELECT COALESCE(MAX(`position`), -1) + 1 AS p FROM `tbl_cms_project_clients` WHERE `project_id` = ?', [$pid]);
                $db->insert('tbl_cms_project_clients', [
                    'project_id'    => $pid,
                    'cms_client_id' => $clientId,
                    'client_name'   => (string) $client['name'],
                    'position'      => (int) ($next['p'] ?? 0),
                ]);
            }
        });
        setFlash('success', 'Portfolio links updated for ' . $client['name'] . ' (' . count($wanted) . ' item' . (count($wanted) === 1 ? '' : 's') . ').');
    } catch (Throwable $e) {
        setFlash('error', 'Could not update portfolio links: ' . $e->getMessage());
    }
    redirect($back);
}

// Fall through to the generic section CRUD for the 'client' section.
$_GET['page'] = 'cms_clients';
include __DIR__ . '/_dispatch.php';
