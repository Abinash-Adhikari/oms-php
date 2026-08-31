<?php
/**
 * SB-Tech — Reports / Finance operations (CSV export).
 */
$db = Database::instance();
$action = (string) ($_POST['action'] ?? '');

try {
    if ($action === 'export_finance') {
        $vouchers = $db->select(
            "SELECT 'Journal' AS type, voucher_no, voucher_date, narration, total_amount, status FROM tbl_journal_vouchers
             UNION ALL SELECT 'Receipt', voucher_no, voucher_date, narration, total_amount, status FROM tbl_receipt_vouchers
             UNION ALL SELECT 'Payment', voucher_no, voucher_date, narration, total_amount, status FROM tbl_payment_vouchers
             UNION ALL SELECT 'Contra', voucher_no, voucher_date, narration, total_amount, status FROM tbl_contra_vouchers
             UNION ALL SELECT 'Purchase', voucher_no, voucher_date, narration, total_amount, status FROM tbl_purchase_vouchers
             UNION ALL SELECT 'Sales', voucher_no, voucher_date, narration, total_amount, status FROM tbl_sales_vouchers
             ORDER BY voucher_date DESC"
        );
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="vouchers_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Type', 'Voucher No', 'Date', 'Narration', 'Amount', 'Status']);
        foreach ($vouchers as $v) {
            fputcsv($out, [$v['type'], $v['voucher_no'], $v['voucher_date'], $v['narration'], $v['total_amount'], $v['status']]);
        }
        fclose($out);
        exit;
    }
    setFlash('error', 'Unknown action.');
    redirect(pageUrl('reports', 'finance'));
} catch (Throwable $e) {
    setFlash('error', 'Export failed: ' . $e->getMessage());
    redirect(pageUrl('reports', 'finance'));
}
