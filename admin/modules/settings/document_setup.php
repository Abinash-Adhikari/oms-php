<?php
/**
 * SB-Tech — Settings → PDF/Word Setup.
 *
 * Single-row configuration used by every PDF/Word document generation page
 * (quotations, proposals, agreements, terms & conditions, vouchers,
 * certificates, reports) through functions/documents.php
 * (documentShellStart() / documentShellEnd()).
 */
$db = Database::instance();
$settings = documentSettings();
$editLogo = isset($_GET['remove_logo']);

$office = $db->selectOne(
    'SELECT `name`, `accronym`, `address1`, `address2`, `email`, `phone1`, `phone2`, `vat_no`, `website`, `slogan`
     FROM `tbl_office_profiles` WHERE `id` = 1 LIMIT 1'
) ?: [];
$jsOffice = [
    'address1' => (string) ($office['address1'] ?? ''),
    'address2' => (string) ($office['address2'] ?? ''),
    'email'    => (string) ($office['email'] ?? ''),
    'phone1'   => (string) ($office['phone1'] ?? ''),
    'phone2'   => (string) ($office['phone2'] ?? ''),
    'website'  => (string) ($office['website'] ?? ''),
    'vat_no'   => (string) ($office['vat_no'] ?? ''),
    'slogan'   => (string) ($office['slogan'] ?? ''),
];
$detailFlags = [
    'show_address' => (int) ($settings['show_address'] ?? 1),
    'show_phone'   => (int) ($settings['show_phone'] ?? 1),
    'show_email'   => (int) ($settings['show_email'] ?? 1),
    'show_website' => (int) ($settings['show_website'] ?? 1),
    'show_vat'     => (int) ($settings['show_vat'] ?? 1),
];

// Export modes — mirror admin/modules/sales/documents.php: ?pdf=1 generates
// and streams the PDF, ?preview=1 shows it inline, ?word=1 downloads a real
// .docx (OOXML package) so Word AND LibreOffice render the document instead
// of exposing the HTML/XML source.
$isPdf    = !empty($_GET['pdf']);
$isPreview = !empty($_GET['preview']);
$isWord   = !empty($_GET['word']);
if ($isPdf || $isPreview || $isWord) {
    // Nested buffer: capture ONLY the clean document (admin chrome sits in the
    // outer buffer started by show_page.php and is discarded).
    ob_start();
    documentShellStart('Quotation', 'QTN-2026-0001');
    ?>
    <p style="margin:0 0 8px"><strong>Client:</strong> Sample Client Pvt. Ltd.</p>
    <p style="margin:0 0 8px"><strong>Date:</strong> <?= e(date('Y-m-d')) ?></p>
    <table class="table table-sm table-bordered mb-2" style="font-size:inherit">
        <thead class="thead-light"><tr><th>Item</th><th class="text-right">Amount</th></tr></thead>
        <tbody>
            <tr><td>Web development module</td><td class="text-right">100,000.00</td></tr>
            <tr><td>Annual support</td><td class="text-right">25,000.00</td></tr>
        </tbody>
    </table>
    <p class="text-right" style="margin:0"><strong>Total: NPR 125,000.00</strong></p>
    <?php documentShellEnd();
    $docBody = ob_get_clean();

    // Lift the shell's <style> block into <head> (Word-HTML needs styles in head).
    $styleHtml = '';
    if (preg_match('#^\s*<style[^>]*>.*?</style>#s', $docBody, $m)) {
        $styleHtml = $m[0];
        $docBody   = substr($docBody, strlen($m[0]));
    }

    if ($isWord) {
        $docx = DocumentWord::sampleBytes();
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="document-setup-sample.docx"');
        header('Content-Length: ' . strlen($docx));
        echo $docx;
        exit;
    }

    $fullHtml = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Sample Document</title>'
        . $styleHtml . '</head><body>' . $docBody . '</body></html>';

    $pdf = new PdfGenerator();
    $pdf->html($fullHtml);
    if ($isPreview) {
        $pdf->inline('document-setup-sample.pdf'); // exits
    }
    $pdf->download('document-setup-sample.pdf'); // exits
}
?>
<style>
    /* Independent scrolling columns: form left, preview right. */
    .doc-setup-side {
        position: sticky;
        top: 66px;
        max-height: calc(100vh - 88px);
        overflow-y: auto;
        scrollbar-width: thin;
    }
    .doc-setup-side::-webkit-scrollbar { width: 6px; }
    .doc-setup-side::-webkit-scrollbar-thumb { background: rgba(0,0,0,.15); border-radius: 3px; }
</style>
<div class="row">
    <div class="col-lg-4">
        <div class="doc-setup-side">
            <div class="card card-primary">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-file-pdf mr-2"></i>PDF / Word Setup</h3></div>
            <form action="operation.php?module=settings&page=document_setup" method="post" enctype="multipart/form-data">
                <?= csrfField() ?>
                <div class="card-body">

                    <h6 class="text-muted text-uppercase mb-2" style="font-size:.75rem">Page geometry</h6>
                    <div class="row">
                        <div class="col-6 form-group">
                            <label>Paper size</label>
                            <select name="paper_size" class="form-control">
                                <?php foreach (['A4', 'Letter', 'Legal'] as $p): ?>
                                    <option value="<?= $p ?>" <?= $settings['paper_size'] === $p ? 'selected' : '' ?>><?= $p ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 form-group">
                            <label>Orientation</label>
                            <select name="orientation" class="form-control">
                                <?php foreach (['Portrait', 'Landscape'] as $o): ?>
                                    <option value="<?= $o ?>" <?= $settings['orientation'] === $o ? 'selected' : '' ?>><?= $o ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 form-group">
                            <label>Font</label>
                            <div class="d-flex">
                                <select name="font_family" class="form-control mr-2">
                                    <?php foreach (['helvetica' => 'Helvetica', 'times' => 'Times', 'courier' => 'Courier', 'dejavusans' => 'DejaVu Sans'] as $fk => $fl): ?>
                                        <option value="<?= $fk ?>" <?= $settings['font_family'] === $fk ? 'selected' : '' ?>><?= $fl ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="number" name="font_size_pt" class="form-control" style="width:90px" min="8" max="20"
                                       value="<?= (int) $settings['font_size_pt'] ?>" title="Font size (pt)">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <?php foreach (['margin_top_mm' => 'Top (mm)', 'margin_right_mm' => 'Right (mm)', 'margin_bottom_mm' => 'Bottom (mm)', 'margin_left_mm' => 'Left (mm)'] as $field => $label): ?>
                            <div class="col-6 form-group">
                                <label><?= $label ?></label>
                                <input type="number" step="0.5" min="0" max="50" name="<?= $field ?>" class="form-control"
                                       value="<?= e((string) $settings[$field]) ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <h6 class="text-muted text-uppercase mb-2 mt-3" style="font-size:.75rem">Letterhead layout</h6>
                    <div class="form-group">
                        <label>Header arrangement</label>
                        <select name="letterhead_style" class="form-control">
                            <?php foreach ([
                                'logo_left_details_right'   => 'Logo left / Details right',
                                'logo_left_details_left'    => 'Logo left / Details left',
                                'details_right_logo_right'  => 'Details left / Logo right',
                                'details_left_logo_right'   => 'Details right / Logo right',
                                'centered'                  => 'Centered logo + details',
                                'logo_left_details_center'  => 'Logo left / Details center',
                                'details_center_logo_right' => 'Details center / Logo right',
                                'logo_top_details_bottom'   => 'Logo top / Details bottom',
                            ] as $val => $label): ?>
                                <option value="<?= $val ?>" <?= ($settings['letterhead_style'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">How the logo and company details are arranged in the document header.</small>
                    </div>

                    <h6 class="text-muted text-uppercase mb-2 mt-3" style="font-size:.75rem">Header</h6>
                    <div class="row">
                        <div class="col-12 form-group">
                            <label>Header mode</label>
                            <select name="header_mode" class="form-control" id="docHeaderMode">
                                <?php foreach ([
                                    'office_logo' => 'Office profile logo',
                                    'custom_logo' => 'Custom logo',
                                    'text_only'   => 'Text only',
                                    'none'        => 'No header',
                                ] as $mk => $ml): ?>
                                    <option value="<?= $mk ?>" <?= $settings['header_mode'] === $mk ? 'selected' : '' ?>><?= $ml ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 form-group">
                            <label>Custom logo <?= !empty($settings['header_logo_location']) ? '(replaces current)' : '' ?></label>
                            <input type="file" name="header_logo" class="form-control" accept=".jpg,.jpeg,.png" <?= $settings['header_mode'] !== 'custom_logo' ? 'disabled' : '' ?>>
                            <?php if (!empty($settings['header_logo_location'])): ?>
                                <small class="text-muted d-block mt-1">
                                    Current: <code><?= e((string) $settings['header_logo_location']) ?></code>
                                    <label class="ml-2 mb-0"><input type="checkbox" name="remove_logo" value="1"> remove</label>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12 form-group">
                            <label>Header title <small class="text-muted">(blank = organization name)</small></label>
                            <input type="text" name="header_title" class="form-control" maxlength="191"
                                   value="<?= e((string) $settings['header_title']) ?>">
                        </div>
                        <div class="col-12 form-group">
                            <label>Header subtitle</label>
                            <input type="text" name="header_subtitle" class="form-control" maxlength="255"
                                   value="<?= e((string) $settings['header_subtitle']) ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="mb-0"><input type="checkbox" name="show_header_line" value="1" <?= (int) $settings['show_header_line'] === 1 ? 'checked' : '' ?>> show divider line under header</label>
                    </div>

                    <h6 class="text-muted text-uppercase mb-2 mt-3" style="font-size:.75rem">Office profile details (letterhead)</h6>
                    <div class="row">
                        <?php foreach ([
                            'show_address' => 'Address',
                            'show_phone'   => 'Phone number',
                            'show_email'   => 'Email',
                            'show_website' => 'Website',
                            'show_vat'     => 'VAT / PAN no.',
                        ] as $flag => $label): ?>
                            <div class="col-6 form-group mb-2">
                                <label class="mb-0"><input type="checkbox" name="<?= $flag ?>" value="1" <?= $detailFlags[$flag] === 1 ? 'checked' : '' ?>> <span><?= $label ?></span></label>
                            </div>
                        <?php endforeach; ?>
                        <div class="col-12">
                            <small class="form-text text-muted">Which office-profile details appear in the document header (from Office Setup → Profile).</small>
                        </div>
                    </div>

                    <h6 class="text-muted text-uppercase mb-2 mt-3" style="font-size:.75rem">Footer, watermark &amp; stamp</h6>
                    <div class="row">
                        <div class="col-12 form-group">
                            <label>Footer text</label>
                            <input type="text" name="footer_text" class="form-control" maxlength="255"
                                   value="<?= e((string) $settings['footer_text']) ?>" placeholder="e.g. Company registered no · VAT no">
                        </div>
                        <div class="col-12 form-group">
                            <label>Watermark text <small class="text-muted">(blank = none)</small></label>
                            <input type="text" name="watermark_text" class="form-control" maxlength="100"
                                   value="<?= e((string) $settings['watermark_text']) ?>" placeholder="e.g. DRAFT / CONFIDENTIAL">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 form-group">
                            <label>Watermark opacity (0–1)</label>
                            <input type="number" step="0.01" min="0.01" max="0.5" name="watermark_opacity" class="form-control"
                                   value="<?= e((string) $settings['watermark_opacity']) ?>">
                        </div>
                        <div class="col-6 form-group">
                            <label>Page number format <small class="text-muted">({PAGE} / {PAGES} placeholders)</small></label>
                            <input type="text" name="page_number_format" class="form-control" maxlength="50"
                                   value="<?= e((string) $settings['page_number_format']) ?>">
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label class="mb-0"><input type="checkbox" name="show_page_numbers" value="1" <?= (int) $settings['show_page_numbers'] === 1 ? 'checked' : '' ?>> show page numbers</label>
                        <label class="mb-0 ml-4"><input type="checkbox" name="show_generated_stamp" value="1" <?= (int) $settings['show_generated_stamp'] === 1 ? 'checked' : '' ?>> show "generated on" stamp</label>
                    </div>

                    <h6 class="text-muted text-uppercase mb-2 mt-3" style="font-size:.75rem">Defaults for business documents</h6>
                    <div class="form-group">
                        <label>Default terms &amp; conditions <small class="text-muted">(prefilled into quotations, proposals, agreements)</small></label>
                        <textarea name="default_terms" class="form-control" rows="4"><?= e((string) $settings['default_terms']) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Signature block</label>
                        <textarea name="signature_block" class="form-control" rows="3"
                                  placeholder="e.g. ______________________&#10;Authorized Signature&#10;Name / Designation"><?= e((string) $settings['signature_block']) ?></textarea>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save setup</button>
                </div>
            </form>
        </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="doc-setup-side">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title text-white"><i class="fas fa-eye mr-2"></i>Live preview</h3>
                <div class="card-tools">
                    <a href="<?= pageUrl('settings', 'document_setup') ?>&pdf=1" target="_blank" class="btn btn-tool btn-sm text-white" title="Download sample as PDF"><i class="fas fa-download mr-1"></i>PDF</a>
                    <a href="<?= pageUrl('settings', 'document_setup') ?>&word=1" target="_blank" class="btn btn-tool btn-sm text-white" title="Download sample as Word document"><i class="fas fa-file-word mr-1"></i>Word</a>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted" style="font-size:.85rem">This is exactly how the header, watermark and footer of every generated PDF/Word document will look.</p>
                <div class="border rounded p-0 overflow-hidden" style="background:#fff">
                    <?php documentShellStart('Quotation', 'QTN-2026-0001'); ?>
                    <p style="margin:0 0 8px"><strong>Client:</strong> Sample Client Pvt. Ltd.</p>
                    <p style="margin:0 0 8px"><strong>Date:</strong> <?= e(date('Y-m-d')) ?></p>
                    <table class="table table-sm table-bordered mb-2" style="font-size:inherit">
                        <thead class="thead-light"><tr><th>Item</th><th class="text-right">Amount</th></tr></thead>
                        <tbody>
                            <tr><td>Web development module</td><td class="text-right">100,000.00</td></tr>
                            <tr><td>Annual support</td><td class="text-right">25,000.00</td></tr>
                        </tbody>
                    </table>
                    <p class="text-right" style="margin:0"><strong>Total: NPR 125,000.00</strong></p>
                    <?php documentShellEnd(); ?>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-info-circle mr-2"></i>Where this applies</h3></div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush" style="font-size:.9rem">
                    <li class="list-group-item"><i class="far fa-file-alt mr-2 text-muted"></i>Quotations &amp; estimates</li>
                    <li class="list-group-item"><i class="far fa-file-alt mr-2 text-muted"></i>Proposals</li>
                    <li class="list-group-item"><i class="far fa-file-alt mr-2 text-muted"></i>Agreement letters &amp; contracts</li>
                    <li class="list-group-item"><i class="far fa-file-alt mr-2 text-muted"></i>Terms &amp; conditions documents</li>
                    <li class="list-group-item"><i class="far fa-file-alt mr-2 text-muted"></i>Vouchers &amp; finance reports</li>
                    <li class="list-group-item"><i class="far fa-file-alt mr-2 text-muted"></i>Staff certificates &amp; letters</li>
                </ul>
            </div>
        </div>
        </div>
    </div>
</div>
<script>
// Live preview updates for PDF/Word setup.
window.DOC_PREVIEW_OFFICE = <?= json_encode($jsOffice, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

(function () {
    'use strict';
    // jQuery is loaded later in the page (footer scripts), so defer init.
    function init() {
    var o = window.DOC_PREVIEW_OFFICE || {};

    function esc(s) {
        return $('<span>').text(String(s == null ? '' : s)).html();
    }

    function previewDetailsHtml() {
        var addr = $.trim((o.address1 || '') + ' ' + (o.address2 || ''));
        var lines = [];
        if ($('[name=show_address]').is(':checked') && addr) lines.push(esc(addr));
        if ($('[name=show_phone]').is(':checked') && (o.phone1 || o.phone2)) {
            var ph = 'Phone: ' + (o.phone1 || '');
            if (o.phone2) ph += ', ' + o.phone2;
            lines.push(esc(ph));
        }
        if ($('[name=show_email]').is(':checked') && o.email) lines.push(esc('Email: ' + o.email));
        if ($('[name=show_website]').is(':checked') && o.website) lines.push(esc('Website: ' + o.website));
        if ($('[name=show_vat]').is(':checked') && o.vat_no) lines.push(esc('VAT/PAN: ' + o.vat_no));
        var sub = $.trim($('[name=header_subtitle]').val() || '');
        if (sub !== '') lines.push(esc(sub));
        return lines.join('<br>');
    }

    function updatePreviewDetails() {
        var $cell = $('.doc-shell .doc-details-lines');
        var html = previewDetailsHtml();
        $cell.html(html);
    }

    function applyHeaderTitle() {
        var t = $.trim($('[name=header_title]').val() || '');
        $('.doc-shell .doc-org-name').text(t || (typeof APP_ORG_NAME !== 'undefined' ? APP_ORG_NAME : ''));
    }

    function applyHeaderMode() {
        var mode = $('#docHeaderMode').val();
        var $shell = $('.doc-shell');
        $('[name=header_logo]').prop('disabled', mode !== 'custom_logo');
        if (mode === 'none') {
            $shell.find('.doc-letterhead, .doc-meta-bar, .doc-header-line').hide();
        } else {
            $shell.find('.doc-letterhead, .doc-meta-bar').show();
            $shell.find('.doc-header-line').toggle($('[name=show_header_line]').is(':checked'));
            $shell.find('.doc-logo-cell').toggle(mode !== 'text_only');
        }
    }

    var PAPER_MM = { A4: [210, 297], Letter: [216, 279], Legal: [216, 356] };
    var FONT_MAP = {
        helvetica:  'Helvetica, Arial, sans-serif',
        times:      "'Times New Roman', Times, serif",
        courier:    "'Courier New', Courier, monospace",
        dejavusans: "'DejaVu Sans', Verdana, sans-serif"
    };

    function applyGeometry() {
        var $page = $('.doc-shell .doc-page');
        var mt = $('[name=margin_top_mm]').val() || 0;
        var mr = $('[name=margin_right_mm]').val() || 0;
        var mb = $('[name=margin_bottom_mm]').val() || 0;
        var ml = $('[name=margin_left_mm]').val() || 0;
        $page.css('padding', mt + 'mm ' + mr + 'mm ' + mb + 'mm ' + ml + 'mm');
        var p = PAPER_MM[$('[name=paper_size]').val()] || PAPER_MM.A4;
        var w = p[0], h = p[1];
        if ($('[name=orientation]').val() === 'Landscape') { w = p[1]; h = p[0]; }
        $page.css('aspect-ratio', w + ' / ' + h);
        $('.doc-shell').css('font-family', FONT_MAP[$('[name=font_family]').val()] || FONT_MAP.helvetica);
        $('.doc-shell').css('font-size', ($('[name=font_size_pt]').val() || 11) + 'pt');
    }

    function applyPageNumberFormat() {
        var fmt = $('[name=page_number_format]').val() || 'Page {PAGE} of {PAGES}';
        $('.doc-shell .doc-footer-pagenums')
            .text(fmt.replace(/\{PAGE\}/g, '#').replace(/\{PAGES\}/g, '#'));
    }

    function applySignature() {
        var v = $.trim($('[name=signature_block]').val() || '');
        var $sig = $('.doc-shell .doc-signature');
        if (v === '') {
            $sig.remove();
            return;
        }
        if (!$sig.length) {
            $sig = $('<div class="doc-signature"></div>').appendTo($('.doc-shell .doc-page'));
        }
        $sig.text(v).css('white-space', 'pre-line');
    }

    function applyLetterheadStyle() {
        var $table = $('.doc-shell .doc-letterhead');
        if (!$table.length) return;
        var map = {
            'logo_left_details_right':   { side: 'left',  align: 'right' },
            'logo_left_details_left':    { side: 'left',  align: 'left' },
            'details_right_logo_right':  { side: 'right', align: 'left' },
            'details_left_logo_right':   { side: 'right', align: 'right' },
            'centered':                  { side: 'top',   align: 'center' },
            'logo_left_details_center':  { side: 'left',  align: 'center' },
            'details_center_logo_right': { side: 'right', align: 'center' },
            'logo_top_details_bottom':   { side: 'top',   align: 'center' }
        };
        var layout = map[$('[name=letterhead_style]').val()] || { side: 'left', align: 'right' };

        var imgSrc = $table.find('.doc-logo-cell img').attr('src') || '';
        var logoCell = function (side) {
            var cls = 'doc-logo-cell';
            if (side === 'right') cls += ' doc-logo-cell-right';
            else if (side === 'top') cls += ' doc-logo-centered';
            return '<td class="' + cls + '">'
                + (imgSrc ? '<img alt="Logo" src="' + imgSrc + '">' : '') + '</td>';
        };
        var block = '<div class="doc-org-name"></div>'
            + (o.slogan ? '<div class="doc-org-slogan"></div>' : '')
            + '<div class="doc-details-lines"></div>';
        var mainCls = 'doc-main-cell' + (layout.side === 'top' ? ' doc-main-centered' : '');
        var mainTd = '<td class="' + mainCls + '" style="text-align:' + layout.align + '">' + block + '</td>';

        var html;
        if (layout.side === 'top') {
            html = '<tr>' + logoCell('top') + '</tr><tr>' + mainTd + '</tr>';
        } else {
            html = layout.side === 'left'
                ? '<tr>' + logoCell('left') + mainTd + '</tr>'
                : '<tr>' + mainTd + logoCell('right') + '</tr>';
        }
        $table.html(html);

        applyHeaderTitle();
        applyHeaderMode();
        updatePreviewDetails();
    }

    function applyWatermark() {
        var txt = $.trim($('[name=watermark_text]').val() || '');
        var $page = $('.doc-shell .doc-page');
        var $wm = $page.find('> .doc-watermark');
        if (txt === '') {
            $wm.remove();
            return;
        }
        if (!$wm.length) {
            $wm = $('<div class="doc-watermark" aria-hidden="true"></div>').prependTo($page);
        }
        $wm.text(txt);
        var op = parseFloat($('[name=watermark_opacity]').val());
        if (!isNaN(op)) $wm.css('opacity', op);
    }

    function applyFooter() {
        var $footer = $('.doc-shell .doc-footer');
        $footer.find('.doc-footer-pagenums').toggle($('[name=show_page_numbers]').is(':checked'));
        $footer.find('.doc-footer-stamp').toggle($('[name=show_generated_stamp]').is(':checked'));
        var txt = $.trim($('[name=footer_text]').val() || '');
        var $text = $footer.find('.doc-footer-text');
        if (txt !== '') {
            if (!$text.length) $text = $('<span class="doc-footer-text"></span>').prependTo($footer);
            $text.text(txt).show();
        } else {
            $text.remove();
        }
        $footer.toggleClass('d-none', $footer.children().length === 0);
    }

    // Custom logo chosen → preview it immediately.
    $('[name=header_logo]').on('change', function () {
        var f = this.files && this.files[0];
        if (!f || f.type.indexOf('image/') !== 0) return;
        var rd = new FileReader();
        rd.onload = function (e) {
            var $cell = $('.doc-shell .doc-logo-cell');
            var $img = $cell.find('img');
            if (!$img.length) $img = $('<img alt="Logo">').appendTo($cell);
            $img.attr('src', e.target.result);
            applyHeaderMode();
        };
        rd.readAsDataURL(f);
    });

    // Bind every editable control.
    $('[name=header_title]').on('input', applyHeaderTitle);
    $('[name=header_subtitle]').on('input', updatePreviewDetails);
    $('[name=show_address], [name=show_phone], [name=show_email], [name=show_website], [name=show_vat]').on('change', updatePreviewDetails);
    $('#docHeaderMode').on('change', applyHeaderMode);
    $('[name=show_header_line]').on('change', function () {
        $('.doc-shell .doc-header-line').toggle(this.checked);
    });
    $('[name=watermark_text]').on('input', applyWatermark);
    $('[name=watermark_opacity]').on('input', applyWatermark);
    $('[name=footer_text]').on('input', applyFooter);
    $('[name=show_page_numbers]').on('change', applyFooter);
    $('[name=show_generated_stamp]').on('change', applyFooter);
    $('[name=paper_size], [name=orientation], [name=font_family]').on('change', applyGeometry);
    $('[name=font_size_pt]').on('input', applyGeometry);
    $('[name=margin_top_mm], [name=margin_right_mm], [name=margin_bottom_mm], [name=margin_left_mm]').on('input', applyGeometry);
    $('[name=page_number_format]').on('input', applyPageNumberFormat);
    $('[name=signature_block]').on('input', applySignature);
    $('[name=letterhead_style]').on('change', applyLetterheadStyle);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
