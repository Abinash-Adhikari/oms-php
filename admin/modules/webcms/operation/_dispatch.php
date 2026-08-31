<?php
/**
 * SB-Tech — Website CMS generic save/delete for content sections.
 * Field handling is driven by cms_config.php (whitelist by construction):
 * text/textarea/longtext/date/number/select/checkbox/department/image/file,
 * plus auto-slug generation with uniqueness for news/careers.
 */
require_once __DIR__ . '/../includes/cms_config.php';

$db = Database::instance();
$me = (int) Auth::id();
$action = (string) ($_POST['action'] ?? '');
$section = (string) ($_POST['section'] ?? '');
$page = (string) ($_GET['page'] ?? '');

if (!in_array($section, cmsSectionKeys(), true)) {
    http_response_code(400);
    die('Unknown CMS section.');
}
$cfg = cmsSections()[$section];
$back = 'show_page.php?module=webcms&page=' . urlencode($page) . '&section=' . urlencode($section);

try {
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $data = [];
        $uploads = []; // ['col_name' => stored path, 'orig' => original name]

        foreach ($cfg['fields'] as $fname => $f) {
            $type = $f['type'];
            if ($type === 'image' || $type === 'file') {
                $input = 'f_' . $fname;
                if (!empty($_FILES[$input]['name'])) {
                    $allowed = $type === 'image' ? ['jpg', 'jpeg', 'png', 'gif', 'webp'] : ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'pptx', 'txt'];
                    $up = validateUpload($_FILES[$input], $allowed);
                    if (!$up['ok']) {
                        setFlash('error', $up['message']);
                        redirect($back);
                    }
                    $loc = storeUpload($_FILES[$input], 'webcms/' . $section, $up['extension']);
                    if ($loc) {
                        $uploads[$f['name']] = ['loc' => $loc, 'orig' => basename((string) $_FILES[$input]['name'])];
                        $uploads[$f['loc']] = ['loc' => $loc, 'orig' => null];
                    }
                }
                continue;
            }
            if ($type === 'checkbox') {
                $data[$fname] = !empty($_POST[$fname]) ? 1 : 0;
                continue;
            }
            if ($type === 'number') {
                $data[$fname] = ($_POST[$fname] ?? '') !== '' ? (int) $_POST[$fname] : 0;
                continue;
            }
            if ($type === 'select') {
                $v = (string) ($_POST[$fname] ?? '');
                $data[$fname] = in_array($v, $f['options'], true) ? $v : ($f['options'][0] ?? null);
                continue;
            }
            if ($type === 'department') {
                $data[$fname] = (int) ($_POST[$fname] ?? 0) ?: null;
                continue;
            }
            // text / textarea / longtext / date
            $v = trim((string) ($_POST[$fname] ?? ''));
            $data[$fname] = $v !== '' ? $v : null;
        }

        // Auto-slug for news/careers with uniqueness (uniq_news_slug, uniq_career_slug).
        if (!empty($cfg['slug_from'])) {
            $slug = trim((string) ($data['slug'] ?? ''));
            if ($slug === '') {
                $slug = slugify((string) ($data[$cfg['slug_from']] ?? ''));
            }
            $base = $slug;
            $i = 1;
            while ($db->selectOne(
                'SELECT `id` FROM `' . $cfg['table'] . '` WHERE `slug` = ? AND `id` != ?',
                [$slug, $id]
            )) {
                $slug = $base . '-' . (++$i);
            }
            $data['slug'] = $slug;
        }

        // Apply uploads to the data set (replace old file on disk when changed).
        $existing = $id ? $db->selectOne('SELECT * FROM `' . $cfg['table'] . '` WHERE `id` = ?', [$id]) : null;
        foreach ($uploads as $col => $u) {
            $data[$col] = $u['loc'];
            if ($existing && !empty($existing[$col])) {
                $path = dirname(__DIR__, 3) . '/user_uploads/' . $existing[$col];
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }

        $data['updated_by'] = $me;
        if ($id) {
            $db->update($cfg['table'], $data, '`id` = ?', [$id]);
            setFlash('success', ucfirst($cfg['label']) . ' updated.');
        } else {
            $data['added_by'] = $me;
            $db->insert($cfg['table'], $data);
            setFlash('success', ucfirst($cfg['label']) . ' added.');
        }
        redirect($back);
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $row = $db->selectOne('SELECT * FROM `' . $cfg['table'] . '` WHERE `id` = ?', [$id]);
        if (!$row) {
            setFlash('error', 'Record not found.');
            redirect($back);
        }
        foreach ($cfg['fields'] as $fname => $f) {
            if (($f['type'] === 'image' || $f['type'] === 'file') && !empty($row[$f['loc']])) {
                $path = dirname(__DIR__, 3) . '/user_uploads/' . $row[$f['loc']];
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
        $db->delete($cfg['table'], '`id` = ?', [$id]);
        setFlash('success', ucfirst($cfg['label']) . ' deleted.');
        redirect($back);
    }

    setFlash('error', 'Unknown CMS action.');
    redirect($back);
} catch (Throwable $e) {
    setFlash('error', 'CMS operation failed: ' . $e->getMessage());
    redirect($back);
}
