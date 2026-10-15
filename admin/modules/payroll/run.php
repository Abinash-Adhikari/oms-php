<?php
/**
 * SB-Tech — Payroll / Run detail (inner page of Payroll Runs).
 * Review lines, adjust a Draft (unpaid days, TDS override, exclusions,
 * one-off additions/deductions), approve, cancel, and open payslips.
 */
$canManage = Auth::hasSpecial('manage_payroll');
$canApprove = Auth::hasSpecial('approve_payroll');
if (!$canManage && !$canApprove) {
    echo '<div class="callout callout-warning"><h5>Payroll access required</h5></div>';
    return;
}
$db = Database::instance();
$run = $db->selectOne(
    'SELECT r.*, p.fullname AS prepared_name, a.fullname AS approved_name, c.fullname AS cancelled_name, t.title AS tax_year_title
     FROM tbl_payroll_runs r
     LEFT JOIN tbl_users_login p ON p.id = r.prepared_by
     LEFT JOIN tbl_users_login a ON a.id = r.approved_by
     LEFT JOIN tbl_users_login c ON c.id = r.cancelled_by
     LEFT JOIN tbl_payroll_tax_years t ON t.id = r.tax_year_id
     WHERE r.id = ?',
    [(int) ($_GET['id'] ?? 0)]
);
if (!$run) {
    echo '<div class="callout callout-danger"><h5>Payroll run not found</h5></div>';
    return;
}
$runId = (int) $run['id'];
$isDraft = $run['status'] === 'Draft';
$lines = $db->select('SELECT * FROM tbl_payroll_run_lines WHERE run_id = ? ORDER BY excluded, fullname', [$runId]);
$adjustments = [];
foreach ($db->select(
    'SELECT i.* FROM tbl_payroll_run_line_items i JOIN tbl_payroll_run_lines l ON l.id = i.line_id
     WHERE l.run_id = ? AND i.is_adjustment = 1 ORDER BY i.id',
    [$runId]
) as $it) {
    $adjustments[(int) $it['line_id']][] = ['id' => (int) $it['id'], 'name' => $it['name'], 'kind' => $it['kind'], 'amount' => (float) $it['amount'], 'taxable' => (int) $it['taxable']];
}
$withWarnings = count(array_filter($lines, static fn ($l) => !$l['excluded'] && $l['warnings']));
$selfPrepared = (int) $run['prepared_by'] === (int) Auth::id() && !Auth::isSuperAdmin();
$pill = ['Draft' => 'warning', 'Approved' => 'good', 'Posted' => 'good', 'Paid' => 'good', 'Cancelled' => ''];
$money = static fn ($v): string => number_format((float) $v, 2);
$days = static fn ($v): string => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
$op = 'operation.php?module=payroll&page=runs';
?>

<div class="ana-toolbar">
    <div class="d-flex align-items-center">
        <a href="<?= e(pageUrl('payroll', 'runs')) ?>" class="btn btn-sm btn-outline-secondary mr-2" aria-label="Back to payroll runs"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h2 class="ana-toolbar__title"><?= e($run['period_label']) ?> payroll <span class="ana-pill<?= $pill[$run['status']] ? ' ana-pill--' . e($pill[$run['status']]) : '' ?> ml-1"><?= e($run['status']) ?></span></h2>
            <p class="ana-toolbar__sub">
                <?= e(formatDateView($run['period_start'])) ?> – <?= e(formatDateView($run['period_end'])) ?> · <?= (int) $run['period_days'] ?> days
                · <?= $run['tax_year_title'] ? 'tax FY ' . e($run['tax_year_title']) . ', ' . (int) $run['months_remaining'] . ' month(s) left incl. this' : 'no tax year — TDS off' ?>
                · prepared by <?= e($run['prepared_name'] ?? '—') ?><?= $run['approved_name'] ? ', approved by ' . e($run['approved_name']) : '' ?>
            </p>
        </div>
    </div>
    <div class="ana-toolbar__actions">
        <?php if ($isDraft && $canManage): ?>
            <form action="<?= e($op) ?>" method="post">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="recalculate"><input type="hidden" name="run_id" value="<?= e($runId) ?>">
                <button class="btn btn-sm btn-outline-secondary" title="Re-read salary setups, attendance and leave"><i class="fas fa-sync-alt mr-1"></i>Recalculate</button>
            </form>
        <?php endif; ?>
        <?php if ($run['status'] !== 'Cancelled' && $lines): ?>
            <a href="<?= e(pageUrl('payroll', 'payslip') . '&run=' . $runId) ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-file-alt mr-1"></i>All payslips</a>
        <?php endif; ?>
        <?php if ($canManage && in_array($run['status'], ['Approved', 'Posted', 'Paid'], true)): ?>
            <form action="<?= e($op) ?>" method="post">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="email_payslips"><input type="hidden" name="run_id" value="<?= $runId ?>">
                <button class="btn btn-sm btn-outline-secondary confirm-submit" data-confirm="Email every staff member in this run a payslip-ready notice with a link (no attachment)?"><i class="fas fa-envelope mr-1"></i>Email notices</button>
            </form>
        <?php endif; ?>
        <?php if ($isDraft && $canApprove): ?>
            <form action="<?= e($op) ?>" method="post">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="approve"><input type="hidden" name="run_id" value="<?= e($runId) ?>">
                <button class="btn btn-sm btn-success confirm-submit" <?= $selfPrepared ? 'disabled title="You prepared this run — another approver must approve it"' : '' ?>
                        data-confirm="Approve <?= e($run['period_label']) ?> payroll for <?= (int) $run['headcount'] ?> staff, net NPR <?= e($money($run['total_net'])) ?>? Lines will be locked and staff notified.">
                    <i class="fas fa-check mr-1"></i>Approve</button>
            </form>
        <?php endif; ?>
        <?php if (($isDraft && ($canManage || $canApprove)) || ($run['status'] === 'Approved' && $canApprove)): ?>
            <button type="button" class="btn btn-sm btn-outline-danger" data-toggle="modal" data-target="#cancelModal"><i class="fas fa-ban mr-1"></i>Cancel run</button>
        <?php endif; ?>
    </div>
</div>

<?php if ($run['status'] === 'Cancelled'): ?>
    <div class="callout callout-secondary small">Cancelled by <?= e($run['cancelled_name'] ?? '—') ?> on <?= e(formatDateView((string) $run['cancelled_on'])) ?><?= $run['cancel_reason'] ? ' — ' . e($run['cancel_reason']) : '' ?>. Start the month again from Payroll Runs.</div>
<?php elseif ($isDraft && $selfPrepared && !$canApprove): ?>
    <div class="callout callout-info small">Draft — when it looks right, ask someone with the <b>Approve payroll</b> permission to approve it.</div>
<?php elseif ($isDraft && $selfPrepared): ?>
    <div class="callout callout-info small">You prepared this run, so a different approver must approve it.</div>
<?php endif; ?>

<div class="ana-grid" style="--ana-cols:5">
    <?= stat_tile(['label' => 'Staff paid', 'icon' => 'fas fa-users', 'tone' => 'info', 'value' => (int) $run['headcount'],
        'sub' => count($lines) - (int) $run['headcount'] > 0 ? (count($lines) - (int) $run['headcount']) . ' excluded' : 'everyone with a pay setup']) ?>
    <?= stat_tile(['label' => 'Gross pay', 'icon' => 'fas fa-coins', 'tone' => 'primary', 'value' => format_compact_money((float) $run['total_gross'], 'NPR', 'NPR 0')]) ?>
    <?= stat_tile(['label' => 'Deductions', 'icon' => 'fas fa-minus-circle', 'tone' => 'warning',
        'value' => format_compact_money((float) $run['total_employee_funds'] + (float) $run['total_tds'] + (float) $run['total_deductions'], 'NPR', 'NPR 0'),
        'sub' => 'TDS ' . format_compact_money((float) $run['total_tds'], 'NPR', 'NPR 0') . ' · funds ' . format_compact_money((float) $run['total_employee_funds'], 'NPR', 'NPR 0')]) ?>
    <?= stat_tile(['label' => 'Net pay', 'icon' => 'fas fa-wallet', 'tone' => 'success', 'value' => format_compact_money((float) $run['total_net'], 'NPR', 'NPR 0'),
        'sub' => 'NPR ' . $money($run['total_net'])]) ?>
    <?= stat_tile(['label' => 'Cost to company', 'icon' => 'fas fa-building', 'tone' => 'neutral',
        'value' => format_compact_money((float) $run['total_gross'] + (float) $run['total_employer_funds'], 'NPR', 'NPR 0'),
        'sub' => 'incl. employer funds ' . format_compact_money((float) $run['total_employer_funds'], 'NPR', 'NPR 0')]) ?>
</div>

<?php
// ── Accounts (P3): journal preview, posting, payment ────────────────────
$inAccounts = in_array($run['status'], ['Approved', 'Posted', 'Paid'], true);
if ($inAccounts):
    $posting = new PayrollPostingService($db);
    $journal = null;
    $journalError = null;
    try {
        $journal = $posting->journalFor($run);
    } catch (RuntimeException $e) {
        $journalError = $e->getMessage();
    }
    $jv = $posting->voucher('journal', $run['journal_voucher_id'] ? (int) $run['journal_voucher_id'] : null);
    $pv = $posting->voucher('payment', $run['payment_voucher_id'] ? (int) $run['payment_voucher_id'] : null);
    $defaultDate = min($run['period_end'], date('Y-m-d'));
    $payTerminal = (int) ($posting->settings()['payment_terminal_id'] ?? 0);
    $voucherPill = static function (?array $v, bool $expected): string {
        if (!$v) {
            return $expected ? '<span class="ana-pill ana-pill--critical">deleted in Accounts</span>' : '';
        }
        return '<span class="ana-pill' . ($v['status'] === 'Approved' ? ' ana-pill--good' : ' ana-pill--warning') . '">' . e($v['status'] === 'Approved' ? 'approved in Accounts' : 'awaiting accountant') . '</span>';
    };
?>
<div class="row ana-row">
    <div class="col-lg-8">
        <div class="ana-card">
            <div class="ana-card__head">
                <div>
                    <h3 class="ana-card__title">Accounting entry</h3>
                    <p class="ana-card__hint">Journal voucher for this run · vouchers are created Pending and approved by an accountant in Accounts → Posting</p>
                </div>
                <?php if ($canManage): ?><a class="ana-card__link" href="<?= e(pageUrl('payroll', 'accounting')) ?>">Accounting setup</a><?php endif; ?>
            </div>
            <?php if ($journalError): ?>
                <div class="alert alert-warning small mb-0"><?= e($journalError) ?></div>
            <?php else: ?>
                <table class="table table-sm ana-table mb-0">
                    <thead><tr><th>Account</th><th>Narration</th><th class="text-right">Debit</th><th class="text-right">Credit</th></tr></thead>
                    <tbody>
                    <?php foreach ($journal as $jl):
                        $meta = accountingTerminalMeta($db, (int) $jl['account_terminal_id']);
                    ?>
                        <tr>
                            <td><strong><?= e($meta['title'] ?? '#' . $jl['account_terminal_id']) ?></strong><br><small class="text-muted"><?= e($meta['group_title'] ?? '') ?></small></td>
                            <td class="small text-muted"><?= e(explode(' — ', $jl['remarks'])[0]) ?></td>
                            <td class="text-right"><?= $jl['debit'] > 0 ? e($money($jl['debit'])) : '' ?></td>
                            <td class="text-right"><?= $jl['credit'] > 0 ? e($money($jl['credit'])) : '' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr><td colspan="2">Balanced</td><td class="text-right"><?= e($money(array_sum(array_column($journal, 'debit')))) ?></td><td class="text-right"><?= e($money(array_sum(array_column($journal, 'credit')))) ?></td></tr></tfoot>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="ana-card">
            <div class="ana-card__head"><div><h3 class="ana-card__title">Posting &amp; payment</h3></div></div>
            <ul class="ana-list mb-3">
                <li>
                    <span class="ana-list__icon"><i class="fas fa-book" aria-hidden="true"></i></span>
                    <div class="ana-list__main">
                        <span class="ana-list__title">Journal <?= $jv ? e($jv['voucher_no']) : '' ?></span>
                        <span class="ana-list__meta"><?= $run['status'] === 'Approved' ? 'Not posted yet' : e('Posted ' . formatDateView((string) ($jv['voucher_date'] ?? $run['posted_on']))) ?></span>
                    </div>
                    <?= $run['status'] !== 'Approved' ? $voucherPill($jv, true) : '' ?>
                </li>
                <li>
                    <span class="ana-list__icon"><i class="fas fa-money-check-alt" aria-hidden="true"></i></span>
                    <div class="ana-list__main">
                        <span class="ana-list__title">Payment <?= $pv ? e($pv['voucher_no']) : '' ?></span>
                        <span class="ana-list__meta"><?= $run['status'] === 'Paid' ? e('Paid ' . formatDateView((string) ($pv['voucher_date'] ?? $run['paid_on']))) : 'Not recorded yet' ?></span>
                    </div>
                    <?= $run['status'] === 'Paid' ? $voucherPill($pv, true) : '' ?>
                </li>
            </ul>
            <?php if ($canManage): ?>
                <div class="d-flex flex-wrap" style="gap:.5rem">
                    <?php if ($run['status'] === 'Approved' && !$journalError): ?>
                        <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#postModal"><i class="fas fa-book mr-1"></i>Post to accounts</button>
                    <?php elseif ($run['status'] === 'Posted'): ?>
                        <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#payModal"><i class="fas fa-money-check-alt mr-1"></i>Record payment</button>
                        <?php if (!$jv || $jv['status'] === 'Pending'): ?>
                            <form action="<?= e($op) ?>" method="post"><?= csrfField() ?><input type="hidden" name="action" value="unpost"><input type="hidden" name="run_id" value="<?= $runId ?>">
                                <button class="btn btn-sm btn-outline-secondary confirm-submit" data-confirm="Remove the journal voucher and return this run to Approved?">Undo posting</button></form>
                        <?php endif; ?>
                    <?php elseif ($run['status'] === 'Paid'): ?>
                        <?php if (!$pv || $pv['status'] === 'Pending'): ?>
                            <form action="<?= e($op) ?>" method="post"><?= csrfField() ?><input type="hidden" name="action" value="unpay"><input type="hidden" name="run_id" value="<?= $runId ?>">
                                <button class="btn btn-sm btn-outline-secondary confirm-submit" data-confirm="Remove the payment voucher and return this run to Posted?">Undo payment</button></form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if (($jv && $jv['status'] === 'Approved' && $run['status'] === 'Posted') || ($pv && $pv['status'] === 'Approved')): ?>
                <p class="small text-muted mt-2 mb-0">Approved vouchers are locked here — to change them, an accountant un-approves in Accounts → Posting first.</p>
            <?php endif; ?>
            <div class="d-flex flex-wrap mt-3" style="gap:.5rem">
                <form action="<?= e($op) ?>" method="post"><?= csrfField() ?><input type="hidden" name="action" value="export_bank"><input type="hidden" name="run_id" value="<?= $runId ?>">
                    <button class="btn btn-sm btn-outline-success"><i class="fas fa-university mr-1"></i>Bank sheet</button></form>
                <form action="<?= e($op) ?>" method="post"><?= csrfField() ?><input type="hidden" name="action" value="export_register"><input type="hidden" name="run_id" value="<?= $runId ?>">
                    <button class="btn btn-sm btn-outline-success"><i class="fas fa-table mr-1"></i>Payroll register</button></form>
            </div>
        </div>
    </div>
</div>

<?php if ($canManage && $run['status'] === 'Approved' && !$journalError): ?>
<div class="modal fade" id="postModal" tabindex="-1" role="dialog" aria-labelledby="postTitle" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form class="modal-content" action="<?= e($op) ?>" method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="post"><input type="hidden" name="run_id" value="<?= $runId ?>">
            <div class="modal-header"><h5 class="modal-title" id="postTitle">Post <?= e($run['period_label']) ?> payroll</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
            <div class="modal-body">
                <p class="small">Creates one balanced journal voucher (NPR <?= e($money(array_sum(array_column($journal, 'debit')))) ?>) as <b>Pending</b>. It counts in the books once an accountant approves it.</p>
                <label class="small mb-1" for="postDate">Voucher date</label>
                <input type="date" id="postDate" name="date" class="form-control" value="<?= e($defaultDate) ?>" max="<?= e(date('Y-m-d')) ?>" required>
                <small class="text-muted">Usually the last day of the month; vouchers can’t be future-dated.</small>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Post</button></div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php if ($canManage && $run['status'] === 'Posted'): ?>
<div class="modal fade" id="payModal" tabindex="-1" role="dialog" aria-labelledby="payTitle" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form class="modal-content" action="<?= e($op) ?>" method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="pay"><input type="hidden" name="run_id" value="<?= $runId ?>">
            <div class="modal-header"><h5 class="modal-title" id="payTitle">Record salary payment</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
            <div class="modal-body">
                <p class="small">Creates a Pending payment voucher for net pay of <b>NPR <?= e($money($run['total_net'])) ?></b>: Dr Staff payable / Cr the account below.</p>
                <div class="form-group"><label class="small mb-1" for="payFrom">Paid from</label>
                    <select id="payFrom" name="payment_terminal_id" class="form-control" required>
                        <?php foreach (accountingTerminalOptions() as $group => $subs): ?>
                            <?php foreach ($subs as $sub => $terms): ?>
                                <optgroup label="<?= e($group . ' / ' . $sub) ?>">
                                    <?php foreach ($terms as $t): ?>
                                        <option value="<?= (int) $t['id'] ?>" <?= (int) $t['id'] === $payTerminal ? 'selected' : '' ?>><?= e($t['title']) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </select></div>
                <label class="small mb-1" for="payDate">Payment date</label>
                <input type="date" id="payDate" name="date" class="form-control" value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>" required>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Record payment</button></div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php if ($isDraft && $canManage && $lines): ?>
<details class="ana-card mb-3" style="padding:.85rem 1.25rem">
    <summary class="ana-card__title" style="cursor:pointer">Add to everyone — festival allowance, bonus or a common deduction</summary>
    <form action="<?= e($op) ?>" method="post" class="form-row align-items-end mt-3">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add_adjustment_all"><input type="hidden" name="run_id" value="<?= $runId ?>">
        <div class="col-md-3 mb-2"><label class="small mb-1">Description</label><input type="text" name="name" maxlength="191" class="form-control form-control-sm" value="Dashain allowance" required></div>
        <div class="col-md-2 mb-2"><label class="small mb-1">Type</label>
            <select name="kind" class="form-control form-control-sm"><option value="earning">Addition</option><option value="deduction">Deduction</option></select></div>
        <div class="col-md-3 mb-2"><label class="small mb-1">Amount is</label>
            <select name="mode" class="form-control form-control-sm">
                <option value="basic_months">months of basic pay</option>
                <option value="fixed">a fixed NPR amount</option>
                <option value="percent_of_basic">% of basic pay</option>
            </select></div>
        <div class="col-md-2 mb-2"><label class="small mb-1">Value</label><input type="number" name="value" step="0.01" min="0.01" class="form-control form-control-sm" value="1" required></div>
        <div class="col-md-2 mb-2"><div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="allTaxable" name="taxable" value="1" checked><label class="custom-control-label small" for="allTaxable">Taxable</label></div></div>
        <div class="col-12"><button type="submit" class="btn btn-sm btn-outline-primary confirm-submit" data-confirm="Add this to every included staff member in this run?"><i class="fas fa-users mr-1"></i>Add to <?= (int) $run['headcount'] ?> staff</button>
            <small class="text-muted ml-2">Dashain allowance under the Labour Act is usually one month’s basic. Each entry can be removed per person under Adjust.</small></div>
    </form>
</details>
<?php endif; ?>

<?php if ($withWarnings && $isDraft): ?>
    <div class="callout callout-warning small"><i class="fas fa-exclamation-triangle mr-1"></i><?= (int) $withWarnings ?> line<?= $withWarnings === 1 ? ' has' : 's have' ?> warnings — check them (hover the <i class="fas fa-exclamation-triangle"></i> icon) before approving.</div>
<?php endif; ?>

<div class="ana-card ana-card--flush">
    <div class="table-responsive">
        <table class="table table-sm ana-table">
            <thead><tr>
                <th>Staff</th><th class="text-right">Days paid</th><th class="text-right">Gross</th><th class="text-right">Funds</th>
                <th class="text-right">TDS</th><th class="text-right">Other</th><th class="text-right">Net pay</th><th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($lines as $l):
                $warn = $l['warnings'] ? (array) json_decode((string) $l['warnings'], true) : [];
                $lineAdj = $adjustments[(int) $l['id']] ?? [];
                $data = ['id' => (int) $l['id'], 'name' => $l['fullname'], 'period_days' => (int) $l['period_days'],
                    'unpaid_days' => (float) $l['unpaid_days'], 'unpaid_override' => $l['unpaid_override'] === null ? '' : (float) $l['unpaid_override'],
                    'tds_computed' => (float) $l['tds_computed'], 'tds_override' => $l['tds_override'] === null ? '' : (float) $l['tds_override'],
                    'excluded' => (int) $l['excluded'], 'adjustments' => $lineAdj];
            ?>
                <tr<?= $l['excluded'] ? ' class="text-muted" style="text-decoration:line-through"' : '' ?>>
                    <td>
                        <strong><?= e($l['fullname']) ?></strong>
                        <?php if ($warn): ?><i class="fas fa-exclamation-triangle text-warning ml-1" title="<?= e(implode("\n", $warn)) ?>" aria-label="Warnings: <?= e(implode(' ', $warn)) ?>"></i><?php endif; ?>
                        <br><small class="text-muted"><?= e(implode(' · ', array_filter([$l['designation'], $l['pay_type'] === 'Stipend' ? 'Stipend' : null, $l['employment_type'] !== 'Permanent' ? $l['employment_type'] : null, $lineAdj ? count($lineAdj) . ' adjustment' . (count($lineAdj) === 1 ? '' : 's') : null]))) ?></small>
                    </td>
                    <td class="text-right"><?= e($days($l['payable_days'])) ?> / <?= (int) $l['period_days'] ?>
                        <?php if ($l['unpaid_override'] !== null): ?><br><small class="text-warning">unpaid set to <?= e($days($l['unpaid_override'])) ?></small>
                        <?php elseif ((float) $l['unpaid_days'] > 0): ?><br><small class="text-muted"><?= e($days($l['unpaid_days'])) ?> unpaid</small><?php endif; ?>
                    </td>
                    <td class="text-right"><?= e($money($l['gross'])) ?></td>
                    <td class="text-right"><?= e($money($l['employee_funds'])) ?></td>
                    <td class="text-right"><?= e($money($l['tds'])) ?><?= $l['tds_override'] !== null ? '<br><small class="text-warning">manual (calc ' . e($money($l['tds_computed'])) . ')</small>' : '' ?></td>
                    <td class="text-right"><?= e($money($l['other_deductions'])) ?></td>
                    <td class="text-right font-weight-bold"><?= e($money($l['net'])) ?></td>
                    <td class="text-right text-nowrap">
                        <a class="btn btn-xs btn-outline-secondary" href="<?= e(pageUrl('payroll', 'payslip') . '&line=' . (int) $l['id']) ?>" aria-label="Payslip for <?= e($l['fullname']) ?>"><i class="fas fa-file-alt"></i></a>
                        <?php if ($isDraft && $canManage): ?>
                            <button type="button" class="btn btn-xs btn-outline-primary" onclick='openAdjust(<?= e(json_encode($data, JSON_UNESCAPED_UNICODE)) ?>)'>Adjust</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$lines): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Nobody to pay — set up pay under <a href="<?= e(pageUrl('payroll', 'salary_setup')) ?>">Salary Setup</a>, then Recalculate.</td></tr>
            <?php endif; ?>
            </tbody>
            <?php if ($lines): ?>
                <tfoot><tr><td>Total (<?= (int) $run['headcount'] ?>)</td><td></td><td class="text-right"><?= e($money($run['total_gross'])) ?></td><td class="text-right"><?= e($money($run['total_employee_funds'])) ?></td><td class="text-right"><?= e($money($run['total_tds'])) ?></td><td class="text-right"><?= e($money($run['total_deductions'])) ?></td><td class="text-right"><?= e($money($run['total_net'])) ?></td><td></td></tr></tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php if ($isDraft && $canManage): ?>
<div class="modal fade" id="adjustModal" tabindex="-1" role="dialog" aria-labelledby="adjustTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="adjustTitle">Adjust</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
            <div class="modal-body">
                <form action="<?= e($op) ?>" method="post" id="lineForm" class="mb-3">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_line"><input type="hidden" name="line_id">
                    <h6 class="ana-section-title mt-0">Days and tax</h6>
                    <div class="form-row">
                        <div class="form-group col-md-6"><label class="small mb-1">Unpaid days <span class="text-muted" id="unpaidHint"></span></label>
                            <input type="number" step="0.5" min="0" name="unpaid_override" class="form-control form-control-sm" placeholder="Use calculated">
                            <small class="text-muted">Leave empty to use attendance &amp; leave.</small></div>
                        <div class="form-group col-md-6"><label class="small mb-1">TDS for this month <span class="text-muted" id="tdsHint"></span></label>
                            <input type="number" step="0.01" min="0" name="tds_override" class="form-control form-control-sm" placeholder="Use calculated">
                            <small class="text-muted">Leave empty to use the calculated tax.</small></div>
                    </div>
                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" class="custom-control-input" id="lineExcluded" name="excluded" value="1">
                        <label class="custom-control-label" for="lineExcluded">Leave this person out of this month’s payroll</label>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-save mr-1"></i>Save &amp; recalculate</button>
                </form>

                <h6 class="ana-section-title">One-off additions &amp; deductions</h6>
                <p class="small text-muted">For this month only — e.g. Dashain allowance, arrears, an advance recovery. Taxable additions are taxed once, not spread over the year.</p>
                <ul class="ana-list mb-3" id="adjList"></ul>
                <form action="<?= e($op) ?>" method="post" class="form-row align-items-end">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add_adjustment"><input type="hidden" name="line_id" id="adjLineId">
                    <div class="col-md-3 mb-2"><label class="small mb-1">Type</label>
                        <select name="kind" class="form-control form-control-sm"><option value="earning">Addition</option><option value="deduction">Deduction</option></select></div>
                    <div class="col-md-4 mb-2"><label class="small mb-1">Description</label><input type="text" name="name" maxlength="191" class="form-control form-control-sm" required placeholder="e.g. Dashain allowance"></div>
                    <div class="col-md-3 mb-2"><label class="small mb-1">Amount (NPR)</label><input type="number" name="amount" step="0.01" min="0.01" class="form-control form-control-sm" required></div>
                    <div class="col-md-2 mb-2"><div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="adjTaxable" name="taxable" value="1" checked><label class="custom-control-label small" for="adjTaxable">Taxable</label></div></div>
                    <div class="col-12"><button type="submit" class="btn btn-sm btn-outline-primary"><i class="fas fa-plus mr-1"></i>Add</button></div>
                </form>
                <form action="<?= e($op) ?>" method="post" id="removeAdjForm" class="d-none">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="remove_adjustment"><input type="hidden" name="item_id">
                </form>
            </div>
        </div>
    </div>
</div>
<script>
function openAdjust(d) {
    var f = document.getElementById('lineForm');
    f.elements.line_id.value = d.id;
    f.elements.unpaid_override.value = d.unpaid_override;
    f.elements.unpaid_override.max = d.period_days;
    f.elements.tds_override.value = d.tds_override;
    f.elements.excluded.checked = d.excluded === 1;
    document.getElementById('unpaidHint').textContent = '(calculated: ' + d.unpaid_days + ')';
    document.getElementById('tdsHint').textContent = '(calculated: ' + Number(d.tds_computed).toLocaleString('en-IN', {minimumFractionDigits: 2}) + ')';
    document.getElementById('adjLineId').value = d.id;
    document.getElementById('adjustTitle').textContent = 'Adjust — ' + d.name;
    var list = document.getElementById('adjList');
    list.innerHTML = '';
    if (!d.adjustments.length) {
        var none = document.createElement('li');
        none.className = 'text-muted small';
        none.textContent = 'None yet.';
        list.appendChild(none);
    }
    d.adjustments.forEach(function (a) {
        var li = document.createElement('li');
        var main = document.createElement('div');
        main.className = 'ana-list__main';
        var t = document.createElement('span');
        t.className = 'ana-list__title';
        t.textContent = a.name;
        var m = document.createElement('span');
        m.className = 'ana-list__meta';
        m.textContent = (a.kind === 'earning' ? 'Addition' + (a.taxable ? ', taxable' : ', tax-free') : 'Deduction') + ' · NPR ' + Number(a.amount).toLocaleString('en-IN', {minimumFractionDigits: 2});
        main.appendChild(t);
        main.appendChild(m);
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-xs btn-outline-danger';
        btn.textContent = 'Remove';
        btn.onclick = function () {
            var rf = document.getElementById('removeAdjForm');
            rf.elements.item_id.value = a.id;
            rf.submit();
        };
        li.appendChild(main);
        li.appendChild(btn);
        list.appendChild(li);
    });
    $('#adjustModal').modal('show');
}
</script>
<?php endif; ?>

<div class="modal fade" id="cancelModal" tabindex="-1" role="dialog" aria-labelledby="cancelTitle" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form class="modal-content" action="<?= e($op) ?>" method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="cancel"><input type="hidden" name="run_id" value="<?= e($runId) ?>">
            <div class="modal-header"><h5 class="modal-title" id="cancelTitle">Cancel <?= e($run['period_label']) ?> payroll?</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
            <div class="modal-body">
                <p class="small">The run is kept for the record but no longer counts. You can start the month again.</p>
                <label class="small mb-1" for="cancelReason">Reason</label>
                <input type="text" id="cancelReason" name="reason" maxlength="255" class="form-control" required>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-danger">Cancel run</button></div>
        </form>
    </div>
</div>
