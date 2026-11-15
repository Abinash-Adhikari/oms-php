<?php

/**
 * SB-Tech — Correspondence (darta / chalani) helpers.
 *
 * Pure display formatting (unit-testable) + atomic per-fiscal-year number
 * allocation through tbl_correspondence_counters. See
 * docs/DARTA_CHALANI_PLAN.md §4.3 for the LAST_INSERT_ID() upsert rationale
 * (a MAX()+1 retry collides with itself under REPEATABLE READ).
 */

/**
 * Display format for a register sequence number: D-12/2082-83 (darta) or
 * C-7/2082-83 (chalani). Latin digits only; Devanagari rendering is a
 * display-layer toggle (D4), never stored.
 */
function correspondence_display_no(string $register, int $seq, string $fyTitle): string
{
    $prefix = match ($register) {
        'darta'   => 'D',
        'chalani' => 'C',
        default   => throw new InvalidArgumentException('Unknown register: ' . $register),
    };

    return $prefix . '-' . $seq . '/' . $fyTitle;
}

/**
 * Atomically allocate the next per-FY sequence number for a register.
 *
 * MUST be called inside the caller's $db->transaction(): the upsert row-locks
 * the counter row until commit, so concurrent saves serialise and receive
 * distinct numbers with no retry loop. A rollback releases the number.
 *
 * @throws InvalidArgumentException on an unknown register
 */
function correspondence_allocate_no(Database $db, string $register, int $fyId): int
{
    if (!in_array($register, ['darta', 'chalani'], true)) {
        throw new InvalidArgumentException('Unknown register: ' . $register);
    }
    if ($fyId < 1) {
        throw new InvalidArgumentException('Invalid fiscal year id: ' . $fyId);
    }

    $db->execute(
        'INSERT INTO `tbl_correspondence_counters` (`register`, `fiscal_year_id`, `last_no`)
         VALUES (?, ?, LAST_INSERT_ID(1))
         ON DUPLICATE KEY UPDATE `last_no` = LAST_INSERT_ID(`last_no` + 1)',
        [$register, $fyId]
    );

    $row = $db->selectOne('SELECT LAST_INSERT_ID() AS `no`');
    if (!$row || (int) $row['no'] < 1) {
        throw new RuntimeException('Could not allocate a ' . $register . ' number.');
    }

    return (int) $row['no'];
}

/**
 * Fiscal year for correspondence numbering: the FY containing today
 * (preferring Open when two overlap). Null when none covers today — the
 * register pages then block saves and point at Setup → Fiscal Years
 * (docs/DARTA_CHALANI_PLAN.md §10 review 1).
 *
 * @return array<string, mixed>|null
 */
function correspondence_current_fy(Database $db): ?array
{
    return $db->selectOne(
        "SELECT * FROM `tbl_fiscal_years`
         WHERE `starting_date` <= CURDATE() AND `ending_date` >= CURDATE()
         ORDER BY (`closing` = 'Open') DESC, `id` DESC
         LIMIT 1"
    );
}

/**
 * Whether a darta status still allows disposal — the dispose action itself
 * and the dispatch-time "mark linked darta Disposed" offer (P2). Pure.
 */
function correspondence_can_dispose(?string $status): bool
{
    return in_array($status, ['Pending', 'In Progress'], true);
}

/**
 * बोधार्थ list for the print slip: cc_recipients is free text, one recipient
 * per line — split, trim, drop blank lines. Pure (unit-testable).
 *
 * @return list<string>
 */
function correspondence_bodharth(?string $ccRecipients): array
{
    if ($ccRecipients === null || trim($ccRecipients) === '') {
        return [];
    }
    $lines = preg_split('/\R/u', $ccRecipients) ?: [];

    return array_values(array_filter(array_map('trim', $lines), static fn (string $line): bool => $line !== ''));
}

/**
 * Bilingual (English + Devanagari) column headers for the printable ledger
 * (?print=1), per register (P2). Pure.
 *
 * @return list<array{en: string, np: string}>
 */
function correspondence_print_columns(string $register): array
{
    return match ($register) {
        'darta' => [
            ['en' => 'Darta No.',       'np' => 'दर्ता नं.'],
            ['en' => "Sender's Ref",    'np' => 'पत्र संख्या'],
            ['en' => 'Letter Date',     'np' => 'मिति'],
            ['en' => 'Sender',          'np' => 'प्रेषक'],
            ['en' => 'Subject',         'np' => 'विषय'],
            ['en' => 'Action / Status', 'np' => 'कारबाही'],
        ],
        'chalani' => [
            ['en' => 'Chalani No.', 'np' => 'चलानी नं.'],
            ['en' => 'Letter Date', 'np' => 'मिति'],
            ['en' => 'Recipient',   'np' => 'प्राप्तकर्ता'],
            ['en' => 'Subject',     'np' => 'विषय'],
            ['en' => 'Mode',        'np' => 'पठाउने तरिका'],
            ['en' => 'Status',      'np' => 'स्थिति'],
        ],
        default => throw new InvalidArgumentException('Unknown register: ' . $register),
    };
}

/**
 * Latin digits → Devanagari digits (०१२३४५६७८९) for the print-time toggle
 * (P3/D4). Never stored — the DB stays Latin so sort/search keep working
 * (plan §10 review note 3). Pure (unit-testable).
 */
function correspondence_devanagari_digits(string $text): string
{
    return strtr($text, [
        '0' => '०', '1' => '१', '2' => '२', '3' => '३', '4' => '४',
        '5' => '५', '6' => '६', '7' => '७', '8' => '८', '9' => '९',
    ]);
}

/**
 * Who to notify when a darta is disposed (P3 §7): the registrar
 * (added_by — the "assigner") and the handler (assigned_to), minus the
 * actor doing the disposal and duplicates. Pure (unit-testable).
 *
 * @param array<string, mixed> $darta tbl_darta_entries row
 * @return list<int> user ids
 */
function correspondence_dispose_recipients(array $darta, int $actorId): array
{
    $recipients = [];
    foreach ([(int) ($darta['added_by'] ?? 0), (int) ($darta['assigned_to'] ?? 0)] as $uid) {
        if ($uid > 0 && $uid !== $actorId) {
            $recipients[$uid] = $uid;
        }
    }

    return array_values($recipients);
}
