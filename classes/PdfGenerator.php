<?php
/**
 * SB-Tech — PDF Generator using Dompdf.
 *
 * Converts HTML document shells (built by documentShellStart/documentShellEnd)
 * into downloadable PDF files. Respects all tbl_document_settings: paper size,
 * margins, orientation, font, watermark, header/footer.
 *
 * Usage:
 *   $pdf = new PdfGenerator();
 *   $pdf->html($htmlString);
 *   $pdf->download('QTN-2026-0001.pdf');
 *   // — or —
 *   $pdf->inline();  // show in browser
 *   // — or —
 *   $pdf->save('/path/to/file.pdf');  // save to disk
 */

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfGenerator
{
    private string $html = '';
    private array  $settings = [];
    private Options $options;

    public function __construct()
    {
        $this->settings = $this->loadSettings();
        $this->options  = $this->buildOptions();
    }

    // ── Public API ──────────────────────────────────────────────

    /** Set raw HTML content. */
    public function html(string $html): self
    {
        $this->html = $html;
        return $this;
    }

    /** Render the PDF and stream as a download. */
    public function download(string $filename = 'document.pdf'): void
    {
        $baseName = pathinfo($filename, PATHINFO_FILENAME);
        $html = $this->prepareHtml($this->html);
        makePDF($html, $baseName, $this->settings['orientation'] ?? 'portrait', $this->settings['paper_size'] ?? 'A4', false, false);
        // makePDF() calls exit on success
    }

    /** Render the PDF and display inline in the browser (preview). */
    public function inline(string $filename = 'document.pdf'): void
    {
        $baseName = pathinfo($filename, PATHINFO_FILENAME);
        $html = $this->prepareHtml($this->html);
        makePDF($html, $baseName, $this->settings['orientation'] ?? 'portrait', $this->settings['paper_size'] ?? 'A4', false, false);
        // makePDF() calls exit on success
    }

    /** Render and save to a file path. Returns the absolute path. */
    public function save(string $path): string
    {
        $baseName = pathinfo($path, PATHINFO_FILENAME);
        $html = $this->prepareHtml($this->html);
        $raw = makePDF($html, $baseName, $this->settings['orientation'] ?? 'portrait', $this->settings['paper_size'] ?? 'A4', false, true);
        if ($raw === null || $raw === '') {
            throw new \RuntimeException('PDF generation failed');
        }
        $fileDir = dirname($path);
        if (!is_dir($fileDir)) {
            mkdir($fileDir, 0755, true);
        }
        file_put_contents($path, $raw);
        return $path;
    }

    /** Render and return raw PDF bytes (for further processing). */
    public function raw(): string
    {
        $baseName = 'document';
        $html = $this->prepareHtml($this->html);
        $raw = makePDF($html, $baseName, $this->settings['orientation'] ?? 'portrait', $this->settings['paper_size'] ?? 'A4', false, true);
        if ($raw === null) {
            throw new \RuntimeException('PDF generation failed');
        }
        return $raw;
    }

    // ── Internals ───────────────────────────────────────────────

    /** Build Dompdf options from tbl_document_settings. */
    private function buildOptions(): Options
    {
        $s = $this->settings;
        $opts = new Options();
        $opts->set('isRemoteEnabled', true);
        $opts->set('isHtml5ParserEnabled', true);
        $opts->set('isFontSubsettingEnabled', true);
        $opts->set('defaultFont', $this->mapFont($s['font_family'] ?? 'helvetica'));

        // Paper size + orientation
        $paper = in_array($s['paper_size'] ?? 'A4', ['A4', 'Letter', 'Legal'], true)
            ? $s['paper_size'] : 'A4';
        if (($s['orientation'] ?? 'Portrait') === 'Landscape') {
            $paper .= '-landscape';
        }
        $opts->set('defaultPaperSize', $paper);

        // Margins (convert mm to Dompdf points: 1mm ≈ 2.835pt)
        $mm2pt = 2.835;
        $opts->set('margin_top',    (float) ($s['margin_top_mm']    ?? 15) * $mm2pt);
        $opts->set('margin_right',  (float) ($s['margin_right_mm']  ?? 15) * $mm2pt);
        $opts->set('margin_bottom', (float) ($s['margin_bottom_mm'] ?? 15) * $mm2pt);
        $opts->set('margin_left',   (float) ($s['margin_left_mm']   ?? 15) * $mm2pt);

        $opts->set('tempDir', sys_get_temp_dir());
        $opts->set('isPhpEnabled', false);
        $opts->set('isJavascriptEnabled', false);

        return $opts;
    }

    /** Convert logo URL to base64 data URI (Dompdf needs embedded images). */
    private function embedImages(string $html): string
    {
        // Match src="..." on img tags
        return preg_replace_callback(
            '/<img\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']/i',
            function ($m) {
                $src = $m[1];
                // Already a data URI
                if (strpos($src, 'data:') === 0) {
                    return $m[0];
                }
                // Relative URL — resolve to absolute file path
                if (strpos($src, 'http') !== 0) {
                    $base = rtrim((string) config('server_path', ''), '/');
                    $src = $base . '/' . ltrim($src, '/');
                }
                // Fetch and convert
                $data = $this->fetchImageAsDataUri($src);
                if ($data !== null) {
                    return str_replace($m[1], $data, $m[0]);
                }
                return $m[0]; // leave unchanged if fetch fails
            },
            $html
        );
    }

    /** Fetch an image URL and return as data URI, or null on failure. */
    private function fetchImageAsDataUri(string $url): ?string
    {
        // Local file
        $serverPath = rtrim((string) config('server_path', ''), '/');
        $relativePath = parse_url($url, PHP_URL_PATH) ?? $url;
        $absolutePath = dirname(__DIR__) . '/' . ltrim($relativePath, '/');

        if (file_exists($absolutePath)) {
            $mime = mime_content_type($absolutePath);
            if ($mime && strpos($mime, 'image/') === 0) {
                $b64 = base64_encode(file_get_contents($absolutePath));
                return 'data:' . $mime . ';base64,' . $b64;
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
                CURLOPT_MAXREDIRS      => 3,
            ]);
            $data = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);
            if ($data && $code >= 200 && $code < 300 && $type) {
                $b64 = base64_encode($data);
                return 'data:' . $type . ';base64,' . $b64;
            }
        }

        return null;
    }

    /** Pre-process HTML for Dompdf compatibility. */
    private function prepareHtml(string $html): string
    {
        // Embed images as data URIs
        $html = $this->embedImages($html);

        // Dompdf doesn't support flex well — convert common flex layouts to tables
        // Keep flex for simple cases, but the doc-header needs special handling
        $html = $this->fixFlexForDompdf($html);

        // Remove @media print rules that Dompdf can't handle
        $html = preg_replace('/@media\s+print\s*\{[^}]*\}/i', '', $html);

        // Convert CSS class-based styles to inline where needed
        // Dompdf handles <style> blocks fine, so we keep them

        return $html;
    }

    /**
     * Fix any remaining flex layouts for Dompdf compatibility.
     * The document shell now uses tables, so this is a safety net.
     */
    private function fixFlexForDompdf(string $html): string
    {
        // The new document shell uses table-based layout, so minimal fixes needed.
        // Just ensure doc-footer uses table layout if it has flex.
        $html = str_replace(
            'display: flex;\n        justify-content: space-between;',
            'display: table;\n        width: 100%;',
            $html
        );
        return $html;
    }

    /** Render the Dompdf instance. */
    private function render(): Dompdf
    {
        $dompdf = new Dompdf($this->options);
        $html = $this->prepareHtml($this->html);
        $dompdf->loadHtml($html);
        $dompdf->render();

        // Add watermark overlay if configured
        $this->addWatermark($dompdf);

        // Add page numbers in footer
        $this->addPageNumbers($dompdf);

        return $dompdf;
    }

    /** Draw watermark text across every page. */
    private function addWatermark(Dompdf $dompdf): void
    {
        $text = trim((string) ($this->settings['watermark_text'] ?? ''));
        if ($text === '') {
            return;
        }
        $opacity = (float) ($this->settings['watermark_opacity'] ?? 0.08);
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('helvetica', 'bold');

        $canvas->page_text(
            $canvas->get_width() / 2 - 120,
            $canvas->get_height() / 2,
            $text,
            $font,
            60,
            [1 - $opacity, 1 - $opacity, 1 - $opacity], // light gray
            0, // angle (0 = horizontal; -30 not natively supported, skip)
            0,
            0,
            -30 // rotation
        );
    }

    /** Stamp page numbers at the bottom of each page. */
    private function addPageNumbers(Dompdf $dompdf): void
    {
        if ((int) ($this->settings['show_page_numbers'] ?? 1) !== 1) {
            return;
        }
        $format = (string) ($this->settings['page_number_format'] ?? 'Page {PAGE} of {PAGES}');
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('helvetica', 'normal');
        $totalPages = $dompdf->getCanvas()->get_page_count();

        for ($i = 1; $i <= $totalPages; $i++) {
            $text = strtr($format, [
                '{PAGE}'  => (string) $i,
                '{PAGES}' => (string) $totalPages,
            ]);
            $canvas->page_text(
                $canvas->get_width() / 2 - 30,
                $canvas->get_height() - 25,
                $text,
                $font,
                9,
                [0.42, 0.42, 0.42] // gray
            );
        }
    }

    /** Map font_family setting to a Dompdf-compatible font name. */
    private function mapFont(string $family): string
    {
        $map = [
            'helvetica'  => 'helvetica',
            'times'      => 'times',
            'courier'    => 'courier',
            'dejavusans' => 'dejavu-sans',
        ];
        return $map[strtolower($family)] ?? 'helvetica';
    }

    /** Load document settings from DB. */
    private function loadSettings(): array
    {
        try {
            $row = Database::instance()->selectOne(
                'SELECT * FROM `tbl_document_settings` WHERE `id` = 1'
            );
        } catch (Throwable $e) {
            $row = null;
        }
        return array_merge(documentSettingsDefaults(), $row ?: []);
    }
}
