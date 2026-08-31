<?php
/**
 * SB-Tech — Staff Management / Daily Tasks.
 * Staff log their day's tasks (one entry per staff per date). Admins can
 * view/edit any staff; staff add/edit their own entry for the selected date.
 */
$db = Database::instance();
$me = (int) Auth::id();
$seeAll = Auth::isSuperAdmin();

$date = (string) ($_GET['date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}
$keyword = trim((string) ($_GET['keyword'] ?? ''));

$where = 'WHERE dt.date = ?';
$params = [$date];
if (!$seeAll) {
    $where .= ' AND dt.staff_id = ?';
    $params[] = $me;
}
if ($keyword !== '') {
    $where .= ' AND dt.tasks LIKE ?';
    $params[] = '%' . $db->escapeLike($keyword) . '%';
}
$rows = $db->select(
    'SELECT dt.*, u.fullname AS staff_name
     FROM `tbl_daily_tasks` dt
     JOIN `tbl_users_login` u ON u.id = dt.staff_id
     ' . $where . '
     ORDER BY u.fullname',
    $params
);

$edit = null;
if (isset($_GET['id'])) {
    $edit = $db->selectOne('SELECT * FROM `tbl_daily_tasks` WHERE `id` = ?', [(int) $_GET['id']]);
    if ($edit && !$seeAll && (int) $edit['staff_id'] !== $me) {
        $edit = null;
    }
}

$staffs = $db->select(
    "SELECT u.id, u.fullname, d.title AS department_title
     FROM `tbl_users_login` u
     LEFT JOIN `tbl_office_departments` d ON d.id = u.department_id
     WHERE u.status = 'Active'
     ORDER BY u.fullname"
);

$drawerOpen = ($edit !== null);
?>

<!-- Data Table (full width) -->
<div class="card card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-calendar-day mr-1"></i>Daily tasks — <?= e(formatDateView($date)) ?></h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary btn-sm" onclick="openDrawer()">
                <i class="fas fa-plus mr-1"></i>Add Task
            </button>
            <form method="get" class="form-inline d-inline ml-2">
                <input type="hidden" name="module" value="staff_management">
                <input type="hidden" name="page" value="staff_daily_tasks">
                <input type="date" name="date" class="form-control form-control-sm mr-1" value="<?= e($date) ?>" onchange="this.form.submit()">
                <input type="text" name="keyword" class="form-control form-control-sm mr-1" placeholder="Keyword" value="<?= e($keyword) ?>">
                <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-search"></i></button>
            </form>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover mb-0">
                <thead><tr><th>#</th><th>Staff</th><th>Tasks</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $i => $r): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= e($r['staff_name']) ?></td>
                        <td><?= nl2br(e($r['tasks'])) ?></td>
                        <td class="text-right">
                            <?php if ($seeAll || (int) $r['staff_id'] === $me): ?>
                                <button type="button" class="btn btn-xs btn-outline-primary" onclick="openDrawer(<?= (int) $r['id'] ?>)"><i class="fas fa-edit"></i></button>
                                <form action="operation.php?module=staff_management&page=staff_daily_tasks" method="post" class="d-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete this daily task log?"><i class="fas fa-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="4" class="text-center text-muted">No daily tasks logged for this date.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Slide-in Drawer Backdrop -->
<div class="cms-drawer-backdrop" id="drawerBackdrop" onclick="closeDrawer()"></div>

<!-- Slide-in Drawer -->
<div class="cms-drawer" id="taskDrawer">
    <div class="cms-drawer-header">
        <h3><i class="fas fa-clipboard-list"></i><?= $edit ? 'Edit daily tasks' : 'Log daily tasks' ?></h3>
        <button type="button" class="cms-drawer-close" onclick="closeDrawer()" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="cms-drawer-body">
        <form action="operation.php?module=staff_management&page=staff_daily_tasks" method="post" id="taskForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="taskId" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
            <div class="form-group">
                <label>Staff</label>
                <select name="staff_id" class="form-control" id="taskStaffId" <?= $seeAll ? '' : 'disabled' ?>>
                    <?php foreach ($staffs as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= (!$seeAll && (int) $s['id'] === $me) || ($edit && (int) $edit['staff_id'] === (int) $s['id']) ? 'selected' : '' ?>><?= e($s['fullname']) ?><?= $s['department_title'] ? ' (' . e($s['department_title']) . ')' : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$seeAll): ?><input type="hidden" name="staff_id" value="<?= (int) $me ?>"><?php endif; ?>
            </div>
            <div class="form-group">
                <label>Date</label>
                <input type="date" name="date" class="form-control" id="taskDate" required value="<?= $edit ? e($edit['date']) : e($date) ?>">
            </div>
            <div class="form-group">
                <label>Tasks done *</label>
                <textarea name="tasks" class="form-control" id="taskText" rows="6" required placeholder="What did you work on today?"><?= $edit ? e($edit['tasks']) : '' ?></textarea>
            </div>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="taskForm" class="btn btn-primary btn-block">
            <i class="fas fa-save mr-1"></i><?= $edit ? 'Update' : 'Log tasks' ?>
        </button>
    </div>
</div>

<script>
// Drawer open/close functions
function openDrawer(editId) {
    var drawer = document.getElementById('taskDrawer');
    var backdrop = document.getElementById('drawerBackdrop');
    drawer.classList.add('open');
    backdrop.classList.add('active');
    document.body.style.overflow = 'hidden';

    if (editId) {
        // Fetch data and populate form for edit
        window.location.href = '<?= pageUrl('staff_management', 'staff_daily_tasks') ?>&id=' + editId + '&date=<?= e($date) ?>';
    }
}

function closeDrawer() {
    var drawer = document.getElementById('taskDrawer');
    var backdrop = document.getElementById('drawerBackdrop');
    drawer.classList.remove('open');
    backdrop.classList.remove('active');
    document.body.style.overflow = '';
}

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeDrawer();
});

// Open drawer on page load if editing
<?php if ($drawerOpen): ?>
document.addEventListener('DOMContentLoaded', function() {
    openDrawer();
});
<?php endif; ?>
</script>
