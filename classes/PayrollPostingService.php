<?php
/**
 * SB-Tech — Payroll ↔ Accounts (docs/PAYROLL_PLAN.md §7, phase P3).
 *
 *   Approved --post--> Posted --pay--> Paid
 *   Posted  --unpost-> Approved   (journal still Pending or already deleted)
 *   Paid    --unpay--> Posted     (payment still Pending or already deleted)
 *
 * Vouchers are created Auto / Pending exactly like expense-claim payments;
 * an accountant with approve_vouchers approves them in Accounts → Posting,
 * so payroll never bypasses accounting control. Callers wrap each mutating
 * method in $db->transaction().
 */
class PayrollPostingService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    /** @return array<string, mixed> tbl_payroll_settings row 1 (created if missing) */
    public function settings(): array
    {
        $row = $this->db->selectOne('SELECT * FROM `tbl_payroll_settings` WHERE `id` = 1');
        if (!$row) {
            $this->db->insert('tbl_payroll_settings', ['id' => 1]);
            $row = (array) $this->db->selectOne('SELECT * FROM `tbl_payroll_settings` WHERE `id` = 1');
        }

        return $row;
    }

    /**
     * Terminal ids by role. 'advances' (column other_deductions_terminal_id,
     * Staff Advances) takes advance recoveries; 'other_deductions'
     * (deductions_terminal_id) takes every other deduction.
     *
     * @return array{salary_expense: ?int, employer_contribution: ?int, staff_payable: ?int, tds_payable: ?int, advances: ?int, other_deductions: ?int, funds: array<string, ?int>}
     */
    public function accountMap(): array
    {
        $s = $this->settings();
        $funds = [];
        foreach ($this->db->select('SELECT `code`, `payable_terminal_id` FROM `tbl_payroll_funds`') as $f) {
            $funds[$f['code']] = $f['payable_terminal_id'] ? (int) $f['payable_terminal_id'] : null;
        }
        $id = static fn ($v) => $v ? (int) $v : null;

        return [
            'salary_expense'        => $id($s['salary_expense_terminal_id']),
            'employer_contribution' => $id($s['employer_contribution_terminal_id']),
            'staff_payable'         => $id($s['staff_payable_terminal_id']),
            'tds_payable'           => $id($s['tds_payable_terminal_id']),
            'advances'              => $id($s['other_deductions_terminal_id']),
            'other_deductions'      => $id($s['deductions_terminal_id'] ?? null),
            'funds'                 => $funds,
        ];
    }

    /**
     * The balanced journal for a run (preview or posting).
     *
     * @return list<array{account_terminal_id: int, debit: float, credit: float, remarks: string}>
     */
    public function journalFor(array $run): array
    {
        $fundTotals = [];
        foreach ($this->db->select(
            "SELECT i.`code`, SUM(i.`amount`) AS total
             FROM `tbl_payroll_run_line_items` i
             JOIN `tbl_payroll_run_lines` l ON l.`id` = i.`line_id`
             WHERE l.`run_id` = ? AND l.`excluded` = 0 AND i.`kind` IN ('fund_employee','fund_employer')
             GROUP BY i.`code` ORDER BY i.`code`",
            [(int) $run['id']]
        ) as $r) {
            $fundTotals[$r['code']] = (float) $r['total'];
        }

        $advanceRecoveries = (float) ($this->db->selectOne(
            "SELECT COALESCE(SUM(i.`amount`), 0) AS t FROM `tbl_payroll_run_line_items` i
             JOIN `tbl_payroll_run_lines` l ON l.`id` = i.`line_id`
             WHERE l.`run_id` = ? AND l.`excluded` = 0 AND i.`kind` = 'deduction' AND i.`advance_id` IS NOT NULL",
            [(int) $run['id']]
        )['t'] ?? 0);

        return payroll_journal_lines([
            'gross'              => (float) $run['total_gross'],
            'employer_funds'     => (float) $run['total_employer_funds'],
            'net'                => (float) $run['total_net'],
            'tds'                => (float) $run['total_tds'],
            'other_deductions'   => (float) $run['total_deductions'],
            'advance_recoveries' => $advanceRecoveries,
        ], $fundTotals, $this->accountMap(), $run['period_label'] . ' payroll');
    }

    /** Voucher date must be a real date, not in the future, inside an open fiscal year. */
    private function fiscalYearFor(string $date): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) === false) {
            throw new RuntimeException('Choose a valid date.');
        }
        if ($date > date('Y-m-d')) {
            throw new RuntimeException('Vouchers cannot be dated in the future — pick today or earlier.');
        }
        $fy = accountingFiscalForDate($date);
        if (!$fy) {
            throw new RuntimeException('No open fiscal year covers ' . $date . '. Open one in Accounts → Fiscal Years.');
        }

        return $fy;
    }

    public function post(int $runId, string $date, int $me): int
    {
        $run = $this->run($runId);
        if ($run['status'] !== 'Approved') {
            throw new RuntimeException('Only an Approved run can be posted to accounts.');
        }
        $lines = $this->journalFor($run);
        $fy = $this->fiscalYearFor($date);
        $amount = round(array_sum(array_column($lines, 'debit')), 2);
        $voucherNo = accountingNextVoucherNo($this->db, 'journal', (int) $fy['id']);
        $narration = 'Payroll ' . $run['period_label'] . ' — ' . (int) $run['headcount'] . ' staff';
        $voucherId = $this->db->insert('tbl_journal_vouchers', [
            'fiscal_year_id' => (int) $fy['id'],
            'voucher_no'     => $voucherNo,
            'voucher_date'   => $date,
            'reference_no'   => 'PAYROLL-' . $run['period_key'],
            'narration'      => $narration,
            'description'    => $narration . '. Created by Payroll; approve in Accounts → Posting.',
            'amount'         => $amount,
            'total_amount'   => $amount,
            'entry_type'     => 'Auto',
            'status'         => 'Pending',
            'added_by'       => $me,
            'updated_by'     => $me,
        ]);
        accountingReplaceLines($this->db, 'Journal', $voucherId, (int) $fy['id'], $date, $lines, $me, 'Pending');
        accountingLogVoucher($this->db, 'journal', $voucherId, $voucherNo, 'Create', null, ['source' => 'payroll', 'run_id' => $runId], $me);
        $this->db->update('tbl_payroll_runs', ['status' => 'Posted', 'journal_voucher_id' => $voucherId, 'posted_by' => $me, 'posted_on' => date('Y-m-d H:i:s')], '`id` = ?', [$runId]);
        auditLog('payroll', 'run_posted', 'payroll_run', $runId, ['status' => 'Approved'], ['status' => 'Posted', 'voucher' => $voucherNo, 'amount' => $amount]);

        return $voucherId;
    }

    public function unpost(int $runId, int $me): void
    {
        $run = $this->run($runId);
        if ($run['status'] !== 'Posted') {
            throw new RuntimeException('Only a Posted run can be un-posted.');
        }
        $this->removeVoucher('journal', (int) $run['journal_voucher_id'], $me, 'Undo posting');
        $this->db->update('tbl_payroll_runs', ['status' => 'Approved', 'journal_voucher_id' => null, 'posted_by' => null, 'posted_on' => null], '`id` = ?', [$runId]);
        auditLog('payroll', 'run_unposted', 'payroll_run', $runId, ['status' => 'Posted'], ['status' => 'Approved']);
    }

    public function pay(int $runId, int $paymentTerminalId, string $date, int $me): int
    {
        $run = $this->run($runId);
        if ($run['status'] !== 'Posted') {
            throw new RuntimeException('Post the run to accounts before recording payment.');
        }
        $map = $this->accountMap();
        if (!$map['staff_payable']) {
            throw new RuntimeException('Map the Staff payable account in Payroll → Accounting Setup first.');
        }
        if (!accountingTerminalMeta($this->db, $paymentTerminalId)) {
            throw new RuntimeException('Choose the bank or cash account the salaries were paid from.');
        }
        $fy = $this->fiscalYearFor($date);
        $amount = round((float) $run['total_net'], 2);
        $label = 'Salary payment ' . $run['period_label'];
        $voucherNo = accountingNextVoucherNo($this->db, 'payment', (int) $fy['id']);
        $voucherId = $this->db->insert('tbl_payment_vouchers', [
            'fiscal_year_id' => (int) $fy['id'],
            'voucher_no'     => $voucherNo,
            'voucher_date'   => $date,
            'reference_no'   => 'PAYROLL-' . $run['period_key'],
            'narration'      => $label . ' — ' . (int) $run['headcount'] . ' staff',
            'description'    => $label . '. Created by Payroll; approve in Accounts → Posting.',
            'amount'         => $amount,
            'total_amount'   => $amount,
            'entry_type'     => 'Auto',
            'status'         => 'Pending',
            'added_by'       => $me,
            'updated_by'     => $me,
        ]);
        accountingReplaceLines($this->db, 'Payment', $voucherId, (int) $fy['id'], $date, [
            ['account_terminal_id' => $map['staff_payable'], 'debit' => $amount, 'credit' => 0.0, 'remarks' => $label],
            ['account_terminal_id' => $paymentTerminalId, 'debit' => 0.0, 'credit' => $amount, 'remarks' => $label],
        ], $me, 'Pending');
        accountingLogVoucher($this->db, 'payment', $voucherId, $voucherNo, 'Create', null, ['source' => 'payroll', 'run_id' => $runId], $me);
        $this->db->update('tbl_payroll_runs', ['status' => 'Paid', 'payment_voucher_id' => $voucherId, 'paid_by' => $me, 'paid_on' => date('Y-m-d H:i:s')], '`id` = ?', [$runId]);
        auditLog('payroll', 'run_paid', 'payroll_run', $runId, ['status' => 'Posted'], ['status' => 'Paid', 'voucher' => $voucherNo, 'amount' => $amount]);

        return $voucherId;
    }

    public function unpay(int $runId, int $me): void
    {
        $run = $this->run($runId);
        if ($run['status'] !== 'Paid') {
            throw new RuntimeException('Only a Paid run can have its payment undone.');
        }
        $this->removeVoucher('payment', (int) $run['payment_voucher_id'], $me, 'Undo payment');
        $this->db->update('tbl_payroll_runs', ['status' => 'Posted', 'payment_voucher_id' => null, 'paid_by' => null, 'paid_on' => null], '`id` = ?', [$runId]);
        auditLog('payroll', 'run_unpaid', 'payroll_run', $runId, ['status' => 'Paid'], ['status' => 'Posted']);
    }

    /**
     * Pending payment voucher for handing out a staff advance:
     * Dr the other-deductions account (Staff Advances) / Cr bank or cash.
     */
    public function advancePayment(int $advanceId, int $paymentTerminalId, string $date, int $me): int
    {
        $adv = $this->db->selectOne(
            'SELECT a.*, u.`fullname` FROM `tbl_staff_advances` a JOIN `tbl_users_login` u ON u.`id` = a.`staff_id` WHERE a.`id` = ?',
            [$advanceId]
        );
        if (!$adv) {
            throw new RuntimeException('Advance not found.');
        }
        $map = $this->accountMap();
        if (!$map['advances']) {
            throw new RuntimeException('Map the Staff advances account in Payroll → Accounting Setup first.');
        }
        if (!accountingTerminalMeta($this->db, $paymentTerminalId)) {
            throw new RuntimeException('Choose the bank or cash account the advance was paid from.');
        }
        $fy = $this->fiscalYearFor($date);
        $amount = round((float) $adv['amount'], 2);
        $label = $adv['title'] . ' — ' . $adv['fullname'];
        $voucherNo = accountingNextVoucherNo($this->db, 'payment', (int) $fy['id']);
        $voucherId = $this->db->insert('tbl_payment_vouchers', [
            'fiscal_year_id' => (int) $fy['id'], 'voucher_no' => $voucherNo, 'voucher_date' => $date,
            'reference_no' => 'ADVANCE-' . $advanceId, 'narration' => $label,
            'description' => $label . '. Created by Payroll → Advances; recovered through payroll.',
            'amount' => $amount, 'total_amount' => $amount, 'entry_type' => 'Auto', 'status' => 'Pending',
            'added_by' => $me, 'updated_by' => $me,
        ]);
        accountingReplaceLines($this->db, 'Payment', $voucherId, (int) $fy['id'], $date, [
            ['account_terminal_id' => $map['advances'], 'debit' => $amount, 'credit' => 0.0, 'remarks' => $label],
            ['account_terminal_id' => $paymentTerminalId, 'debit' => 0.0, 'credit' => $amount, 'remarks' => $label],
        ], $me, 'Pending');
        accountingLogVoucher($this->db, 'payment', $voucherId, $voucherNo, 'Create', null, ['source' => 'staff_advance', 'advance_id' => $advanceId], $me);
        $this->db->update('tbl_staff_advances', ['payment_voucher_id' => $voucherId], '`id` = ?', [$advanceId]);

        return $voucherId;
    }

    /** Remove an advance's payment voucher while it is still Pending. */
    public function removeAdvancePayment(int $advanceId, int $me): void
    {
        $adv = $this->db->selectOne('SELECT `payment_voucher_id` FROM `tbl_staff_advances` WHERE `id` = ?', [$advanceId]);
        if ($adv && $adv['payment_voucher_id']) {
            $this->removeVoucher('payment', (int) $adv['payment_voucher_id'], $me, 'Advance cancelled');
            $this->db->update('tbl_staff_advances', ['payment_voucher_id' => null], '`id` = ?', [$advanceId]);
        }
    }

    /**
     * A run's voucher with its current status, or null if it was deleted in Accounts.
     *
     * @return array<string, mixed>|null
     */
    public function voucher(string $typeKey, ?int $id): ?array
    {
        return $id ? accountingVoucherById($this->db, $typeKey, $id) : null;
    }

    /** Delete a Pending auto voucher (approved ones need a reversing entry in Accounts). */
    private function removeVoucher(string $typeKey, int $voucherId, int $me, string $why): void
    {
        $v = $voucherId ? accountingVoucherById($this->db, $typeKey, $voucherId) : null;
        if (!$v) {
            return; // already deleted in Accounts — just detach
        }
        if ($v['status'] !== 'Pending') {
            throw new RuntimeException($v['voucher_no'] . ' is already ' . strtolower($v['status'])
                . ' in Accounts. Ask an accountant to un-approve it (or post a reversing entry) first.');
        }
        $cfg = accountingVoucherConfig()[$typeKey];
        $this->db->delete('tbl_ledger_particulars', '`voucher_type` = ? AND `voucher_type_id` = ?', [$cfg['type'], $voucherId]);
        $this->db->delete($cfg['table'], '`id` = ?', [$voucherId]);
        accountingLogVoucher($this->db, $typeKey, $voucherId, $v['voucher_no'], 'Delete', $v, ['reason' => 'Payroll: ' . $why], $me);
    }

    /**
     * The run row, locked FOR UPDATE (callers are inside a transaction), so a
     * double-submitted Post / Pay waits and then sees the new status instead
     * of creating a second voucher.
     *
     * @return array<string, mixed>
     */
    private function run(int $runId): array
    {
        $run = $this->db->selectOne('SELECT * FROM `tbl_payroll_runs` WHERE `id` = ? FOR UPDATE', [$runId]);
        if (!$run) {
            throw new RuntimeException('Payroll run not found.');
        }

        return $run;
    }
}
