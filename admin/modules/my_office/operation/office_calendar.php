<?php
/**
 * SB-Tech — Office calendar personal to-dos (shown on date-click modal).
 *   save_todo     — create/update a personal todo
 *   delete_todo   — delete a todo (creator or Super Admin only)
 *   toggle_todo   — flip the completed flag
 *
 * Included by admin/operation.php (CSRF + permission already verified).
 */
$db = Database::instance();
$me = (int) Auth::id();
$seeAll = Auth::isSuperAdmin();
$action = (string) ($_POST['action'] ?? '');
$back = pageUrl('my_office', 'office_calendar');

try {
    if ($action === 'delete_todo') {
        $id = (int) ($_POST['id'] ?? 0);
        $todo = $db->selectOne('SELECT * FROM `tbl_office_todos` WHERE `id` = ?', [$id]);
        if (!$todo) {
            setFlash('error', 'Todo not found.');
        } elseif (!$seeAll && (int) $todo['added_by'] !== $me) {
            setFlash('error', 'You can only delete your own to-dos.');
        } else {
            $db->delete('tbl_office_todos', '`id` = ?', [$id]);
            setFlash('success', 'Todo deleted.');
        }
        redirect($back);
    }

    if ($action === 'toggle_todo') {
        $id = (int) ($_POST['id'] ?? 0);
        $todo = $db->selectOne('SELECT * FROM `tbl_office_todos` WHERE `id` = ?', [$id]);
        if (!$todo) {
            setFlash('error', 'Todo not found.');
        } elseif (!$seeAll && (int) $todo['added_by'] !== $me) {
            setFlash('error', 'You can only toggle your own to-dos.');
        } else {
            $db->update('tbl_office_todos', [
                'completed' => $todo['completed'] ? 0 : 1,
                'updated_by' => $me,
            ], '`id` = ?', [$id]);
        }
        redirect($back);
    }

    $id = (int) ($_POST['id'] ?? 0);
    $title = trim((string) ($_POST['title'] ?? ''));
    $todoDate = trim((string) ($_POST['todo_date'] ?? ''));
    $todoTime = trim((string) ($_POST['todo_time'] ?? ''));
    $remarks = trim((string) ($_POST['remarks'] ?? ''));

    if ($title === '' || $todoDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $todoDate)) {
        setFlash('error', 'Title and a valid date are required.');
        redirect($back);
    }
    if ($todoTime !== '' && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $todoTime)) {
        $todoTime = '';
    }

    $data = [
        'title'      => $title,
        'todo_date'  => $todoDate,
        'todo_time'  => $todoTime !== '' ? $todoTime : null,
        'remarks'    => $remarks !== '' ? $remarks : null,
        'updated_by' => $me,
    ];

    if ($id > 0) {
        $todo = $db->selectOne('SELECT * FROM `tbl_office_todos` WHERE `id` = ?', [$id]);
        if (!$todo) {
            setFlash('error', 'Todo not found.');
            redirect($back);
        }
        if (!$seeAll && (int) $todo['added_by'] !== $me) {
            setFlash('error', 'You can only edit your own to-dos.');
            redirect($back);
        }
        $db->update('tbl_office_todos', $data, '`id` = ?', [$id]);
        setFlash('success', 'Todo updated.');
    } else {
        $db->insert('tbl_office_todos', array_merge($data, ['added_by' => $me]));
        setFlash('success', 'Todo added.');
    }
    redirect($back);
} catch (Throwable $e) {
    setFlash('error', 'Todo operation failed: ' . $e->getMessage());
    redirect($back);
}