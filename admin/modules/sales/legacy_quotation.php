<?php
/**
 * SB-Tech — redirect for the retired legacy quotation screen.
 *
 * Old ?module=leads&page=quotations links land here (via $routeCanonical) and
 * are forwarded to Sales Documents. Legacy quotation ids are translated to
 * their migrated tbl_documents id (tbl_quotations.migrated_document_id).
 * Inner page: shares the sales/documents permission ($innerPageGrants).
 */
$db = Database::instance();
$target = './show_page.php?module=sales&page=documents&type=quotation';

$legacyId = (int) ($_GET['id'] ?? ($_GET['edit'] ?? 0));
if ($legacyId) {
    $row = $db->selectOne('SELECT `migrated_document_id` FROM `tbl_quotations` WHERE `id` = ?', [$legacyId]);
    if ($row && $row['migrated_document_id']) {
        $target .= (isset($_GET['edit']) ? '&edit=' : '&id=') . (int) $row['migrated_document_id'];
        foreach (['print', 'pdf', 'preview', 'word'] as $flag) {
            if (!empty($_GET[$flag])) {
                $target .= '&' . $flag . '=1';
            }
        }
    } else {
        setFlash('warning', 'That quotation no longer exists.');
    }
} elseif (isset($_GET['add'])) {
    $target .= '&add=1';
    foreach (['client_id', 'lead_id'] as $key) {
        if ((int) ($_GET[$key] ?? 0)) {
            $target .= '&' . $key . '=' . (int) $_GET[$key];
        }
    }
}

redirect($target);
exit;
