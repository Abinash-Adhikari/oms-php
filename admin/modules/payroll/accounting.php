<?php
/**
 * SB-Tech — Payroll / Accounting Setup.
 * Which chart-of-account terminals a payroll run posts to. Pre-filled by
 * seed-table-account_terminals-payroll; any account can be chosen.
 */
if (!Auth::hasSpecial('manage_payroll')) {
    echo '<div class="callout callout-warning"><h5>Payroll access required</h5><p>Ask an administrator for the <b>Manage payroll</b> permission.</p></div>';
    return;
}
$db = Database::instance();
$posting = new PayrollPostingService($db);
$settings = $posting->settings();
$funds = $db->select('SELECT id, code, name, payable_terminal_id, is_active FROM tbl_payroll_funds ORDER BY is_active DESC, code');
$tree = accountingTerminalOptions();

$fields = [
    'salary_expense_terminal_id'        => ['Salary expense', 'Debited with gross pay (expense)'],
    'employer_contribution_terminal_id' => ['Employer fund contributions', 'Debited with the employer’s SSF / PF share (expense)'],
    'staff_payable_terminal_id'         => ['Staff payable', 'Credited with net pay; cleared when salaries are paid (liability)'],
    'tds_payable_terminal_id'           => ['TDS payable', 'Credited with salary TDS withheld until it is remitted (liability)'],
    'other_deductions_terminal_id'      => ['Staff advances', 'Debited when an advance is paid out, credited as payroll recovers it (asset)'],
    'deductions_terminal_id'            => ['Other deductions', 'Credited with every other payroll deduction — canteen, fines, recoveries that aren’t advances'],
    'payment_terminal_id'               => ['Default “paid from” account', 'Suggested bank / cash account when recording payment'],
];
$select = static function (string $name, ?int $current) use ($tree): string {
    $html = '<select name="' . e($name) . '" class="form-control form-control-sm"><option value="">— not mapped —</option>';
    foreach ($tree as $group => $subs) {
        foreach ($subs as $sub => $terms) {
            $html .= '<optgroup label="' . e($group . ' / ' . $sub) . '">';
            foreach ($terms as $t) {
                $html .= '<option value="' . (int) $t['id'] . '"' . ((int) $t['id'] === (int) $current ? ' selected' : '') . '>' . e($t['title']) . '</option>';
            }
            $html .= '</optgroup>';
        }
    }

    return $html . '</select>';
};
$unmapped = count(array_filter(array_keys($fields), static fn ($k) => empty($settings[$k])))
    + count(array_filter($funds, static fn ($f) => $f['is_active'] && empty($f['payable_terminal_id'])));
?>

<div class="ana-toolbar">
    <div>
        <h2 class="ana-toolbar__title">Accounting setup</h2>
        <p class="ana-toolbar__sub">Where payroll posts in the chart of accounts. Add accounts in <a href="<?= e(pageUrl('accounts', 'chart_of_account')) ?>">Accounts → Chart of Accounts</a>.</p>
    </div>
</div>

<?php if ($unmapped): ?>
    <div class="callout callout-warning small"><?= (int) $unmapped ?> account<?= $unmapped === 1 ? ' is' : 's are' ?> not mapped — runs that need them can’t be posted.</div>
<?php endif; ?>

<form action="operation.php?module=payroll&page=accounting" method="post">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save">
    <div class="row ana-row">
        <div class="col-lg-7">
            <div class="ana-card">
                <div class="ana-card__head"><div><h3 class="ana-card__title">Payroll accounts</h3></div></div>
                <?php foreach ($fields as $key => [$label, $hint]): ?>
                    <div class="form-group">
                        <label class="small mb-1 font-weight-bold"><?= e($label) ?></label>
                        <?= $select($key, $settings[$key] ? (int) $settings[$key] : null) ?>
                        <small class="text-muted"><?= e($hint) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="ana-card">
                <div class="ana-card__head"><div><h3 class="ana-card__title">Fund payables</h3>
                    <p class="ana-card__hint">Credited with employee + employer contributions until remitted</p></div></div>
                <?php foreach ($funds as $f): ?>
                    <div class="form-group">
                        <label class="small mb-1 font-weight-bold"><?= e($f['code']) ?> · <?= e($f['name']) ?><?= $f['is_active'] ? '' : ' <span class="text-muted">(inactive)</span>' ?></label>
                        <?= $select('fund[' . (int) $f['id'] . ']', $f['payable_terminal_id'] ? (int) $f['payable_terminal_id'] : null) ?>
                    </div>
                <?php endforeach; ?>
                <?php if (!$funds): ?><p class="text-muted small">No funds yet.</p><?php endif; ?>
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i>Save accounting setup</button>
</form>
