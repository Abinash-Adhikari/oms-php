<?php
/**
 * SB-Tech — Real Word (.docx) export for the document shell sample.
 *
 * Produces a genuine OOXML package (via PhpOffice\PhpWord) from the same
 * tbl_document_settings + office profile used by documentShellStart()/
 * documentShellEnd(), so the downloaded file opens as a rendered document in
 * Microsoft Word and LibreOffice alike. (Serving HTML as a .doc works in Word
 * but LibreOffice/other readers show the raw XML source instead.)
 *
 * Usage:
 *   $bytes = DocumentWord::sampleBytes();
 *   header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
 *   header('Content-Disposition: attachment; filename="document-setup-sample.docx"');
 *   echo $bytes;
 */

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\SimpleType\Jc;

class DocumentWord
{
    /** Paper sizes in millimetres [width, height]. */
    private const PAPER_MM = [
        'A4'     => [210, 297],
        'Letter' => [216, 279],
        'Legal'  => [216, 356],
    ];

    /** font_family setting → PhpWord font name. */
    private const FONT_MAP = [
        'helvetica'  => 'Helvetica',
        'times'      => 'Times New Roman',
        'courier'    => 'Courier New',
        'dejavusans' => 'DejaVu Sans',
    ];

    /** letterhead_style → [logo side, detail-block alignment]. */
    private const LETTERHEAD_MAP = [
        'logo_left_details_right'   => ['left',  'right'],
        'logo_left_details_left'    => ['left',  'left'],
        'details_right_logo_right'  => ['right', 'left'],
        'details_left_logo_right'   => ['right', 'right'],
        'centered'                  => ['top',   'center'],
        'logo_left_details_center'  => ['left',  'center'],
        'details_center_logo_right' => ['right', 'center'],
        'logo_top_details_bottom'   => ['top',   'center'],
    ];

    /**
     * Build the sample quotation as .docx and return the raw bytes.
     */
    public static function sampleBytes(): string
    {
        [$phpWord, $wmFile] = self::buildSample();
        return self::writeBytes($phpWord, $wmFile);
    }

    /**
     * Build a Word document from an arbitrary document-shell body HTML,
     * reusing the standard shell: letterhead, watermark, doc-type bar,
     * footer and page geometry. Used by makeWord() for the on-demand Word
     * export of any document page.
     *
     * @return array [PhpWord, temp watermark file|null]
     */
    public static function build(string $bodyHtml, string $docTitle = 'Document', string $docSubtitle = ''): array
    {
        [$phpWord, $section, $wmFile, $baseSize, $s] = self::createShell();
        if (($s['header_mode'] ?? 'office_logo') !== 'none') {
            self::addLetterhead($section, $s, documentOfficeDetails(), $baseSize);
        }
        self::addDocMeta($section, $docTitle, $docSubtitle, $baseSize);
        Html::addHtml($section, (string) $bodyHtml);
        self::addFooter($section, $s, $baseSize);
        return [$phpWord, $wmFile];
    }

    /**
     * Shared shell scaffolding: default font, section geometry, watermark.
     * Returns [PhpWord, $section, $wmFile, $baseSize, $settings].
     */
    private static function createShell(): array
    {
        $s = documentSettings();

        $phpWord = new PhpWord();
        $phpWord->getDocInfo()->setTitle('Document');
        $phpWord->setDefaultFontName(self::FONT_MAP[$s['font_family']] ?? 'Helvetica');
        $baseSize = max(8, min(20, (int) $s['font_size_pt']));
        $phpWord->setDefaultFontSize($baseSize);

        $section = $phpWord->addSection(self::sectionOptions($s));

        $wmFile = self::renderWatermarkFile($s);
        if ($wmFile !== null) {
            $section->addHeader()->addWatermark($wmFile);
        }

        return [$phpWord, $section, $wmFile, $baseSize, $s];
    }

    /**
     * Build the PhpWord document mirroring the live-preview shell:
     * letterhead, header divider, doc-type bar, sample body, footer and
     * (image) watermark. Returns [PhpWord, temp watermark file|null].
     */
    private static function buildSample(): array
    {
        [$phpWord, $section, $wmFile, $baseSize, $s] = self::createShell();
        if (($s['header_mode'] ?? 'office_logo') !== 'none') {
            self::addLetterhead($section, $s, documentOfficeDetails(), $baseSize);
        }
        self::addDocMeta($section, 'Quotation', 'QTN-2026-0001', $baseSize);
        self::addSampleBody($section, $baseSize);
        self::addFooter($section, $s, $baseSize);
        return [$phpWord, $wmFile];
    }

    private static function writeBytes(PhpWord $phpWord, ?string $wmFile): string
    {
        $temp = tempnam(sys_get_temp_dir(), 'docx_');
        try {
            IOFactory::createWriter($phpWord, 'Word2007')->save($temp);
            return (string) file_get_contents($temp);
        } finally {
            @unlink($temp);
            if ($wmFile !== null) {
                @unlink($wmFile);
            }
        }
    }

    // ── Page geometry ───────────────────────────────────────────

    private static function sectionOptions(array $s): array
    {
        $paper = self::PAPER_MM[$s['paper_size']] ?? self::PAPER_MM['A4'];
        [$wMm, $hMm] = $paper;
        $landscape = ($s['orientation'] ?? 'Portrait') === 'Landscape';

        $options = [
            // Orientation first: Section::setOrientation() swaps W/H itself,
            // the explicit pageSizeW/H below then wins deterministically.
            'orientation'     => $landscape ? 'landscape' : 'portrait',
            'marginTop'       => (int) round(Converter::cmToTwip(((float) ($s['margin_top_mm'] ?? 15)) / 10)),
            'marginRight'     => (int) round(Converter::cmToTwip(((float) ($s['margin_right_mm'] ?? 15)) / 10)),
            'marginBottom'    => (int) round(Converter::cmToTwip(((float) ($s['margin_bottom_mm'] ?? 15)) / 10)),
            'marginLeft'      => (int) round(Converter::cmToTwip(((float) ($s['margin_left_mm'] ?? 15)) / 10)),
            'pageSizeW'       => (int) round(Converter::cmToTwip(($landscape ? $hMm : $wMm) / 10)),
            'pageSizeH'       => (int) round(Converter::cmToTwip(($landscape ? $wMm : $hMm) / 10)),
        ];
        return $options;
    }

    /** Usable page width in twips (page width minus left/right margins). */
    private static function usableWidthTwips(array $s): int
    {
        $paper = self::PAPER_MM[$s['paper_size']] ?? self::PAPER_MM['A4'];
        $landscape = ($s['orientation'] ?? 'Portrait') === 'Landscape';
        $wMm = $landscape ? $paper[1] : $paper[0];

        return (int) round(
            Converter::cmToTwip($wMm / 10)
            - Converter::cmToTwip(((float) ($s['margin_left_mm'] ?? 15)) / 10)
            - Converter::cmToTwip(((float) ($s['margin_right_mm'] ?? 15)) / 10)
        );
    }

    // ── Letterhead ───────────────────────────────────────────────

    private static function addLetterhead($section, array $s, array $profile, int $baseSize): void
    {
        $usable   = self::usableWidthTwips($s);
        $logoPath = self::logoFile($s);
        $orgName  = documentHeaderTitle();
        $subtitle = trim((string) ($s['header_subtitle'] ?? ''));

        [$side, $align] = self::LETTERHEAD_MAP[(string) ($s['letterhead_style'] ?? 'logo_left_details_right')]
            ?? ['left', 'right'];
        $jc = ['left' => Jc::LEFT, 'center' => Jc::CENTER, 'right' => Jc::RIGHT][$align] ?? Jc::RIGHT;

        $hasDivider = (int) ($s['show_header_line'] ?? 1) === 1;
        $tableStyle = ['cellMarginTop' => 40, 'cellMarginBottom' => 20, 'cellMarginLeft' => 0, 'cellMarginRight' => 0];
        if ($hasDivider) {
            $tableStyle['borderBottomSize']  = 8;
            $tableStyle['borderBottomColor'] = '1F2937';
        }
        $table = $section->addTable($tableStyle);

        $logoCellFn = function ($cell) use ($logoPath) {
            if ($logoPath !== null) {
                // Explicit width+height (capped at the 65px letterhead logo) so
                // PhpWord doesn't emit a zero-width image when only one side is set.
                $style = ['spacing' => 10];
                $image = @getimagesize($logoPath);
                if (is_array($image) && ($image[0] ?? 0) > 0 && ($image[1] ?? 0) > 0) {
                    $scale = $image[1] > 65 ? 65 / $image[1] : 1;
                    $style['width']  = (int) round($image[0] * $scale);
                    $style['height'] = (int) round($image[1] * $scale);
                }
                $cell->addImage($logoPath, $style);
            }
        };

        // Blurb block: org name, slogan then the (optionally filtered) details.
        $mainFiller = function ($cell, string $jcAlign) use ($orgName, $profile, $s, $subtitle, $baseSize) {
            $cell->addText($orgName, [
                'bold' => true,
                'size' => (int) round($baseSize * 1.3),
                'color' => '111827',
            ], ['alignment' => $jcAlign, 'spacing' => ['after' => 40]]);

            if (!empty($profile['slogan'])) {
                $cell->addText((string) $profile['slogan'], [
                    'italic' => true,
                    'size'   => max(8, $baseSize - 2),
                    'color'  => '6B7280',
                ], ['alignment' => $jcAlign, 'spacing' => ['after' => 40]]);
            }

            $lines = self::detailsLines($s, $profile, $subtitle);
            if ($lines) {
                $run = $cell->addTextRun(['alignment' => $jcAlign]);
                $detailFont = [
                    'size'  => max(8, $baseSize - 2),
                    'color' => '374151',
                ];
                $run->addText($lines[0], $detailFont);
                foreach (array_slice($lines, 1) as $line) {
                    $run->addTextBreak(1, null, null);
                    $run->addText($line, $detailFont);
                }
            }
        };

        $logoW = (int) round($usable * 0.20);
        $mainW = $usable - $logoW;

        if ($side === 'top') {
            $row = $table->addRow();
            $logoCellFn($table->addCell($usable, ['valign' => 'top']));
            $row = $table->addRow();
            $mainFiller($table->addCell($usable, ['valign' => 'top']), Jc::CENTER);
        } else {
            $row = $table->addRow();
            if ($side === 'left') {
                $logoCellFn($table->addCell($logoW, ['valign' => 'top']));
                $mainFiller($table->addCell($mainW, ['valign' => 'top']), $jc);
            } else {
                $mainFiller($table->addCell($mainW, ['valign' => 'top']), $jc);
                $logoCellFn($table->addCell($logoW, ['valign' => 'top']));
            }
        }

        // A little breathing room before the doc-type bar.
        $section->addTextRun(['spacing' => ['before' => 40, 'after' => 120]])->addText('');
    }

    private static function detailsLines(array $s, array $profile, string $subtitle): array
    {
        $lines = [];
        if ((int) ($s['show_address'] ?? 1) === 1) {
            $addr = trim(($profile['address1'] ?? '') . ' ' . ($profile['address2'] ?? ''));
            if ($addr !== '') {
                $lines[] = $addr;
            }
        }
        if ((int) ($s['show_phone'] ?? 1) === 1 && !empty($profile['phone1'])) {
            $line = 'Phone: ' . $profile['phone1'];
            if (!empty($profile['phone2'])) {
                $line .= ', ' . $profile['phone2'];
            }
            $lines[] = $line;
        }
        if ((int) ($s['show_email'] ?? 1) === 1 && !empty($profile['email'])) {
            $lines[] = 'Email: ' . $profile['email'];
        }
        if ((int) ($s['show_website'] ?? 1) === 1 && !empty($profile['website'])) {
            $lines[] = 'Website: ' . $profile['website'];
        }
        if ((int) ($s['show_vat'] ?? 1) === 1 && !empty($profile['vat_no'])) {
            $lines[] = 'VAT/PAN: ' . $profile['vat_no'];
        }
        if ($subtitle !== '') {
            $lines[] = $subtitle;
        }
        return $lines;
    }

    /** Resolve the header logo to a file on disk, or null. */
    private static function logoFile(array $s): ?string
    {
        $rel = null;
        if (($s['header_mode'] ?? 'office_logo') === 'custom_logo') {
            $rel = $s['header_logo_location'] ?? null;
        } elseif (($s['header_mode'] ?? 'office_logo') === 'office_logo') {
            // documentOfficeDetails() does not select the logo column, so query it
            // directly (mirrors documentHeaderLogoUrl()).
            try {
                $profile = Database::instance()->selectOne(
                    'SELECT `logo` FROM `tbl_office_profiles` WHERE `id` = 1'
                );
                $rel = $profile['logo'] ?? null;
            } catch (Throwable $e) {
                $rel = null;
            }
        }
        if (empty($rel)) {
            return null;
        }
        $path = dirname(__DIR__) . '/user_uploads/' . ltrim((string) $rel, '/');
        return is_file($path) ? $path : null;
    }

    // ── Meta bar (doc type + number) ─────────────────────────────

    private static function addDocMeta($section, string $title, string $number, int $baseSize): void
    {
        $usable = self::usableWidthTwips(documentSettings());
        $table  = $section->addTable();
        $row    = $table->addRow();
        $table->addCell((int) round($usable * 0.6))
            ->addText($title, ['bold' => true, 'size' => $baseSize + 2, 'color' => '1F2937'],
                ['spacing' => ['before' => 120, 'after' => 120]]);
        $table->addCell((int) round($usable * 0.4))
            ->addText($number, ['size' => $baseSize - 1, 'color' => '4B5563'],
                ['alignment' => Jc::RIGHT, 'spacing' => ['before' => 120, 'after' => 120]]);
    }

    // ── Sample body ──────────────────────────────────────────────

    private static function addSampleBody($section, int $baseSize): void
    {
        $labelRun = $section->addTextRun(['spacing' => ['after' => 80]]);
        $labelRun->addText('Client: ', ['bold' => true]);
        $labelRun->addText('Sample Client Pvt. Ltd.');

        $dateRun = $section->addTextRun(['spacing' => ['after' => 160]]);
        $dateRun->addText('Date: ', ['bold' => true]);
        $dateRun->addText(date('Y-m-d'));

        $usable = self::usableWidthTwips(documentSettings());
        $table  = $section->addTable([
            'borderSize'    => 6,
            'borderColor'   => 'D1D5DB',
            'cellMarginTop' => 40,
            'cellMarginRight' => 60,
            'cellMarginLeft'  => 60,
            'cellMarginBottom' => 40,
        ]);
        $headStyle = ['bold' => true, 'size' => max(8, $baseSize - 1), 'color' => '374151'];

        $wDesc = (int) round($usable * 0.66);
        $wAmt  = $usable - $wDesc;

        $head = $table->addRow();
        $table->addCell($wDesc, ['shading' => ['fill' => 'F3F4F6']])->addText('Item', $headStyle);
        $table->addCell($wAmt, ['shading' => ['fill' => 'F3F4F6']])->addText('Amount', $headStyle, ['alignment' => Jc::RIGHT]);

        $rows = [
            ['Web development module', '100,000.00'],
            ['Annual support', '25,000.00'],
        ];
        foreach ($rows as [$item, $amount]) {
            $table->addRow();
            $table->addCell($wDesc)->addText($item, ['size' => max(8, $baseSize - 1)]);
            $table->addCell($wAmt)->addText($amount, ['size' => max(8, $baseSize - 1)], ['alignment' => Jc::RIGHT]);
        }

        $section->addText('Total: NPR 125,000.00', [
            'bold' => true,
            'size' => $baseSize + 1,
        ], ['alignment' => Jc::RIGHT, 'spacing' => ['before' => 80, 'after' => 160]]);

        $sig = trim((string) (documentSettings()['signature_block'] ?? ''));
        if ($sig !== '') {
            foreach (preg_split('/\R/', $sig) as $line) {
                if ($line !== '') {
                    $section->addText($line, ['size' => max(8, $baseSize - 2), 'color' => '374151'],
                        ['spacing' => ['after' => 60]]);
                }
            }
        }
    }

    // ── Footer ───────────────────────────────────────────────────

    private static function addFooter($section, array $s, int $baseSize): void
    {
        $footerText = trim((string) ($s['footer_text'] ?? ''));
        $showPages  = (int) ($s['show_page_numbers'] ?? 1) === 1;
        $showStamp  = (int) ($s['show_generated_stamp'] ?? 1) === 1;
        if ($footerText === '' && !$showPages && !$showStamp) {
            return;
        }

        $footer = $section->addFooter();
        $small  = ['size' => 8, 'color' => '9CA3AF'];

        if ($footerText !== '') {
            $footer->addText($footerText, $small, ['spacing' => ['after' => 40]]);
        }
        if ($showPages) {
            $format = (string) ($s['page_number_format'] ?? 'Page {PAGE} of {PAGES}');
            $run = $footer->addTextRun($small);
            foreach (self::pagenumberTokens($format) as [$type, $value]) {
                if ($type === 'PAGE') {
                    $run->addField('PAGE', [], [], null);
                } elseif ($type === 'PAGES') {
                    $run->addField('NUMPAGES', [], [], null);
                } else {
                    $run->addText($value, $small);
                }
            }
        }
        if ($showStamp) {
            $footer->addText('Generated on ' . date('Y-m-d H:i'), $small, ['spacing' => ['before' => 40]]);
        }
    }

    /**
     * Split a "{PAGE} of {PAGES}"-style format into tokens:
     * [['text', 'Page '], ['PAGE', ''], ...].
     */
    private static function pagenumberTokens(string $format): array
    {
        if ($format === '') {
            $format = 'Page {PAGE} of {PAGES}';
        }
        $tokens = [];
        foreach (preg_split('/(\{PAGE\}|\{PAGES\})/', $format, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $part) {
            if ($part === '{PAGE}') {
                $tokens[] = ['PAGE', ''];
            } elseif ($part === '{PAGES}') {
                $tokens[] = ['PAGES', ''];
            } else {
                $tokens[] = ['text', $part];
            }
        }
        return $tokens;
    }

    // ── Watermark ────────────────────────────────────────────────

    /**
     * Render the configured watermark into a transparent PNG (rotated -30°,
     * at the configured opacity) for a genuine Word watermark. Returns the
     * temp file path, or null when disabled / GD or fonts unavailable.
     */
    private static function renderWatermarkFile(array $s): ?string
    {
        $text = trim((string) ($s['watermark_text'] ?? ''));
        if ($text === '' || !function_exists('imagecreatetruecolor') || !function_exists('imagefttext')) {
            return null;
        }
        $font = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
        if (!is_file($font)) {
            return null;
        }

        $opacity = (float) ($s['watermark_opacity'] ?? 0.08);
        $alpha   = (int) round((1 - max(0.01, min(0.5, $opacity))) * 127); // remaining darkness

        $bbox = imageftbbox(42, -30, $font, $text);
        $minX = min($bbox[0], $bbox[2], $bbox[4], $bbox[6]);
        $maxX = max($bbox[0], $bbox[2], $bbox[4], $bbox[6]);
        $minY = min($bbox[1], $bbox[3], $bbox[5], $bbox[7]);
        $maxY = max($bbox[1], $bbox[3], $bbox[5], $bbox[7]);

        $w = $maxX - $minX + 20;
        $h = $maxY - $minY + 20;

        $img = imagecreatetruecolor(max(1, $w), max(1, $h));
        imagesavealpha($img, true);
        $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
        imagefill($img, 0, 0, $transparent);
        $color = imagecolorallocatealpha($img, 31, 41, 55, $alpha);
        imagefttext($img, 42, -30, 10 - $minX, 10 - $minY, $color, $font, $text);

        $file = tempnam(sys_get_temp_dir(), 'wm_') . '.png';
        imagepng($img, $file);
        imagedestroy($img);

        return $file;
    }
}