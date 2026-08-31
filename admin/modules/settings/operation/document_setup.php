<?php
/**
 * SB-Tech — Settings → PDF/Word Setup save handler.
 * Included by admin/operation.php (CSRF + permission already verified).
 */
$db = Database::instance();
$back = pageUrl('settings', 'document_setup');

$data = [
    'paper_size'           => in_array($_POST['paper_size'] ?? 'A4', ['A4', 'Letter', 'Legal'], true) ? $_POST['paper_size'] : 'A4',
    'orientation'          => ($_POST['orientation'] ?? 'Portrait') === 'Landscape' ? 'Landscape' : 'Portrait',
    'margin_top_mm'        => max(0, min(50, (float) ($_POST['margin_top_mm'] ?? 15))),
    'margin_right_mm'      => max(0, min(50, (float) ($_POST['margin_right_mm'] ?? 15))),
    'margin_bottom_mm'     => max(0, min(50, (float) ($_POST['margin_bottom_mm'] ?? 15))),
    'margin_left_mm'       => max(0, min(50, (float) ($_POST['margin_left_mm'] ?? 15))),
    'font_family'          => in_array($_POST['font_family'] ?? 'helvetica', ['helvetica', 'times', 'courier', 'dejavusans'], true) ? $_POST['font_family'] : 'helvetica',
    'font_size_pt'         => max(8, min(20, (int) ($_POST['font_size_pt'] ?? 11))),
    'header_mode'          => in_array($_POST['header_mode'] ?? 'office_logo', ['office_logo', 'custom_logo', 'text_only', 'none'], true) ? $_POST['header_mode'] : 'office_logo',
    'letterhead_style'     => in_array($_POST['letterhead_style'] ?? 'logo_left_details_right', [
        'logo_left_details_right', 'logo_left_details_left', 'details_right_logo_right',
        'details_left_logo_right', 'centered', 'logo_left_details_center',
        'details_center_logo_right', 'logo_top_details_bottom',
    ], true) ? $_POST['letterhead_style'] : 'logo_left_details_right',
    'header_title'         => trim((string) ($_POST['header_title'] ?? '')) ?: null,
    'header_subtitle'      => trim((string) ($_POST['header_subtitle'] ?? '')) ?: null,
    'show_header_line'     => isset($_POST['show_header_line']) ? 1 : 0,
    'show_address'         => isset($_POST['show_address']) ? 1 : 0,
    'show_phone'           => isset($_POST['show_phone']) ? 1 : 0,
    'show_email'           => isset($_POST['show_email']) ? 1 : 0,
    'show_website'         => isset($_POST['show_website']) ? 1 : 0,
    'show_vat'             => isset($_POST['show_vat']) ? 1 : 0,
    'footer_text'          => trim((string) ($_POST['footer_text'] ?? '')) ?: null,
    'show_page_numbers'    => isset($_POST['show_page_numbers']) ? 1 : 0,
    'page_number_format'   => trim((string) ($_POST['page_number_format'] ?? '')) ?: 'Page {PAGE} of {PAGES}',
    'show_generated_stamp' => isset($_POST['show_generated_stamp']) ? 1 : 0,
    'watermark_text'       => trim((string) ($_POST['watermark_text'] ?? '')) ?: null,
    'watermark_opacity'    => max(0.01, min(0.5, (float) ($_POST['watermark_opacity'] ?? 0.08))),
    'default_terms'        => trim((string) ($_POST['default_terms'] ?? '')) ?: null,
    'signature_block'      => trim((string) ($_POST['signature_block'] ?? '')) ?: null,
    'updated_by'           => Auth::id(),
];

// Optional custom logo upload (X-03 whitelist + size cap).
if (($_POST['header_mode'] ?? '') === 'custom_logo'
    && isset($_FILES['header_logo'])
    && ($_FILES['header_logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    try {
        $ext = validateUpload($_FILES['header_logo'], ['jpg', 'jpeg', 'png']);
        $path = storeUpload($_FILES['header_logo'], 'documents', $ext);
        if ($path === null) {
            setFlash('error', 'Logo upload failed. Please try again.');
            redirect($back);
        }
        $data['header_logo_location'] = $path;
    } catch (Throwable $e) {
        setFlash('error', 'Logo upload rejected: ' . $e->getMessage());
        redirect($back);
    }
} elseif (!empty($_POST['remove_logo'])) {
    $data['header_logo_location'] = null;
}

try {
    $exists = $db->selectOne('SELECT `id` FROM `tbl_document_settings` WHERE `id` = 1');
    if ($exists) {
        $db->update('tbl_document_settings', $data, '`id` = ?', [1]);
    } else {
        $data['id'] = 1;
        $db->insert('tbl_document_settings', $data);
    }
    auditLog('settings', 'document_setup_updated', 'document_settings', 1, null, $data);
    setFlash('success', 'PDF/Word setup saved. All document generation pages now use this configuration.');
} catch (Throwable $e) {
    setFlash('error', 'Could not save PDF/Word setup: ' . $e->getMessage());
}
redirect($back);
