<?php
/**
 * SB-Tech — departments CRUD (US-SET-02).
 * Included by admin/operation.php (CSRF + permission already verified).
 * Supports bulk add (tabular grid posts ids[]/titles[]/positions[]) plus the
 * old single-field form for external callers.
 */
$db = Database::instance();
$action = $_POST['action'] ?? 'save';
$back = pageUrl('office_setup', 'departments');

if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    $count = (int) ($db->selectOne('SELECT COUNT(*) AS c FROM `tbl_users_login` WHERE `department_id` = ?', [$id])['c'] ?? 0);
    if ($count > 0) {
        setFlash('error', 'Cannot delete: staff are assigned to this department.');
        redirect($back);
    }
    $db->delete('tbl_office_departments', '`id` = ?', [$id]);
    setFlash('success', 'Department deleted.');
    redirect($back);
}

$ids = isset($_POST['ids']) && is_array($_POST['ids']) ? $_POST['ids'] : [];
$titles = isset($_POST['titles']) && is_array($_POST['titles']) ? $_POST['titles'] : [];
$positions = isset($_POST['positions']) && is_array($_POST['positions']) ? $_POST['positions'] : [];

// Legacy single-field POST (id/title/position) → normalise into one row.
if (!$titles) {
    $singleId = (int) ($_POST['id'] ?? 0);
    $singleTitle = trim((string) ($_POST['title'] ?? ''));
    if ($singleTitle === '') {
        setFlash('error', 'Title is required.');
        redirect($back);
    }
    $ids = [$singleId];
    $titles = [$singleTitle];
    $positions = [(int) ($_POST['position'] ?? 0)];
}

try {
    $done = $db->transaction(function ($db) use ($ids, $titles, $positions) {
        $updated = 0;
        $added = 0;
        foreach ($titles as $i => $raw) {
            $title = trim((string) $raw);
            if ($title === '') {
                continue;
            }
            $id = (int) ($ids[$i] ?? 0);
            $position = (int) ($positions[$i] ?? 0);
            if ($id > 0) {
                $db->update('tbl_office_departments', ['title' => $title, 'position' => $position, 'updated_by' => Auth::id()], '`id` = ?', [$id]);
                $updated++;
            } else {
                $db->insert('tbl_office_departments', ['title' => $title, 'position' => $position, 'added_by' => Auth::id()]);
                $added++;
            }
        }
        return [$added, $updated];
    });
} catch (Throwable $e) {
    setFlash('error', 'Could not save: ' . (str_contains($e->getMessage(), 'Duplicate') ? 'A department with this title already exists.' : $e->getMessage()));
    redirect($back);
}

if ($done[0] > 0 && $done[1] > 0) {
    setFlash('success', $done[0] . ' department(s) added, ' . $done[1] . ' updated.');
} elseif ($done[0] > 0) {
    setFlash('success', $done[0] . ' department(s) added.');
} else {
    setFlash('success', $done[1] . ' department(s) updated.');
}
redirect($back);