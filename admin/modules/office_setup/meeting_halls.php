<?php
/**
 * SB-Tech — Office Setup / Meeting Halls (US-SET-04.2).
 * Halls are selectable as venues when creating meetings/events.
 */
$db = Database::instance();
$edit = null;
if (isset($_GET['id'])) {
    $edit = $db->selectOne('SELECT * FROM `tbl_office_meeting_hall_setup` WHERE `id` = ?', [(int) $_GET['id']]);
}
$rows = $db->select('SELECT * FROM `tbl_office_meeting_hall_setup` ORDER BY hall_name');
$drawerOpen = ($edit !== null);
?>

<!-- Data Table (full width) -->
<div class="card card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-door-open mr-1"></i>Meeting Halls</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary btn-sm" onclick="openDrawer()">
                <i class="fas fa-plus mr-1"></i>Add Hall
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover mb-0">
                <thead><tr><th>#</th><th>Hall Name</th><th>Occupancy</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $i => $r): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= e($r['hall_name']) ?></td>
                        <td><?= $r['occupancy'] !== null ? (int) $r['occupancy'] : '—' ?></td>
                        <td class="text-right">
                            <button type="button" class="btn btn-xs btn-outline-primary" onclick="openDrawer(<?= (int) $r['id'] ?>)"><i class="fas fa-edit"></i></button>
                            <form action="operation.php?module=office_setup&page=meeting_halls" method="post" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <button type="submit" class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete hall '<?= e($r['hall_name']) ?>'?"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="4" class="text-center text-muted">No meeting halls yet.</td></tr><?php endif; ?>
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
        <h3><i class="fas fa-door-open"></i><span id="drawerTitle"><?= $edit ? 'Edit' : 'Add' ?> Meeting Hall</span></h3>
        <button type="button" class="cms-drawer-close" onclick="closeDrawer()" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="cms-drawer-body">
        <form action="operation.php?module=office_setup&page=meeting_halls" method="post" id="drawerForm">
            <?= csrfField() ?>
            <input type="hidden" name="id" id="formId" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
            <div class="form-group">
                <label>Hall name *</label>
                <input type="text" name="hall_name" class="form-control" required value="<?= $edit ? e($edit['hall_name']) : '' ?>">
            </div>
            <div class="form-group">
                <label>Occupancy</label>
                <input type="number" name="occupancy" class="form-control" min="1" value="<?= $edit ? (int) $edit['occupancy'] : '' ?>">
            </div>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="drawerForm" class="btn btn-primary btn-block">
            <i class="fas fa-save mr-1"></i><span id="drawerBtnText"><?= $edit ? 'Update' : 'Save' ?></span>
        </button>
    </div>
</div>

<script>
function openDrawer(editId) {
    var drawer = document.getElementById('formDrawer');
    var backdrop = document.getElementById('drawerBackdrop');
    drawer.classList.add('open');
    backdrop.classList.add('active');
    document.body.style.overflow = 'hidden';

    if (editId) {
        window.location.href = '<?= pageUrl('office_setup', 'meeting_halls') ?>&id=' + editId;
    }
}

function closeDrawer() {
    var drawer = document.getElementById('formDrawer');
    var backdrop = document.getElementById('drawerBackdrop');
    drawer.classList.remove('open');
    backdrop.classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeDrawer();
});

<?php if ($drawerOpen): ?>
document.addEventListener('DOMContentLoaded', function() {
    openDrawer();
});
<?php endif; ?>
</script>
