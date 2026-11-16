<?php
/**
 * SB-Tech — Website CMS / Setup operations (tbl_cms_setup single record).
 * Also stores the logo/favicon uploads (user_uploads/webcms/setup/); a
 * replaced or removed image file is deleted only after the row is saved.
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

        // Logo / favicon: validate extension AND that the file is a real image.
        $current = $db->selectOne('SELECT `logo`, `favicon` FROM `tbl_cms_setup` WHERE `id` = 1');
        $imageTypes = [
            'logo'    => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'favicon' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'ico'],
        ];
        $obsolete = [];
        foreach ($imageTypes as $col => $allowed) {
            $old = (string) ($current[$col] ?? '');
            if (!empty($_FILES[$col]['name'])) {
                $up = validateUpload($_FILES[$col], $allowed);
                if (!$up['ok']) {
                    setFlash('error', ucfirst($col) . ': ' . $up['message']);
                    redirect($back);
                }
                if (@getimagesize((string) $_FILES[$col]['tmp_name']) === false) {
                    setFlash('error', ucfirst($col) . ': the file is not a valid image.');
                    redirect($back);
                }
                $loc = storeUpload($_FILES[$col], 'webcms/setup', $up['extension']);
                if ($loc === null) {
                    setFlash('error', ucfirst($col) . ': could not store the upload.');
                    redirect($back);
                }
                $data[$col] = $loc;
                $obsolete[] = $old;
            } elseif (!empty($_POST['remove_' . $col])) {
                $data[$col] = null;
                $obsolete[] = $old;
            }
        }

        if ($current !== null) {
            $db->update('tbl_cms_setup', $data, '`id` = 1');
        } else {
            $data['id'] = 1;
            $data['added_by'] = $me;
            $db->insert('tbl_cms_setup', $data);
        }
        foreach ($obsolete as $oldLoc) {
            $path = dirname(__DIR__, 4) . '/user_uploads/' . $oldLoc;
            if ($oldLoc !== '' && strpos($oldLoc, '..') === false && is_file($path)) {
                @unlink($path);
            }
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
