<?php
/**
 * SB-Tech — Website CMS / Setup operations (tbl_cms_setup single record).
 */
$db = Database::instance();
$me = (int) Auth::id();
$action = (string) ($_POST['action'] ?? '');
$back = 'show_page.php?module=webcms&page=webcms_setup';

try {
    if ($action === 'save_setup') {
        $keys = [
            'site_title', 'tagline', 'template', 'primary_color', 'secondary_color',
            'maps_embed', 'contact_email', 'contact_phone',
            'facebook', 'instagram', 'linkedin', 'twitter', 'seo_meta_keywords',
        ];
        $data = ['updated_by' => $me];
        foreach ($keys as $k) {
            $v = trim((string) ($_POST[$k] ?? ''));
            $data[$k] = $v !== '' ? $v : null;
        }
        if ($db->selectOne('SELECT `id` FROM `tbl_cms_setup` WHERE `id` = 1')) {
            $db->update('tbl_cms_setup', $data, '`id` = 1');
        } else {
            $data['id'] = 1;
            $data['added_by'] = $me;
            $db->insert('tbl_cms_setup', $data);
        }
        setFlash('success', 'Website settings saved.');
        redirect($back);
    }
    setFlash('error', 'Unknown setup action.');
    redirect($back);
} catch (Throwable $e) {
    setFlash('error', 'Setup operation failed: ' . $e->getMessage());
    redirect($back);
}
