<?php
/**
 * SB-Tech — Office Setup / Documents operations (US-DOC-01).
 *   save_category / delete_category
 *   save_document / delete_document  (multi-file upload, disk cleanup)
 *   export_documents                (CSV register, access-scoped)
 */
$db = Database::instance();
$me = (int) Auth::id();
$canSeePrivate = Auth::isSuperAdmin() || Auth::hasSpecial('access_private_documents');
$action = (string) ($_POST['action'] ?? '');
$back = 'show_page.php?module=office_setup&page=documents';

try {
    if ($action === 'save_category') {
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            setFlash('error', 'Category title is required.');
            redirect($back);
        }
        if ($db->selectOne('SELECT `id` FROM `tbl_office_document_category` WHERE BINARY `title` = ?', [$title])) {
            setFlash('error', 'This category already exists.');
            redirect($back);
        }
        $db->insert('tbl_office_document_category', ['title' => $title, 'added_by' => $me]);
        setFlash('success', 'Category created.');
        redirect($back);
    }

    if ($action === 'delete_category') {
        $id = (int) ($_POST['id'] ?? 0);
        $db->delete('tbl_office_document_category', '`id` = ?', [$id]);
        setFlash('success', 'Category deleted.');
        redirect($back);
    }

    if ($action === 'save_document') {
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $categoryId = (int) ($_POST['category_id'] ?? 0) ?: null;
        $renewDate = trim((string) ($_POST['renew_date'] ?? ''));
        if ($renewDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $renewDate)) {
            $renewDate = '';
        }
        $accessType = (string) ($_POST['access_type'] ?? 'Public');
        if (!in_array($accessType, ['Public', 'Private'], true)) {
            $accessType = 'Public';
        }
        if ($title === '') {
            setFlash('error', 'Document title is required.');
            redirect($back);
        }
        $categoryTitle = null;
        if ($categoryId) {
            $cat = $db->selectOne('SELECT `title` FROM `tbl_office_document_category` WHERE `id` = ?', [$categoryId]);
            $categoryTitle = $cat['title'] ?? null;
        }

        // Collect uploaded files (array upload).
        $uploaded = [];
        if (!empty($_FILES['doc_files']['name']) && is_array($_FILES['doc_files']['name'])) {
            foreach ($_FILES['doc_files']['name'] as $i => $name) {
                if (trim((string) $name) === '') {
                    continue;
                }
                $file = [
                    'name'     => $name,
                    'type'     => $_FILES['doc_files']['type'][$i] ?? '',
                    'tmp_name' => $_FILES['doc_files']['tmp_name'][$i] ?? '',
                    'error'    => $_FILES['doc_files']['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'size'     => $_FILES['doc_files']['size'][$i] ?? 0,
                ];
                $up = validateUpload($file);
                if (!$up['ok']) {
                    setFlash('error', $name . ': ' . $up['message']);
                    redirect($back);
                }
                $uploaded[] = ['file' => $file, 'ext' => $up['extension']];
            }
        }
        if (!$id && count($uploaded) === 0) {
            setFlash('error', 'Attach at least one file.');
            redirect($back);
        }

        if ($id) {
            $existing = $db->selectOne('SELECT * FROM `tbl_office_documents` WHERE `id` = ?', [$id]);
            if (!$existing) {
                setFlash('error', 'Document not found.');
                redirect($back);
            }
            $db->update('tbl_office_documents', [
                'title'       => $title,
                'category_id' => $categoryId,
                'category'    => $categoryTitle,
                'renew_date'  => $renewDate ?: null,
                'access_type' => $accessType,
                'updated_by'  => $me,
            ], '`id` = ?', [$id]);
            foreach ($uploaded as $u) {
                $loc = storeUpload($u['file'], 'documents', $u['ext']);
                if ($loc) {
                    $db->insert('tbl_office_document_files', [
                        'document_id'    => $id,
                        'file_location'  => $loc,
                        'file_name'      => basename((string) $u['file']['name']),
                        'file_extension' => $u['ext'],
                        'added_by'       => $me,
                    ]);
                }
            }
            setFlash('success', 'Document updated.');
        } else {
            $first = $uploaded[0];
            $docId = $db->insert('tbl_office_documents', [
                'title'       => $title,
                'filename'    => basename((string) $first['file']['name']),
                'type'        => strtoupper($first['ext']),
                'size'        => (int) $first['file']['size'],
                'renew_date'  => $renewDate ?: null,
                'access_type' => $accessType,
                'category_id' => $categoryId,
                'category'    => $categoryTitle,
                'added_by'    => $me,
            ]);
            foreach ($uploaded as $u) {
                $loc = storeUpload($u['file'], 'documents', $u['ext']);
                if ($loc) {
                    $db->insert('tbl_office_document_files', [
                        'document_id'    => $docId,
                        'file_location'  => $loc,
                        'file_name'      => basename((string) $u['file']['name']),
                        'file_extension' => $u['ext'],
                        'added_by'       => $me,
                    ]);
                }
            }
            setFlash('success', 'Document saved with ' . count($uploaded) . ' file(s).');
        }
        redirect($back);
    }

    if ($action === 'delete_document') {
        $id = (int) ($_POST['id'] ?? 0);
        foreach ($db->select('SELECT `file_location` FROM `tbl_office_document_files` WHERE `document_id` = ?', [$id]) as $f) {
            if (!empty($f['file_location'])) {
                $path = dirname(__DIR__, 3) . '/user_uploads/' . $f['file_location'];
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
        $db->delete('tbl_office_document_files', '`document_id` = ?', [$id]);
        $db->delete('tbl_office_documents', '`id` = ?', [$id]);
        setFlash('success', 'Document deleted.');
        redirect($back);
    }

    if ($action === 'export_documents') {
        $where = ['1=1'];
        $params = [];
        if (!$canSeePrivate) {
            $where[] = 'd.access_type = ?';
            $params[] = 'Public';
        }
        $rows = $db->select(
            'SELECT d.*, c.title AS category_title,
                    (SELECT COUNT(*) FROM `tbl_office_document_files` f WHERE f.document_id = d.id) AS file_count
             FROM `tbl_office_documents` d
             LEFT JOIN `tbl_office_document_category` c ON c.id = d.category_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY d.title',
            $params
        );
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="document_register_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Title', 'Category', 'Files', 'Access', 'Renew date', 'Type', 'Size (bytes)']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['title'],
                $r['category_title'] ?? $r['category'] ?? '',
                (int) $r['file_count'],
                $r['access_type'],
                $r['renew_date'] ?? '',
                $r['type'] ?? '',
                (int) ($r['size'] ?? 0),
            ]);
        }
        fclose($out);
        exit;
    }

    setFlash('error', 'Unknown document action.');
    redirect($back);
} catch (Throwable $e) {
    setFlash('error', 'Document operation failed: ' . $e->getMessage());
    redirect($back);
}
