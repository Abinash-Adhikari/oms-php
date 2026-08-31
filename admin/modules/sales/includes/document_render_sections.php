<?php
/**
 * SB-Tech — Type-specific section renderer for the shared document engine.
 *
 * Renders the "extra" content blocks the generic engine cannot display:
 *   - invoice:   payment terms, bank details, late fee
 *   - proposal:  exec summary, problem, solution, timeline, team, case studies, why us
 *   - contract:  numbered clauses, payment schedule, signatures
 *   - price_list:category
 *   - brochure:  about, services, key facts, contact, call to action
 *   - credit_note: credited-against invoice, reason for credit
 *
 * Output is plain, inline-styled HTML so it renders identically in the screen
 * detail view, the print view, and the PDF (Dompdf) shell.
 *
 * Usage: echo renderDocumentTypeSections($doc);
 *
 * @param array $doc Document array from DocumentEngine::get()
 * @return string HTML fragment (empty string when no type-specific content)
 */

function renderDocumentTypeSections(array $doc): string
{
    $type = (string) ($doc['document_type'] ?? '');
    if ($type === '') {
        return '';
    }

    switch ($type) {
        case 'invoice':     return invoiceSectionsHtml($doc);
        case 'proposal':    return proposalSectionsHtml($doc);
        case 'contract':    return contractSectionsHtml($doc);
        case 'price_list':  return priceListSectionsHtml($doc);
        case 'brochure':    return brochureSectionsHtml($doc);
        case 'credit_note': return creditNoteSectionsHtml($doc);
        default:            return '';
    }
}

/** Section heading styled to work in the plain document shell and AdminLTE. */
function docSectionHeading(string $title): string
{
    return '<h4 style="margin:18px 0 6px;font-size:.95em;font-weight:700;text-transform:uppercase;letter-spacing:.03em;border-bottom:2px solid #e5e7eb;padding-bottom:4px">' . e($title) . '</h4>';
}

/** Split a textarea value into trimmed, non-empty lines. */
function docLines(string $value): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $value)), 'strlen'));
}

/** Build a two-column label/value table row. */
function docInfoRow(string $label, string $value): string
{
    if (trim($value) === '') {
        return '';
    }
    return '<tr><td style="padding:3px 12px 3px 0;font-weight:600;white-space:nowrap">' . e($label) . '</td>'
        . '<td style="padding:3px 0">' . e($value) . '</td></tr>';
}

// ════════════════════════════════════════════════════════════════
// INVOICE
// ════════════════════════════════════════════════════════════════
function invoiceSectionsHtml(array $doc): string
{
    $html = '';

    if (trim((string) ($doc['payment_terms'] ?? '')) !== '') {
        $html .= docSectionHeading('Payment Terms');
        $html .= '<p style="margin:6px 0 0">' . nl2br(e((string) $doc['payment_terms'])) . '</p>';
    }

    $bankRows = docInfoRow('Bank', (string) ($doc['bank_name'] ?? ''))
        . docInfoRow('Account No', (string) ($doc['bank_account'] ?? ''))
        . docInfoRow('Routing / Swift', (string) ($doc['bank_routing'] ?? ''));
    if ($bankRows !== '') {
        $html .= docSectionHeading('Bank Details');
        $html .= '<table style="width:340px;border-collapse:collapse;font-size:.9em;margin:6px 0 0">' . $bankRows . '</table>';
    }

    $lateFee = (float) ($doc['late_fee_pct'] ?? 0);
    if ($lateFee > 0) {
        $html .= docSectionHeading('Late Fee');
        $html .= '<p style="margin:6px 0 0">A late fee of ' . e(rtrim(rtrim(number_format($lateFee, 2), '0'), '.')) . '% per month applies to overdue balances.</p>';
    }

    return $html;
}

// ════════════════════════════════════════════════════════════════
// PROPOSAL
// ════════════════════════════════════════════════════════════════
function proposalSectionsHtml(array $doc): string
{
    $sections = [
        'Executive Summary'         => $doc['exec_summary'] ?? '',
        'Problem Statement'         => $doc['problem_statement'] ?? '',
        'Proposed Solution'         => $doc['proposed_solution'] ?? '',
        'Timeline &amp; Milestones' => $doc['timeline_text'] ?? '',
        'Team'                      => $doc['team_text'] ?? '',
        'Case Studies / Past Work'  => $doc['case_studies'] ?? '',
        'Why Choose Us'             => $doc['why_us'] ?? '',
    ];

    $html = '';
    foreach ($sections as $title => $content) {
        if (trim((string) $content) === '') {
            continue;
        }
        $html .= docSectionHeading($title);
        $html .= '<p style="margin:6px 0 0">' . nl2br(e((string) $content)) . '</p>';
    }
    return $html;
}

// ════════════════════════════════════════════════════════════════
// CONTRACT
// ════════════════════════════════════════════════════════════════
function contractSectionsHtml(array $doc): string
{
    $html = '';

    $clauses = docLines((string) ($doc['contract_clauses'] ?? ''));
    if ($clauses) {
        $html .= docSectionHeading('Terms &amp; Conditions');
        $html .= '<div style="margin:6px 0 0">';
        $clauseNo = 1;
        foreach ($clauses as $clause) {
            $cleaned = preg_replace('/^\s*\d+[.)]?\s*/', '', $clause);
            $html .= '<p style="margin:3px 0">' . $clauseNo . '. ' . e((string) $cleaned) . '</p>';
            $clauseNo++;
        }
        $html .= '</div>';
    }

    $scheduleLines = docLines((string) ($doc['payment_schedule'] ?? ''));
    if ($scheduleLines) {
        $html .= docSectionHeading('Payment Schedule');
        $html .= '<table style="width:100%;border-collapse:collapse;font-size:.9em;margin:6px 0 0">';
        $html .= '<tr><th style="border:1px solid #d1d5db;padding:5px 8px;text-align:left;background:#f9fafb">Milestone</th><th style="border:1px solid #d1d5db;padding:5px 8px;text-align:left;background:#f9fafb">Amount</th></tr>';
        foreach ($scheduleLines as $line) {
            $parts = preg_split('/\s*[–—-]\s*/', $line, 2);
            $milestone = trim((string) ($parts[0] ?? $line));
            $amount = trim((string) ($parts[1] ?? ''));
            $html .= '<tr><td style="border:1px solid #d1d5db;padding:5px 8px">' . e($milestone) . '</td>'
                . '<td style="border:1px solid #d1d5db;padding:5px 8px">' . e($amount) . '</td></tr>';
        }
        $html .= '</table>';
    }

    $hasLeft  = trim((string) ($doc['signature_left_name'] ?? '')) !== '';
    $hasRight = trim((string) ($doc['signature_right_name'] ?? '')) !== '';
    if ($hasLeft || $hasRight) {
        $html .= docSectionHeading('Signatures');
        $html .= '<table style="width:100%;margin:6px 0 0"><tr>';
        if ($hasLeft) {
            $html .= '<td style="width:48%;vertical-align:top">';
            $html .= '<div style="border-bottom:1px solid #000;height:34px;margin-bottom:4px"></div>';
            $html .= '<div style="font-size:.9em"><strong>' . e((string) $doc['signature_left_name']) . '</strong></div>';
            if (!empty($doc['signature_left_title'])) {
                $html .= '<div style="font-size:.8em;color:#6b7280">' . e((string) $doc['signature_left_title']) . '</div>';
            }
            if (!empty($doc['signature_left_date'])) {
                $html .= '<div style="font-size:.8em;color:#6b7280">Date: ' . e((string) $doc['signature_left_date']) . '</div>';
            }
            $html .= '</td>';
        }
        if ($hasRight) {
            if ($hasLeft) {
                $html .= '<td style="width:4%"></td>';
            }
            $html .= '<td style="width:48%;vertical-align:top">';
            $html .= '<div style="border-bottom:1px solid #000;height:34px;margin-bottom:4px"></div>';
            $html .= '<div style="font-size:.9em"><strong>' . e((string) $doc['signature_right_name']) . '</strong></div>';
            if (!empty($doc['signature_right_title'])) {
                $html .= '<div style="font-size:.8em;color:#6b7280">' . e((string) $doc['signature_right_title']) . '</div>';
            }
            if (!empty($doc['signature_right_date'])) {
                $html .= '<div style="font-size:.8em;color:#6b7280">Date: ' . e((string) $doc['signature_right_date']) . '</div>';
            }
            $html .= '</td>';
        }
        $html .= '</tr></table>';
    }

    return $html;
}

// ════════════════════════════════════════════════════════════════
// PRICE LIST
// ════════════════════════════════════════════════════════════════
function priceListSectionsHtml(array $doc): string
{
    $category = trim((string) ($doc['pl_category'] ?? ''));
    if ($category === '') {
        return '';
    }
    return docSectionHeading('Category') . '<p style="margin:6px 0 0">' . e($category) . '</p>';
}

// ════════════════════════════════════════════════════════════════
// BROCHURE
// ════════════════════════════════════════════════════════════════
function brochureSectionsHtml(array $doc): string
{
    $html = '';

    $about = trim((string) ($doc['exec_summary'] ?? ''));
    if ($about !== '') {
        $html .= docSectionHeading('About Us');
        $html .= '<p style="margin:6px 0 0">' . nl2br(e($about)) . '</p>';
    }

    $services = docLines((string) ($doc['proposed_solution'] ?? ''));
    if ($services) {
        $html .= docSectionHeading('Our Services');
        $html .= '<table style="width:100%;border-collapse:collapse;font-size:.9em;margin:6px 0 0">';
        $html .= '<tr><th style="border:1px solid #d1d5db;padding:5px 8px;text-align:left;background:#f9fafb;width:30%">Service</th><th style="border:1px solid #d1d5db;padding:5px 8px;text-align:left;background:#f9fafb">Description</th></tr>';
        foreach ($services as $line) {
            $parts = preg_split('/\s*[–—-]\s*/', $line, 2);
            $name = trim((string) ($parts[0] ?? $line));
            $desc = trim((string) ($parts[1] ?? ''));
            $html .= '<tr><td style="border:1px solid #d1d5db;padding:5px 8px"><strong>' . e($name) . '</strong></td>'
                . '<td style="border:1px solid #d1d5db;padding:5px 8px">' . e($desc) . '</td></tr>';
        }
        $html .= '</table>';
    }

    $stats = docLines((string) ($doc['why_us'] ?? ''));
    if ($stats) {
        $html .= docSectionHeading('Key Facts');
        $html .= '<div style="margin:6px 0 0">';
        foreach ($stats as $stat) {
            $html .= '<div style="border:1px solid #e5e7eb;border-radius:6px;padding:8px 10px;margin:4px 0;font-weight:600">' . e($stat) . '</div>';
        }
        $html .= '</div>';
    }

    $contact = trim((string) ($doc['notes'] ?? ''));
    if ($contact !== '') {
        $html .= docSectionHeading('Contact');
        $html .= '<p style="margin:6px 0 0">' . nl2br(e($contact)) . '</p>';
    }

    $cta = trim((string) ($doc['terms'] ?? ''));
    if ($cta !== '') {
        $html .= docSectionHeading('Call to Action');
        $html .= '<p style="margin:6px 0 0;padding:10px 12px;background:#f3f4f6;border-left:4px solid #f97316;font-weight:600">' . e($cta) . '</p>';
    }

    return $html;
}

// ════════════════════════════════════════════════════════════════
// CREDIT NOTE
// ════════════════════════════════════════════════════════════════
function creditNoteSectionsHtml(array $doc): string
{
    $html = '';

    $refId = (int) ($doc['original_invoice_id'] ?? 0);
    if ($refId > 0) {
        $inv = Database::instance()->selectOne(
            'SELECT document_number, client_name, document_date, total, status FROM `tbl_documents` WHERE `id` = ? LIMIT 1',
            [$refId]
        );
        if (is_array($inv) && $inv) {
            $html .= docSectionHeading('Credited Against');
            $html .= '<table style="width:100%;border-collapse:collapse;font-size:.9em;margin:6px 0 0">';
            $html .= docInfoRow('Invoice #', (string) ($inv['document_number'] ?? '—'));
            $html .= docInfoRow('Client', (string) ($inv['client_name'] ?? '—'));
            $html .= docInfoRow('Date', (string) ($inv['document_date'] ?? '—'));
            $html .= docInfoRow('Invoice Total', 'NPR ' . formatMoney((float) ($inv['total'] ?? 0)));
            $html .= docInfoRow('Status', (string) ($inv['status'] ?? '—'));
            $html .= '</table>';
        }
    }

    $reason = trim((string) ($doc['credit_reason'] ?? ''));
    if ($reason !== '') {
        $html .= docSectionHeading('Reason for Credit');
        $html .= '<p style="margin:6px 0 0">' . nl2br(e($reason)) . '</p>';
    }

    return $html;
}