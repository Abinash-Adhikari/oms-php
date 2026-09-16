<?php
/**
 * SB-Tech — global search endpoint (omnibar).
 * GET /admin/search.php?q=… returns JSON groups across leads, clients,
 * project catalog, quotations and client projects.
 */
include __DIR__ . '/../config/setup.php';

header('Content-Type: application/json; charset=utf-8');

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$q = trim((string) ($_GET['q'] ?? ''));
if ($q === '' || mb_strlen($q) < 2) {
    echo json_encode(['q' => $q, 'results' => []]);
    exit;
}

$db  = Database::instance();
$kw  = '%' . $db->escapeLike($q) . '%';
$out = [];

foreach ($db->select(
    'SELECT id, company, contact_name, email, stage, priority
     FROM `tbl_leads`
     WHERE `company` LIKE ? OR `contact_name` LIKE ? OR `email` LIKE ? OR `phone` LIKE ? OR `service_interest` LIKE ?
     ORDER BY FIELD(stage, "New","Contacted","Qualified","Proposal","Won","Lost"), id DESC LIMIT 8',
    [$kw, $kw, $kw, $kw, $kw]
) as $r) {
    $out[] = [
        'type'  => 'lead',
        'group' => 'Leads',
        'icon'  => 'fas fa-funnel-dollar text-primary',
        'title' => $r['company'] ?: $r['contact_name'],
        'sub'   => $r['stage'] . ($r['email'] ? ' · ' . $r['email'] : ''),
        'url'   => pageUrl('leads', 'leads') . '&id=' . (int) $r['id'],
    ];
}

foreach ($db->select(
    'SELECT id, name, contact_person, email, phone
     FROM `tbl_clients`
     WHERE `name` LIKE ? OR `contact_person` LIKE ? OR `email` LIKE ? OR `phone` LIKE ?
     ORDER BY `name` ASC LIMIT 8',
    [$kw, $kw, $kw, $kw]
) as $r) {
    $out[] = [
        'type'  => 'client',
        'group' => 'Clients',
        'icon'  => 'fas fa-building text-success',
        'title' => $r['name'],
        'sub'   => ($r['contact_person'] ? $r['contact_person'] . ' · ' : '') . ($r['email'] ?: $r['phone']),
        'url'   => pageUrl('clients', 'detail') . '&id=' . (int) $r['id'],
    ];
}

foreach ($db->select(
    'SELECT id, name, code, category
     FROM `tbl_projects`
     WHERE `name` LIKE ? OR `code` LIKE ? OR `category` LIKE ?
     ORDER BY `name` ASC LIMIT 8',
    [$kw, $kw, $kw]
) as $r) {
    $out[] = [
        'type'  => 'project',
        'group' => 'Project Catalog',
        'icon'  => 'fas fa-project-diagram text-info',
        'title' => $r['name'],
        'sub'   => ($r['code'] ? $r['code'] . ' · ' : '') . ($r['category'] ?: ''),
        'url'   => pageUrl('leads', 'projects') . '&edit=' . (int) $r['id'],
    ];
}

foreach ($db->select(
    'SELECT id, quotation_number, client_name, subject, status
     FROM `tbl_quotations`
     WHERE `quotation_number` LIKE ? OR `client_name` LIKE ? OR `subject` LIKE ?
     ORDER BY id DESC LIMIT 8',
    [$kw, $kw, $kw]
) as $r) {
    $out[] = [
        'type'  => 'quotation',
        'group' => 'Quotations',
        'icon'  => 'fas fa-file-invoice text-warning',
        'title' => $r['quotation_number'],
        'sub'   => $r['client_name'] . ' · ' . $r['status'] . ($r['subject'] ? ' — ' . mb_strimwidth($r['subject'], 0, 40, '…') : ''),
        'url'   => pageUrl('leads', 'quotations') . '&id=' . (int) $r['id'],
    ];
}

foreach ($db->select(
    'SELECT cp.id, cp.title, cp.db_name, cp.status, c.name AS client_name
     FROM `tbl_client_projects` cp
     LEFT JOIN `tbl_clients` c ON c.id = cp.client_id
     WHERE cp.title LIKE ? OR cp.db_name LIKE ? OR cp.package LIKE ? OR c.name LIKE ?
     ORDER BY cp.id DESC LIMIT 8',
    [$kw, $kw, $kw, $kw]
) as $r) {
    $out[] = [
        'type'  => 'client_project',
        'group' => 'Client Projects',
        'icon'  => 'fas fa-handshake text-success',
        'title' => $r['title'],
        'sub'   => ($r['client_name'] ? $r['client_name'] . ' · ' : '') . ($r['db_name'] ?: '') . ' · ' . $r['status'],
        'url'   => pageUrl('leads', 'client_permissions') . '&id=' . (int) $r['id'],
    ];
}

echo json_encode(['q' => $q, 'results' => $out]);