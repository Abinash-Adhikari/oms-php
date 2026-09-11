<?php
/**
 * SB-Tech — Office Setup / Designations (US-SET-02).
 * Delete is blocked while staff hold the designation.
 * Add/edit live in a client-side drawer (no page reload on edit) and support
 * adding multiple designations at once in a tabular grid.
 */
$db = Database::instance();
$rows = $db->select('SELECT d.*, (SELECT COUNT(*) FROM `tbl_users_login` u WHERE u.designation_id = d.id) AS staff_count FROM `tbl_office_designation` d ORDER BY d.position, d.title');
?>

<!-- Data Table (full width) -->
<div class="card card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-user-tie mr-1"></i>Designations</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary btn-sm" onclick="openDrawer()">
                <i class="fas fa-plus mr-1"></i>Add Designation
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover mb-0">
                <thead><tr><th>#</th><th>Title</th><th>Position</th><th>Staff</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $i => $r): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= e($r['title']) ?></td>
                        <td><?= (int) $r['position'] ?></td>
                        <td><span class="badge badge-<?= (int) $r['staff_count'] > 0 ? 'info' : 'secondary' ?>"><?= (int) $r['staff_count'] ?></span></td>
                        <td class="text-right">
                            <button type="button" class="btn btn-xs btn-outline-primary" onclick="openDrawer(this)" data-id="<?= (int) $r['id'] ?>" data-title="<?= e($r['title']) ?>" data-position="<?= (int) $r['position'] ?>"><i class="fas fa-edit"></i></button>
                            <form action="operation.php?module=office_setup&page=designations" method="post" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <?php if ((int) $r['staff_count'] > 0): ?>
                                    <button type="button" class="btn btn-xs btn-outline-danger" title="Cannot delete: staff assigned" disabled><i class="fas fa-trash"></i></button>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete designation '<?= e($r['title']) ?>'?"><i class="fas fa-trash"></i></button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted">No designations yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Slide-in Drawer Backdrop -->
<div class="cms-drawer-backdrop" id="drawerBackdrop" onclick="closeDrawer()"></div>

<!-- Slide-in Drawer -->
<div class="cms-drawer" id="formDrawer">
    <div class="cms-drawer-header">
        <h3><i class="fas fa-user-tie"></i><span id="drawerTitle">Add Designation</span></h3>
        <button type="button" class="cms-drawer-close" onclick="closeDrawer()" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="cms-drawer-body">
        <form action="operation.php?module=office_setup&page=designations" method="post" id="drawerForm">
            <?= csrfField() ?>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-2">
                    <thead><tr><th>Title *</th><th class="text-center" style="width:90px">Position</th><th style="width:40px"></th></tr></thead>
                    <tbody id="bulkRows"></tbody>
                </table>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm" onclick="addBulkRow()">
                <i class="fas fa-plus mr-1"></i>Add row
            </button>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="drawerForm" class="btn btn-primary btn-block">
            <i class="fas fa-save mr-1"></i><span id="drawerBtnText">Save</span>
        </button>
    </div>
</div>

<script>
function escHtml(s) {
    return String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/"/g, '&quot;')
        .replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function addBulkRow(data) {
    data = data || {};
    var tbody = document.getElementById('bulkRows');
    var tr = document.createElement('tr');
    tr.innerHTML =
        '<td>' +
            '<input type="hidden" name="ids[]" value="' + (parseInt(data.id, 10) || 0) + '">' +
            '<input type="text" name="titles[]" class="form-control form-control-sm" value="' + escHtml(data.title || '') + '">' +
        '</td>' +
        '<td class="text-center"><input type="number" name="positions[]" class="form-control form-control-sm" min="0" step="1" value="' + (parseInt(data.position, 10) || 0) + '"></td>' +
        '<td class="text-center"><button type="button" class="btn btn-xs btn-outline-danger" title="Remove row" onclick="this.closest(\'tr\').remove()"><i class="fas fa-times"></i></button></td>';
    tbody.appendChild(tr);
}

function openDrawer(btn) {
    var tbody = document.getElementById('bulkRows');
    tbody.innerHTML = '';
    if (btn) {
        addBulkRow({
            id: btn.getAttribute('data-id'),
            title: btn.getAttribute('data-title'),
            position: btn.getAttribute('data-position')
        });
        document.getElementById('drawerTitle').textContent = 'Edit Designation';
        document.getElementById('drawerBtnText').textContent = 'Update';
    } else {
        addBulkRow();
        document.getElementById('drawerTitle').textContent = 'Add Designation';
        document.getElementById('drawerBtnText').textContent = 'Save';
    }
    document.getElementById('formDrawer').classList.add('open');
    document.getElementById('drawerBackdrop').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeDrawer() {
    document.getElementById('formDrawer').classList.remove('open');
    document.getElementById('drawerBackdrop').classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeDrawer();
});

document.addEventListener('DOMContentLoaded', function() {
    addBulkRow();
});
</script>