<?php
/**
 * SB-Tech — Accounts / Fiscal Years operations (US-FIN-01).
 */
$db = Database::instance();
$me = (int) Auth::id();
$action = (string) ($_POST['action'] ?? '');
$back = pageUrl('accounts', 'fiscal_years');

try {
    if ($action === 'save_fy') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $start = (string) ($_POST['starting_date'] ?? '');
        $end = (string) ($_POST['ending_date'] ?? '');
        $closing = (string) ($_POST['closing'] ?? 'Open') === 'Closed' ? 'Closed' : 'Open';
        if ($title === '' || !strtotime($start) || !strtotime($end)) {
            setFlash('error', 'Title and valid dates are required.');
            redirect($back);
        }
        if ($end <= $start) {
            setFlash('error', 'Ending date must be after the starting date.');
            redirect($back);
        }
        $db->insert('tbl_fiscal_years', [
            'title' => $title, 'starting_date' => $start, 'ending_date' => $end,
            'closing' => $closing, 'added_by' => $me, 'updated_by' => $me,
        ]);
        setFlash('success', 'Fiscal year ' . $title . ' created.');
        redirect($back);
    }

    if ($action === 'close_fy' || $action === 'open_fy') {
        $id = (int) ($_POST['id'] ?? 0);
        $closing = $action === 'close_fy' ? 'Closed' : 'Open';
        $db->update('tbl_fiscal_years', ['closing' => $closing, 'updated_by' => $me], '`id` = ?', [$id]);
        setFlash('success', 'Fiscal year updated (' . $closing . ').');
        redirect($back);
    }

    if ($action === 'delete_fy') {
        $id = (int) ($_POST['id'] ?? 0);
        $used = $db->count('tbl_ledger_particulars', '`fiscal_year_id` = ?', [$id]);
        $vouchers = 0;
        foreach (accountingVoucherConfig() as $cfg) {
            $vouchers += $db->count($cfg['table'], '`fiscal_year_id` = ?', [$id]);
        }
        if ($used > 0 || $vouchers > 0) {
            setFlash('error', 'Cannot delete — ' . ($used + $vouchers) . ' voucher line(s) reference this fiscal year.');
        } else {
            $db->delete('tbl_fiscal_years', '`id` = ?', [$id]);
            setFlash('success', 'Fiscal year deleted.');
        }
        redirect($back);
    }

    setFlash('error', 'Unknown fiscal year action.');
    redirect($back);
} catch (Throwable $e) {
    setFlash('error', 'Fiscal year operation failed: ' . $e->getMessage());
    redirect($back);
}
