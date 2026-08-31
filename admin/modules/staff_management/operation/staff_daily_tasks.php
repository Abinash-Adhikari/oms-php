<?php
/**
 * SB-Tech — Staff Management / Daily Tasks operations.
 *   save   — upsert one entry per staff + date
 *   delete — own entry (or any entry for admins)
 */
$db = Database::instance();
$me = (int) Auth::id();
$seeAll = Auth::isSuperAdmin();
$action = (string) ($_POST['action'] ?? '');
$back = 'show_page.php?module=staff_management&page=staff_daily_tasks';

try {
    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $staffId = (int) ($_POST['staff_id'] ?? 0);
        $date = trim((string) ($_POST['date'] ?? ''));
        $tasks = trim((string) ($_POST['tasks'] ?? ''));

        if (!$seeAll) {
            $staffId = $me; // staff always log for themselves
        }
        if (!$staffId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            setFlash('error', 'Staff and date are required.');
            redirect($back);
        }
        if ($tasks === '') {
            setFlash('error', 'Tasks cannot be empty.');
            redirect($back);
        }
        $staff = $db->selectOne('SELECT `fullname` FROM `tbl_users_login` WHERE `id` = ?', [$staffId]);
        if (!$staff) {
            setFlash('error', 'Staff not found.');
            redirect($back);
        }

        $existing = $db->selectOne(
            'SELECT * FROM `tbl_daily_tasks` WHERE `staff_id` = ? AND `date` = ?',
            [$staffId, $date]
        );
        if ($existing && $id === 0) {
            $id = (int) $existing['id'];
        }
        if ($id) {
            $row = $db->selectOne('SELECT * FROM `tbl_daily_tasks` WHERE `id` = ?', [$id]);
            if (!$row || (!$seeAll && (int) $row['staff_id'] !== $me)) {
                setFlash('error', 'Entry not found or not yours to edit.');
                redirect($back);
            }
            $db->update('tbl_daily_tasks', [
                'staff_id'   => $staffId,
                'fullname'   => $staff['fullname'],
                'date'       => $date,
                'tasks'      => $tasks,
                'updated_by' => $me,
            ], '`id` = ?', [$id]);
            setFlash('success', 'Daily tasks updated.');
        } else {
            $db->insert('tbl_daily_tasks', [
                'staff_id'   => $staffId,
                'fullname'   => $staff['fullname'],
                'date'       => $date,
                'tasks'      => $tasks,
                'added_by'   => $me,
                'updated_by' => $me,
            ]);
            setFlash('success', 'Daily tasks logged.');
        }
        redirect($back . '&date=' . urlencode($date));
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $row = $db->selectOne('SELECT * FROM `tbl_daily_tasks` WHERE `id` = ?', [$id]);
        if (!$row) {
            setFlash('error', 'Entry not found.');
        } elseif (!$seeAll && (int) $row['staff_id'] !== $me) {
            setFlash('error', 'You can only delete your own entries.');
        } else {
            $db->delete('tbl_daily_tasks', '`id` = ?', [$id]);
            setFlash('success', 'Entry deleted.');
        }
        redirect($back);
    }

    setFlash('error', 'Unknown daily task action.');
    redirect($back);
} catch (Throwable $e) {
    setFlash('error', 'Daily task operation failed: ' . $e->getMessage());
    redirect($back);
}
