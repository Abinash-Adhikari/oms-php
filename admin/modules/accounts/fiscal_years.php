<?php
/**
 * SB-Tech — Accounts / Fiscal Years (US-FIN-01).
 * CRUD with one-open-FY rule; closing an FY makes it read-only for
 * postings (AC-FIN-01.1/01.2).
 */
$db = Database::instance();
$fys = $db->select(
    'SELECT f.*,
            (SELECT COUNT(*) FROM `tbl_ledger_particulars` lp WHERE lp.fiscal_year_id = f.id) AS posted_lines
     FROM `tbl_fiscal_years` f
     ORDER BY f.starting_date DESC'
);
?>

<!-- Data Table (full width) -->
<div class="card card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-calendar-alt mr-1"></i>Fiscal Years</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-primary btn-sm" onclick="openDrawer()">
                <i class="fas fa-plus mr-1"></i>New Fiscal Year
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover mb-0">
                <thead><tr><th>Title</th><th>Period</th><th>Status</th><th class="text-right">Posted lines</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($fys as $fy): ?>
                    <tr>
                        <td class="font-weight-bold"><?= e($fy['title']) ?></td>
                        <td><?= e(formatDateView($fy['starting_date'])) ?> → <?= e(formatDateView($fy['ending_date'])) ?></td>
                        <td>
                            <?php if ($fy['closing'] === 'Open'): ?>
                                <span class="badge badge-success">Open</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Closed</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right"><?= (int) $fy['posted_lines'] ?></td>
                        <td class="text-right">
                            <form action="operation.php?module=accounts&page=fiscal_years" method="post" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="<?= $fy['closing'] === 'Open' ? 'close_fy' : 'open_fy' ?>">
                                <input type="hidden" name="id" value="<?= (int) $fy['id'] ?>">
                                <button class="btn btn-xs btn-<?= $fy['closing'] === 'Open' ? 'warning' : 'success' ?> confirm-submit"
                                    data-confirm="<?= $fy['closing'] === 'Open' ? 'Close ' . e($fy['title']) . '? New postings in it will be blocked.' : 'Reopen ' . e($fy['title']) . '?' ?>">
                                    <?= $fy['closing'] === 'Open' ? 'Close' : 'Reopen' ?>
                                </button>
                            </form>
                            <form action="operation.php?module=accounts&page=fiscal_years" method="post" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete_fy">
                                <input type="hidden" name="id" value="<?= (int) $fy['id'] ?>">
                                <button class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Delete fiscal year <?= e($fy['title']) ?>? This is blocked while any voucher lines exist."><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$fys): ?><tr><td colspan="5" class="text-center text-muted">No fiscal years yet.</td></tr><?php endif; ?>
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
        <h3><i class="fas fa-calendar-plus"></i>New Fiscal Year</h3>
        <button type="button" class="cms-drawer-close" onclick="closeDrawer()" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="cms-drawer-body">
        <form action="operation.php?module=accounts&page=fiscal_years" method="post" id="drawerForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_fy">
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" class="form-control" required placeholder="e.g. 2027/28">
            </div>
            <div class="form-group">
                <label>Starting date *</label>
                <input type="date" name="starting_date" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Ending date *</label>
                <input type="date" name="ending_date" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="closing" class="form-control">
                    <option value="Open">Open (postings allowed)</option>
                    <option value="Closed">Closed (read-only)</option>
                </select>
            </div>
        </form>
    </div>
    <div class="cms-drawer-footer">
        <button type="submit" form="drawerForm" class="btn btn-primary btn-block">
            <i class="fas fa-save mr-1"></i>Save Fiscal Year
        </button>
    </div>
</div>

<script>
function openDrawer() {
    var drawer = document.getElementById('formDrawer');
    var backdrop = document.getElementById('drawerBackdrop');
    drawer.classList.add('open');
    backdrop.classList.add('active');
    document.body.style.overflow = 'hidden';
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
</script>
