<?php
/**
 * SB-Tech — Payslip renderer (shared by payroll/payslip and my_office/payslips).
 *
 * Expects:
 *   $slipLines    list of tbl_payroll_run_lines rows joined with run fields
 *                 (period_label, period_start, period_end, run_status)
 *   $slipFilename download name without extension
 * Modes: ?pdf=1 downloads a PDF, ?print=1 sends a clean printable page.
 * Both exit; the caller renders its own screen view otherwise.
 * show_page.php starts output buffering for these modes, so the admin chrome
 * already emitted is discarded.
 */
$slipDb = Database::instance();
$slipMoney = static fn ($v): string => number_format((float) $v, 2);

ob_start();
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . e($slipFilename) . '</title>'
    . '<style>
        .slip-meta { width:100%; border-collapse:collapse; font-size:.86em; margin-bottom:12px; }
        .slip-meta td { padding:3px 6px; vertical-align:top; }
        .slip-meta td.k { color:#64748b; width:18%; }
        .slip-cols { width:100%; border-collapse:collapse; font-size:.9em; }
        .slip-cols th { text-align:left; border-bottom:1.5px solid #0f172a; padding:6px; }
        .slip-cols td { padding:5px 6px; border-bottom:1px solid #e2e8f0; }
        .slip-cols .amt { text-align:right; white-space:nowrap; }
        .slip-total td { font-weight:700; border-top:1.5px solid #0f172a; border-bottom:none; }
        .slip-net { margin-top:14px; padding:10px 12px; border:1.5px solid #0f172a; font-size:1.05em; display:flex; justify-content:space-between; }
        .slip-note { margin-top:12px; font-size:.78em; color:#64748b; }
        .slip-break { page-break-after: always; break-after: page; }
    </style></head><body>';

foreach (array_values($slipLines) as $i => $L) {
    $items = $slipDb->select('SELECT * FROM `tbl_payroll_run_line_items` WHERE `line_id` = ? ORDER BY `position`, `id`', [(int) $L['id']]);
    $earnings = array_values(array_filter($items, static fn ($it) => $it['kind'] === 'earning'));
    $deductions = array_values(array_filter($items, static fn ($it) => in_array($it['kind'], ['fund_employee', 'tax', 'deduction'], true)));
    $employer = array_values(array_filter($items, static fn ($it) => $it['kind'] === 'fund_employer'));
    $totalDeductions = array_sum(array_map(static fn ($it) => (float) $it['amount'], $deductions));
    $rows = max(count($earnings), count($deductions));

    if ($i > 0) {
        echo '<div class="slip-break"></div>';
    }
    documentShellStart('Payslip', e($L['period_label']));
    ?>
    <table class="slip-meta">
        <tr><td class="k">Employee</td><td><strong><?= e($L['fullname']) ?></strong></td><td class="k">Pay period</td><td><?= e($L['period_label']) ?> (<?= e(formatDateView((string) $L['period_start'])) ?> – <?= e(formatDateView((string) $L['period_end'])) ?>)</td></tr>
        <tr><td class="k">Designation</td><td><?= e($L['designation'] ?: '—') ?><?= $L['department'] ? ', ' . e($L['department']) : '' ?></td><td class="k">Days paid</td><td><?= e(rtrim(rtrim(number_format((float) $L['payable_days'], 2), '0'), '.')) ?> of <?= (int) $L['period_days'] ?><?= (float) $L['payable_days'] < (int) $L['period_days'] ? ' (unpaid / outside employment deducted)' : '' ?></td></tr>
        <tr><td class="k">PAN</td><td><?= e($L['pan_num'] ?: '—') ?></td><td class="k">Paid to</td><td><?= e(trim(($L['bank'] ?? '') . ' ' . ($L['bank_account_num'] ?? '')) ?: '—') ?></td></tr>
    </table>

    <table class="slip-cols">
        <thead><tr><th>Earnings</th><th class="amt">Amount</th><th style="width:4%"></th><th>Deductions</th><th class="amt">Amount</th></tr></thead>
        <tbody>
        <?php for ($r = 0; $r < $rows; $r++):
            $er = $earnings[$r] ?? null;
            $de = $deductions[$r] ?? null;
            $deName = $de ? ($de['kind'] === 'fund_employee' ? $de['code'] . ' contribution' : $de['name']) : '';
        ?>
            <tr>
                <td><?= $er ? e($er['name']) : '' ?></td><td class="amt"><?= $er ? e($slipMoney($er['amount'])) : '' ?></td>
                <td></td>
                <td><?= e($deName) ?></td><td class="amt"><?= $de ? e($slipMoney($de['amount'])) : '' ?></td>
            </tr>
        <?php endfor; ?>
        <tr class="slip-total"><td>Gross pay</td><td class="amt"><?= e($slipMoney($L['gross'])) ?></td><td></td><td>Total deductions</td><td class="amt"><?= e($slipMoney($totalDeductions)) ?></td></tr>
        </tbody>
    </table>

    <div class="slip-net"><strong>Net pay</strong><strong>NPR <?= e($slipMoney($L['net'])) ?></strong></div>

    <?php if ($employer): ?>
        <p class="slip-note">Employer contributions (not deducted from your pay):
            <?= e(implode(' · ', array_map(static fn ($it) => $it['code'] . ' ' . $slipMoney($it['amount']), $employer))) ?></p>
    <?php endif; ?>
    <p class="slip-note">Computer-generated payslip<?= $L['run_status'] === 'Draft' ? ' — <strong>DRAFT, not yet approved</strong>' : '' ?>.</p>
    <?php
    documentShellEnd();
}
echo '</body></html>';
$slipHtml = (string) ob_get_clean();

if (!empty($_GET['pdf'])) {
    $pdf = new PdfGenerator();
    $pdf->html($slipHtml);
    $pdf->download(preg_replace('/[^A-Za-z0-9 _-]/', '', $slipFilename) . '.pdf');
    // download() exits.
}
while (ob_get_level()) {
    ob_end_clean();
}
echo $slipHtml;
exit;
