<?php

/**
 * SB-Tech — shared PDF/Word document generation helpers.
 *
 * Every page that renders (or streams to PDF/Word) a business document —
 * quotations, proposals, agreements, terms & conditions, vouchers,
 * certificates, reports — must wrap its content in documentShellStart() /
 * documentShellEnd(). Those render the header (logo, title, subtitle),
 * watermark, footer text, page-number note and generated-on stamp from the
 * single-row configuration managed in Settings → PDF/Word Setup
 * (tbl_document_settings, admin/modules/settings/document_setup.php).
 *
 * Usage inside any admin module page:
 *   documentShellStart('Quotation', 'QTN-2026-0001');
 *   echo '<p>…document body…</p>';
 *   documentShellEnd();
 */

/** Singleton accessor for the document settings row (defaults on error). */
function documentSettings(): array
{
    static $settings = null;
    if ($settings === null) {
        try {
            $row = Database::instance()->selectOne('SELECT * FROM `tbl_document_settings` WHERE `id` = 1');
        } catch (Throwable $e) {
            $row = null;
        }
        $settings = array_merge(documentSettingsDefaults(), $row ?: []);
    }
    return $settings;
}

/** Safe defaults used before the settings row exists. */
function documentSettingsDefaults(): array
{
    return [
        'id'                   => 1,
        'paper_size'           => 'A4',
        'orientation'          => 'Portrait',
        'margin_top_mm'        => '15',
        'margin_right_mm'      => '15',
        'margin_bottom_mm'     => '15',
        'margin_left_mm'       => '15',
        'font_family'          => 'helvetica',
        'font_size_pt'         => 11,
        'header_mode'          => 'office_logo',
        'letterhead_style'     => 'logo_left_details_right',
        'header_logo_location' => null,
        'header_title'         => null,
        'header_subtitle'      => null,
        'show_header_line'     => 1,
        'show_address'         => 1,
        'show_phone'           => 1,
        'show_email'           => 1,
        'show_website'         => 1,
        'show_vat'             => 1,
        'footer_text'          => null,
        'show_page_numbers'    => 1,
        'page_number_format'   => 'Page {PAGE} of {PAGES}',
        'show_generated_stamp' => 1,
        'watermark_text'       => null,
        'watermark_opacity'    => '0.08',
        'default_terms'        => null,
        'signature_block'      => null,
    ];
}

/** Header logo URL (custom upload, office-profile logo, or null). */
function documentHeaderLogoUrl(): ?string
{
    $s = documentSettings();
    if ($s['header_mode'] === 'none' || $s['header_mode'] === 'text_only') {
        return null;
    }
    if ($s['header_mode'] === 'custom_logo' && !empty($s['header_logo_location'])) {
        return assetUrl('user_uploads/' . $s['header_logo_location']);
    }
    // office_logo mode: fall back to the active office profile logo.
    try {
        $profile = Database::instance()->selectOne(
            'SELECT `logo` FROM `tbl_office_profiles` WHERE `id` = 1'
        );
        if (!empty($profile['logo'])) {
            return assetUrl('user_uploads/' . $profile['logo']);
        }
    } catch (Throwable $e) {
        // fall through
    }
    return null;
}

/** Header display title: configured header_title, else organization name. */
function documentHeaderTitle(): string
{
    $s = documentSettings();
    return trim((string) ($s['header_title'] ?? '')) !== ''
        ? (string) $s['header_title']
        : (string) config('organization_name', '');
}

/** Get office profile details for the letterhead. */
function documentOfficeDetails(): array
{
    static $details = null;
    if ($details === null) {
        try {
            $row = Database::instance()->selectOne(
                'SELECT name, accronym, address1, address2, email, phone1, phone2, vat_no, website, slogan, estd FROM `tbl_office_profiles` WHERE `id` = 1'
            );
            $details = $row ?: [];
        } catch (Throwable $e) {
            $details = [];
        }
    }
    return $details;
}

/**
 * Print-oriented CSS shared by every document shell (screen preview and
 * print/PDF export). Uses table-based header for Dompdf compatibility.
 */
function documentCss(): string
{
    $s = documentSettings();
    $fontMap = [
        'helvetica'  => "Helvetica, Arial, sans-serif",
        'times'      => "'Times New Roman', Times, serif",
        'courier'    => "'Courier New', Courier, monospace",
        'dejavusans' => "'DejaVu Sans', Verdana, sans-serif",
    ];
    $font = $fontMap[$s['font_family']] ?? $fontMap['helvetica'];
    $size = max(8, min(20, (int) $s['font_size_pt']));
    $paper = in_array($s['paper_size'], ['A4', 'Letter', 'Legal'], true) ? $s['paper_size'] : 'A4';
    $orient = $s['orientation'] === 'Landscape' ? 'landscape' : 'portrait';
    $mt = (float) $s['margin_top_mm'];
    $mr = (float) $s['margin_right_mm'];
    $mb = (float) $s['margin_bottom_mm'];
    $ml = (float) $s['margin_left_mm'];
    $headerBorder = (int) $s['show_header_line'] === 1 ? '2px solid #1f2937' : 'none';
    $wmOpacity = (float) $s['watermark_opacity'];

    return <<<CSS
    <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { margin: 0; padding: 0; }
    .doc-shell {
        font-family: {$font};
        font-size: {$size}pt;
        color: #111827;
        background: #fff;
        line-height: 1.5;
    }
    .doc-page {
        width: 100%;
        padding: {$mt}mm {$mr}mm {$mb}mm {$ml}mm;
        position: relative;
        box-sizing: border-box;
    }

    /* ── Letterhead Header (table-based for Dompdf) ── */
    .doc-letterhead {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 8px;
    }
    .doc-letterhead td {
        vertical-align: top;
        padding: 0;
    }
    .doc-letterhead .doc-logo-cell {
        width: 20%;
        text-align: left;
        vertical-align: top;
    }
    .doc-letterhead .doc-logo-cell img {
        max-height: 65px;
        max-width: 80px;
        display: block;
    }
    .doc-letterhead .doc-logo-cell-right {
        width: 20%;
        text-align: right;
        vertical-align: top;
    }
    .doc-letterhead .doc-logo-cell-right img {
        margin-left: auto;
    }
    .doc-letterhead .doc-logo-centered {
        width: 100%;
        text-align: center;
        vertical-align: top;
    }
    .doc-letterhead .doc-logo-centered img {
        margin: 0 auto;
        max-height: 65px;
        max-width: 80px;
    }
    .doc-letterhead .doc-main-cell {
        width: 80%;
        vertical-align: top;
        padding-top: 4px;
        line-height: 1.5;
    }
    .doc-letterhead .doc-main-centered {
        width: 100%;
        text-align: center;
    }
    .doc-letterhead .doc-org-name {
        font-size: 1.3em;
        font-weight: 700;
        color: #111827;
        line-height: 1.2;
        margin-bottom: 2px;
    }
    .doc-letterhead .doc-org-slogan {
        font-size: 0.8em;
        color: #6b7280;
        font-style: italic;
    }
    .doc-letterhead .doc-details-lines {
        font-size: 0.78em;
        color: #374151;
        line-height: 1.6;
        margin-top: 2px;
    }
    .doc-letterhead .doc-details-lines strong {
        color: #1f2937;
    }
    .doc-header-line {
        border: none;
        border-top: {$headerBorder};
        margin: 8px 0 18px 0;
    }

    /* ── Document Meta (type + number) ── */
    .doc-meta-bar {
        width: 100%;
        margin-bottom: 16px;
    }
    .doc-meta-bar td {
        padding: 6px 0;
        vertical-align: top;
    }
    .doc-meta-bar .doc-type-label {
        font-size: 1.1em;
        font-weight: 700;
        color: #1f2937;
    }
    .doc-meta-bar .doc-type-number {
        font-size: 0.9em;
        color: #4b5563;
    }

    /* ── Watermark ── */
    .doc-watermark {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-30deg);
        font-size: 4em;
        font-weight: 800;
        color: rgba(31, 41, 55, {$wmOpacity});
        pointer-events: none;
        user-select: none;
        white-space: nowrap;
        z-index: 0;
    }

    /* ── Document Body ── */
    .doc-body {
        position: relative;
        z-index: 1;
    }

    /* ── Tables ── */
    .doc-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 16px;
        font-size: 0.9em;
    }
    .doc-table th {
        background: #f3f4f6;
        border: 1px solid #d1d5db;
        padding: 8px 10px;
        text-align: left;
        font-weight: 600;
        color: #374151;
        font-size: 0.9em;
    }
    .doc-table td {
        border: 1px solid #d1d5db;
        padding: 8px 10px;
        vertical-align: top;
    }
    .doc-table tr:nth-child(even) td {
        background: #f9fafb;
    }

    /* ── Totals table ── */
    .doc-totals {
        width: 280px;
        margin-left: auto;
        margin-bottom: 16px;
        border-collapse: collapse;
        font-size: 0.9em;
    }
    .doc-totals td {
        padding: 4px 10px;
    }
    .doc-totals td:first-child {
        text-align: right;
        color: #6b7280;
    }
    .doc-totals td:last-child {
        text-align: right;
        font-weight: 600;
    }
    .doc-totals .doc-total-row td {
        border-top: 2px solid #1f2937;
        padding-top: 6px;
        font-weight: 700;
        font-size: 1.05em;
        color: #111827;
    }

    /* ── Footer ── */
    .doc-footer {
        margin-top: 24px;
        padding-top: 10px;
        border-top: 1px solid #d1d5db;
        font-size: 0.75em;
        color: #9ca3af;
        display: flex;
        justify-content: space-between;
        gap: 12px;
    }

    /* ── Signature block ── */
    .doc-signature {
        margin-top: 40px;
        font-size: 0.85em;
        color: #374151;
    }
    .doc-signature-line {
        width: 180px;
        border-bottom: 1px solid #1f2937;
        margin-bottom: 4px;
        height: 1px;
    }

    /* ── Print styles ── */
    @media print {
        @page { size: {$paper} {$orient}; margin: 0; }
        body { background: #fff !important; margin: 0; }
        .doc-shell { box-shadow: none !important; }
    }
    </style>
    CSS;
}

/**
 * Open a document shell: prints CSS + letterhead header + watermark.
 * Uses table-based layout for Dompdf compatibility.
 *
 * @param string $docTitle    Document type label, e.g. 'Quotation'.
 * @param string $docSubtitle Reference no / client name shown under the title.
 */
function documentShellStart(string $docTitle = '', string $docSubtitle = ''): void
{
    $s = documentSettings();
    echo documentCss();
    echo '<div class="doc-shell"><div class="doc-page">';

    if (!empty($s['watermark_text'])) {
        echo '<div class="doc-watermark" aria-hidden="true">' . e((string) $s['watermark_text']) . '</div>';
    }

    if ($s['header_mode'] !== 'none') {
        $logoUrl  = documentHeaderLogoUrl();
        $orgName  = documentHeaderTitle();
        $profile  = documentOfficeDetails();
        $subtitle = trim((string) ($s['header_subtitle'] ?? ''));

        // Build contact lines from profile (each line respects its show/hide toggle)
        $contactLines = [];
        if ((int) ($s['show_address'] ?? 1) === 1) {
            $addr = trim(($profile['address1'] ?? '') . ' ' . ($profile['address2'] ?? ''));
            if ($addr !== '') $contactLines[] = e($addr);
        }
        if ((int) ($s['show_phone'] ?? 1) === 1 && !empty($profile['phone1'])) {
            $line = 'Phone: ' . e($profile['phone1']);
            if (!empty($profile['phone2'])) $line .= ', ' . e($profile['phone2']);
            $contactLines[] = $line;
        }
        if ((int) ($s['show_email'] ?? 1) === 1 && !empty($profile['email'])) {
            $contactLines[] = 'Email: ' . e($profile['email']);
        }
        if ((int) ($s['show_website'] ?? 1) === 1 && !empty($profile['website'])) {
            $contactLines[] = 'Website: ' . e($profile['website']);
        }
        if ((int) ($s['show_vat'] ?? 1) === 1 && !empty($profile['vat_no'])) {
            $contactLines[] = 'VAT/PAN: ' . e($profile['vat_no']);
        }

        // If subtitle configured in settings, add it
        if ($subtitle !== '') {
            $contactLines[] = e($subtitle);
        }

        // ── Letterhead arrangement (logo position + name/details block) ──
        $style = (string) ($s['letterhead_style'] ?? 'logo_left_details_right');
        $logoSide   = 'left';
        $blockAlign = 'right';
        switch ($style) {
            case 'logo_left_details_left':     $blockAlign = 'left';                       break;
            case 'details_right_logo_right':   $logoSide = 'right'; $blockAlign = 'left';  break; // Details left / Logo right
            case 'details_left_logo_right':    $logoSide = 'right';                        break; // Details right / Logo right
            case 'logo_left_details_center':   $blockAlign = 'center';                     break;
            case 'details_center_logo_right':  $logoSide = 'right'; $blockAlign = 'center'; break;
            case 'centered':                   $logoSide = 'top';  $blockAlign = 'center'; break;
            case 'logo_top_details_bottom':    $logoSide = 'top';  $blockAlign = 'center'; break;
        }

        $logoCell = '<td class="doc-logo-cell'
            . ($logoSide === 'right' ? ' doc-logo-cell-right' : ($logoSide === 'top' ? ' doc-logo-centered' : '')) . '">';
        if ($logoUrl) {
            $logoCell .= '<img src="' . e($logoUrl) . '" alt="Logo">';
        }
        $logoCell .= '</td>';

        echo '<table class="doc-letterhead">';
        if ($logoSide === 'top') {
            echo '<tr>' . $logoCell . '</tr><tr>';
            echo '<td class="doc-main-cell doc-main-centered" style="text-align:' . $blockAlign . '">';
        } else {
            echo '<tr>';
            if ($logoSide === 'left') {
                echo $logoCell;
            }
            echo '<td class="doc-main-cell" style="text-align:' . $blockAlign . '">';
        }

        // Organization name sits ON TOP of the contact details in this block.
        echo '<div class="doc-org-name">' . e($orgName) . '</div>';
        if (!empty($profile['slogan'])) {
            echo '<div class="doc-org-slogan">' . e($profile['slogan']) . '</div>';
        }
        if ($contactLines) {
            echo '<div class="doc-details-lines">' . implode('<br>', $contactLines) . '</div>';
        }
        echo '</td>';
        if ($logoSide === 'right') {
            echo $logoCell;
        }
        echo '</tr></table>';

        // Header separator line
        if ((int) $s['show_header_line'] === 1) {
            echo '<hr class="doc-header-line">';
        }

        // Document type + number bar (e.g. "Quotation — QTN-2026-0001")
        if ($docTitle !== '' || $docSubtitle !== '') {
            echo '<table class="doc-meta-bar"><tr>';
            echo '<td style="text-align:left">';
            if ($docTitle !== '') {
                echo '<span class="doc-type-label">' . e($docTitle) . '</span>';
            }
            echo '</td>';
            echo '<td style="text-align:right">';
            if ($docSubtitle !== '') {
                echo '<span class="doc-type-number">' . e($docSubtitle) . '</span>';
            }
            echo '</td>';
            echo '</tr></table>';
        }
    }

    echo '<div class="doc-body">';
}

/**
 * Close a document shell: footer text, page-number note and generated-on
 * stamp, then the signature block configured in PDF/Word Setup.
 */
function documentShellEnd(): void
{
    $s = documentSettings();
    echo '</div>'; // .doc-body

    $footerParts = [];
    if (trim((string) ($s['footer_text'] ?? '')) !== '') {
        $footerParts[] = '<span class="doc-footer-text">' . e((string) $s['footer_text']) . '</span>';
    }
    if ((int) $s['show_page_numbers'] === 1) {
        $format = (string) ($s['page_number_format'] ?: 'Page {PAGE} of {PAGES}');
        // Screen preview shows placeholders; PDF engines replace {PAGE}/{PAGES}.
        $footerParts[] = '<span class="doc-footer-pagenums">' . e(strtr($format, ['{PAGE}' => '#', '{PAGES}' => '#'])) . '</span>';
    }
    if ((int) $s['show_generated_stamp'] === 1) {
        $footerParts[] = '<span class="doc-footer-stamp">Generated on ' . e(date('Y-m-d H:i')) . '</span>';
    }
    if ($footerParts) {
        echo '<div class="doc-footer">' . implode('', $footerParts) . '</div>';
    }

    if (trim((string) ($s['signature_block'] ?? '')) !== '') {
        echo '<div class="doc-signature">' . nl2br(e((string) $s['signature_block'])) . '</div>';
    }

    echo '</div></div>'; // .doc-page / .doc-shell
}
