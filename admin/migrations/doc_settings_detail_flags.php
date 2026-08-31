<?php
/**
 * Settings → PDF/Word Setup: letterhead detail show/hide flags.
 *
 * Adds per-detail toggles (address, phone, email, website, VAT/PAN) to
 * tbl_document_settings so the document header can pick which office-profile
 * lines to render. Default = shown (1). Idempotent.
 *
 *   php admin/migrations/doc_settings_detail_flags.php [--apply]
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require __DIR__ . '/../../config/setup.php';

$db = Database::instance();
$apply = in_array('--apply', $argv, true);

$columns = ['show_address', 'show_phone', 'show_email', 'show_website', 'show_vat'];

foreach ($columns as $col) {
    $found = $db->selectOne(
        'SELECT COUNT(*) AS c FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = \'tbl_document_settings\' AND COLUMN_NAME = ?',
        [$col]
    );
    if ($found && (int) $found['c'] > 0) {
        echo "skip $col (already exists)\n";
        continue;
    }
    echo ($apply ? '[apply] ' : '[dry ] ') . "add $col\n";
    if ($apply) {
        $db->execute("ALTER TABLE `tbl_document_settings` ADD COLUMN `$col` tinyint(1) NOT NULL DEFAULT 1");
    }
}

echo $apply ? "Migration applied.\n" : "Dry-run only — rerun with --apply to write.\n";