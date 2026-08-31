<?php
/**
 * SB-Tech — Accounts / Posting (US-FIN-03, US-FIN-09).
 * Tabbed voucher entry: Journal · Receipt · Payment · Contra · Purchase ·
 * Sales, plus the voucher audit log. Each tab is a generic voucher panel
 * driven by accountingVoucherConfig().
 */
$db = Database::instance();
$me = (int) Auth::id();
$canApprove = Auth::isSuperAdmin() || Auth::hasSpecial('approve_vouchers');

$postingTabs = [];
foreach (accountingVoucherConfig() as $key => $cfg) {
    $postingTabs[$key] = ['label' => $cfg['label'], 'fa' => $cfg['fa']];
}
$postingTabs['voucher_logs'] = ['label' => 'Voucher Logs', 'fa' => 'fa-history'];

$tab = (string) ($_GET['tab'] ?? 'journal');
if (!isset($postingTabs[$tab])) {
    $tab = 'journal';
}

$openFy = accountingCurrentOpenFy();
?>
<div class="card card-primary card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-paper-plane mr-1"></i>Posting</h3>
        <div class="card-tools">
            <?php foreach ($postingTabs as $tk => $meta): ?>
                <a href="<?= pageUrl('accounts', 'postings') ?>&tab=<?= urlencode($tk) ?>"
                   class="btn btn-sm btn-<?= $tab === $tk ? 'primary' : 'default' ?>">
                    <i class="fas <?= e($meta['fa']) ?> mr-1"></i><?= e($meta['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card-body">
        <?php if (!$openFy): ?>
            <div class="callout callout-danger">
                <h5>No open fiscal year</h5>
                <p>Create an open fiscal year in <a href="<?= pageUrl('accounts', 'fiscal_years') ?>">Fiscal Years</a>
                before entering vouchers. Closed fiscal years are read-only (AC-FIN-01.2).</p>
            </div>
        <?php elseif ($tab === 'voucher_logs'): ?>
            <?php include __DIR__ . '/includes/_voucher_logs_tab.php'; ?>
        <?php elseif (isset(accountingVoucherConfig()[$tab])): ?>
            <?php
            $vt = accountingVoucherConfig()[$tab];
            include __DIR__ . '/includes/_voucher_panel.php';
            ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($tab !== 'voucher_logs' && isset(accountingVoucherConfig()[$tab])): ?>
<script>
(function () {
    // Dynamic voucher lines: add/remove rows + live balance check.
    var lineTpl = document.getElementById('voucherLineTpl');
    var rowsWrap = document.getElementById('voucherLines');
    var balanceBox = document.getElementById('voucherBalance');
    var saveBtn = document.getElementById('voucherSaveBtn');
    var lineCount = rowsWrap ? rowsWrap.querySelectorAll('.voucher-line').length : 0;

    window.addVoucherLine = function () {
        if (!lineTpl || !rowsWrap) return;
        var div = document.createElement('div');
        div.className = 'voucher-line';
        div.innerHTML = lineTpl.innerHTML.replace(/__IDX__/g, lineCount++);
        rowsWrap.appendChild(div);
        updateBalance();
    };
    window.removeVoucherLine = function (btn) {
        var row = btn.closest('.voucher-line');
        if (row && rowsWrap.querySelectorAll('.voucher-line').length > 1) {
            row.parentNode.removeChild(row);
            updateBalance();
        }
    };
    function updateBalance() {
        if (!rowsWrap || !balanceBox || !saveBtn) return;
        var d = 0, c = 0;
        rowsWrap.querySelectorAll('.voucher-line').forEach(function (row) {
            d += parseFloat(row.querySelector('.v-line-debit').value) || 0;
            c += parseFloat(row.querySelector('.v-line-credit').value) || 0;
        });
        var diff = Math.abs(d - c);
        var ok = d > 0 && diff < 0.01;
        balanceBox.textContent = 'Debit ' + d.toFixed(2) + ' / Credit ' + c.toFixed(2) + (ok ? ' — balanced ✓' : ' — ' + (d > 0 ? 'unbalanced (diff ' + diff.toFixed(2) + ')' : ''));
        balanceBox.className = 'small ' + (ok ? 'text-success font-weight-bold' : 'text-danger font-weight-bold');
        saveBtn.disabled = !ok;
    }
    if (rowsWrap) {
        rowsWrap.addEventListener('input', updateBalance);
        updateBalance();
    }
})();
</script>
<?php endif; ?>
