<?php
/**
 * SB-Tech — HR Care / Leaves tab (US-LV-02, US-LV-03.4).
 * Self-service: apply (types with remaining > 0 only, substitute required,
 * live day count + balance guard), edit/delete while Pending, and a live
 * balance + status list.
 */
$db = Database::instance();
$me = (int) Auth::id();
$year = currentLeaveYear();
$allocations = getStaffLeaveAllocationsWithBalance($me, true, $year);
$allAllocations = getStaffLeaveAllocationsWithBalance($me, false, $year);

$substitutes = $db->select(
    "SELECT `id`, `fullname` FROM `tbl_users_login`
     WHERE `status` = 'Active' AND `id` != ?
     ORDER BY `fullname`",
    [$me]
);

$edit = null;
if (isset($_GET['leave_id'])) {
    $edit = $db->selectOne(
        'SELECT * FROM `tbl_staff_leave_applications` WHERE `id` = ? AND `staff_id` = ?',
        [(int) $_GET['leave_id'], $me]
    );
    if ($edit && $edit['status'] !== 'Pending') {
        $edit = null;
    }
}

$mine = $db->select(
    'SELECT l.*, lc.title AS leave_title
     FROM `tbl_staff_leave_applications` l
     JOIN `tbl_office_leave_configs` lc ON lc.id = l.leave_type_id
     WHERE l.staff_id = ?
     ORDER BY l.added_on DESC',
    [$me]
);

$drawerOpen = ($edit !== null);
?>

<!-- Balance Summary (compact top bar) -->
<?php if ($allAllocations): ?>
<div class="row mb-3">
    <?php foreach ($allAllocations as $al): ?>
        <div class="col-lg col-md-4 col-6 mb-2">
            <div class="card card-outline card-light text-center py-2">
                <div class="card-body p-2">
                    <div class="text-muted small"><?= e($al['leave_title']) ?></div>
                    <div class="font-weight-bold" style="color: <?= (float) $al['remaining'] > 0 ? 'var(--accent)' : 'var(--text-muted)' ?>"><?= e((float) $al['remaining']) ?></div>
                    <div class="text-muted" style="font-size: .7rem">of <?= e((float) $al['allocated_days'] + (float) $al['carry_forward_days']) ?> left</div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Applications Table (full width) -->
<div class="card card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-list mr-1"></i>My Applications</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary btn-sm" onclick="openDrawer()">
                <i class="fas fa-plus mr-1"></i>Apply for Leave
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover mb-0">
                <thead>
                    <tr><th>#</th><th>Type</th><th>Dates</th><th>Days</th><th>Substitute</th><th>Status</th><th class="text-right">Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($mine as $i => $l): ?>
                    <?php
                    $stCls = ['Pending' => 'warning', 'Verified' => 'info', 'Approved' => 'success', 'Rejected' => 'danger'][$l['status']] ?? 'secondary';
                    ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><strong><?= e($l['leave_title']) ?></strong><?= $l['half_day'] ? ' <small class="text-muted">(half)</small>' : '' ?></td>
                        <td><?= e(formatDateView($l['from_date'])) ?> → <?= e(formatDateView($l['to_date'])) ?></td>
                        <td><strong><?= e((float) $l['leave_days']) ?></strong></td>
                        <td><?= e($l['filler_name'] ?? '—') ?></td>
                        <td>
                            <span class="badge badge-<?= $stCls ?>"><?= e($l['status']) ?></span>
                            <?php if ($l['status'] === 'Rejected' && $l['reject_reason']): ?>
                                <br><small class="text-danger" title="Reason"><?= e($l['reject_reason']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-right">
                            <?php if ($l['status'] === 'Pending'): ?>
                                <button type="button" class="btn btn-xs btn-outline-primary" onclick="openDrawer(<?= (int) $l['id'] ?>)"><i class="fas fa-edit"></i></button>
                                <form action="operation.php?module=staff_management&page=hr_care" method="post" class="d-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete_leave">
                                    <input type="hidden" name="leave_id" value="<?= (int) $l['id'] ?>">
                                    <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete this application?"><i class="fas fa-trash"></i></button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$mine): ?><tr><td colspan="7" class="text-center text-muted">No leave applications yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Slide-in Drawer Backdrop -->
<div class="cms-drawer-backdrop" id="drawerBackdrop" onclick="closeDrawer()"></div>

<!-- Slide-in Drawer -->
<div class="cms-drawer" id="leaveDrawer">
    <div class="cms-drawer-header">
        <h3><i class="fas fa-paper-plane"></i><?= $edit ? 'Edit Application' : 'Apply for Leave' ?></h3>
        <button type="button" class="cms-drawer-close" onclick="closeDrawer()" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="cms-drawer-body">
        <form action="operation.php?module=staff_management&page=hr_care" method="post" id="leaveForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_leave">
            <input type="hidden" name="leave_id" id="leaveId" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
            <div class="form-group">
                <label>Leave type *</label>
                <select name="leave_type_id" id="leave_type_id" class="form-control" required>
                    <option value="">Select type…</option>
                    <?php foreach ($allocations as $al): ?>
                        <option value="<?= (int) $al['leave_id'] ?>"
                                data-balance="<?= (float) $al['remaining'] ?>"
                                <?= $edit && (int) $edit['leave_type_id'] === (int) $al['leave_id'] ? 'selected' : '' ?>>
                            <?= e($al['leave_title']) ?> (<?= e((float) $al['remaining']) ?> days left)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$allocations): ?>
                    <small class="text-muted">No leave balance available. Contact HR for an allocation.</small>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>Substitute staff *</label>
                <select name="absence_filler" class="form-control" id="leaveFiller" required>
                    <option value="">Select staff…</option>
                    <?php foreach ($substitutes as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= $edit && (int) $edit['absence_filler'] === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['fullname']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="col-6 form-group">
                    <label>From *</label>
                    <input type="date" name="from_date" id="from_date" class="form-control" required value="<?= $edit ? e($edit['from_date']) : '' ?>">
                </div>
                <div class="col-6 form-group">
                    <label>To *</label>
                    <input type="date" name="to_date" id="to_date" class="form-control" required value="<?= $edit ? e($edit['to_date']) : '' ?>">
                </div>
            </div>
            <div class="form-group">
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="half_day" name="half_day" value="1" <?= $edit && $edit['half_day'] ? 'checked' : '' ?>>
                    <label class="custom-control-label" for="half_day">Half day (0.5)</label>
                </div>
            </div>
            <div class="form-group" id="halfGroup">
                <label>Half</label>
                <select name="first_half" class="form-control" id="leaveHalf">
                    <option value="1" <?= $edit && $edit['first_half'] ? 'selected' : '' ?>>First half</option>
                    <option value="0" <?= $edit && !$edit['first_half'] ? 'selected' : '' ?>>Second half</option>
                </select>
            </div>
            <div class="form-group">
                <label>Reason *</label>
                <textarea name="reason" class="form-control" id="leaveReason" rows="3" required><?= $edit ? e($edit['reason']) : '' ?></textarea>
            </div>
            <div class="alert alert-light border mb-2 py-2">
                <strong>Days:</strong> <span id="day_count" class="font-weight-bold">0</span>
                &nbsp;·&nbsp; <strong>Remaining:</strong> <span id="balance_hint">—</span>
            </div>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="leaveForm" id="apply_btn" class="btn btn-primary btn-block">
            <i class="fas fa-paper-plane mr-1"></i><?= $edit ? 'Update Application' : 'Submit Application' ?>
        </button>
    </div>
</div>

<script>
var leavesData = <?= json_encode(array_values($mine)) ?>;

function openDrawer(editId) {
    var drawer = document.getElementById('leaveDrawer');
    var backdrop = document.getElementById('drawerBackdrop');
    drawer.classList.add('open');
    backdrop.classList.add('active');
    document.body.style.overflow = 'hidden';

    var title = drawer.querySelector('.cms-drawer-header h3');
    if (editId) {
        var leave = leavesData.find(function(l) { return l.id == editId; });
        if (leave) {
            title.innerHTML = '<i class="fas fa-paper-plane"></i>Edit Application';
            document.getElementById('leaveId').value = leave.id;
            document.getElementById('leave_type_id').value = leave.leave_type_id;
            document.getElementById('leaveFiller').value = leave.absence_filler;
            document.getElementById('from_date').value = leave.from_date || '';
            document.getElementById('to_date').value = leave.to_date || '';
            document.getElementById('half_day').checked = leave.half_day == 1;
            document.getElementById('leaveHalf').value = leave.first_half || '1';
            document.getElementById('leaveReason').value = leave.reason || '';
            // Trigger day count refresh
            document.getElementById('leave_type_id').dispatchEvent(new Event('change'));
            document.getElementById('from_date').dispatchEvent(new Event('change'));
        }
    } else {
        title.innerHTML = '<i class="fas fa-paper-plane"></i>Apply for Leave';
        document.getElementById('leaveId').value = '0';
        document.getElementById('leave_type_id').value = '';
        document.getElementById('leaveFiller').value = '';
        document.getElementById('from_date').value = '';
        document.getElementById('to_date').value = '';
        document.getElementById('half_day').checked = false;
        document.getElementById('leaveHalf').value = '1';
        document.getElementById('leaveReason').value = '';
        document.getElementById('day_count').textContent = '0';
        document.getElementById('balance_hint').textContent = '—';
    }
}

function closeDrawer() {
    document.getElementById('leaveDrawer').classList.remove('open');
    document.getElementById('drawerBackdrop').classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeDrawer(); });

<?php if ($drawerOpen): ?>
document.addEventListener('DOMContentLoaded', function() { openDrawer(<?= (int) $edit['id'] ?>); });
<?php endif; ?>

// --- Day count + balance guard ---
(function () {
    var typeSel = document.getElementById('leave_type_id');
    var from = document.getElementById('from_date');
    var to = document.getElementById('to_date');
    var half = document.getElementById('half_day');
    var count = document.getElementById('day_count');
    var hint = document.getElementById('balance_hint');
    var btn = document.getElementById('apply_btn');
    if (!typeSel || !from || !to || !count) { return; }

    function balanceOf() {
        var opt = typeSel.options[typeSel.selectedIndex];
        return opt && opt.dataset.balance !== undefined ? parseFloat(opt.dataset.balance) : 0;
    }

    function dayCount() {
        if (half.checked) { return 0.5; }
        if (!from.value || !to.value || to.value < from.value) { return 0; }
        var a = new Date(from.value), b = new Date(to.value);
        return Math.round((b - a) / 86400000) + 1;
    }

    function refresh() {
        var days = dayCount();
        var bal = balanceOf();
        count.textContent = days;
        hint.textContent = bal + ' days';
        if (days > bal) {
            count.style.color = '#dc3545';
            hint.style.color = '#dc3545';
            btn.disabled = true;
            btn.title = 'Exceeds remaining balance (' + bal + ' days)';
        } else {
            count.style.color = '';
            hint.style.color = '';
            btn.disabled = false;
            btn.title = '';
        }
    }

    [typeSel, from, to, half].forEach(function (el) { el.addEventListener('change', refresh); el.addEventListener('input', refresh); });
    refresh();
})();
</script>
