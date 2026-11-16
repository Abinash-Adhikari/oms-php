<?php

/**
 * SB-Tech — sales / lead helpers shared by the admin pipeline
 * (admin/modules/sales/operation/leads.php) and the public website intake
 * (contact.php).
 *
 * A lead can have MANY contact persons (tbl_client_contacts pattern): the
 * first row of the list is primary and mirrors onto tbl_leads
 * contact_name/email/phone. The referrer — the external person who referred
 * the lead — is a single, separate fact (tbl_leads.referrer_* columns) and is
 * never a contact person.
 */

/**
 * Normalize parallel contact-person form arrays (contact_name[] /
 * contact_email[] / contact_phone[]) into row arrays ready for
 * sync_lead_contacts(). Rows without a name are dropped; the first surviving
 * row becomes primary. Pure — unit-testable (TEST-05).
 */
function normalize_lead_contacts(array $names, array $emails, array $phones): array
{
    $rows = [];
    foreach ($names as $i => $rawName) {
        $name = trim((string) $rawName);
        if ($name === '') {
            continue;
        }
        $rows[] = [
            'name'       => $name,
            'email'      => trim((string) ($emails[$i] ?? '')) ?: null,
            'phone'      => trim((string) ($phones[$i] ?? '')) ?: null,
            'is_primary' => $rows === [] ? 1 : 0,
        ];
    }

    return $rows;
}

/**
 * Replace a lead's contact persons and refresh the primary mirror
 * (contact_name/email/phone) on tbl_leads. Intentionally does NOT open its
 * own transaction — Database::transaction() is not nestable — so call it
 * inside the caller's transaction; exceptions propagate so that transaction
 * can roll back.
 */
function sync_lead_contacts(Database $db, int $leadId, array $contacts, ?int $actor = null): void
{
    $db->delete('tbl_lead_contacts', '`lead_id` = ?', [$leadId]);

    $primary = null;
    foreach ($contacts as $contact) {
        $db->insert('tbl_lead_contacts', [
            'lead_id'    => $leadId,
            'name'       => (string) $contact['name'],
            'email'      => $contact['email'] ?? null,
            'phone'      => $contact['phone'] ?? null,
            'is_primary' => (int) ($contact['is_primary'] ?? 0),
            'added_by'   => $actor,
        ]);
        if (!empty($contact['is_primary'])) {
            $primary = $contact;
        }
    }

    $mirror = [
        'contact_name' => $primary['name'] ?? null,
        'email'        => $primary['email'] ?? null,
        'phone'        => $primary['phone'] ?? null,
    ];
    if ($actor !== null) {
        $mirror['updated_by'] = $actor;
    }
    $db->update('tbl_leads', $mirror, '`id` = ?', [$leadId]);
}

/**
 * Copy a lead's contact persons onto a client created from that lead
 * (convert_lead / provision_win). The convert form's single "Contact person"
 * value, when typed, wins as the primary row; a name not already among the
 * lead's contacts is added as an extra primary row. Call inside the caller's
 * transaction; exceptions propagate so that transaction can roll back.
 */
function copy_lead_contacts_to_client(Database $db, int $leadId, int $clientId, ?string $primaryNameOverride = null, ?int $actor = null): void
{
    $rows = $db->select(
        'SELECT `name`, `email`, `phone` FROM `tbl_lead_contacts`
         WHERE `lead_id` = ? ORDER BY `is_primary` DESC, `id` ASC',
        [$leadId]
    );
    $override = trim((string) $primaryNameOverride);
    if ($override !== '') {
        $names = array_column($rows, 'name');
        if (!in_array($override, $names, true)) {
            array_unshift($rows, ['name' => $override, 'email' => null, 'phone' => null]);
        }
    }
    foreach ($rows as $i => $row) {
        $db->insert('tbl_client_contacts', [
            'client_id'  => $clientId,
            'name'       => (string) $row['name'],
            'email'      => $row['email'] ?? null,
            'phone'      => $row['phone'] ?? null,
            'is_primary' => $i === 0 ? 1 : 0,
            'added_by'   => $actor,
        ]);
    }
}

/**
 * Normalise the posted clients of a CMS portfolio item.
 *
 * Each posted value is either "client:<id>" (a website client from
 * tbl_cms_clients, kept only when <id> is in $validClientIds) or a free-text
 * client name. Blank values are
 * dropped, names are trimmed and capped at 191 chars, and duplicates (same
 * client, or same name case-insensitively) are removed, keeping order.
 * Pure function (no DB) so it is unit-testable.
 *
 * @param array<int, mixed>  $raw            Posted values (e.g. $_POST['clients']).
 * @param array<int, string> $validClientIds Website clients as id => name.
 * @return list<array{client_id: ?int, client_name: string}>
 */
function normalize_portfolio_clients(array $raw, array $validClientIds): array
{
    $out = [];
    $seen = [];
    foreach ($raw as $value) {
        if (!is_scalar($value)) {
            continue;
        }
        $value = trim((string) $value);
        if ($value === '') {
            continue;
        }
        $clientId = null;
        if (preg_match('/^client:(\d+)$/', $value, $m)) {
            $id = (int) $m[1];
            if (!array_key_exists($id, $validClientIds)) {
                continue; // stale or forged client id
            }
            $clientId = $id;
            $name = (string) $validClientIds[$id];
            $key = 'id:' . $id;
        } else {
            $name = mb_substr($value, 0, 191);
            $key = 'name:' . mb_strtolower($name);
        }
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out[] = ['client_id' => $clientId, 'client_name' => $name];
    }
    return $out;
}
