<?php
/**
 * SB-Tech — HR Care / Tasks tab (US-TSK-01/02/03).
 * Assignment with multi-assignee, deadline tracking ("new" + "Past Due"
 * badges), per-assignee updates with files, and a 7-day edit/delete window
 * for authors/admins. Non-admin scope: own authored + assigned tasks.
 */
$db = Database::instance();
$me = (int) Auth::id();
$seeAll = canSeeAllTasks();

// --- Filters (AC-TSK-03.1) ---
$view = (string) ($_GET['task_view'] ?? ($seeAll ? 'all' : 'mine'));
if (!in_array($view, ['all', 'mine', 'authored'], true)) {
    $view = $seeAll ? 'all' : 'mine';
}
$statusFilter = (string) ($_GET['task_status'] ?? '');
$keyword = trim((string) ($_GET['task_keyword'] ?? ''));

[$scopeSql, $scopeParams] = taskScopeSql($me, $seeAll);
$where = [$scopeSql];
$params = $scopeParams;
if ($view === 'mine') {
    $where[] = 'EXISTS (SELECT 1 FROM `tbl_office_task_assignees` ta WHERE ta.task_id = t.id AND ta.staff_id = ?)';
    $params[] = $me;
} elseif ($view === 'authored') {
    $where[] = 't.author = ?';
    $params[] = $me;
}
if ($statusFilter !== '' && in_array($statusFilter, ['Pending', 'In Progress', 'Done', 'Rejected', 'Cancelled'], true)) {
    $where[] = 't.status = ?';
    $params[] = $statusFilter;
}
if ($keyword !== '') {
    $where[] = '(t.title LIKE ? OR t.description LIKE ?)';
    $params[] = '%' . $db->escapeLike($keyword) . '%';
    $params[] = '%' . $db->escapeLike($keyword) . '%';
}

$tasks = $db->select(
    'SELECT t.*, u.fullname AS author_name,
            GROUP_CONCAT(DISTINCT CONCAT(a.staff_id, ":", COALESCE(us.fullname, a.staff_id)) SEPARATOR "|") AS assignees
     FROM `tbl_office_tasks` t
     JOIN `tbl_users_login` u ON u.id = t.author
     LEFT JOIN `tbl_office_task_assignees` a ON a.task_id = t.id
     LEFT JOIN `tbl_users_login` us ON us.id = a.staff_id
     WHERE ' . implode(' AND ', $where) . '
     GROUP BY t.id
     ORDER BY t.added_on DESC',
    $params
);
foreach ($tasks as &$t) {
    $t['assignee_list'] = [];
    foreach (array_filter(explode('|', (string) $t['assignees'])) as $pair) {
        [$id, $name] = array_pad(explode(':', $pair, 2), 2, '');
        $t['assignee_list'][] = ['id' => (int) $id, 'name' => $name];
    }
}
unset($t);

// --- Edit context ---
$editTask = null;
if (isset($_GET['task_id'])) {
    $editTask = $db->selectOne('SELECT * FROM `tbl_office_tasks` WHERE `id` = ?', [(int) $_GET['task_id']]);
    if ($editTask) {
        $window = strtotime((string) $editTask['added_on']) + 7 * 86400;
        $isAuthorOrAdmin = $seeAll || (int) $editTask['author'] === $me;
        if (!$isAuthorOrAdmin || time() > $window) {
            $editTask = null;
        }
    }
}
$editAssignees = [];
if ($editTask) {
    foreach ($db->select('SELECT `staff_id` FROM `tbl_office_task_assignees` WHERE `task_id` = ?', [(int) $editTask['id']]) as $ea) {
        $editAssignees[] = (int) $ea['staff_id'];
    }
}

$departments = $db->select('SELECT * FROM `tbl_office_departments` ORDER BY position, title');
$staffs = $db->select(
    "SELECT u.id, u.fullname, u.department_id, d.title AS department_title
     FROM `tbl_users_login` u
     LEFT JOIN `tbl_office_departments` d ON d.id = u.department_id
     WHERE u.status = 'Active'
     ORDER BY u.fullname"
);

$statusBadges = ['Pending' => 'warning', 'In Progress' => 'info', 'Done' => 'success', 'Rejected' => 'danger', 'Cancelled' => 'secondary'];

$drawerOpen = ($editTask !== null);
?>

<!-- Tasks List (full width) -->
<div class="card card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-tasks mr-1"></i>Tasks</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary btn-sm" onclick="openDrawer()">
                <i class="fas fa-plus mr-1"></i>New Task
            </button>
            <form method="get" class="form-inline d-inline ml-2">
                <input type="hidden" name="module" value="my_office">
                <input type="hidden" name="page" value="hr_care">
                <input type="hidden" name="tab" value="tasks">
                <select name="task_view" class="form-control form-control-sm mr-1" onchange="this.form.submit()">
                    <?php if ($seeAll): ?><option value="all" <?= $view === 'all' ? 'selected' : '' ?>>All tasks</option><?php endif; ?>
                    <option value="mine" <?= $view === 'mine' ? 'selected' : '' ?>>Assigned to me</option>
                    <option value="authored" <?= $view === 'authored' ? 'selected' : '' ?>>Authored by me</option>
                </select>
                <select name="task_status" class="form-control form-control-sm mr-1" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    <?php foreach (array_keys($statusBadges) as $st): ?>
                        <option value="<?= $st ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="task_keyword" class="form-control form-control-sm mr-1" placeholder="Keyword" value="<?= e($keyword) ?>">
                <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-search"></i></button>
            </form>
            <form action="operation.php?module=staff_management&page=hr_care" method="post" class="d-inline ml-1">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="export_tasks">
                <button class="btn btn-sm btn-success"><i class="fas fa-download mr-1"></i>CSV</button>
            </form>
        </div>
    </div>
    <div class="card-body p-0">
        <?php foreach ($tasks as $t): ?>
            <?php
            $badges = taskBadgeClasses($t);
            $withinWindow = time() <= strtotime((string) $t['added_on']) + 7 * 86400;
            $canEditDelete = ($seeAll || (int) $t['author'] === $me) && $withinWindow;
            $updates = $db->select(
                'SELECT f.*, u.fullname AS updater
                 FROM `tbl_office_task_files` f
                 LEFT JOIN `tbl_users_login` u ON u.id = f.added_by
                 WHERE f.ref_id = ? AND f.type = ?
                 ORDER BY f.added_on DESC',
                [(int) $t['id'], 'Update']
            );
            $attachments = $db->select(
                'SELECT * FROM `tbl_office_task_files` WHERE ref_id = ? AND type = ? ORDER BY added_on DESC',
                [(int) $t['id'], 'Task']
            );
            ?>
            <div class="card card-outline card-light m-2">
                <div class="card-header py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?= e($t['title']) ?></strong>
                            <?php if ($badges['new']): ?><span class="badge badge-new blink ml-1">New</span><?php endif; ?>
                            <?php if ($badges['past_due']): ?><span class="badge badge-danger ml-1">Past Due</span><?php endif; ?>
                            <span class="badge badge-<?= $statusBadges[$t['status']] ?? 'secondary' ?> ml-1"><?= e($t['status']) ?></span>
                        </div>
                        <div class="text-right">
                            <?php if ($canEditDelete): ?>
                                <button type="button" class="btn btn-xs btn-outline-primary" onclick="openDrawer(<?= (int) $t['id'] ?>)" title="Edit (7-day window)"><i class="fas fa-edit"></i></button>
                                <form action="operation.php?module=staff_management&page=hr_care" method="post" class="d-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete_task">
                                    <input type="hidden" name="task_id" value="<?= (int) $t['id'] ?>">
                                    <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete this task?"><i class="fas fa-trash"></i></button>
                                </form>
                            <?php endif; ?>
                            <button type="button" class="btn btn-xs btn-outline-secondary" data-toggle="collapse" data-target="#task-updates-<?= (int) $t['id'] ?>"><i class="fas fa-comments"></i> <?= count($updates) ?></button>
                        </div>
                    </div>
                    <div class="text-muted small">
                        by <?= e($t['author_name']) ?> ·
                        <?php foreach ($t['assignee_list'] as $as): ?>
                            <span class="badge badge-light border"><?= e($as['name']) ?></span>
                        <?php endforeach; ?>
                        <?php if ($t['deadline']): ?> · deadline <span class="<?= $badges['past_due'] ? 'text-danger font-weight-bold' : '' ?>"><?= e(date('M j, g:i A', strtotime($t['deadline']))) ?></span><?php endif; ?>
                    </div>
                </div>
                <div class="card-body py-2">
                    <?php if ($t['description']): ?><p class="mb-1"><?= nl2br(e($t['description'])) ?></p><?php endif; ?>
                    <?php if ($attachments): ?>
                        <div class="mb-1">
                            <?php foreach ($attachments as $f): ?>
                                <a href="<?= assetUrl('user_uploads/' . $f['file_location']) ?>" class="badge badge-light border mr-1" target="_blank"><i class="fas fa-paperclip mr-1"></i><?= e($f['filename']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="collapse" id="task-updates-<?= (int) $t['id'] ?>">
                        <hr class="my-2">
                        <form action="operation.php?module=staff_management&page=hr_care" method="post" enctype="multipart/form-data" class="row align-items-end bg-light p-2 rounded">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="post_update">
                            <input type="hidden" name="task_id" value="<?= (int) $t['id'] ?>">
                            <div class="col-md-3">
                                <label class="small mb-0">Status</label>
                                <select name="status" class="form-control form-control-sm">
                                    <?php foreach (array_keys($statusBadges) as $st): ?>
                                        <option value="<?= $st ?>" <?= $t['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="small mb-0">Progress update</label>
                                <input type="text" name="update_text" class="form-control form-control-sm" placeholder="What did you do?">
                            </div>
                            <div class="col-md-3">
                                <label class="small mb-0">File (optional)</label>
                                <input type="file" name="update_file" class="form-control-file form-control-sm">
                            </div>
                            <div class="col-md-1">
                                <button type="submit" class="btn btn-sm btn-primary btn-block"><i class="fas fa-paper-plane"></i></button>
                            </div>
                        </form>
                        <?php if ($updates): ?>
                            <ul class="list-unstyled mt-2 mb-0">
                                <?php foreach ($updates as $up): ?>
                                    <li class="media mb-1">
                                        <div class="media-body border-left pl-2">
                                            <div class="small">
                                                <strong><?= e($up['updater'] ?? 'Staff') ?></strong>
                                                <span class="text-muted"><?= e(date('M j, g:i A', strtotime($up['added_on']))) ?></span>
                                                <?php if ($up['file_location']): ?>
                                                    <a href="<?= assetUrl('user_uploads/' . $up['file_location']) ?>" target="_blank" class="ml-1"><i class="fas fa-paperclip"></i> <?= e($up['filename']) ?></a>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!$up['file_location'] && $up['filename']): ?><div class="text-muted"><?= e($up['filename']) ?></div><?php endif; ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$tasks): ?>
            <div class="text-center text-muted py-4">No tasks found.</div>
        <?php endif; ?>
    </div>
</div>

<!-- Slide-in Drawer Backdrop -->
<div class="cms-drawer-backdrop" id="drawerBackdrop" onclick="closeDrawer()"></div>

<!-- Slide-in Drawer -->
<div class="cms-drawer" id="taskDrawer">
    <div class="cms-drawer-header">
        <h3><i class="fas fa-tasks"></i><?= $editTask ? 'Edit Task' : 'New Task' ?></h3>
        <button type="button" class="cms-drawer-close" onclick="closeDrawer()" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="cms-drawer-body">
        <form action="operation.php?module=staff_management&page=hr_care" method="post" enctype="multipart/form-data" id="taskForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_task">
            <input type="hidden" name="task_id" id="taskId" value="<?= $editTask ? (int) $editTask['id'] : 0 ?>">
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" class="form-control" id="taskTitle" required value="<?= $editTask ? e($editTask['title']) : '' ?>">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" id="taskDescription" rows="3"><?= $editTask ? e($editTask['description']) : '' ?></textarea>
            </div>
            <div class="form-group">
                <label>Deadline</label>
                <input type="datetime-local" name="deadline" class="form-control" id="taskDeadline" value="<?= $editTask && $editTask['deadline'] ? e(date('Y-m-d\TH:i', strtotime($editTask['deadline']))) : '' ?>">
            </div>
            <div class="form-group">
                <label>Department (filter assignees)</label>
                <select id="task_dept" class="form-control">
                    <option value="0">All departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int) $d['id'] ?>"><?= e($d['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Assign to *</label>
                <select name="assignees[]" class="form-control" id="taskAssignees" size="5" multiple required>
                    <?php foreach ($staffs as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" data-dept="<?= (int) $s['department_id'] ?>"
                            <?= $editTask && in_array((int) $s['id'], $editAssignees, true) ? 'selected' : '' ?>>
                            <?= e($s['fullname']) ?><?= $s['department_title'] ? ' (' . e($s['department_title']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Attachment (optional)</label>
                <div class="custom-file">
                    <input type="file" class="custom-file-input" id="task_file" name="task_file">
                    <label class="custom-file-label" for="task_file">Choose file</label>
                </div>
            </div>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="taskForm" class="btn btn-primary btn-block">
            <i class="fas fa-save mr-1"></i><?= $editTask ? 'Update Task' : 'Create Task' ?>
        </button>
    </div>
</div>

<script>
var tasksData = <?= json_encode(array_values($tasks)) ?>;

function openDrawer(editId) {
    var drawer = document.getElementById('taskDrawer');
    var backdrop = document.getElementById('drawerBackdrop');
    drawer.classList.add('open');
    backdrop.classList.add('active');
    document.body.style.overflow = 'hidden';

    var title = drawer.querySelector('.cms-drawer-header h3');
    if (editId) {
        var task = tasksData.find(function(t) { return t.id == editId; });
        if (task) {
            title.innerHTML = '<i class="fas fa-tasks"></i>Edit Task';
            document.getElementById('taskId').value = task.id;
            document.getElementById('taskTitle').value = task.title || '';
            document.getElementById('taskDescription').value = task.description || '';
            document.getElementById('taskDeadline').value = task.deadline ? task.deadline.replace(' ', 'T').substring(0, 16) : '';
            // Select assignees
            var assigneeIds = task.assignee_list.map(function(a) { return a.id.toString(); });
            var opts = document.getElementById('taskAssignees').options;
            for (var i = 0; i < opts.length; i++) {
                opts[i].selected = assigneeIds.indexOf(opts[i].value) !== -1;
            }
        }
    } else {
        title.innerHTML = '<i class="fas fa-tasks"></i>New Task';
        document.getElementById('taskId').value = '0';
        document.getElementById('taskTitle').value = '';
        document.getElementById('taskDescription').value = '';
        document.getElementById('taskDeadline').value = '';
        var opts = document.getElementById('taskAssignees').options;
        for (var i = 0; i < opts.length; i++) { opts[i].selected = false; }
    }
}

function closeDrawer() {
    document.getElementById('taskDrawer').classList.remove('open');
    document.getElementById('drawerBackdrop').classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeDrawer(); });

<?php if ($drawerOpen): ?>
document.addEventListener('DOMContentLoaded', function() { openDrawer(<?= (int) $editTask['id'] ?>); });
<?php endif; ?>

// Department filter for assignees
(function () {
    var dept = document.getElementById('task_dept');
    if (!dept) { return; }
    var opts = Array.prototype.slice.call(dept.form.querySelectorAll('select[name="assignees[]"] option'));
    dept.addEventListener('change', function () {
        var d = parseInt(dept.value, 10) || 0;
        opts.forEach(function (o) {
            o.hidden = d !== 0 && parseInt(o.dataset.dept || '0', 10) !== d;
        });
    });
})();
</script>
