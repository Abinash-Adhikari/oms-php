<?php
/**
 * SB-Tech — Payroll / Payslip (payroll roles).
 * ?line=ID one payslip · ?run=ID every included line of a run.
 * &print=1 printable page · &pdf=1 PDF download (payslip_render.php exits).
 */
if (!Auth::hasSpecial('manage_payroll') && !Auth::hasSpecial('approve_payroll')) {
    echo '<div class="callout callout-warning"><h5>Payroll access required</h5></div>';
    return;
}
$db = Database::instance();
$lineId = (int) ($_GET['line'] ?? 0);
$runId = (int) ($_GET['run'] ?? 0);
$select = 'SELECT l.*, r.period_label, r.period_start, r.period_end, r.status AS run_status, r.period_key
           FROM tbl_payroll_run_lines l JOIN tbl_payroll_runs r ON r.id = l.run_id';
$slipLines = $lineId
    ? $db->select($select . ' WHERE l.id = ?', [$lineId])
    : $db->select($select . ' WHERE l.run_id = ? AND l.excluded = 0 ORDER BY l.fullname', [$runId]);

if (!$slipLines) {
    echo '<div class="callout callout-danger"><h5>Payslip not found</h5></div>';
    return;
}
$first = $slipLines[0];
$slipFilename = 'Payslip ' . $first['period_key'] . ($lineId ? ' ' . $first['fullname'] : ' all staff');

if (!empty($_GET['pdf']) || !empty($_GET['print'])) {
    include __DIR__ . '/includes/payslip_render.php';
}
$self = pageUrl('payroll', 'payslip') . ($lineId ? '&line=' . $lineId : '&run=' . $runId);
?>
<div class="ana-toolbar">
    <div class="d-flex align-items-center">
        <a href="<?= e(pageUrl('payroll', 'run') . '&id=' . (int) $first['run_id']) ?>" class="btn btn-sm btn-outline-secondary mr-2" aria-label="Back to run"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h2 class="ana-toolbar__title">Payslip<?= $lineId ? ' — ' . e($first['fullname']) : 's — all staff (' . count($slipLines) . ')' ?></h2>
            <p class="ana-toolbar__sub"><?= e($first['period_label']) ?> · run <?= e($first['run_status']) ?></p>
        </div>
    </div>
    <div class="ana-toolbar__actions">
        <a href="<?= e($self . '&print=1') ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><i class="fas fa-print mr-1"></i>Print</a>
        <a href="<?= e($self . '&pdf=1') ?>" class="btn btn-sm btn-primary"><i class="fas fa-file-pdf mr-1"></i>Download PDF</a>
    </div>
</div>
<div class="ana-card p-0" style="overflow:hidden">
    <iframe src="<?= e($self . '&print=1') ?>" title="Payslip preview" style="width:100%;height:80vh;border:0;background:#fff"></iframe>
</div>
