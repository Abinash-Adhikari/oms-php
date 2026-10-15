<?php
/**
 * SB-Tech — My Office / My Payslips.
 * Every staff member sees only their own payslips from approved runs.
 * ?line=ID shows one; &print=1 / &pdf=1 via the shared payroll renderer.
 */
$db = Database::instance();
$me = (int) Auth::id();
$select = "SELECT l.*, r.period_label, r.period_start, r.period_end, r.status AS run_status, r.period_key
           FROM tbl_payroll_run_lines l JOIN tbl_payroll_runs r ON r.id = l.run_id
           WHERE l.staff_id = ? AND l.excluded = 0 AND r.status IN ('Approved','Posted','Paid')";
$lineId = (int) ($_GET['line'] ?? 0);

if ($lineId) {
    $slipLines = $db->select($select . ' AND l.id = ?', [$me, $lineId]);
    if (!$slipLines) {
        echo '<div class="callout callout-warning"><h5>Payslip not available</h5><p>It may belong to someone else or not be approved yet.</p></div>';
        return;
    }
    $slipFilename = 'Payslip ' . $slipLines[0]['period_key'];
    if (!empty($_GET['pdf']) || !empty($_GET['print'])) {
        include __DIR__ . '/../payroll/includes/payslip_render.php';
    }
    $self = pageUrl('my_office', 'payslips') . '&line=' . $lineId;
    ?>
    <div class="ana-toolbar">
        <div class="d-flex align-items-center">
            <a href="<?= e(pageUrl('my_office', 'payslips')) ?>" class="btn btn-sm btn-outline-secondary mr-2" aria-label="Back to my payslips"><i class="fas fa-arrow-left"></i></a>
            <h2 class="ana-toolbar__title">Payslip — <?= e($slipLines[0]['period_label']) ?></h2>
        </div>
        <div class="ana-toolbar__actions">
            <a href="<?= e($self . '&print=1') ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><i class="fas fa-print mr-1"></i>Print</a>
            <a href="<?= e($self . '&pdf=1') ?>" class="btn btn-sm btn-primary"><i class="fas fa-file-pdf mr-1"></i>Download PDF</a>
        </div>
    </div>
    <div class="ana-card p-0" style="overflow:hidden">
        <iframe src="<?= e($self . '&print=1') ?>" title="Payslip" style="width:100%;height:80vh;border:0;background:#fff"></iframe>
    </div>
    <?php
    return;
}

$slips = $db->select($select . ' ORDER BY r.period_start DESC', [$me]);
$runSvc = new PayrollRunService($db);
$myAdvances = array_map(static fn ($a) => $a + ['outstanding' => $runSvc->advanceOutstanding((int) $a['id'])],
    $db->select("SELECT * FROM tbl_staff_advances WHERE staff_id = ? AND status = 'Active' ORDER BY issued_on", [$me]));
$ytdNet = 0.0;
$ytdTds = 0.0;
$taxYear = (new PayrollService($db))->taxYearFor(date('Y-m-d'));
foreach ($slips as $s) {
    if ($taxYear && $s['period_start'] >= $taxYear['start_date'] && $s['period_start'] <= $taxYear['end_date']) {
        $ytdNet += (float) $s['net'];
        $ytdTds += (float) $s['tds'];
    }
}
?>
<div class="ana-toolbar">
    <div>
        <h2 class="ana-toolbar__title">My payslips</h2>
        <p class="ana-toolbar__sub">Approved payslips only. Contact HR if something looks wrong.</p>
    </div>
</div>

<?php foreach ($myAdvances as $adv): ?>
    <div class="callout callout-info small mb-3"><i class="fas fa-hand-holding-usd mr-1"></i><?= e($adv['title']) ?>: NPR <?= e(number_format($adv['outstanding'], 2)) ?> still to be recovered, NPR <?= e(number_format(min((float) $adv['monthly_installment'], $adv['outstanding']), 2)) ?> per month from your salary.</div>
<?php endforeach; ?>

<?php if ($slips): ?>
    <div class="ana-grid" style="--ana-cols:3">
        <?= stat_tile(['label' => 'Latest net pay', 'icon' => 'fas fa-wallet', 'tone' => 'success',
            'value' => 'NPR ' . number_format((float) $slips[0]['net'], 2), 'sub' => $slips[0]['period_label']]) ?>
        <?= stat_tile(['label' => 'Net pay this tax year', 'icon' => 'fas fa-coins', 'tone' => 'primary',
            'value' => format_compact_money($ytdNet, 'NPR', 'NPR 0'), 'sub' => $taxYear ? 'FY ' . $taxYear['title'] : '']) ?>
        <?= stat_tile(['label' => 'Tax withheld this year', 'icon' => 'fas fa-percentage', 'tone' => 'neutral',
            'value' => format_compact_money($ytdTds, 'NPR', 'NPR 0')]) ?>
    </div>
<?php endif; ?>

<div class="ana-card ana-card--flush">
    <div class="table-responsive">
        <table class="table table-sm ana-table">
            <thead><tr><th>Month</th><th class="text-right">Days paid</th><th class="text-right">Gross</th><th class="text-right">Deductions</th><th class="text-right">Net pay</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($slips as $s): ?>
                <tr>
                    <td><strong><?= e($s['period_label']) ?></strong></td>
                    <td class="text-right"><?= e(rtrim(rtrim(number_format((float) $s['payable_days'], 2), '0'), '.')) ?> / <?= (int) $s['period_days'] ?></td>
                    <td class="text-right"><?= e(formatMoney($s['gross'])) ?></td>
                    <td class="text-right"><?= e(formatMoney((float) $s['employee_funds'] + (float) $s['tds'] + (float) $s['other_deductions'])) ?></td>
                    <td class="text-right font-weight-bold"><?= e(formatMoney($s['net'])) ?></td>
                    <td class="text-right text-nowrap">
                        <a class="btn btn-xs btn-outline-primary" href="<?= e(pageUrl('my_office', 'payslips') . '&line=' . (int) $s['id']) ?>">View</a>
                        <a class="btn btn-xs btn-outline-secondary" href="<?= e(pageUrl('my_office', 'payslips') . '&line=' . (int) $s['id'] . '&pdf=1') ?>" aria-label="Download PDF"><i class="fas fa-file-pdf"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$slips): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No payslips yet — they appear here once a payroll run is approved.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
