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

// ─────────────────────────────────────────────────────────────────────────
// Client payments & balances (tbl_client_payments). All pure — the DB side
// lives in classes/ClientPaymentService.php.
//
// Outstanding = sum of issued invoices (not Draft / Cancelled)
//             − sum of every payment (invoice payments + advances).
// ─────────────────────────────────────────────────────────────────────────

/** Invoice statuses that count as money owed (Draft and Cancelled do not). */
const CLIENT_BILLABLE_INVOICE_EXCLUDED = ['Draft', 'Cancelled'];

/** @return list<string> Allowed tbl_client_payments.method values. */
function client_payment_methods(): array
{
    return ['Cash', 'Bank Transfer', 'Cheque', 'Wallet', 'Card', 'Other'];
}

/**
 * The status an invoice should have after its payments change.
 *
 * Fully paid (within 0.01, ACCOUNTS-02) → 'Paid'; some money in →
 * 'Partially Paid'; nothing paid → the invoice falls back from a
 * payment-derived status to 'Overdue' (past $dueDate) or 'Sent'. Draft and
 * Cancelled invoices with no payment keep their status.
 *
 * @param string      $current Current tbl_documents.status
 * @param float       $total   Invoice total
 * @param float       $paid    Sum of payments recorded against the invoice
 * @param string|null $dueDate Y-m-d or null
 * @param string      $today   Y-m-d (passed in, so the function stays pure)
 */
function invoice_status_for_payments(string $current, float $total, float $paid, ?string $dueDate, string $today): string
{
    if ($paid > 0.0 && $paid >= $total - 0.01) {
        return 'Paid';
    }
    if ($paid > 0.0) {
        return 'Partially Paid';
    }
    if (in_array($current, ['Paid', 'Partially Paid'], true)) {
        return ($dueDate !== null && $dueDate !== '' && $dueDate < $today) ? 'Overdue' : 'Sent';
    }

    return $current;
}

/**
 * Validate a posted payment.
 *
 * @param array<string, mixed>      $input        Posted fields: payment_date, amount, method, reference_no, notes
 * @param array<string, mixed>|null $invoice      The invoice row being paid (needs total, status, document_number), or null for an advance
 * @param float                     $alreadyPaid  Payments already recorded against that invoice (excluding this one)
 * @param string                    $today        Y-m-d
 * @return array{ok: bool, error?: string, data?: array{payment_date: string, amount: float, method: string, reference_no: ?string, notes: ?string}}
 */
function validate_client_payment(array $input, ?array $invoice, float $alreadyPaid, string $today): array
{
    $date = trim((string) ($input['payment_date'] ?? ''));
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$dt || $dt->format('Y-m-d') !== $date) {
        return ['ok' => false, 'error' => 'Choose a valid payment date.'];
    }
    if ($date > $today) {
        return ['ok' => false, 'error' => 'Payment date cannot be in the future.'];
    }
    $rawAmount = trim((string) ($input['amount'] ?? ''));
    if (!is_numeric($rawAmount) || (float) $rawAmount <= 0) {
        return ['ok' => false, 'error' => 'Amount must be more than zero.'];
    }
    $amount = round((float) $rawAmount, 2);
    $method = (string) ($input['method'] ?? '');
    if (!in_array($method, client_payment_methods(), true)) {
        return ['ok' => false, 'error' => 'Choose how the payment was made.'];
    }
    if ($invoice !== null) {
        if (($invoice['status'] ?? '') === 'Cancelled') {
            return ['ok' => false, 'error' => 'Invoice ' . ($invoice['document_number'] ?? '') . ' is cancelled — record this as an advance instead.'];
        }
        $remaining = round((float) ($invoice['total'] ?? 0) - $alreadyPaid, 2);
        if ($amount > $remaining + 0.01) {
            return ['ok' => false, 'error' => 'Only NPR ' . number_format(max($remaining, 0), 2)
                . ' is left to pay on ' . ($invoice['document_number'] ?? 'this invoice')
                . '. Record the extra as a separate advance (no invoice).'];
        }
    }

    return ['ok' => true, 'data' => [
        'payment_date' => $date,
        'amount'       => $amount,
        'method'       => $method,
        'reference_no' => mb_substr(trim((string) ($input['reference_no'] ?? '')), 0, 128) ?: null,
        'notes'        => mb_substr(trim((string) ($input['notes'] ?? '')), 0, 1024) ?: null,
    ]];
}

/**
 * Label for a client's balance, used for the badge on the client list.
 *
 * @return string 'Overdue' | 'Due' | 'Advance' | 'Settled' | 'No invoices'
 */
function client_balance_status(float $invoiced, float $outstanding, float $overdue): string
{
    if ($overdue > 0.01) {
        return 'Overdue';
    }
    if ($outstanding > 0.01) {
        return 'Due';
    }
    if ($outstanding < -0.01) {
        return 'Advance';
    }

    return $invoiced > 0.01 ? 'Settled' : 'No invoices';
}

/**
 * Amount still unpaid on invoices whose due date has passed.
 *
 * @param list<array{total: float|string, paid: float|string, due_date: ?string, status: string}> $invoices
 */
function client_overdue_amount(array $invoices, string $today): float
{
    $sum = 0.0;
    foreach ($invoices as $inv) {
        if (in_array($inv['status'], CLIENT_BILLABLE_INVOICE_EXCLUDED, true)) {
            continue;
        }
        $left = (float) $inv['total'] - (float) $inv['paid'];
        if ($left > 0.01 && !empty($inv['due_date']) && $inv['due_date'] < $today) {
            $sum += $left;
        }
    }

    return round($sum, 2);
}

/**
 * A client statement: invoices (charges) and payments merged by date with a
 * running balance. Draft / Cancelled invoices are left out. On the same date
 * invoices come before payments.
 *
 * @param list<array{id: int|string, document_number: string, document_date: string, total: float|string, status: string, subject?: ?string}> $invoices
 * @param list<array{id: int|string, payment_date: string, amount: float|string, method: string, reference_no?: ?string, document_number?: ?string}> $payments
 * @return list<array{date: string, kind: string, ref: string, detail: string, charge: float, payment: float, balance: float}>
 */
function client_statement(array $invoices, array $payments): array
{
    $rows = [];
    foreach ($invoices as $inv) {
        if (in_array($inv['status'], CLIENT_BILLABLE_INVOICE_EXCLUDED, true)) {
            continue;
        }
        $rows[] = [
            'date' => (string) $inv['document_date'], 'order' => 0, 'id' => (int) $inv['id'],
            'kind' => 'Invoice', 'ref' => (string) $inv['document_number'],
            'detail' => (string) ($inv['subject'] ?? ''),
            'charge' => round((float) $inv['total'], 2), 'payment' => 0.0,
        ];
    }
    foreach ($payments as $p) {
        $against = !empty($p['document_number']) ? 'For ' . $p['document_number'] : 'Advance';
        $rows[] = [
            'date' => (string) $p['payment_date'], 'order' => 1, 'id' => (int) $p['id'],
            'kind' => 'Payment', 'ref' => (string) ($p['reference_no'] ?? ''),
            'detail' => $p['method'] . ' · ' . $against,
            'charge' => 0.0, 'payment' => round((float) $p['amount'], 2),
        ];
    }
    usort($rows, static fn ($a, $b) => [$a['date'], $a['order'], $a['id']] <=> [$b['date'], $b['order'], $b['id']]);

    $balance = 0.0;
    $out = [];
    foreach ($rows as $r) {
        $balance = round($balance + $r['charge'] - $r['payment'], 2);
        unset($r['order'], $r['id']);
        $r['balance'] = $balance;
        $out[] = $r;
    }

    return $out;
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
