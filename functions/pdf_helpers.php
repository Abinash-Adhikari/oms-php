<?php
/**
 * SB-Tech — PDF generation helpers.
 *
 * Dual-engine approach: Chrome headless (primary) → Dompdf (fallback).
 * Chrome renders CSS perfectly (flexbox, gradients, shadows, etc.);
 * Dompdf is the safety net when Chrome is unavailable.
 *
 * Usage:
 *   makePDF($html, 'QTN-2026-0001');           // inline preview
 *   makePDF($html, 'QTN-2026-0001', 'portrait', 'A4', false, true); // return bytes
 */

use Dompdf\Dompdf;
use Dompdf\Options;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;

/**
 * Normalize paper size and orientation into Chrome-compatible values.
 *
 * @return array{0: string, 1: string} [paperSize, orientation]
 */
function pdfNormalizePaperSize(string $paperSize = 'A4', string $orientation = 'portrait'): array
{
    $valid = ['A4', 'Letter', 'Legal', 'A3', 'A5'];
    $paper = in_array(strtoupper($paperSize), $valid, true) ? strtoupper($paperSize) : 'A4';
    $orient = strtolower($orientation) === 'landscape' ? 'landscape' : 'portrait';
    return [$paper, $orient];
}

/**
 * Render HTML to PDF using headless Chrome/Chromium.
 *
 * @param string $html      Full HTML document to render
 * @param string $paper     Paper size: A4, Letter, Legal, etc.
 * @param string $orient    portrait or landscape
 * @param bool   $watermask Reserved for future use
 * @return string|null      PDF bytes on success, null on failure
 */
function pdfRenderWithChrome(string $html, string $paper = 'A4', string $orient = 'portrait', bool $watermask = false): ?string
{
    // Find Chrome binary — explicit config override first, then known paths, then PATH lookup.
    $chrome = null;
    $pdfCfg = config('pdf');
    $override = is_array($pdfCfg) ? (string) ($pdfCfg['chrome_bin'] ?? '') : '';
    if ($override !== '' && is_executable($override)) {
        $chrome = $override;
    } else {
        $candidates = array_merge(
            ['/usr/bin/google-chrome', '/usr/bin/google-chrome-stable', '/opt/google/chrome/chrome',
             '/usr/bin/chromium-browser', '/usr/bin/chromium', '/snap/bin/chromium'],
            array_filter(array_map('trim', explode("\n", (string) shell_exec('which google-chrome google-chrome-stable chromium-browser chromium 2>/dev/null'))))
        );
        foreach (array_values(array_unique($candidates)) as $bin) {
            if ($bin !== '' && file_exists($bin) && is_executable($bin)) {
                $chrome = $bin;
                break;
            }
        }
    }
    if ($chrome === null) {
        error_log('PDF Generation: no Chrome/Chromium binary found');
        return null;
    }

    // Paper dimensions in inches for Chrome
    $dimensions = [
        'A4'     => ['portrait' => '8.27in',  'landscape' => '11.69in'],
        'Letter' => ['portrait' => '8.5in',   'landscape' => '11in'],
        'Legal'  => ['portrait' => '8.5in',   'landscape' => '14in'],
        'A3'     => ['portrait' => '11.69in', 'landscape' => '16.54in'],
        'A5'     => ['portrait' => '5.83in',  'landscape' => '8.27in'],
    ];
    $heights = [
        'A4'     => ['portrait' => '11.69in', 'landscape' => '8.27in'],
        'Letter' => ['portrait' => '11in',    'landscape' => '8.5in'],
        'Legal'  => ['portrait' => '14in',    'landscape' => '8.5in'],
        'A3'     => ['portrait' => '16.54in', 'landscape' => '11.69in'],
        'A5'     => ['portrait' => '8.27in',  'landscape' => '5.83in'],
    ];

    $width  = $dimensions[$paper][$orient] ?? '8.27in';
    $height = $heights[$paper][$orient]    ?? '11.69in';

    // Write HTML to temp file
    $tmpHtml = tempnam(sys_get_temp_dir(), 'sb_pdf_') . '.html';
    file_put_contents($tmpHtml, $html);

    // Chrome arguments for PDF generation
    $tmpPdf = tempnam(sys_get_temp_dir(), 'sb_pdf_') . '.pdf';

    $args = [
        '--headless',
        '--disable-gpu',
        '--no-sandbox',
        '--disable-dev-shm-usage',
        '--disable-software-rasterizer',
        '--run-all-compositor-stages-before-draw',
        '--print-to-pdf=' . $tmpPdf,
        '--print-to-pdf-no-header',
        '--no-pdf-header-footer',
        '--virtual-time-budget=10000',
        '--window-size=1024,768',
        '--default-paper-size=' . strtolower($paper),
        '--print-to-pdf-paper-width=' . $width,
        '--print-to-pdf-paper-height=' . $height,
        '--margin-top=0',
        '--margin-right=0',
        '--margin-bottom=0',
        '--margin-left=0',
        'file://' . $tmpHtml,
    ];

    $cmd = escapeshellcmd($chrome) . ' ' . implode(' ', array_map('escapeshellarg', $args));
    $cmd .= ' 2>/dev/null';

    exec($cmd, $output, $exitCode);

    // Cleanup temp HTML
    @unlink($tmpHtml);

    // Chrome can exit before the PDF file is fully flushed on disk; wait for it.
    if ($exitCode === 0) {
        for ($i = 0; $i < 50; $i++) {
            if (file_exists($tmpPdf) && filesize($tmpPdf) > 100) {
                break;
            }
            usleep(100000); // 100ms steps, up to 5s
        }
    }

    if ($exitCode !== 0 || !file_exists($tmpPdf) || filesize($tmpPdf) < 100) {
        @unlink($tmpPdf);
        return null;
    }

    $pdfBytes = file_get_contents($tmpPdf);
    @unlink($tmpPdf);

    // Verify it's a valid PDF
    if ($pdfBytes === false || strlen($pdfBytes) < 10 || substr($pdfBytes, 0, 4) !== '%PDF') {
        return null;
    }

    return $pdfBytes;
}

/**
 * Dompdf fallback renderer.
 *
 * @param string $html      Full HTML document
 * @param string $fileName  Output filename (without .pdf)
 * @param string $orient    portrait or landscape
 * @param string $paper     Paper size
 * @param bool   $watermask Reserved for future use
 * @param bool   $returnBytes If true, return raw PDF bytes instead of streaming
 * @return string|null      PDF bytes if $returnBytes, null otherwise
 */
function makePDF_dompdf(string $html, string $fileName, string $orient = 'portrait', string $paper = 'A4', bool $watermask = false, bool $returnBytes = false): ?string
{
    $opts = new Options();
    $opts->set('isRemoteEnabled', true);
    $opts->set('isHtml5ParserEnabled', true);
    $opts->set('isFontSubsettingEnabled', true);
    $opts->set('defaultFont', 'helvetica');
    $opts->set('defaultPaperSize', $paper . ($orient === 'landscape' ? '-landscape' : ''));
    $opts->set('tempDir', sys_get_temp_dir());
    $opts->set('isPhpEnabled', false);
    $opts->set('isJavascriptEnabled', false);

    // Margins
    $mm2pt = 2.835;
    $docSettings = documentSettings();
    $opts->set('margin_top',    (float) ($docSettings['margin_top_mm']    ?? 15) * $mm2pt);
    $opts->set('margin_right',  (float) ($docSettings['margin_right_mm']  ?? 15) * $mm2pt);
    $opts->set('margin_bottom', (float) ($docSettings['margin_bottom_mm'] ?? 15) * $mm2pt);
    $opts->set('margin_left',   (float) ($docSettings['margin_left_mm']   ?? 15) * $mm2pt);

    $dompdf = new Dompdf($opts);

    // Embed images as data URIs for Dompdf
    $html = pdfEmbedImages($html);

    $dompdf->loadHtml($html);
    $dompdf->render();

    // Watermark
    pdfAddWatermark($dompdf, $docSettings);

    // Page numbers
    pdfAddPageNumbers($dompdf, $docSettings);

    $raw = $dompdf->output();

    if ($returnBytes) {
        return $raw;
    }

    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $fileName . '.pdf"');
    header('Content-Length: ' . strlen($raw));
    header('Cache-Control: private, must-revalidate');
    echo $raw;
    exit;
}

/**
 * Main PDF generation function. Chrome first, Dompdf fallback.
 *
 * @param string $html         Full HTML document
 * @param string $fileName     Output filename (without .pdf)
 * @param string $orientation  portrait or landscape
 * @param string $paperSize    A4, Letter, Legal, etc.
 * @param bool   $watermask    Reserved for future use
 * @param bool   $returnBytes  If true, return raw PDF bytes instead of streaming
 * @return string|null         PDF bytes if $returnBytes, null otherwise
 */
function makePDF(string $html, string $fileName, string $orientation = 'portrait', string $paperSize = 'A4', bool $watermask = false, bool $returnBytes = false): ?string
{
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('max_execution_time', '300');
    ini_set('memory_limit', '512M');
    set_time_limit(300);

    try {
        [$paper, $orient] = pdfNormalizePaperSize($paperSize, $orientation);
        $html = (string) $html;

        // Try Chrome first
        $pdfBytes = pdfRenderWithChrome($html, $paper, $orient, (bool) $watermask);

        if (!is_string($pdfBytes) || $pdfBytes === '') {
            error_log('PDF Generation: Chrome print-to-PDF failed, falling back to Dompdf');
            $pdfBytes = makePDF_dompdf($html, $fileName, $orient, $paper, $watermask, true);
        }

        if ($pdfBytes === null || $pdfBytes === '') {
            error_log('PDF Generation: Both engines failed');
            return null;
        }

        if ($returnBytes) {
            return $pdfBytes;
        }

        // Stream to browser
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $fileName . '.pdf"');
        header('Content-Length: ' . strlen($pdfBytes));
        header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        echo $pdfBytes;
        exit;
    } catch (\Throwable $e) {
        error_log('PDF Generation Error: ' . $e->getMessage());
        // Final fallback: try Dompdf directly
        try {
            [$paper, $orient] = pdfNormalizePaperSize($paperSize, $orientation);
            return makePDF_dompdf($html, $fileName, $orient, $paper, $watermask, $returnBytes);
        } catch (\Throwable $e2) {
            error_log('PDF Dompdf fallback also failed: ' . $e2->getMessage());
            return null;
        }
    }
}

// ══════════════════════════════════════════════════════════════════
// Internal helpers (shared by Chrome and Dompdf renderers)
// ══════════════════════════════════════════════════════════════════

/** Embed local/remote images as base64 data URIs (for Dompdf). */
function pdfEmbedImages(string $html): string
{
    return preg_replace_callback(
        '/<img\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']/i',
        function ($m) {
            $src = $m[1];
            if (strpos($src, 'data:') === 0) {
                return $m[0];
            }
            // Resolve relative URL
            if (strpos($src, 'http') !== 0) {
                $base = rtrim((string) config('server_path', ''), '/');
                $src = $base . '/' . ltrim($src, '/');
            }
            $data = pdfFetchImageAsDataUri($src);
            return $data !== null ? str_replace($m[1], $data, $m[0]) : $m[0];
        },
        $html
    );
}

/** Fetch an image and return as data URI. */
function pdfFetchImageAsDataUri(string $url): ?string
{
    // Local file
    $serverPath = rtrim((string) config('server_path', ''), '/');
    $relativePath = parse_url($url, PHP_URL_PATH) ?? $url;
    $absolutePath = dirname(__DIR__) . '/' . ltrim($relativePath, '/');

    if (file_exists($absolutePath)) {
        $mime = mime_content_type($absolutePath);
        if ($mime && strpos($mime, 'image/') === 0) {
            return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($absolutePath));
        }
    }

    // Remote URL
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $data = curl_exec($ch);
        $type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        if ($data && $type) {
            return 'data:' . $type . ';base64,' . base64_encode($data);
        }
    }
    return null;
}

/** Add watermark to Dompdf canvas. */
function pdfAddWatermark(Dompdf $dompdf, array $settings): void
{
    $text = trim((string) ($settings['watermark_text'] ?? ''));
    if ($text === '') return;
    $opacity = (float) ($settings['watermark_opacity'] ?? 0.08);
    $canvas = $dompdf->getCanvas();
    $font = $dompdf->getFontMetrics()->getFont('helvetica', 'bold');
    $canvas->page_text(
        $canvas->get_width() / 2 - 120,
        $canvas->get_height() / 2,
        $text, $font, 60,
        [1 - $opacity, 1 - $opacity, 1 - $opacity],
        0, 0, 0, -30
    );
}

/** Add page numbers to Dompdf canvas. */
function pdfAddPageNumbers(Dompdf $dompdf, array $settings): void
{
    if ((int) ($settings['show_page_numbers'] ?? 1) !== 1) return;
    $format = (string) ($settings['page_number_format'] ?? 'Page {PAGE} of {PAGES}');
    $canvas = $dompdf->getCanvas();
    $font = $dompdf->getFontMetrics()->getFont('helvetica', 'normal');
    $total = $canvas->get_page_count();
    for ($i = 1; $i <= $total; $i++) {
        $text = strtr($format, ['{PAGE}' => (string) $i, '{PAGES}' => (string) $total]);
        $canvas->page_text($canvas->get_width() / 2 - 30, $canvas->get_height() - 25, $text, $font, 9, [0.42, 0.42, 0.42]);
    }
}

// ══════════════════════════════════════════════════════════════════
// Word (.docx) helpers — companion to makePDF().
// Renders the document-shell page as a real Word document via PhpWord.
// ══════════════════════════════════════════════════════════════════

/**
 * PhpWord's HTML importer parses with DOMDocument::loadXML(), so it needs
 * well-formed XHTML. Convert an HTML fragment to XHTML (closes <br>, <img>,
 * etc., and normalizes HTML entities) without losing inline styles.
 */
function wordHtmlToXhtml(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }
    $dom = new \DOMDocument();
    $prev = libxml_use_internal_errors(true);
    $ok = $dom->loadHTML('<?xml encoding="utf-8" ?><!DOCTYPE html><html><body>' . $html . '</body></html>', LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $body = $dom->getElementsByTagName('body')->item(0);
    if (!$ok || $body === null) {
        return $html;
    }
    $out = '';
    foreach ($body->childNodes as $node) {
        $out .= $dom->saveXML($node);
    }
    return $out;
}

/**
 * Extract the real content block (inside <div class="doc-body">) from a
 * document-shell page so it can be imported into Word. Returns the full
 * fragment as a fallback when the shell markup is not found.
 */
function wordExtractBody(string $html): string
{
    $html    = (string) $html;
    $startTag = '<div class="doc-body">';
    $begin   = strpos($html, $startTag);
    if ($begin === false) {
        $body = preg_replace('#<style[^>]*>.*?</style>#is', '', $html);
        $body = preg_replace('#<div class="doc-watermark"[^>]*>.*?</div>#is', '', $body);
        $body = preg_replace('#<div class="doc-header">.*?</div>#is', '', $body);
        $body = preg_replace('#<div class="doc-footer">.*?</div>#is', '', $body);
        $body = preg_replace('#<div class="doc-type-bar">.*?</div>#is', '', $body);
        return trim($body);
    }

    $start = $begin + strlen($startTag);

    // Content ends at the shell footer or signature block (outside .doc-body).
    $ends = [];
    foreach (['<div class="doc-footer">', '<div class="doc-signature">'] as $marker) {
        $pos = strpos($html, $marker, $start);
        if ($pos !== false) {
            $ends[] = $pos;
        }
    }
    $end  = $ends ? min($ends) : strlen($html);
    $body = substr($html, $start, $end - $start);
    /* Drop the closing </div> of .doc-body (plus trailing whitespace). */
    $body = preg_replace('#\s*</div>\s*$#is', '', trim($body));

    /* Signature block content sits outside .doc-body — re-attach it. */
    if (preg_match('#<div class="doc-signature">(.*?)</div>#is', $html, $m)) {
        $body = trim($body) . "\n" . trim($m[1]);
    }

    return trim($body);
}

/**
 * Read the doc-type label or number from the shell meta bar. $which is
 * 'label' or 'number'; anything else also returns the label.
 */
function wordExtractMeta(string $html, string $which = 'label'): string
{
    $class = $which === 'number' ? 'doc-type-number' : 'doc-type-label';
    if (preg_match('#<span class="' . preg_quote($class, '#') . '">(.*?)</span>#is', (string) $html, $m)) {
        return trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }
    return '';
}

/**
 * Convert a document-shell page (or any HTML fragment) into a real .docx and
 * stream it as an attachment (mirrors makePDF()). When $returnBytes is true,
 * returns the docx bytes instead of streaming.
 */
function makeWord(string $html, string $fileName, string $docTitle = '', string $docSubtitle = '', bool $returnBytes = false): ?string
{
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('max_execution_time', '300');
    ini_set('memory_limit', '512M');
    set_time_limit(300);

    try {
        // Html importer decodes entities, so the Word writer must escape text
        // when serializing (Settings::isOutputEscapingEnabled() defaults to false).
        Settings::setOutputEscapingEnabled(true);

        $bodyHtml = wordHtmlToXhtml(wordExtractBody($html));
        if ($docTitle === '') {
            $docTitle = wordExtractMeta($html, 'label');
        }
        if ($docSubtitle === '') {
            $docSubtitle = wordExtractMeta($html, 'number');
        }
        $docTitle    = html_entity_decode(trim((string) $docTitle), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $docSubtitle = html_entity_decode(trim((string) $docSubtitle), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if ($docTitle === '') {
            $docTitle = 'Document';
        }

        [$phpWord, $wmFile] = DocumentWord::build($bodyHtml, $docTitle, $docSubtitle);

        $temp = tempnam(sys_get_temp_dir(), 'docx_');
        try {
            IOFactory::createWriter($phpWord, 'Word2007')->save($temp);
            $bytes = (string) file_get_contents($temp);
        } finally {
            @unlink($temp);
            if ($wmFile !== null) {
                @unlink($wmFile);
            }
        }

        if ($returnBytes) {
            return $bytes;
        }

        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . $fileName . '.docx"');
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        echo $bytes;
        exit;
    } catch (\Throwable $e) {
        error_log('Word Generation Error: ' . $e->getMessage());
        return null;
    }
}
