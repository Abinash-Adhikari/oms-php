<?php
/**
 * SB-Tech — Clients operations.
 *   save_client / delete_client
 *   save_contact / delete_contact / set_primary_contact
 * The first contact on a client becomes its primary; deleting (or un-priming)
 * the primary promotes the next contact and mirrors it onto the client row.
 */
$db = Database::instance();
$me = (int) Auth::id();
if (!(Auth::isSuperAdmin() || Auth::hasSpecial('manage_leads'))) {
    http_response_code(403);
    die('Access denied: you need the manage_leads permission.');
}
$action = (string) ($_POST['action'] ?? '');
$back = pageUrl('clients', 'clients');
$clientDetail = pageUrl('clients', 'detail');

/** Mirror the primary contact (or null) onto the client's contact_person column. */
function sync_client_primary_contact(Database $db, int $clientId): void
{
    $primary = $db->selectOne(
        'SELECT `name` FROM `tbl_client_contacts`
         WHERE `client_id` = ? AND `is_primary` = 1 LIMIT 1',
        [$clientId]
    );
    $db->update('tbl_clients', [
        'contact_person' => $primary ? $primary['name'] : null,
    ], '`id` = ?', [$clientId]);
}

try {
    if ($action === 'save_client') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            setFlash('error', 'Client name is required.');
            redirect($back);
        }
        $type = (string) ($_POST['type'] ?? 'Company');
        if (!in_array($type, ['Individual', 'Company'], true)) {
            $type = 'Company';
        }
        $data = [
            'type'           => $type,
            'name'           => $name,
            'contact_person' => trim((string) ($_POST['contact_person'] ?? '')) ?: null,
            'email'          => trim((string) ($_POST['email'] ?? '')) ?: null,
            'phone'          => trim((string) ($_POST['phone'] ?? '')) ?: null,
            'address'        => trim((string) ($_POST['address'] ?? '')) ?: null,
            'pan_num'        => trim((string) ($_POST['pan_num'] ?? '')) ?: null,
            'notes'          => trim((string) ($_POST['notes'] ?? '')) ?: null,
            'updated_by'     => $me,
        ];
        if ($id) {
            $db->update('tbl_clients', $data, '`id` = ?', [$id]);
            setFlash('success', 'Client updated.');
        } else {
            $data['added_by'] = $me;
            $id = $db->insert('tbl_clients', $data);
            if (!empty($data['contact_person'])) {
                $db->insert('tbl_client_contacts', [
                    'client_id' => $id,
                    'name' => $data['contact_person'],
                    'is_primary' => 1,
                    'added_by' => $me,
                ]);
            }
            setFlash('success', 'Client created.');
        }
        redirect($clientDetail . '&id=' . $id);
    }

    if ($action === 'delete_client') {
        $id = (int) ($_POST['id'] ?? 0);
        $db->delete('tbl_clients', '`id` = ?', [$id]);
        setFlash('success', 'Client deleted.');
        redirect($back);
    }

    if ($action === 'save_contact') {
        $id = (int) ($_POST['id'] ?? 0);
        $clientId = (int) ($_POST['client_id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            setFlash('error', 'Contact name is required.');
redirect($clientDetail . ($clientId ? '&id=' . $clientId : ''));
        }
        if (!$clientId || !$db->selectOne('SELECT id FROM `tbl_clients` WHERE `id` = ?', [$clientId])) {
            setFlash('error', 'Client not found.');
            redirect($back);
        }
        $wantPrimary = !empty($_POST['is_primary']) || $id === 0;
        $isFirst = $id === 0
            && !$db->selectOne('SELECT id FROM `tbl_client_contacts` WHERE `client_id` = ? LIMIT 1', [$clientId]);
        $makePrimary = $isFirst || $id === 0 || $wantPrimary;

        $data = [
            'client_id'      => $clientId,
            'name'           => $name,
            'designation'    => trim((string) ($_POST['designation'] ?? '')) ?: null,
            'email'          => trim((string) ($_POST['email'] ?? '')) ?: null,
            'phone'          => trim((string) ($_POST['phone'] ?? '')) ?: null,
            'notes'          => trim((string) ($_POST['notes'] ?? '')) ?: null,
            'updated_by'     => $me,
        ];
        if ($id) {
            $existing = $db->selectOne('SELECT * FROM `tbl_client_contacts` WHERE `id` = ?', [$id]);
            if (!$existing) {
                setFlash('error', 'Contact not found.');
                redirect($clientDetail . '&id=' . $clientId);
            }
            $data['is_primary'] = $existing['is_primary'] ? 1 : 0;
            $db->update('tbl_client_contacts', $data, '`id` = ?', [$id]);
            if ($wantPrimary) {
                $db->update('tbl_client_contacts', ['is_primary' => 0], '`client_id` = ? AND `id` <> ?', [$clientId, $id]);
                $db->update('tbl_client_contacts', ['is_primary' => 1], '`id` = ?', [$id]);
            }
            setFlash('success', 'Contact updated.');
        } else {
            $data['added_by'] = $me;
            $data['is_primary'] = $makePrimary ? 1 : 0;
            if ($makePrimary) {
                $db->update('tbl_client_contacts', ['is_primary' => 0], '`client_id` = ?', [$clientId]);
            }
            $id = $db->insert('tbl_client_contacts', $data);
            setFlash('success', 'Contact added.');
        }
        sync_client_primary_contact($db, $clientId);
        redirect($clientDetail . '&id=' . $clientId);
    }

    if ($action === 'set_primary_contact') {
        $id = (int) ($_POST['id'] ?? 0);
        $clientId = (int) ($_POST['client_id'] ?? 0);
        $contact = $db->selectOne('SELECT * FROM `tbl_client_contacts` WHERE `id` = ?', [$id]);
        if (!$contact) {
            setFlash('error', 'Contact not found.');
            redirect($back);
        }
        $clientId = $clientId ?: (int) $contact['client_id'];
        $db->update('tbl_client_contacts', ['is_primary' => 0], '`client_id` = ?', [$clientId]);
        $db->update('tbl_client_contacts', ['is_primary' => 1], '`id` = ?', [$id]);
        sync_client_primary_contact($db, $clientId);
        setFlash('success', 'Primary contact updated.');
        redirect($clientDetail . '&id=' . $clientId);
    }

    if ($action === 'delete_contact') {
        $id = (int) ($_POST['id'] ?? 0);
        $clientId = (int) ($_POST['client_id'] ?? 0);
        $contact = $db->selectOne('SELECT * FROM `tbl_client_contacts` WHERE `id` = ?', [$id]);
        $wasPrimary = $contact && (int) $contact['is_primary'] === 1;
        $db->delete('tbl_client_contacts', '`id` = ?', [$id]);
        $clientId = $clientId ?: ($contact ? (int) $contact['client_id'] : 0);
        if ($clientId && $wasPrimary) {
            $next = $db->selectOne(
                'SELECT `id` FROM `tbl_client_contacts`
                 WHERE `client_id` = ? ORDER BY `id` ASC LIMIT 1',
                [$clientId]
            );
            if ($next) {
                $db->update('tbl_client_contacts', ['is_primary' => 1], '`id` = ?', [(int) $next['id']]);
            }
        }
        if ($clientId) {
            sync_client_primary_contact($db, $clientId);
        }
        setFlash('success', 'Contact deleted.');
        redirect($clientDetail . ($clientId ? '&id=' . $clientId : ''));
    }

    setFlash('error', 'Unknown client action.');
    redirect($back);
} catch (Throwable $e) {
    setFlash('error', 'Client operation failed: ' . $e->getMessage());
    redirect($back);
}
