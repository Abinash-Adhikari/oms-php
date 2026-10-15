<?php
/**
 * SB-Tech — Payroll / Advances.
 * Salary advances and staff loans, recovered automatically through payroll
 * in monthly instalments (in full when the person leaves). Recovered and
 * outstanding amounts come from approved payroll runs.
 */
if (!Auth::hasSpecial('manage_payroll')) {
    echo '<div class="callout callout-warning"><h5>Payroll access required</h5><p>Ask an administrator for the <b>Manage payroll</b> permission.</p></div>';
    return;
}
$db = Database::instance();
$runs = new PayrollRunService($db);
$posting = new PayrollPostingService($db);
$today = date('Y-m-d');
$showClosed = !empty($_GET['all']);

$advances = $db->select(
    'SELECT a.*, u.fullname, u.status AS staff_status FROM tbl_staff_advances a JOIN tbl_users_login u ON u.id = a.staff_id'
    . ($showClosed ? '' : " WHERE a.status = 'Active'")
    . ' ORDER BY a.status, a.issued_on DESC, a.id DESC'
);
foreach ($advances as &$a) {
    $a['outstanding'] = $runs->advanceOutstanding((int) $a['id']);
    $a['recovered'] = round((float) $a['amount'] - $a['outstanding'], 2);
    $a['voucher'] = $posting->voucher('payment', $a['payment_voucher_id'] ? (int) $a['payment_voucher_id'] : null);
}
unset($a);
$active = array_filter($advances, static fn ($a) => $a['status'] === 'Active');
$outstanding = array_sum(array_column($active, 'outstanding'));
$monthly = array_sum(array_map(static fn ($a) => min((float) $a['monthly_installment'], $a['outstanding']), $active));

$staffs = $db->select("SELECT id, fullname FROM tbl_users_login WHERE status = 'Active' ORDER BY fullname");
$months = $db->select(
    'SELECT nepali_year, month_code FROM tbl_calendar WHERE eng_end_date >= ? ORDER BY eng_start_date LIMIT 6',
    [$today]
);
$payTerminal = (int) ($posting->settings()['payment_terminal_id'] ?? 0);
$money = static fn ($v): string => number_format((float) $v, 2);
$pill = ['Active' => 'warning', 'Closed' => 'good', 'Cancelled' => ''];
?>

<div class="ana-toolbar">
    <div>
        <h2 class="ana-toolbar__title">Staff advances</h2>
        <p class="ana-toolbar__sub">Recovered through payroll each month; the full balance is recovered when someone leaves.</p>
    </div>
    <div class="ana-toolbar__actions">
        <a href="<?= e(pageUrl('payroll', 'advances') . ($showClosed ? '' : '&all=1')) ?>" class="btn btn-sm btn-outline-secondary"><?= $showClosed ? 'Active only' : 'Show closed' ?></a>
        <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#advanceModal"><i class="fas fa-plus mr-1"></i>New advance</button>
    </div>
</div>

<div class="ana-grid" style="--ana-cols:3">
    <?= stat_tile(['label' => 'Outstanding', 'icon' => 'fas fa-hand-holding-usd', 'tone' => 'warning',
        'value' => format_compact_money($outstanding, 'NPR', 'NPR 0'), 'sub' => count($active) . ' active advance' . (count($active) === 1 ? '' : 's')]) ?>
    <?= stat_tile(['label' => 'Recovered next payroll', 'icon' => 'fas fa-undo', 'tone' => 'primary',
        'value' => format_compact_money($monthly, 'NPR', 'NPR 0'), 'sub' => 'sum of monthly instalments']) ?>
    <?= stat_tile(['label' => 'Staff with advances', 'icon' => 'fas fa-users', 'tone' => 'neutral',
        'value' => count(array_unique(array_column($active, 'staff_id')))]) ?>
</div>

<div class="ana-card ana-card--flush">
    <div class="table-responsive">
        <table class="table table-sm ana-table">
            <thead><tr><th>Staff</th><th>Advance</th><th class="text-right">Amount</th><th class="text-right">Per month</th><th style="width:20%">Recovered</th><th class="text-right">Outstanding</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($advances as $a): ?>
                <tr>
                    <td><strong><?= e($a['fullname']) ?></strong><?= $a['staff_status'] === 'Terminated' ? ' <span class="ana-pill ana-pill--critical">left</span>' : '' ?></td>
                    <td><?= e($a['title']) ?><br><small class="text-muted">issued <?= e(formatDateView($a['issued_on'])) ?><?= $a['start_period_key'] ? ' · from ' . e(bsMonthName((int) substr($a['start_period_key'], 5, 2)) . ' ' . substr($a['start_period_key'], 0, 4)) : '' ?><?= $a['voucher'] ? ' · ' . e($a['voucher']['voucher_no']) . ' (' . e(strtolower($a['voucher']['status'])) . ')' : '' ?></small></td>
                    <td class="text-right"><?= e($money($a['amount'])) ?></td>
                    <td class="text-right"><?= e($money($a['monthly_installment'])) ?></td>
                    <td><?= meter_bar(percent_of($a['recovered'], (float) $a['amount']), 'good') ?></td>
                    <td class="text-right font-weight-bold"><?= e($money($a['outstanding'])) ?></td>
                    <td><span class="ana-pill<?= $pill[$a['status']] ? ' ana-pill--' . e($pill[$a['status']]) : '' ?>"><?= e($a['status']) ?></span></td>
                    <td class="text-right text-nowrap">
                        <?php if ($a['status'] === 'Active'): ?>
                            <button type="button" class="btn btn-xs btn-outline-primary" onclick='openEdit(<?= e(json_encode(['id' => (int) $a['id'], 'title' => $a['title'], 'monthly_installment' => (float) $a['monthly_installment'], 'notes' => $a['notes'], 'name' => $a['fullname']], JSON_UNESCAPED_UNICODE)) ?>)'>Edit</button>
                            <form action="operation.php?module=payroll&page=advances" method="post" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                <?php if ($a['recovered'] <= 0.005): ?>
                                    <input type="hidden" name="action" value="cancel">
                                    <button class="btn btn-xs btn-outline-danger confirm-submit" data-confirm="Cancel this advance? Its pending payment voucher (if any) is removed.">Cancel</button>
                                <?php else: ?>
                                    <input type="hidden" name="action" value="close">
                                    <button class="btn btn-xs btn-outline-secondary confirm-submit" data-confirm="Stop recovering? NPR <?= e($money($a['outstanding'])) ?> stays unrecovered on the Staff Advances account.">Close</button>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$advances): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No <?= $showClosed ? '' : 'active ' ?>advances.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="advanceModal" tabindex="-1" role="dialog" aria-labelledby="advanceTitle" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form class="modal-content" action="operation.php?module=payroll&page=advances" method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-header"><h5 class="modal-title" id="advanceTitle">New advance</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
            <div class="modal-body">
                <div class="form-group"><label class="small mb-1" for="advStaff">Staff member</label>
                    <select id="advStaff" name="staff_id" class="form-control" required>
                        <option value="">—</option>
                        <?php foreach ($staffs as $st): ?><option value="<?= (int) $st['id'] ?>"><?= e($st['fullname']) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label class="small mb-1" for="advTitle">Description</label>
                    <input type="text" id="advTitle" name="title" maxlength="191" class="form-control" value="Salary advance" required></div>
                <div class="form-row">
                    <div class="form-group col-6"><label class="small mb-1" for="advAmount">Amount (NPR)</label>
                        <input type="number" id="advAmount" name="amount" step="0.01" min="1" class="form-control" required></div>
                    <div class="form-group col-6"><label class="small mb-1" for="advInst">Recover per month</label>
                        <input type="number" id="advInst" name="monthly_installment" step="0.01" min="1" class="form-control" required></div>
                </div>
                <div class="form-row">
                    <div class="form-group col-6"><label class="small mb-1" for="advIssued">Issued on</label>
                        <input type="date" id="advIssued" name="issued_on" class="form-control" value="<?= e($today) ?>" max="<?= e($today) ?>" required></div>
                    <div class="form-group col-6"><label class="small mb-1" for="advStart">Start recovering in</label>
                        <select id="advStart" name="start_period_key" class="form-control">
                            <?php foreach ($months as $i => $m): $k = sprintf('%04d-%02d', $m['nepali_year'], $m['month_code']); ?>
                                <option value="<?= e($k) ?>" <?= $i === 0 ? 'selected' : '' ?>><?= e(bsMonthName((int) $m['month_code']) . ' ' . $m['nepali_year']) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                </div>
                <div class="form-group"><label class="small mb-1" for="advNotes">Notes</label>
                    <input type="text" id="advNotes" name="notes" maxlength="500" class="form-control"></div>
                <div class="custom-control custom-checkbox mb-2">
                    <input type="checkbox" class="custom-control-input" id="advVoucher" name="record_payment" value="1" checked>
                    <label class="custom-control-label" for="advVoucher">Record the payout in Accounts (Pending payment voucher: Dr Staff Advances / Cr below)</label>
                </div>
                <select name="payment_terminal_id" class="form-control form-control-sm" aria-label="Paid from">
                    <?php foreach (accountingTerminalOptions() as $group => $subs): foreach ($subs as $sub => $terms): ?>
                        <optgroup label="<?= e($group . ' / ' . $sub) ?>">
                            <?php foreach ($terms as $t): ?><option value="<?= (int) $t['id'] ?>" <?= (int) $t['id'] === $payTerminal ? 'selected' : '' ?>><?= e($t['title']) ?></option><?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; endforeach; ?>
                </select>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Create advance</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="editTitle" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form class="modal-content" action="operation.php?module=payroll&page=advances" method="post" id="editForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update"><input type="hidden" name="id">
            <div class="modal-header"><h5 class="modal-title" id="editTitle">Edit advance</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
            <div class="modal-body">
                <div class="form-group"><label class="small mb-1">Description</label><input type="text" name="title" maxlength="191" class="form-control" required></div>
                <div class="form-group"><label class="small mb-1">Recover per month</label><input type="number" name="monthly_installment" step="0.01" min="1" class="form-control" required></div>
                <div class="form-group"><label class="small mb-1">Notes</label><input type="text" name="notes" maxlength="500" class="form-control"></div>
                <p class="small text-muted mb-0">The amount can’t change once issued — close this advance and create a new one instead.</p>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
    </div>
</div>
<script>
function openEdit(a) {
    var f = document.getElementById('editForm');
    f.elements.id.value = a.id;
    f.elements.title.value = a.title;
    f.elements.monthly_installment.value = a.monthly_installment;
    f.elements.notes.value = a.notes || '';
    document.getElementById('editTitle').textContent = 'Edit advance — ' + a.name;
    $('#editModal').modal('show');
}
</script>
