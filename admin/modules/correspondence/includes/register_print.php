<?php
/**
 * SB-Tech — Correspondence printable output (P2, docs/DARTA_CHALANI_PLAN.md §6).
 *
 * Included by darta.php / chalani.php when ?print=1 (landscape ledger of the
 * filtered register) or ?entry=ID&print=1 (single-entry slip). Renders a
 * self-contained page and exits — show_page.php has an output buffer open in
 * these modes, so the admin chrome already emitted is discarded.
 *
 * Expects from the caller: $db, $printRegister ('darta'|'chalani'),
 * $printRows + $printFyLabel (ledger mode), $displayNo closure, and
 * $replyMap (darta ledger — replies per entry id).
 */
$printEntryId = (int) ($_GET['entry'] ?? 0);
$printTitle = $printRegister === 'darta' ? 'Darta Register — Inward' : 'Chalani Register — Outward';
$printPageSize = $printEntryId > 0 ? 'portrait' : 'landscape';
$printPaperSize = (string) (documentSettings()['paper_size'] ?? 'A4');

// Export modes (PDF/Word, same content as ?print=1): show_page.php already
// opened a page-level buffer for ?pdf=1 / ?word=1, so render into a nested
// buffer and hand the finished HTML to makePDF() / makeWord() at the exits.
$printExport = !empty($_GET['pdf']) ? 'pdf' : (!empty($_GET['word']) ? 'word' : '');
if ($printExport !== '') {
    ob_start();
}

// Finish the document: print mode just exits (the page buffer carries the
// markup); export modes stream the file and exit. makePDF()/makeWord() clear
// all open buffers before sending headers.
$printFinish = static function (string $docTitle, string $docSubtitle) use ($printExport, $printRegister, $printEntryId): void {
    if ($printExport === '') {
        exit;
    }
    $html = (string) ob_get_clean();
    $base = $printRegister . ($printEntryId > 0 ? '-entry-' . $printEntryId : '-register') . '-' . date('Ymd');
    if ($printExport === 'word') {
        $ok = makeWord($html, $base, $docTitle, $docSubtitle);
    } else {
        $settings = documentSettings();
        $ok = makePDF($html, $base, $printEntryId > 0 ? 'portrait' : 'landscape', (string) ($settings['paper_size'] ?? 'A4'), false, false);
    }
    if ($ok === null) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(500);
    }
    exit;
};
$printOffice = documentOfficeDetails();
$printOrg = documentHeaderTitle();

// P3/D4: Devanagari digits are a render-time toggle (?nep=1), never stored.
$printNep = ($_GET['nep'] ?? '') === '1';
$pd = static fn (string $text): string => $printNep
    ? correspondence_devanagari_digits($text)
    : $text;
$printToggleQs = $_GET;
$printToggleQs['nep'] = $printNep ? '0' : '1';
$printToggleUrl = strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?') . '?' . http_build_query($printToggleQs);

$printDatePair = static function (?string $adDate) use ($pd): string {
    if ($adDate === null || $adDate === '') {
        return '—';
    }
    $pair = formatDateView($adDate);
    $bs = adToBs($adDate);
    // formatDateView already returns B.S. in B.S.-mode offices — only append
    // the pair when the displayed value is the A.D. date.
    if ($bs !== null && $bs !== $pair) {
        $pair .= ' / ' . $bs . ' (B.S.)';
    }

    return $pd($pair);
};

$printHeader = <<<CSS
    <style>
    @page { size: {$printPaperSize} {$printPageSize}; margin: 12mm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: "Noto Sans Devanagari", "DejaVu Sans", Arial, sans-serif;
           font-size: 10.5pt; color: #111827; line-height: 1.45; }
    .print-head { text-align: center; border-bottom: 2px solid #0f172a; padding-bottom: 8px; margin-bottom: 10px; }
    .print-head h1 { font-size: 15pt; margin-bottom: 2px; }
    .print-head .org { font-size: 11pt; font-weight: 600; }
    .print-head .meta { font-size: 9pt; color: #334155; }
    .print-fy { display: flex; justify-content: space-between; font-size: 9.5pt; margin-bottom: 8px; }
    table.ledger { width: 100%; border-collapse: collapse; }
    table.ledger th, table.ledger td { border: 1px solid #0f172a; padding: 4px 6px; vertical-align: top; }
    table.ledger th { background: #f1f5f9; font-weight: 600; text-align: left; }
    table.ledger th .np { display: block; font-size: 9pt; }
    table.ledger th .en { display: block; font-size: 8pt; color: #475569; font-weight: 500; }
    table.ledger tr.cancelled td { text-decoration: line-through; color: #64748b; }
    .print-foot { margin-top: 10px; font-size: 9pt; color: #334155; display: flex; justify-content: space-between; }
    /* Slip */
    .slip-title { text-align: center; font-size: 13pt; font-weight: 700; margin: 4px 0 2px; }
    .slip-no { text-align: center; font-size: 11pt; margin-bottom: 10px; }
    table.meta { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    table.meta td { padding: 4px 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
    table.meta td.k { width: 22%; color: #475569; font-size: 9.5pt; }
    .slip-subject { font-weight: 700; margin: 8px 0 4px; }
    .slip-body { white-space: pre-wrap; margin-bottom: 10px; }
    .bodharth { margin: 8px 0; }
    .bodharth h3 { font-size: 11pt; margin-bottom: 4px; }
    .bodharth ol { margin: 0 0 0 20px; }
    .stamp-area { margin-top: 28px; display: flex; justify-content: space-between; font-size: 9.5pt; color: #334155; }
    .stamp-box { width: 45%; border-top: 1px dotted #0f172a; padding-top: 4px; text-align: center; }
    .print-only-note { font-size: 8.5pt; color: #64748b; margin-top: 8px; }
    .digit-toggle { color: #2563eb; text-decoration: underline; cursor: pointer; }
    @media print { .digit-toggle { display: none; } }
    </style>
CSS;

echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>'
    . e($printTitle . ($printEntryId ? ' slip' : '')) . '</title>' . $printHeader . '</head><body>';

// Word output already carries the office letterhead from DocumentWord::build,
// so suppress the print banner there to avoid showing the org block twice.
$printHead = $printExport === 'word' ? '' : '<div class="print-head">'
    . '<div class="org">' . e($printOrg) . '</div>'
    . (!empty($printOffice['address1']) || !empty($printOffice['address2'])
        ? '<div class="meta">' . e(trim(($printOffice['address1'] ?? '') . ' ' . ($printOffice['address2'] ?? ''))) . '</div>'
        : '')
    . (!empty($printOffice['phone1']) ? '<div class="meta">Phone: ' . e((string) $printOffice['phone1']) . '</div>' : '')
    . '</div>';

if ($printEntryId > 0) {
    // ── Single-entry slip ────────────────────────────────────────────────
    $entry = null;
    $refDarta = null;
    if ($printRegister === 'darta') {
        $entry = $db->selectOne(
            'SELECT d.*, fy.`title` AS fy_title, a.`fullname` AS assigned_name
             FROM `tbl_darta_entries` d
             JOIN `tbl_fiscal_years` fy ON fy.`id` = d.`fiscal_year_id`
             LEFT JOIN `tbl_users_login` a ON a.`id` = d.`assigned_to`
             WHERE d.`id` = ?',
            [$printEntryId]
        );
    } else {
        $entry = $db->selectOne(
            'SELECT c.*, fy.`title` AS fy_title, s.`fullname` AS sent_by_name,
                    d.`darta_no` AS ref_darta_no, dfy.`title` AS ref_darta_fy_title
             FROM `tbl_chalani_entries` c
             JOIN `tbl_fiscal_years` fy ON fy.`id` = c.`fiscal_year_id`
             LEFT JOIN `tbl_users_login` s ON s.`id` = c.`sent_by`
             LEFT JOIN `tbl_darta_entries` d ON d.`id` = c.`ref_darta_id`
             LEFT JOIN `tbl_fiscal_years` dfy ON dfy.`id` = d.`fiscal_year_id`
             WHERE c.`id` = ?',
            [$printEntryId]
        );
    }
    if ($entry === null) {
        if ($printExport !== '') {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            http_response_code(404);
            exit;
        }
        echo '<p>Entry not found.</p></body></html>';
        exit;
    }

    echo $printHead;
    echo '<div class="slip-title" style="text-align:center;font-size:13pt;font-weight:bold">'
        . ($printRegister === 'darta' ? 'दर्ता / DARTA' : 'चलानी / CHALANI') . '</div>';
    // The Word letterhead already shows the entry number as its subtitle.
    if ($printExport !== 'word') {
        echo '<div class="slip-no" style="text-align:center;font-size:11pt"><strong>'
            . e($pd($displayNo($entry))) . '</strong></div>';
    }
    echo '<table class="meta">';

    if ($printRegister === 'darta') {
        echo '<tr><td class="k" style="padding:4px 8px;border-bottom:1px #e2e8f0 solid;vertical-align:top;color:#475569;font-size:9.5pt">मिति / Letter Date</td><td style="padding:4px 8px;border-bottom:1px #e2e8f0 solid;vertical-align:top">' . e($printDatePair((string) $entry['letter_date'])) . '</td>'
            . '<td class="k" style="padding:4px 8px;border-bottom:1px #e2e8f0 solid;vertical-align:top;color:#475569;font-size:9.5pt">Status</td><td style="padding:4px 8px;border-bottom:1px #e2e8f0 solid;vertical-align:top">' . e((string) $entry['status']) . '</td></tr>';
        echo '<tr><td class="k" style="padding:4px 8px;border-bottom:1px #e2e8f0 solid;vertical-align:top;color:#475569;font-size:9.5pt">प्रेषक / Sender</td><td style="padding:4px 8px;border-bottom:1px #e2e8f0 solid;vertical-align:top">' . e((string) $entry['sender_name'])
            . ($entry['sender_address'] ? ' — ' . e((string) $entry['sender_address']) : '') . '</td>'
            . '<td class="k" style="padding:4px 8px;border-bottom:1px #e2e8f0 solid;vertical-align:top;color:#475569;font-size:9.5pt">पत्र संख्या / Ref No.</td><td style="padding:4px 8px;border-bottom:1px #e2e8f0 solid;vertical-align:top">' . e((string) ($entry['sender_ref_no'] ?? '—')) . '</td></tr>';
        echo '<tr><td class="k" style="padding:4px 8px;border-bottom:1px #e2e8f0 solid;vertical-align:top;color:#475569;font-size:9.5pt">Received via</td><td style="padding:4px 8px;border-bottom:1px #e2e8f0 solid;vertical-align:top">' . e((string) $entry['received_via']) . '</td>'
            . '<td class="k" style="padding:4px 8px;border-bottom:1px #e2e8f0 solid;vertical-align:top;color:#475569;font-size:9.5pt">Assigned To</td><td style="padding:4px 8px;border-bottom:1px #e2e8f0 solid;vertical-align:top">' . e((string) ($entry['assigned_name'] ?? '—')) . '</td></tr>';
        echo '<tr><td class="k" style="padding:4px 8px;border-bottom:1px #e2e8f0 solid;vertical-align:top;color:#475569;font-size:9.5pt">Priority</td><td style="padding:4px 8px;border-bottom:1px #e2e8f0 solid;vertical-align:top">' . e((string) $entry['priority']) . '</td>'
            . '<td class="k" style="padding:4px 8px;border-bottom:1px #e2e8f0 solid;vertical-align:top;color:#475569;font-size:9.5pt">Received on</td><td style="padding:4px 8px;border-bottom:1px #e2e8f0 solid;vertical-align:top">' . e($pd(formatDateView((string) $entry['received_on']))) . '</td></tr>';
    } else {
        echo '<tr><td class="k" style="padding:4px 8px;border-bottom:1px #e2e8f0 solid;vertical-align:top;color:#475569;font-size:9.5pt">मिति / Letter Date</td><td style="padding:4px 8px;border-bottom:1px #e2e8f0 solid;vertical-align:top">' . e($printDatePair((string) $entry['letter_date'])) . '</td>'
            . '<td class="k" style="padding:4px 8px;border-bottom:1px #e2e8f0 solid;vertical-align:top;color:#475569;font-size:9.5pt">Status</td><td style="padding:4px 8px;border-bottom:1px #e2e8f0 solid;vertical-align:top">' . e((string) $entry['status']) . '</td></tr>';
        echo '<tr><td class="k" style="padding:4px 8px;border-bottom:1px #e2e8f0 solid;vertical-align:top;color:#475569;font-size:9.5pt">प्राप्तकर्ता / Recipient</td><td style="padding:4px 8px;border-bottom:1px #e2e8f0 solid;vertical-align:top">' . e((string) $entry['recipient_name'])
            . ($entry['recipient_address'] ? ' — ' . e((string) $entry['recipient_address']) : '') . '</td>'
            . '<td class="k" style="padding:4px 8px;border-bottom:1px #e2e8f0 solid;vertical-align:top;color:#475569;font-size:9.5pt">Mode</td><td style="padding:4px 8px;border-bottom:1px #e2e8f0 solid;vertical-align:top">' . e((string) $entry['dispatch_mode']) . '</td></tr>';
        if ($entry['ref_darta_id'] !== null) {
            echo '<tr><td class="k" style="padding:4px 8px;border-bottom:1px #e2e8f0 solid;vertical-align:top;color:#475569;font-size:9.5pt">Linked Darta</td><td colspan="3" style="padding:4px 8px;border-bottom:1px #e2e8f0 solid;vertical-align:top">'
                . ($entry['ref_darta_no'] !== null
                    ? e($pd(correspondence_display_no('darta', (int) $entry['ref_darta_no'], (string) $entry['ref_darta_fy_title'])))
                    : 'Darta #' . (int) $entry['ref_darta_id'])
                . '</td></tr>';
        }
        if ($entry['status'] === 'Dispatched' && !empty($entry['dispatched_on'])) {
            echo '<tr><td class="k" style="padding:4px 8px;border-bottom:1px #e2e8f0 solid;vertical-align:top;color:#475569;font-size:9.5pt">Dispatched on</td><td colspan="3" style="padding:4px 8px;border-bottom:1px #e2e8f0 solid;vertical-align:top">' . e($pd(formatDateView((string) $entry['dispatched_on'])))
                . ($entry['sent_by_name'] ? ' — ' . e((string) $entry['sent_by_name']) : '') . '</td></tr>';
        }
    }

    echo '<tr><td class="k" style="padding:4px 8px;border-bottom:1px #e2e8f0 solid;vertical-align:top;color:#475569;font-size:9.5pt">विषय / Subject</td><td colspan="3" style="padding:4px 8px;border-bottom:1px #e2e8f0 solid;vertical-align:top"><strong>' . e((string) $entry['subject']) . '</strong></td></tr>';
    echo '</table>';

    $body = $printRegister === 'darta' ? ($entry['summary'] ?? '') : ($entry['description'] ?? '');
    if (!empty($body)) {
        echo '<div class="slip-body">' . renderRichText((string) $body) . '</div>';
    }

    if ($printRegister === 'chalani') {
        $bodharth = correspondence_bodharth($entry['cc_recipients'] ?? null);
        if ($bodharth) {
            echo '<div class="bodharth"><h3>बोधार्थ / To be noted by:</h3><ol>';
            foreach ($bodharth as $line) {
                echo '<li>' . e($line) . '</li>';
            }
            echo '</ol></div>';
        }
    } else {
        $replies = $db->select(
            'SELECT c.`chalani_no`, fy.`title` AS fy_title
             FROM `tbl_chalani_entries` c
             JOIN `tbl_fiscal_years` fy ON fy.`id` = c.`fiscal_year_id`
             WHERE c.`ref_darta_id` = ? ORDER BY c.`chalani_no` ASC',
            [$printEntryId]
        );
        if ($replies) {
            $replyNos = array_map(
                static fn (array $r): string => $pd(correspondence_display_no('chalani', (int) $r['chalani_no'], (string) $r['fy_title'])),
                $replies
            );
            echo '<div class="bodharth"><h3>Reply chalanis:</h3>' . e(implode(', ', $replyNos)) . '</div>';
        }
    }

    echo '<div class="stamp-area"><div class="stamp-box">प्राप्ति मुहर / Received Stamp</div>'
        . '<div class="stamp-box">कार्यालय मुहर र स्वाक्षरी / Office Stamp &amp; Signature</div></div>';
    echo '<p class="print-only-note">Computer-generated slip · Printed ' . e($pd(date('Y-m-d H:i')))
        . ($printExport === ''
            ? ' · <a class="digit-toggle" href="' . e($printToggleUrl) . '">'
                . ($printNep ? 'Latin digits' : 'नेपाली अंक') . '</a>'
            : '')
        . '</p>';
    echo '</body></html>';
    $printFinish($printTitle . ' — slip', $displayNo($entry));
}

// ── Landscape ledger of the filtered register ─────────────────────────────
echo $printHead;
// The Word letterhead already carries the title + fiscal year as its meta.
echo '<div class="print-fy">' . ($printExport === 'word' ? '' : '<span><strong>' . e($printTitle) . '</strong> — FY: ' . e($printFyLabel) . '</span>')
    . '<span>' . e(date_system_label()) . ' dates · Printed ' . e($pd(date('Y-m-d H:i')))
    . ($printExport === ''
        ? ' · <a class="digit-toggle" href="' . e($printToggleUrl) . '">'
            . ($printNep ? 'Latin digits' : 'नेपाली अंक') . '</a>'
        : '')
    . '</span></div>';

$columns = correspondence_print_columns($printRegister);
echo '<table class="ledger"><thead><tr>';
foreach ($columns as $col) {
    echo '<th style="border:1px #0f172a solid;padding:4px 6px;background-color:#f1f5f9;font-weight:bold;text-align:left;vertical-align:top"><span class="np">' . e($col['np']) . '</span><span class="en">' . e($col['en']) . '</span></th>';
}
echo '</tr></thead><tbody>';

if (!$printRows) {
    echo '<tr><td colspan="' . count($columns) . '" style="border:1px #0f172a solid;padding:4px 6px;text-align:center">No entries.</td></tr>';
}
foreach ($printRows as $row) {
    $cancelled = (string) $row['status'] === 'Cancelled';
    echo '<tr' . ($cancelled ? ' class="cancelled"' : '') . '>';
    if ($printRegister === 'darta') {
        echo '<td style="border:1px #0f172a solid;padding:4px 6px;vertical-align:top"><strong>' . e($pd($displayNo($row))) . '</strong></td>';
        echo '<td style="border:1px #0f172a solid;padding:4px 6px;vertical-align:top">' . e($pd((string) ($row['sender_ref_no'] ?? '—')))
            . (($row['sender_chalani_no'] ?? '') !== '' ? '<br>चलानी: ' . e($pd((string) $row['sender_chalani_no'])) : '') . '</td>';
        echo '<td style="border:1px #0f172a solid;padding:4px 6px;vertical-align:top">' . e($printDatePair((string) $row['letter_date'])) . '</td>';
        echo '<td style="border:1px #0f172a solid;padding:4px 6px;vertical-align:top">' . e((string) $row['sender_name']) . '<br><span style="font-size:8.5pt;color:#475569">' . e((string) $row['sender_type']) . '</span></td>';
        echo '<td style="border:1px #0f172a solid;padding:4px 6px;vertical-align:top">' . e((string) $row['subject']) . '</td>';
        echo '<td style="border:1px #0f172a solid;padding:4px 6px;vertical-align:top">' . e((string) $row['status'])
            . (($row['priority'] ?? '') === 'Urgent' ? ' (Urgent)' : '')
            . (($row['disposal_note'] ?? '') !== '' ? '<br><span style="font-size:8.5pt">' . e(mb_strimwidth((string) $row['disposal_note'], 0, 90, '…')) . '</span>' : '')
            . (!empty($replyMap[(int) $row['id']]) ? '<br><span style="font-size:8.5pt">Reply: ' . e($pd(implode(', ', $replyMap[(int) $row['id']]))) . '</span>' : '')
            . '</td>';
    } else {
        echo '<td style="border:1px #0f172a solid;padding:4px 6px;vertical-align:top"><strong>' . e($pd($displayNo($row))) . '</strong></td>';
        echo '<td style="border:1px #0f172a solid;padding:4px 6px;vertical-align:top">' . e($printDatePair((string) $row['letter_date'])) . '</td>';
        echo '<td style="border:1px #0f172a solid;padding:4px 6px;vertical-align:top">' . e((string) $row['recipient_name']) . '<br><span style="font-size:8.5pt;color:#475569">' . e((string) $row['recipient_type']) . '</span></td>';
        echo '<td style="border:1px #0f172a solid;padding:4px 6px;vertical-align:top">' . e((string) $row['subject'])
            . (($row['ref_darta_id'] ?? null) !== null && !empty($row['ref_darta_fy_title'])
                ? '<br><span style="font-size:8.5pt">Reply to: ' . e($pd(correspondence_display_no('darta', (int) $row['ref_darta_no'], (string) $row['ref_darta_fy_title']))) . '</span>'
                : '') . '</td>';
        echo '<td style="border:1px #0f172a solid;padding:4px 6px;vertical-align:top">' . e((string) $row['dispatch_mode']) . '</td>';
        echo '<td style="border:1px #0f172a solid;padding:4px 6px;vertical-align:top">' . e((string) $row['status'])
            . (($row['dispatched_on'] ?? '') !== '' ? '<br><span style="font-size:8.5pt">' . e($pd(formatDateView((string) $row['dispatched_on']))) . '</span>' : '')
            . '</td>';
    }
    echo '</tr>';
}

echo '</tbody></table>';
echo '<div class="print-foot"><span>' . $pd(number_format(count($printRows))) . ' entr' . (count($printRows) === 1 ? 'y' : 'ies')
    . ' · Cancelled rows shown struck through (numbers are never reused)</span>'
    . '<span>' . e($printOrg) . '</span></div>';
echo '</body></html>';
$printFinish($printTitle, $printFyLabel);
