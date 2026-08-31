<?php
/**
 * SB-Tech — My Office / Tasks operations (US-TSK-01/02).
 *   save_task     — create or update (update only within the 7-day window
 *                   and by author/admin; assignee set is replaced, new
 *                   assignees notified)
 *   delete_task   — within 7-day window, author/admin only
 *   post_update   — assignee/author posts progress (status + text + file),
 *                   notifies the task author (AC-TSK-02.2)
 */
$db = Database::instance();
$me = (int) Auth::id();
$seeAll = canSeeAllTasks();
$action = (string) ($_POST['action'] ?? '');
$back = 'show_page.php?module=staff_management&page=hr_care&tab=tasks';

/** Parse a datetime-local input into a DB datetime (or null). */
function parseDeadlineInput(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    $ts = strtotime($raw);
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
}

/** Remove stored files for a task (disk + rows). */
function deleteTaskFiles(Database $db, int $taskId): void
{
    foreach ($db->select('SELECT `file_location` FROM `tbl_office_task_files` WHERE `ref_id` = ?', [$taskId]) as $f) {
        if (!empty($f['file_location'])) {
            $path = dirname(__DIR__, 3) . '/user_uploads/' . $f['file_location'];
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
    $db->delete('tbl_office_task_files', '`ref_id` = ?', [$taskId]);
}

try {
    if ($action === 'save_task') {
        $taskId = (int) ($_POST['task_id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $deadline = parseDeadlineInput((string) ($_POST['deadline'] ?? ''));
        $assignees = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['assignees'] ?? [])))));

        if ($title === '') {
            setFlash('error', 'Task title is required.');
            redirect($back);
        }
        if (count($assignees) === 0) {
            setFlash('error', 'Assign at least one staff member.');
            redirect($back);
        }

        $validStaff = [];
        foreach ($db->select("SELECT `id` FROM `tbl_users_login` WHERE `status` = 'Active'") as $s) {
            $validStaff[(int) $s['id']] = true;
        }
        $assignees = array_values(array_filter($assignees, fn ($id) => isset($validStaff[$id])));
        if (count($assignees) === 0) {
            setFlash('error', 'No valid assignees selected.');
            redirect($back);
        }

        $deptRow = $db->selectOne('SELECT `department_id` FROM `tbl_users_login` WHERE `id` = ?', [$assignees[0]]);

        if ($taskId) {
            $existing = $db->selectOne('SELECT * FROM `tbl_office_tasks` WHERE `id` = ?', [$taskId]);
            if (!$existing) {
                setFlash('error', 'Task not found.');
                redirect($back);
            }
            if (!($seeAll || (int) $existing['author'] === $me) || time() > strtotime((string) $existing['added_on']) + 7 * 86400) {
                setFlash('error', 'Tasks can only be edited within 7 days of creation by the author or an admin.');
                redirect($back);
            }
            $db->update('tbl_office_tasks', [
                'title'         => $title,
                'description'   => $description ?: null,
                'deadline'      => $deadline,
                'department_id' => $deptRow['department_id'] ?? null,
                'updated_by'    => $me,
            ], '`id` = ?', [$taskId]);

            $current = [];
            foreach ($db->select('SELECT `staff_id` FROM `tbl_office_task_assignees` WHERE `task_id` = ?', [$taskId]) as $a) {
                $current[(int) $a['staff_id']] = true;
            }
            $db->delete('tbl_office_task_assignees', '`task_id` = ?', [$taskId]);
            foreach ($assignees as $sid) {
                $db->insert('tbl_office_task_assignees', ['task_id' => $taskId, 'staff_id' => $sid, 'status' => 'Pending']);
                if (!isset($current[$sid])) {
                    notifyUser($sid, 'You were assigned to task "' . e($title) . '".', 'Task', (string) $taskId, $me);
                }
            }
            if (!empty($_FILES['task_file']['name']) && ($up = validateUpload($_FILES['task_file']))) {
                $loc = storeUpload($_FILES['task_file'], 'tasks', $up['extension']);
                if ($loc) {
                    $db->insert('tbl_office_task_files', [
                        'ref_id' => $taskId, 'type' => 'Task',
                        'file_location' => $loc, 'filename' => basename((string) $_FILES['task_file']['name']),
                        'added_by' => $me,
                    ]);
                }
            }
            setFlash('success', 'Task updated.');
        } else {
            $assignmentId = uniqid('asn');
            $taskId = $db->insert('tbl_office_tasks', [
                'assignment_id' => $assignmentId,
                'title'         => $title,
                'description'   => $description ?: null,
                'author'        => $me,
                'deadline'      => $deadline,
                'status'        => 'Pending',
                'department_id' => $deptRow['department_id'] ?? null,
                'added_by'      => $me,
            ]);
            foreach ($assignees as $sid) {
                $db->insert('tbl_office_task_assignees', ['task_id' => $taskId, 'staff_id' => $sid, 'status' => 'Pending']);
                notifyUser($sid, 'New task assigned to you: "' . e($title) . '".', 'Task', $assignmentId, $me);
            }
            if (!empty($_FILES['task_file']['name']) && ($up = validateUpload($_FILES['task_file']))) {
                $loc = storeUpload($_FILES['task_file'], 'tasks', $up['extension']);
                if ($loc) {
                    $db->insert('tbl_office_task_files', [
                        'ref_id' => $taskId, 'type' => 'Task',
                        'file_location' => $loc, 'filename' => basename((string) $_FILES['task_file']['name']),
                        'added_by' => $me,
                    ]);
                }
            }
            setFlash('success', 'Task created and assigned to ' . count($assignees) . ' staff member(s).');
        }
        redirect($back);
    }

    if ($action === 'delete_task') {
        $taskId = (int) ($_POST['task_id'] ?? 0);
        $existing = $db->selectOne('SELECT * FROM `tbl_office_tasks` WHERE `id` = ?', [$taskId]);
        if (!$existing) {
            setFlash('error', 'Task not found.');
        } elseif (!($seeAll || (int) $existing['author'] === $me) || time() > strtotime((string) $existing['added_on']) + 7 * 86400) {
            setFlash('error', 'Tasks can only be deleted within 7 days of creation by the author or an admin.');
        } else {
            deleteTaskFiles($db, $taskId);
            $db->delete('tbl_office_tasks', '`id` = ?', [$taskId]);
            setFlash('success', 'Task deleted.');
        }
        redirect($back);
    }

    if ($action === 'post_update') {
        $taskId = (int) ($_POST['task_id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');
        $updateText = trim((string) ($_POST['update_text'] ?? ''));
        if (!in_array($status, ['Pending', 'In Progress', 'Done', 'Rejected', 'Cancelled'], true)) {
            setFlash('error', 'Invalid status.');
            redirect($back);
        }
        $task = $db->selectOne('SELECT * FROM `tbl_office_tasks` WHERE `id` = ?', [$taskId]);
        if (!$task) {
            setFlash('error', 'Task not found.');
            redirect($back);
        }
        $isAssignee = (bool) $db->selectOne(
            'SELECT `id` FROM `tbl_office_task_assignees` WHERE `task_id` = ? AND `staff_id` = ?',
            [$taskId, $me]
        );
        if (!$seeAll && !$isAssignee && (int) $task['author'] !== $me) {
            setFlash('error', 'Only the author or assignees can update this task.');
            redirect($back);
        }

        $fileLoc = null;
        $fileName = null;
        if (!empty($_FILES['update_file']['name'])) {
            $up = validateUpload($_FILES['update_file']);
            if ($up['ok']) {
                $fileLoc = storeUpload($_FILES['update_file'], 'tasks', $up['extension']);
                $fileName = basename((string) $_FILES['update_file']['name']);
            } else {
                setFlash('error', $up['message']);
                redirect($back);
            }
        }
        if ($updateText === '' && $fileName === null) {
            setFlash('error', 'Add an update note or file.');
            redirect($back);
        }

        // Update the poster's assignee row + the task-level status.
        $db->update('tbl_office_task_assignees', ['status' => $status], '`task_id` = ? AND `staff_id` = ?', [$taskId, $me]);
        $db->update('tbl_office_tasks', ['status' => $status, 'updated_by' => $me], '`id` = ?', [$taskId]);

        $db->insert('tbl_office_task_files', [
            'ref_id'        => $taskId,
            'type'          => 'Update',
            'file_location' => $fileLoc,
            'filename'      => $fileName ?: $updateText,
            'added_by'      => $me,
        ]);

        notifyUser((int) $task['author'], 'Task "' . e($task['title']) . '" was updated to ' . e($status) . '.', 'Task', (string) $taskId, $me);
        setFlash('success', 'Task updated to ' . e($status) . '.');
        redirect($back);
    }

    if ($action === 'export_tasks') {
        $scope = canSeeAllTasks() ? '1=1' : '(t.author = ' . $me . ' OR EXISTS (SELECT 1 FROM `tbl_office_task_assignees` ta WHERE ta.task_id = t.id AND ta.staff_id = ' . $me . '))';
        $tasks = $db->select(
            'SELECT t.*, u.fullname AS author_name FROM `tbl_office_tasks` t
             LEFT JOIN `tbl_users_login` u ON u.id = t.author
             WHERE ' . $scope . '
             ORDER BY t.added_on DESC'
        );
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="tasks_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Title', 'Author', 'Status', 'Deadline', 'Created', 'Updated']);
        foreach ($tasks as $t) {
            fputcsv($out, [
                $t['id'], $t['title'], $t['author_name'], $t['status'],
                $t['deadline'], $t['added_on'], $t['updated_on'],
            ]);
        }
        fclose($out);
        exit;
    }

    setFlash('error', 'Unknown task action.');
    redirect($back);
} catch (Throwable $e) {
    setFlash('error', 'Task operation failed: ' . $e->getMessage());
    redirect($back);
}
