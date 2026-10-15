<?php
/**
 * SB-Tech — Payroll run writes (PRG). Every action goes through
 * PayrollRunService inside one transaction.
 *   manage_payroll : create, recalculate, update_line, add_adjustment, remove_adjustment, cancel (Draft)
 *   approve_payroll: approve, cancel (Draft or Approved)
 *   manage_payroll : post / unpost / pay / unpay (P3, via PayrollPostingService)
 *   either         : export_bank, export_register (CSV)
 */
$db = Database::instance();
$svc = new PayrollRunService($db);
$me = (int) Auth::id();
$action = (string) ($_POST['action'] ?? '');
$runId = (int) ($_POST['run_id'] ?? 0);
$list = pageUrl('payroll', 'runs');
$runUrl = static fn (int $id): string => pageUrl('payroll', 'run') . '&id=' . $id;
$optFloat = static function ($v): ?float {
    $v = trim((string) $v);
    return $v === '' ? null : (float) $v;
};

$needs = $action === 'approve' ? 'approve_payroll' : 'manage_payroll';
if (in_array($action, ['cancel', 'export_bank', 'export_register'], true) && Auth::hasSpecial('approve_payroll')) {
    $needs = 'approve_payroll';
}
if (!Auth::hasSpecial($needs)) {
    setFlash('error', 'You need the ' . ($needs === 'approve_payroll' ? 'Approve payroll' : 'Manage payroll') . ' permission.');
    redirect($runId ? $runUrl($runId) : $list);
    exit;
}

try {
    switch ($action) {
        case 'create':
            $runId = $db->transaction(fn () => $svc->createRun((string) ($_POST['period_key'] ?? ''), $me));
            setFlash('success', 'Draft payroll created — review each line, then ask an approver to approve it.');
            break;

        case 'recalculate':
            $db->transaction(fn () => $svc->recalculate($runId));
            setFlash('success', 'Recalculated from the latest salary setups, attendance and leave.');
            break;

        case 'update_line':
            $runId = $db->transaction(fn () => $svc->updateLine(
                (int) ($_POST['line_id'] ?? 0),
                $optFloat($_POST['unpaid_override'] ?? ''),
                $optFloat($_POST['tds_override'] ?? ''),
                !empty($_POST['excluded'])
            ));
            setFlash('success', 'Line updated and run recalculated.');
            break;

        case 'add_adjustment':
            $runId = $db->transaction(fn () => $svc->addAdjustment(
                (int) ($_POST['line_id'] ?? 0),
                (string) ($_POST['kind'] ?? 'earning'),
                trim((string) ($_POST['name'] ?? '')),
                (float) ($_POST['amount'] ?? 0),
                !empty($_POST['taxable'])
            ));
            setFlash('success', 'Adjustment added.');
            break;

        case 'add_adjustment_all':
            $n = $db->transaction(fn () => $svc->addAdjustmentToAll(
                $runId,
                trim((string) ($_POST['name'] ?? '')),
                (string) ($_POST['kind'] ?? 'earning'),
                (string) ($_POST['mode'] ?? 'fixed'),
                (float) ($_POST['value'] ?? 0),
                !empty($_POST['taxable'])
            ));
            setFlash('success', 'Added to ' . $n . ' staff and recalculated.');
            break;

        case 'email_payslips':
            $c = $svc->emailPayslips($runId, $me);
            $msg = $c['sent'] . ' payslip notice' . ($c['sent'] === 1 ? '' : 's') . ' emailed'
                . ($c['skipped'] ? ', ' . $c['skipped'] . ' skipped (no email address)' : '')
                . ($c['failed'] ? ', ' . $c['failed'] . ' failed — check Communication → Email/SMS settings' : '') . '.';
            setFlash($c['failed'] && !$c['sent'] ? 'warning' : 'success', $msg);
            break;

        case 'remove_adjustment':
            $runId = $db->transaction(fn () => $svc->removeAdjustment((int) ($_POST['item_id'] ?? 0)));
            setFlash('success', 'Adjustment removed.');
            break;

        case 'approve':
            if ($db->transaction(fn () => $svc->approve($runId, $me, Auth::isSuperAdmin()))) {
                setFlash('success', 'Payroll approved. Staff have been notified that their payslips are ready.');
            } else {
                setFlash('warning', 'Not approved yet: figures changed since this run was last calculated (salary setups, advances, leave or an earlier month’s approval). The run has been refreshed — review it and approve again.');
            }
            break;

        case 'cancel':
            $run = $svc->run($runId);
            if ($run['status'] === 'Approved' && !Auth::hasSpecial('approve_payroll')) {
                throw new RuntimeException('Only an approver can cancel an approved run.');
            }
            $db->transaction(fn () => $svc->cancel($runId, $me, trim((string) ($_POST['reason'] ?? ''))));
            setFlash('success', 'Run cancelled. You can start that month again.');
            break;

        case 'post':
            $posting = new PayrollPostingService($db);
            $db->transaction(fn () => $posting->post($runId, (string) ($_POST['date'] ?? ''), $me));
            setFlash('success', 'Posted to accounts as a Pending journal voucher — an accountant approves it in Accounts → Posting.');
            break;

        case 'unpost':
            $posting = new PayrollPostingService($db);
            $db->transaction(fn () => $posting->unpost($runId, $me));
            setFlash('success', 'Posting undone — the journal voucher was removed.');
            break;

        case 'pay':
            $posting = new PayrollPostingService($db);
            $db->transaction(fn () => $posting->pay($runId, (int) ($_POST['payment_terminal_id'] ?? 0), (string) ($_POST['date'] ?? ''), $me));
            setFlash('success', 'Payment recorded as a Pending payment voucher — an accountant approves it in Accounts → Posting.');
            break;

        case 'unpay':
            $posting = new PayrollPostingService($db);
            $db->transaction(fn () => $posting->unpay($runId, $me));
            setFlash('success', 'Payment undone — the payment voucher was removed.');
            break;

        case 'export_bank':
        case 'export_register':
            $run = $svc->run($runId);
            if (!in_array($run['status'], ['Approved', 'Posted', 'Paid'], true)) {
                throw new RuntimeException('Exports are available once the run is approved.');
            }
            $lines = $db->select('SELECT * FROM tbl_payroll_run_lines WHERE run_id = ? AND excluded = 0 ORDER BY fullname', [$runId]);
            $name = ($action === 'export_bank' ? 'salary_bank_sheet_' : 'payroll_register_') . $run['period_key'] . '.csv';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $name . '"');
            $out = fopen('php://output', 'w');
            if ($action === 'export_bank') {
                fputcsv($out, ['S.N.', 'Employee', 'Bank', 'Account number', 'Account name', 'Amount (NPR)', 'Remarks'], ',', '"', '');
                foreach ($lines as $i => $l) {
                    fputcsv($out, [$i + 1, $l['fullname'], $l['bank'], $l['bank_account_num'], $l['bank_account_name'] ?: $l['fullname'],
                        number_format((float) $l['net'], 2, '.', ''), 'Salary ' . $run['period_label']], ',', '"', '');
                }
                fputcsv($out, ['', 'TOTAL', '', '', '', number_format((float) $run['total_net'], 2, '.', ''), ''], ',', '"', '');
            } else {
                $fundCodes = array_column($db->select(
                    "SELECT DISTINCT i.code FROM tbl_payroll_run_line_items i JOIN tbl_payroll_run_lines l ON l.id = i.line_id
                     WHERE l.run_id = ? AND i.kind IN ('fund_employee','fund_employer') ORDER BY i.code",
                    [$runId]
                ), 'code');
                $head = ['Employee', 'Designation', 'PAN', 'Pay type', 'Days paid', 'Unpaid days', 'Gross'];
                foreach ($fundCodes as $c) {
                    $head[] = $c . ' (employee)';
                    $head[] = $c . ' (employer)';
                }
                fputcsv($out, array_merge($head, ['TDS', 'Other deductions', 'Net pay', 'Cost to company']), ',', '"', '');
                foreach ($lines as $l) {
                    $f = [];
                    foreach ($db->select("SELECT code, kind, amount FROM tbl_payroll_run_line_items WHERE line_id = ? AND kind IN ('fund_employee','fund_employer')", [(int) $l['id']]) as $it) {
                        $f[$it['code']][$it['kind']] = (float) $it['amount'];
                    }
                    $row = [$l['fullname'], $l['designation'], $l['pan_num'], $l['pay_type'], (float) $l['payable_days'],
                        (float) ($l['unpaid_override'] ?? $l['unpaid_days']), number_format((float) $l['gross'], 2, '.', '')];
                    foreach ($fundCodes as $c) {
                        $row[] = number_format($f[$c]['fund_employee'] ?? 0, 2, '.', '');
                        $row[] = number_format($f[$c]['fund_employer'] ?? 0, 2, '.', '');
                    }
                    fputcsv($out, array_merge($row, [number_format((float) $l['tds'], 2, '.', ''), number_format((float) $l['other_deductions'], 2, '.', ''),
                        number_format((float) $l['net'], 2, '.', ''), number_format((float) $l['employer_cost'], 2, '.', '')]), ',', '"', '');
                }
            }
            fclose($out);
            exit;

        default:
            setFlash('error', 'Unknown action.');
            redirect($list);
            exit;
    }
} catch (RuntimeException $e) {
    setFlash('error', $e->getMessage());
}
redirect($runId ? $runUrl($runId) : $list);
exit;
