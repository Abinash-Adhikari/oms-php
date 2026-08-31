<?php
/**
 * SB-Tech — My Office / Speak Up operations (US-GRV-01).
 *   save_grievance          — staff submit with optional attachment
 *   delete_grievance        — author only while Pending (admin may delete any)
 *   post_grievance_update   — author / assignee / admin post progress + file
 *   admin_update_grievance  — Super Admin assigns, sets status + deadline;
 *                             status changes notify author and assignee
 */
$db = Database::instance();
$me = (int) Auth::id();
$seeAll = Auth::isSuperAdmin();
$action = (string) ($_POST['action'] ?? '');
$back = 'show_page.php?module=staff_management&page=hr_care&tab=speak_up';

/** Notify all Super Admin users (grievance handlers). */
function notifySuperAdmins(string $details, string $type, $refId, ?int $actor = null): void
{
    try {
        $users = Database::instance()->select(
            'SELECT `id`, `role`, `permitted_modules` FROM `tbl_users_login` WHERE `status` = ?',
            ['Active']
        );
        foreach ($users as $u) {
            if (Auth::isSuperAdmin($u)) {
                notifyUser((int) $u['id'], $details, $type, $refId, $actor);
            }
        }
    } catch (Throwable $e) {
        // ignore
    }
}

try {
    if ($action === 'save_grievance') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        if ($title === '' || $description === '') {
            setFlash('error', 'Title and description are required.');
            redirect($back);
        }
        $id = $db->insert('tbl_office_grievances', [
            'assignment_id' => uniqid('asn'),
            'title'         => $title,
            'description'   => $description,
            'author'        => $me,
            'status'        => 'Pending',
            'added_by'      => $me,
        ]);
        if (!empty($_FILES['grievance_file']['name']) && ($up = validateUpload($_FILES['grievance_file']))) {
            $loc = storeUpload($_FILES['grievance_file'], 'speakup', $up['extension']);
            if ($loc) {
                $db->insert('tbl_office_grievance_files', [
                    'ref_id' => $id, 'type' => 'grievance',
                    'file_location' => $loc, 'filename' => basename((string) $_FILES['grievance_file']['name']),
                    'added_by' => $me,
                ]);
            }
        }
        $author = $db->selectOne('SELECT `fullname` FROM `tbl_users_login` WHERE `id` = ?', [$me]);
        notifySuperAdmins(e($author['fullname'] ?? 'A staff member') . ' raised a concern: "' . e($title) . '".', 'Grievance', (string) $id, $me);
        setFlash('success', 'Your concern has been submitted.');
        redirect($back);
    }

    if ($action === 'delete_grievance') {
        $id = (int) ($_POST['id'] ?? 0);
        $g = $db->selectOne('SELECT * FROM `tbl_office_grievances` WHERE `id` = ?', [$id]);
        if (!$g) {
            setFlash('error', 'Concern not found.');
        } elseif (!$seeAll && ((int) $g['author'] !== $me || $g['status'] !== 'Pending')) {
            setFlash('error', 'You can only delete your own concerns while they are Pending.');
        } else {
            foreach ($db->select('SELECT `file_location` FROM `tbl_office_grievance_files` WHERE `ref_id` = ?', [$id]) as $f) {
                if (!empty($f['file_location'])) {
                    $path = dirname(__DIR__, 3) . '/user_uploads/' . $f['file_location'];
                    if (is_file($path)) {
                        @unlink($path);
                    }
                }
            }
            $db->delete('tbl_office_grievance_files', '`ref_id` = ?', [$id]);
            $db->delete('tbl_office_grievances', '`id` = ?', [$id]);
            setFlash('success', 'Concern deleted.');
        }
        redirect($back);
    }

    if ($action === 'post_grievance_update') {
        $id = (int) ($_POST['id'] ?? 0);
        $text = trim((string) ($_POST['update_text'] ?? ''));
        $g = $db->selectOne('SELECT * FROM `tbl_office_grievances` WHERE `id` = ?', [$id]);
        if (!$g) {
            setFlash('error', 'Concern not found.');
            redirect($back);
        }
        if (!$seeAll && (int) $g['author'] !== $me && (int) $g['assigned'] !== $me) {
            setFlash('error', 'Only the author, assignee, or an admin can update this concern.');
            redirect($back);
        }
        $fileLoc = null;
        $fileName = null;
        if (!empty($_FILES['update_file']['name'])) {
            $up = validateUpload($_FILES['update_file']);
            if ($up['ok']) {
                $fileLoc = storeUpload($_FILES['update_file'], 'speakup', $up['extension']);
                $fileName = basename((string) $_FILES['update_file']['name']);
            } else {
                setFlash('error', $up['message']);
                redirect($back);
            }
        }
        if ($text === '' && $fileName === null) {
            setFlash('error', 'Add an update note or file.');
            redirect($back);
        }
        $db->insert('tbl_office_grievance_files', [
            'ref_id' => $id, 'type' => 'Update',
            'file_location' => $fileLoc, 'filename' => $fileName ?: $text,
            'added_by' => $me,
        ]);
        setFlash('success', 'Update posted.');
        redirect($back);
    }

    if ($action === 'admin_update_grievance') {
        if (!$seeAll) {
            http_response_code(403);
            die('Access denied: only a Super Admin can manage grievances.');
        }
        $id = (int) ($_POST['id'] ?? 0);
        $g = $db->selectOne('SELECT * FROM `tbl_office_grievances` WHERE `id` = ?', [$id]);
        if (!$g) {
            setFlash('error', 'Concern not found.');
            redirect($back);
        }
        $assigned = (int) ($_POST['assigned'] ?? 0) ?: null;
        $status = (string) ($_POST['status'] ?? 'Pending');
        if (!in_array($status, ['Pending', 'In Progress', 'Done', 'Rejected', 'Acknowledged'], true)) {
            $status = 'Pending';
        }
        $deadlineRaw = trim((string) ($_POST['deadline'] ?? ''));
        $deadline = $deadlineRaw !== '' ? date('Y-m-d H:i:s', strtotime($deadlineRaw)) : null;

        $db->update('tbl_office_grievances', [
            'assigned'    => $assigned,
            'status'      => $status,
            'deadline'    => $deadline,
            'updated_by'  => $me,
        ], '`id` = ?', [$id]);

        if ($status !== $g['status'] || $assigned !== (int) $g['assigned']) {
            notifyUser((int) $g['author'], 'Your concern "' . e($g['title']) . '" is now ' . e($status) . '.', 'Grievance', (string) $id, $me);
            if ($assigned) {
                notifyUser($assigned, 'You were assigned concern "' . e($g['title']) . '" (' . e($status) . ').', 'Grievance', (string) $id, $me);
            }
        }
        setFlash('success', 'Concern updated.');
        redirect($back);
    }

    setFlash('error', 'Unknown grievance action.');
    redirect($back);
} catch (Throwable $e) {
    setFlash('error', 'Grievance operation failed: ' . $e->getMessage());
    redirect($back);
}
