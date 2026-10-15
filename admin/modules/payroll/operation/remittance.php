<?php
/**
 * SB-Tech — Payroll / Remittance CSV: per-staff TDS and fund contributions
 * for every approved / posted / paid run of a tax year (one row per person
 * per month — the detail the IRD and fund returns ask for).
 */
if (!Auth::hasSpecial('manage_payroll') && !Auth::hasSpecial('approve_payroll')) {
    setFlash('error', 'You need a payroll permission.');
    redirect(pageUrl('payroll', 'remittance'));
    exit;
}
$db = Database::instance();
$yearId = (int) ($_POST['year'] ?? 0);
$year = $db->selectOne('SELECT * FROM tbl_payroll_tax_years WHERE id = ?', [$yearId]);
if (!$year || ($_POST['action'] ?? '') !== 'export') {
    setFlash('error', 'Choose a tax year to export.');
    redirect(pageUrl('payroll', 'remittance'));
    exit;
}
$lines = $db->select(
    "SELECT r.period_key, r.period_label, l.id, l.fullname, l.pan_num, l.gross, l.tds
     FROM tbl_payroll_run_lines l JOIN tbl_payroll_runs r ON r.id = l.run_id
     WHERE r.tax_year_id = ? AND r.status IN ('Approved','Posted','Paid') AND l.excluded = 0
     ORDER BY r.period_start, l.fullname",
    [$yearId]
);
$fundCodes = array_column($db->select(
    "SELECT DISTINCT i.code FROM tbl_payroll_run_line_items i
     JOIN tbl_payroll_run_lines l ON l.id = i.line_id JOIN tbl_payroll_runs r ON r.id = l.run_id
     WHERE r.tax_year_id = ? AND r.status IN ('Approved','Posted','Paid') AND i.kind IN ('fund_employee','fund_employer')
     ORDER BY i.code",
    [$yearId]
), 'code');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="payroll_remittance_' . preg_replace('/[^0-9]/', '-', (string) $year['title']) . '.csv"');
$out = fopen('php://output', 'w');
$head = ['Month', 'Employee', 'PAN', 'Gross', 'TDS'];
foreach ($fundCodes as $c) {
    $head[] = $c . ' employee';
    $head[] = $c . ' employer';
}
fputcsv($out, $head, ',', '"', '');
foreach ($lines as $l) {
    $f = [];
    foreach ($db->select("SELECT code, kind, amount FROM tbl_payroll_run_line_items WHERE line_id = ? AND kind IN ('fund_employee','fund_employer')", [(int) $l['id']]) as $it) {
        $f[$it['code']][$it['kind']] = (float) $it['amount'];
    }
    $row = [$l['period_label'], $l['fullname'], $l['pan_num'], number_format((float) $l['gross'], 2, '.', ''), number_format((float) $l['tds'], 2, '.', '')];
    foreach ($fundCodes as $c) {
        $row[] = number_format($f[$c]['fund_employee'] ?? 0, 2, '.', '');
        $row[] = number_format($f[$c]['fund_employer'] ?? 0, 2, '.', '');
    }
    fputcsv($out, $row, ',', '"', '');
}
fclose($out);
exit;
