<?php
/**
 * SB-Tech — Correspondence Module Home.
 * Landing card for the two official registers (docs/DARTA_CHALANI_PLAN.md).
 */
$db = Database::instance();

$currentFy = correspondence_current_fy($db);
$fiscalYears = $db->select('SELECT * FROM `tbl_fiscal_years` ORDER BY `starting_date` DESC');

$fyWhere = $currentFy ? ' AND `fiscal_year_id` = ?' : '';
$fyParams = $currentFy ? [(int) $currentFy['id']] : [];

$pendingDarta = (int) ($db->selectOne(
    "SELECT COUNT(*) AS c FROM `tbl_darta_entries` WHERE `status` IN ('Pending','In Progress')" . $fyWhere,
    $fyParams
)['c'] ?? 0);
$totalDarta = (int) ($db->selectOne(
    'SELECT COUNT(*) AS c FROM `tbl_darta_entries` WHERE 1=1' . $fyWhere,
    $fyParams
)['c'] ?? 0);
$reservedChalani = (int) ($db->selectOne(
    "SELECT COUNT(*) AS c FROM `tbl_chalani_entries` WHERE `status` = 'Reserved'" . $fyWhere,
    $fyParams
)['c'] ?? 0);
$totalChalani = (int) ($db->selectOne(
    'SELECT COUNT(*) AS c FROM `tbl_chalani_entries` WHERE 1=1' . $fyWhere,
    $fyParams
)['c'] ?? 0);
?>
<div class="row">
    <div class="col-md-8">
        <?php if (!$currentFy): ?>
        <div class="callout callout-warning">
            <h5><i class="fas fa-exclamation-triangle mr-1"></i>No fiscal year covers today</h5>
            <p class="mb-0">Register numbers cannot be allocated. Create or open the current fiscal year under
                <a href="<?= e(pageUrl('accounts', 'fiscal_years')) ?>">Setup → Fiscal Years</a> first.</p>
        </div>
        <?php endif; ?>

        <div class="card card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-book-open mr-1"></i>Correspondence Registers</h3>
                <?php if ($currentFy): ?>
                <div class="card-tools"><span class="badge badge-primary">FY <?= e($currentFy['title']) ?></span></div>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-box">
                            <span class="info-box-icon bg-warning"><i class="fas fa-inbox"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Darta pending disposal</span>
                                <span class="info-box-number"><?= $pendingDarta ?> <small class="text-muted">/ <?= $totalDarta ?> total</small></span>
                                <a href="<?= e(pageUrl('correspondence', 'darta')) ?>" class="small">Open inward register →</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-box">
                            <span class="info-box-icon bg-info"><i class="fas fa-share-square"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Chalani reserved (not yet dispatched)</span>
                                <span class="info-box-number"><?= $reservedChalani ?> <small class="text-muted">/ <?= $totalChalani ?> total</small></span>
                                <a href="<?= e(pageUrl('correspondence', 'chalani')) ?>" class="small">Open outward register →</a>
                            </div>
                        </div>
                    </div>
                </div>
                <p class="text-muted small mb-0">
                    Numbers are allocated per fiscal year and cannot be edited or reused
                    (<code>D-12/<?= e($currentFy['title'] ?? 'YYYY-YY') ?></code> / <code>C-7/<?= e($currentFy['title'] ?? 'YYYY-YY') ?></code>).
                    Cancelled entries keep their number so the register sequence never has gaps.
                </p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card card-outline">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-bolt mr-1"></i>Quick actions</h3></div>
            <div class="card-body">
                <a href="<?= e(pageUrl('correspondence', 'darta') . '&new=1') ?>" class="btn btn-primary btn-block mb-2 <?= $currentFy ? '' : 'disabled' ?>">
                    <i class="fas fa-plus mr-1"></i>Register incoming letter (Darta)
                </a>
                <a href="<?= e(pageUrl('correspondence', 'chalani') . '&new=1') ?>" class="btn btn-outline-primary btn-block mb-2 <?= $currentFy ? '' : 'disabled' ?>">
                    <i class="fas fa-plus mr-1"></i>Reserve outgoing number (Chalani)
                </a>
                <a href="<?= e(pageUrl('correspondence', 'chalani')) ?>" class="btn btn-outline-secondary btn-block mb-0">
                    <i class="fas fa-paper-plane mr-1"></i>Mark dispatched
                </a>
            </div>
        </div>
        <div class="card card-outline">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-calendar mr-1"></i>Fiscal years</h3></div>
            <div class="list-group list-group-flush">
                <?php if (!$fiscalYears): ?>
                    <div class="list-group-item text-muted">No fiscal years yet.</div>
                <?php else: foreach (array_slice($fiscalYears, 0, 5) as $fy): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><?= e($fy['title']) ?></span>
                        <span class="badge badge-<?= $fy['closing'] === 'Open' ? 'success' : 'secondary' ?>"><?= e($fy['closing']) ?></span>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>
