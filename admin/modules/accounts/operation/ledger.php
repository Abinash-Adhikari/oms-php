<?php
/**
 * SB-Tech — Accounts / Ledger operations (AC-FIN-04.2): CSV export.
 */
$db = Database::instance();
$action = (string) ($_POST['action'] ?? '');
if ($action !== 'export_ledger') {
    setFlash('error', 'Unknown ledger action.');
    redirect(pageUrl('accounts', 'ledger'));
}
$terminalId = (int) ($_POST['terminal_id'] ?? 0);
$from = (string) ($_POST['from'] ?? '');
$to = (string) ($_POST['to'] ?? '');
$onlyApproved = ($_POST['status'] ?? 'Approved') === 'Approved';

$terminal = $db->selectOne('SELECT * FROM `tbl_account_terminals` WHERE `id` = ?', [$terminalId]);
if (!$terminal) {
    setFlash('error', 'Terminal not found.');
    redirect(pageUrl('accounts', 'ledger'));
}
$extra = $onlyApproved ? ['lp.voucher_status = ?'] : [];
$params = $onlyApproved ? ['Approved'] : [];
$lines = accountingLedgerLines($db, $terminalId, $from ?: '1000-01-01', $to ?: '9999-12-31', $extra, $params);
$opening = accountingOpeningBalance($db, $terminalId, $from ?: '1000-01-01');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="ledger_' . preg_replace('/[^a-z0-9]+/i', '_', $terminal['title']) . '_' . date('Ymd') . '.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['Date', 'Voucher', 'Debit', 'Credit', 'Balance', 'Status', 'Remark']);
fputcsv($out, ['', 'Opening balance', '', '', $opening, '', '']);
$running = (float) $opening;
foreach ($lines as $lp) {
    $running = round($running + (float) $lp['debit'] - (float) $lp['credit'], 4);
    fputcsv($out, [
        $lp['particulars_date'], $lp['voucher_no'] ?? '',
        (float) $lp['debit'], (float) $lp['credit'], $running,
        $lp['voucher_status'], $lp['remarks'] ?? '',
    ]);
}
fclose($out);
exit;
