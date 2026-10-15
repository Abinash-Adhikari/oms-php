<?php
/**
 * SB-Tech — Monthly payroll runs (docs/PAYROLL_PLAN.md §6, phase P2).
 *
 * Lifecycle: Draft (calculate → adjust → recalculate) → Approved (frozen,
 * staff notified) → [P3: Posted → Paid]; Draft/Approved → Cancelled.
 * Every figure comes from payroll_compute_line() via the same inputs
 * PayrollService uses for the Salary Setup estimate.
 *
 * Callers wrap mutating methods in $db->transaction() (PRG handlers).
 */
class PayrollRunService
{
    private const WEEKDAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    private Database $db;
    private PayrollService $pay;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
        $this->pay = new PayrollService($this->db);
    }

    // ── Periods ──────────────────────────────────────────────────────────

    /**
     * A BS month by key 'YYYY-MM' with its AD bounds.
     *
     * @return array{key: string, label: string, start: string, end: string, days: int}|null
     */
    public function period(string $periodKey): ?array
    {
        if (!preg_match('/^(\d{4})-(\d{2})$/', $periodKey, $m)) {
            return null;
        }
        $row = $this->db->selectOne(
            'SELECT `eng_start_date`, `eng_end_date` FROM `tbl_calendar` WHERE `nepali_year` = ? AND `month_code` = ?',
            [(int) $m[1], (int) $m[2]]
        );
        if (!$row) {
            return null;
        }
        $days = (int) ((strtotime($row['eng_end_date']) - strtotime($row['eng_start_date'])) / 86400) + 1;

        return [
            'key'   => $periodKey,
            'label' => bsMonthName((int) $m[2]) . ' ' . $m[1],
            'start' => $row['eng_start_date'],
            'end'   => $row['eng_end_date'],
            'days'  => $days,
        ];
    }

    /**
     * Recent BS months up to the current one, newest first, for the "new run" picker.
     *
     * @return list<array{key: string, label: string, start: string, end: string}>
     */
    public function periodOptions(string $today, int $count = 13): array
    {
        $rows = $this->db->select(
            'SELECT `nepali_year`, `month_code`, `eng_start_date`, `eng_end_date` FROM `tbl_calendar`
             WHERE `eng_start_date` <= ? ORDER BY `eng_start_date` DESC LIMIT ' . max(1, $count),
            [$today]
        );

        return array_map(static fn ($r) => [
            'key'   => sprintf('%04d-%02d', $r['nepali_year'], $r['month_code']),
            'label' => bsMonthName((int) $r['month_code']) . ' ' . $r['nepali_year'],
            'start' => $r['eng_start_date'],
            'end'   => $r['eng_end_date'],
        ], $rows);
    }

    /** BS months from $periodStart to the tax year's end, inclusive (≥ 1). */
    public function monthsRemaining(string $periodStart, ?array $taxYear): int
    {
        if (!$taxYear) {
            return 1;
        }
        $n = (int) ($this->db->selectOne(
            'SELECT COUNT(*) AS c FROM `tbl_calendar` WHERE `eng_start_date` >= ? AND `eng_start_date` <= ?',
            [$periodStart, $taxYear['end_date']]
        )['c'] ?? 0);

        return max(1, $n);
    }

    /** @return array<string, mixed>|null the non-cancelled run for a period */
    public function activeRunFor(string $periodKey): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM `tbl_payroll_runs` WHERE `period_key` = ? AND `status` <> 'Cancelled' LIMIT 1",
            [$periodKey]
        );
    }

    // ── Lifecycle ────────────────────────────────────────────────────────

    public function createRun(string $periodKey, int $me): int
    {
        $period = $this->period($periodKey);
        if (!$period) {
            throw new RuntimeException('That month is not in the BS calendar.');
        }
        if ($existing = $this->activeRunFor($periodKey)) {
            throw new RuntimeException($period['label'] . ' already has a ' . strtolower($existing['status']) . ' run.');
        }
        $taxYear = $this->pay->taxYearFor($period['end']);
        try {
            $runId = $this->db->insert('tbl_payroll_runs', [
            'tax_year_id'      => $taxYear ? (int) $taxYear['id'] : null,
            'period_key'       => $period['key'],
            'period_label'     => $period['label'],
            'period_start'     => $period['start'],
            'period_end'       => $period['end'],
            'period_days'      => $period['days'],
            'months_remaining' => $this->monthsRemaining($period['start'], $taxYear),
            'status'           => 'Draft',
            'prepared_by'      => $me,
            ]);
        } catch (QueryException $e) {
            // uniq_payroll_run_active_period: someone else just started this month.
            if (str_contains($e->getMessage(), 'Duplicate')) {
                throw new RuntimeException($period['label'] . ' already has a run — it was probably just started.');
            }
            throw $e;
        }
        $this->recalculate($runId);
        auditLog('payroll', 'run_created', 'payroll_run', $runId, null, ['period' => $period['key']]);

        return $runId;
    }

    /**
     * A run row. With $lock (inside a transaction) the row is held FOR UPDATE,
     * so two concurrent state changes on the same run queue up and the second
     * one sees the first one's result instead of acting on stale status.
     *
     * @return array<string, mixed>
     */
    public function run(int $runId, bool $lock = false): array
    {
        $run = $this->db->selectOne('SELECT * FROM `tbl_payroll_runs` WHERE `id` = ?' . ($lock ? ' FOR UPDATE' : ''), [$runId]);
        if (!$run) {
            throw new RuntimeException('Payroll run not found.');
        }

        return $run;
    }

    private function requireDraft(array $run): void
    {
        if ($run['status'] !== 'Draft') {
            throw new RuntimeException('This run is ' . $run['status'] . ' — only a Draft can be changed.');
        }
    }

    /**
     * Rebuild every line of a Draft run from current salary setups, attendance
     * and leave, keeping per-line overrides, exclusions and adjustments.
     */
    public function recalculate(int $runId): void
    {
        $run = $this->run($runId, true);
        $this->requireDraft($run);
        $taxYear = $run['tax_year_id'] ? $this->db->selectOne('SELECT * FROM `tbl_payroll_tax_years` WHERE `id` = ?', [(int) $run['tax_year_id']]) : null;
        if ($taxYear) {
            $taxYear['slabs'] = $this->pay->slabs((int) $taxYear['id']);
        }

        $officeOff = [];
        $profile = $this->db->selectOne('SELECT `weekly_off_days` FROM `tbl_office_profiles` WHERE `id` = 1');
        $decoded = json_decode((string) ($profile['weekly_off_days'] ?? ''), true);
        if (is_array($decoded)) {
            $officeOff = array_values(array_intersect(self::WEEKDAYS, $decoded));
        }
        $holidays = $this->db->select(
            'SELECT `from_date`, `to_date`, `department_id`, `gender_to` FROM `tbl_office_holidays` WHERE `from_date` <= ? AND `to_date` >= ?',
            [$run['period_end'], $run['period_start']]
        );

        $existing = [];
        foreach ($this->db->select('SELECT * FROM `tbl_payroll_run_lines` WHERE `run_id` = ?', [$runId]) as $l) {
            $existing[(int) $l['staff_id']] = $l;
        }

        $kept = [];
        foreach ($this->eligibleStaff($run) as $staff) {
            $structure = $this->pay->structureFor((int) $staff['id'], $run['period_end']);
            if (!$structure) {
                continue;
            }
            $old = $existing[(int) $staff['id']] ?? null;
            $adjustments = $old ? $this->db->select(
                'SELECT * FROM `tbl_payroll_run_line_items` WHERE `line_id` = ? AND `is_adjustment` = 1 ORDER BY `id`',
                [(int) $old['id']]
            ) : [];
            [$row, $items] = $this->buildLine($run, $taxYear, $staff, $structure, $old, $adjustments, $officeOff, $holidays);

            if ($old) {
                $lineId = (int) $old['id'];
                $this->db->update('tbl_payroll_run_lines', $row, '`id` = ?', [$lineId]);
                $this->db->delete('tbl_payroll_run_line_items', '`line_id` = ? AND `is_adjustment` = 0', [$lineId]);
            } else {
                $lineId = $this->db->insert('tbl_payroll_run_lines', $row + ['run_id' => $runId, 'staff_id' => (int) $staff['id']]);
            }
            foreach ($items as $pos => $it) {
                $this->db->insert('tbl_payroll_run_line_items', $it + ['line_id' => $lineId, 'position' => $pos]);
            }
            $kept[] = $lineId;
        }

        // Staff who are no longer eligible (pay removed, left before the period) drop out.
        foreach ($existing as $l) {
            if (!in_array((int) $l['id'], $kept, true)) {
                $this->db->delete('tbl_payroll_run_lines', '`id` = ?', [(int) $l['id']]);
            }
        }
        $this->refreshTotals($runId);
        $this->db->update('tbl_payroll_runs', ['calculated_on' => date('Y-m-d H:i:s')], '`id` = ?', [$runId]);
    }

    /**
     * Approve a Draft. The run is recalculated first, so the figures that get
     * frozen reflect the latest salary setups, advance balances (incl. ones
     * recovered by runs approved since) and year-to-date tax. If anything
     * changed, nothing is approved and false is returned so the approver
     * reviews the fresh figures; true means approved.
     */
    public function approve(int $runId, int $me, bool $isSuperAdmin): bool
    {
        $run = $this->run($runId, true);
        $this->requireDraft($run);
        if ((int) $run['prepared_by'] === $me && !$isSuperAdmin) {
            throw new RuntimeException('A different person must approve the run you prepared.');
        }
        $before = $this->lineFingerprint($runId);
        $this->recalculate($runId);
        if ($this->lineFingerprint($runId) !== $before) {
            auditLog('payroll', 'run_refreshed_on_approve', 'payroll_run', $runId, null, ['reason' => 'figures changed since last calculation']);

            return false;
        }
        $run = $this->run($runId, true);
        $lines = $this->db->select('SELECT * FROM `tbl_payroll_run_lines` WHERE `run_id` = ? AND `excluded` = 0', [$runId]);
        if (!$lines) {
            throw new RuntimeException('There is nobody to pay in this run.');
        }
        foreach ($lines as $l) {
            if ((float) $l['net'] < 0) {
                throw new RuntimeException($l['fullname'] . ' has negative net pay — fix it before approving.');
            }
        }
        $this->db->update('tbl_payroll_runs', ['status' => 'Approved', 'approved_by' => $me, 'approved_on' => date('Y-m-d H:i:s')], '`id` = ?', [$runId]);
        // Advances this run finished repaying close automatically.
        foreach ($this->db->select(
            "SELECT DISTINCT i.`advance_id` FROM `tbl_payroll_run_line_items` i JOIN `tbl_payroll_run_lines` l ON l.`id` = i.`line_id`
             WHERE l.`run_id` = ? AND l.`excluded` = 0 AND i.`advance_id` IS NOT NULL",
            [$runId]
        ) as $a) {
            if ($this->advanceOutstanding((int) $a['advance_id']) <= 0.005) {
                $this->db->update('tbl_staff_advances', ['status' => 'Closed', 'updated_by' => $me], "`id` = ? AND `status` = 'Active'", [(int) $a['advance_id']]);
            }
        }
        auditLog('payroll', 'run_approved', 'payroll_run', $runId, ['status' => 'Draft'], ['status' => 'Approved', 'total_net' => $run['total_net']]);
        foreach ($lines as $l) {
            notifyUser((int) $l['staff_id'], 'Your payslip for ' . $run['period_label'] . ' is ready.', 'payroll', (int) $l['id'], $me,
                pageUrl('my_office', 'payslips') . '&line=' . (int) $l['id']);
        }

        return true;
    }

    /** Every money/day figure of every line, to detect changes across a recalculation. */
    private function lineFingerprint(int $runId): string
    {
        $rows = $this->db->select(
            'SELECT `staff_id`, `excluded`, `payable_days`, `gross`, `employee_funds`, `employer_funds`, `tds`, `other_deductions`, `net`
             FROM `tbl_payroll_run_lines` WHERE `run_id` = ? ORDER BY `staff_id`',
            [$runId]
        );

        return md5((string) json_encode(array_map(static fn ($r) => array_map(static fn ($v) => is_numeric($v) ? round((float) $v, 2) : $v, $r), $rows)));
    }

    public function cancel(int $runId, int $me, string $reason): void
    {
        $run = $this->run($runId, true);
        if (!in_array($run['status'], ['Draft', 'Approved'], true)) {
            throw new RuntimeException('A ' . $run['status'] . ' run cannot be cancelled.');
        }
        $advanceIds = array_map('intval', array_column($this->db->select(
            'SELECT DISTINCT i.`advance_id` FROM `tbl_payroll_run_line_items` i JOIN `tbl_payroll_run_lines` l ON l.`id` = i.`line_id`
             WHERE l.`run_id` = ? AND i.`advance_id` IS NOT NULL',
            [$runId]
        ), 'advance_id'));
        $this->db->update('tbl_payroll_runs', [
            'status' => 'Cancelled', 'cancelled_by' => $me, 'cancelled_on' => date('Y-m-d H:i:s'),
            'cancel_reason' => mb_substr($reason, 0, 255) ?: null,
        ], '`id` = ?', [$runId]);
        // Recoveries in a cancelled run no longer count: reopen advances that
        // its approval had closed and that now have a balance again.
        foreach ($advanceIds as $advId) {
            if ($this->advanceOutstanding($advId) > 0.005) {
                $this->db->update('tbl_staff_advances', ['status' => 'Active', 'updated_by' => $me], "`id` = ? AND `status` = 'Closed'", [$advId]);
            }
        }
        auditLog('payroll', 'run_cancelled', 'payroll_run', $runId, ['status' => $run['status']], ['status' => 'Cancelled', 'reason' => $reason]);
    }

    // ── Line adjustments (Draft only) ────────────────────────────────────

    /** @return array<string, mixed> the line joined with its run */
    public function line(int $lineId): array
    {
        $line = $this->db->selectOne(
            'SELECT l.*, r.`status` AS run_status, r.`period_label`, r.`id` AS run_id
             FROM `tbl_payroll_run_lines` l JOIN `tbl_payroll_runs` r ON r.`id` = l.`run_id` WHERE l.`id` = ?',
            [$lineId]
        );
        if (!$line) {
            throw new RuntimeException('Payroll line not found.');
        }

        return $line;
    }

    public function updateLine(int $lineId, ?float $unpaidOverride, ?float $tdsOverride, bool $excluded): int
    {
        $line = $this->line($lineId);
        $this->requireDraft($this->run((int) $line['run_id'], true));
        $old = ['unpaid_override' => $line['unpaid_override'], 'tds_override' => $line['tds_override'], 'excluded' => $line['excluded']];
        $new = [
            'unpaid_override' => $unpaidOverride === null ? null : max(0.0, min((float) $line['period_days'], $unpaidOverride)),
            'tds_override'    => $tdsOverride === null ? null : max(0.0, $tdsOverride),
            'excluded'        => $excluded ? 1 : 0,
        ];
        $this->db->update('tbl_payroll_run_lines', $new, '`id` = ?', [$lineId]);
        auditLog('payroll', 'line_overridden', 'payroll_line', $lineId, $old, $new);
        $this->recalculate((int) $line['run_id']);

        return (int) $line['run_id'];
    }

    public function addAdjustment(int $lineId, string $kind, string $name, float $amount, bool $taxable): int
    {
        $line = $this->line($lineId);
        $this->requireDraft($this->run((int) $line['run_id'], true));
        if ($name === '' || $amount <= 0) {
            throw new RuntimeException('An adjustment needs a name and an amount above 0.');
        }
        $kind = $kind === 'deduction' ? 'deduction' : 'earning';
        $row = ['line_id' => $lineId, 'kind' => $kind, 'code' => 'ADJ', 'name' => mb_substr($name, 0, 191),
            'amount' => round($amount, 2), 'taxable' => $kind === 'earning' && $taxable ? 1 : 0, 'is_adjustment' => 1, 'position' => 900];
        $itemId = $this->db->insert('tbl_payroll_run_line_items', $row);
        auditLog('payroll', 'adjustment_added', 'payroll_line', $lineId, null, $row + ['item_id' => $itemId]);
        $this->recalculate((int) $line['run_id']);

        return (int) $line['run_id'];
    }

    public function removeAdjustment(int $itemId): int
    {
        $item = $this->db->selectOne('SELECT * FROM `tbl_payroll_run_line_items` WHERE `id` = ? AND `is_adjustment` = 1', [$itemId]);
        if (!$item) {
            throw new RuntimeException('Adjustment not found.');
        }
        $line = $this->line((int) $item['line_id']);
        $this->requireDraft($this->run((int) $line['run_id'], true));
        $this->db->delete('tbl_payroll_run_line_items', '`id` = ?', [$itemId]);
        auditLog('payroll', 'adjustment_removed', 'payroll_line', (int) $item['line_id'], $item, null);
        $this->recalculate((int) $line['run_id']);

        return (int) $line['run_id'];
    }

    // ── Internals ────────────────────────────────────────────────────────

    /** @return list<array<string, mixed>> staff employed at some point in the period */
    private function eligibleStaff(array $run): array
    {
        return $this->db->select(
            "SELECT u.*, d.`title` AS designation_title, dp.`title` AS department_title
             FROM `tbl_users_login` u
             LEFT JOIN `tbl_office_designation` d ON d.`id` = u.`designation_id`
             LEFT JOIN `tbl_office_departments` dp ON dp.`id` = u.`department_id`
             WHERE (u.`status` <> 'Terminated' OR u.`termination_date` >= ?)
               AND (u.`join_date` IS NULL OR u.`join_date` <= ?)
             ORDER BY u.`fullname`",
            [$run['period_start'], $run['period_end']]
        );
    }

    /**
     * @param list<array<string, mixed>> $adjustments
     * @param list<string>               $officeOff
     * @param list<array<string, mixed>> $holidays
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>}
     */
    private function buildLine(array $run, ?array $taxYear, array $staff, array $structure, ?array $old, array $adjustments, array $officeOff, array $holidays): array
    {
        $start = $run['period_start'];
        $end = $run['period_end'];
        $staffId = (int) $staff['id'];
        $joinDate = $staff['join_date'] ?: null;
        $leaveDate = $staff['status'] === 'Terminated' ? ($staff['termination_date'] ?: null) : null;
        $employed = static fn (string $d): bool => (!$joinDate || $d >= $joinDate) && (!$leaveDate || $d <= $leaveDate);

        // Weekly off-days: the person's own (if valid weekday names), else the office's.
        $ownOff = array_values(array_intersect(self::WEEKDAYS, array_map('trim', explode(',', (string) ($staff['off_day'] ?? '')))));
        $holidayDates = [];
        foreach ($holidays as $h) {
            if (!holiday_applies_to($h, $staff)) {
                continue;
            }
            foreach (payroll_working_dates(max($start, $h['from_date']), min($end, $h['to_date']), [], []) as $d) {
                $holidayDates[] = $d;
            }
        }
        $working = array_values(array_filter(payroll_working_dates($start, $end, $ownOff ?: $officeOff, $holidayDates), $employed));
        $workingSet = array_flip($working);

        $absent = [];
        $present = [];
        $marked = [];
        foreach ($this->db->select(
            'SELECT `date`, `status` FROM `tbl_staff_attendances` WHERE `user_id` = ? AND `date` BETWEEN ? AND ?',
            [$staffId, $start, $end]
        ) as $a) {
            $marked[$a['date']] = true;
            if ($a['status'] === 'absent' && isset($workingSet[$a['date']])) {
                $absent[$a['date']] = 1.0;
            } elseif ($a['status'] === 'present') {
                $present[$a['date']] = 1.0;
            }
        }

        $unpaidLeave = [];
        $unapproved = [];
        $onLeave = [];
        foreach ($this->db->select(
            'SELECT a.`from_date`, a.`to_date`, a.`half_day`, a.`status`, COALESCE(c.`is_paid`, 1) AS is_paid
             FROM `tbl_staff_leave_applications` a
             LEFT JOIN `tbl_office_leave_configs` c ON c.`id` = a.`leave_type_id`
             WHERE a.`staff_id` = ? AND a.`from_date` <= ? AND a.`to_date` >= ?',
            [$staffId, $end, $start]
        ) as $la) {
            $dates = payroll_leave_dates($la['from_date'], $la['to_date'], (bool) $la['half_day'], $working);
            $onLeave += $dates;
            if ($la['status'] === 'Approved') {
                if (!(int) $la['is_paid']) {
                    foreach ($dates as $d => $f) {
                        $unpaidLeave[$d] = max($unpaidLeave[$d] ?? 0, $f);
                    }
                }
            } else {
                // Pending, Verified (not yet approved) and Rejected: unpaid by default (PAYROLL_PLAN D4).
                foreach ($dates as $d => $f) {
                    $unapproved[$d] = max($unapproved[$d] ?? 0, $f);
                }
            }
        }
        $unpaidComputed = payroll_unpaid_days($absent, $unpaidLeave, $unapproved, $present);

        $lastCountable = min($end, date('Y-m-d'));
        $missing = count(array_filter($working, static fn ($d) => $d <= $lastCountable && !isset($marked[$d]) && !isset($onLeave[$d])));

        $outside = payroll_outside_employment_days($start, $end, $joinDate, $leaveDate);
        $unpaidUsed = $old && $old['unpaid_override'] !== null ? (float) $old['unpaid_override'] : $unpaidComputed;
        $effectiveUnpaid = ((int) $structure['deduct_unpaid_days'] ? $unpaidUsed : 0.0) + $outside;

        $ytd = $this->ytdFor($staffId, $run['tax_year_id'] ? (int) $run['tax_year_id'] : null, $start);
        $items = $structure['items'];
        foreach ($adjustments as $adj) {
            $items[] = ['code' => 'ADJ', 'name' => $adj['name'], 'kind' => $adj['kind'], 'calc' => 'fixed',
                'taxable' => $adj['taxable'], 'prorate' => 0, 'value' => $adj['amount'], 'one_off' => true];
        }
        $taxApplicable = (bool) $structure['tax_applicable'];
        $compute = fn (array $lineItemsIn): array => payroll_compute_line([
            'pay_type'           => $structure['pay_type'],
            'basic'              => (float) $structure['basic'],
            'tax_applicable'     => $taxApplicable && $taxYear !== null,
            'deduct_unpaid_days' => true,
            'items'              => $lineItemsIn,
            'funds'              => $this->pay->enrollmentsFor($staffId, $end),
            'period_days'        => (int) $run['period_days'],
            'unpaid_days'        => $effectiveUnpaid,
            'tax'                => [
                'slabs'                 => payroll_slabs_for($taxYear['slabs'] ?? [], (string) ($staff['tax_filing_status'] ?? 'Single')),
                'retirement_cap'        => (float) ($taxYear['retirement_cap'] ?? 0),
                'retirement_fraction'   => (float) ($taxYear['retirement_fraction'] ?? 0),
                'female_rebate_percent' => (float) ($taxYear['female_rebate_percent'] ?? 0),
                'is_female'             => ($staff['gender'] ?? '') === 'Female',
                'months_remaining'      => (int) $run['months_remaining'],
                'ytd_assessable'        => $ytd['assessable'],
                'ytd_contributions'     => $ytd['contributions'],
                'ytd_tds'               => $ytd['tds'],
            ],
            'tds_override'       => $old['tds_override'] ?? null,
        ]);
        $result = $compute($items);

        // Staff advances: recover after everything else, capped at the net pay left;
        // someone leaving this month repays the whole balance (PAYROLL_PLAN P4).
        $leavingNow = $leaveDate !== null && $leaveDate >= $start && $leaveDate <= $end;
        $available = (float) $result['net'];
        $advanceWarnings = [];
        $recovering = false;
        foreach ($this->db->select(
            "SELECT * FROM `tbl_staff_advances` WHERE `staff_id` = ? AND `status` = 'Active' AND `issued_on` <= ?
               AND (`start_period_key` IS NULL OR `start_period_key` <= ?) ORDER BY `issued_on`, `id`",
            [$staffId, $end, $run['period_key']]
        ) as $adv) {
            $outstanding = $this->advanceOutstanding((int) $adv['id'], (int) $run['id']);
            $rec = payroll_advance_recovery($outstanding, (float) $adv['monthly_installment'], $leavingNow, $available);
            if ($rec > 0) {
                $items[] = ['code' => 'ADV-' . (int) $adv['id'], 'name' => $adv['title'] . ' recovery', 'kind' => 'deduction', 'calc' => 'fixed',
                    'taxable' => 0, 'prorate' => 0, 'value' => $rec];
                $available -= $rec;
                $recovering = true;
            }
            if ($leavingNow && $outstanding - $rec > 0.005) {
                $advanceWarnings[] = 'Leaving with ' . number_format($outstanding - $rec, 2) . ' of “' . $adv['title'] . '” still unrecovered.';
            } elseif (!$leavingNow && $outstanding > 0.005 && $rec < min((float) $adv['monthly_installment'], $outstanding) - 0.005) {
                $advanceWarnings[] = 'Only ' . number_format($rec, 2) . ' of the “' . $adv['title'] . '” instalment could be recovered (net pay too low).';
            }
        }
        if ($recovering) {
            $result = $compute($items);
        }

        $warnings = array_merge($result['warnings'], $advanceWarnings);
        if ($taxApplicable && !$taxYear) {
            $warnings[] = 'No tax year covers this month — TDS not calculated.';
        }
        if ($missing > 0) {
            $warnings[] = $missing . ' working day' . ($missing === 1 ? '' : 's') . ' with no attendance or leave record (not deducted).';
        }
        if ($outside > 0) {
            $warnings[] = $outside . ' day' . ($outside === 1 ? '' : 's') . ' outside employment (joined or left mid-month).';
        }

        $row = [
            'fullname'                => $staff['fullname'],
            'designation'             => $staff['designation_title'],
            'department'              => $staff['department_title'],
            'employment_type'         => $staff['employment_type'],
            'pan_num'                 => $staff['pan_num'] ?: null,
            'bank'                    => $staff['bank'] ?: null,
            'bank_account_num'        => $staff['bank_account_num'] ?: null,
            'bank_account_name'       => $staff['bank_account_name'] ?: null,
            'pay_type'                => $structure['pay_type'],
            'basic_full'              => (float) $structure['basic'],
            'tax_applicable'          => $taxApplicable ? 1 : 0,
            'period_days'             => (int) $run['period_days'],
            'outside_days'            => $outside,
            'unpaid_days'             => $unpaidComputed,
            'payable_days'            => $result['payable_days'],
            'missing_attendance_days' => $missing,
            'gross'                   => $result['gross'],
            'taxable_earnings'        => $result['taxable_earnings'],
            'assessable'              => $result['assessable_month'],
            'employee_funds'          => $result['employee_contributions'],
            'employer_funds'          => $result['employer_contributions'],
            'deductible_funds'        => $result['deductible_contributions'],
            'other_deductions'        => $result['other_deductions'],
            'annual_tax'              => $result['annual']['tax'],
            'tds_computed'            => $result['tds_computed'],
            'tds'                     => $result['tds'],
            'net'                     => $result['net'],
            'employer_cost'           => $result['employer_cost'],
            'warnings'                => $warnings ? json_encode($warnings, JSON_UNESCAPED_UNICODE) : null,
        ];

        $lineItems = [];
        foreach ($result['earnings'] as $e) {
            if (empty($e['one_off'])) {
                $lineItems[] = ['kind' => 'earning', 'code' => $e['code'], 'name' => $e['name'], 'amount' => $e['amount'], 'taxable' => $e['taxable'] ? 1 : 0, 'is_adjustment' => 0];
            }
        }
        foreach ($result['funds'] as $f) {
            if ($f['employee'] > 0) {
                $lineItems[] = ['kind' => 'fund_employee', 'code' => $f['code'], 'name' => $f['name'], 'amount' => $f['employee'], 'taxable' => 0, 'is_adjustment' => 0];
            }
            if ($f['employer'] > 0) {
                $lineItems[] = ['kind' => 'fund_employer', 'code' => $f['code'], 'name' => $f['name'], 'amount' => $f['employer'], 'taxable' => 0, 'is_adjustment' => 0];
            }
        }
        if ($result['tds'] > 0) {
            $lineItems[] = ['kind' => 'tax', 'code' => 'TDS', 'name' => 'Income tax (TDS)', 'amount' => $result['tds'], 'taxable' => 0, 'is_adjustment' => 0];
        }
        foreach ($result['deductions'] as $d) {
            if (empty($d['one_off'])) {
                $advanceId = preg_match('/^ADV-(\d+)$/', $d['code'], $m) ? (int) $m[1] : null;
                $lineItems[] = ['kind' => 'deduction', 'code' => $advanceId ? 'ADV' : $d['code'], 'name' => $d['name'], 'amount' => $d['amount'],
                    'taxable' => 0, 'is_adjustment' => 0, 'advance_id' => $advanceId];
            }
        }

        return [$row, $lineItems];
    }

    /**
     * Balance still owed on an advance: amount − recoveries in Approved /
     * Posted / Paid runs (optionally ignoring one run, e.g. the draft being built).
     */
    public function advanceOutstanding(int $advanceId, ?int $excludeRunId = null): float
    {
        $adv = $this->db->selectOne('SELECT `amount` FROM `tbl_staff_advances` WHERE `id` = ?', [$advanceId]);
        if (!$adv) {
            return 0.0;
        }
        $recovered = (float) ($this->db->selectOne(
            "SELECT COALESCE(SUM(i.`amount`), 0) AS r
             FROM `tbl_payroll_run_line_items` i
             JOIN `tbl_payroll_run_lines` l ON l.`id` = i.`line_id`
             JOIN `tbl_payroll_runs` r ON r.`id` = l.`run_id`
             WHERE i.`advance_id` = ? AND l.`excluded` = 0 AND r.`status` IN ('Approved','Posted','Paid') AND r.`id` <> ?",
            [$advanceId, (int) $excludeRunId]
        )['r'] ?? 0);

        return round(max(0.0, (float) $adv['amount'] - $recovered), 2);
    }

    /**
     * Add the same one-off (e.g. Dashain allowance) to every included line of
     * a Draft run. $mode: fixed | basic_months | percent_of_basic.
     */
    public function addAdjustmentToAll(int $runId, string $name, string $kind, string $mode, float $value, bool $taxable): int
    {
        $run = $this->run($runId, true);
        $this->requireDraft($run);
        if ($name === '' || $value <= 0) {
            throw new RuntimeException('Give the addition a name and a value above 0.');
        }
        $kind = $kind === 'deduction' ? 'deduction' : 'earning';
        $added = 0;
        foreach ($this->db->select('SELECT `id`, `basic_full` FROM `tbl_payroll_run_lines` WHERE `run_id` = ? AND `excluded` = 0', [$runId]) as $l) {
            $amount = payroll_bulk_amount($mode, $value, (float) $l['basic_full']);
            if ($amount <= 0) {
                continue;
            }
            $this->db->insert('tbl_payroll_run_line_items', ['line_id' => (int) $l['id'], 'kind' => $kind, 'code' => 'ADJ', 'name' => mb_substr($name, 0, 191),
                'amount' => $amount, 'taxable' => $kind === 'earning' && $taxable ? 1 : 0, 'is_adjustment' => 1, 'position' => 900]);
            $added++;
        }
        auditLog('payroll', 'adjustment_added_all', 'payroll_run', $runId, null, ['name' => $name, 'mode' => $mode, 'value' => $value, 'lines' => $added]);
        $this->recalculate($runId);

        return $added;
    }

    /**
     * Email every included staff member a payslip-ready notice with a link
     * (no attachment). Skips people without an email address.
     *
     * @return array{sent: int, skipped: int, failed: int}
     */
    public function emailPayslips(int $runId, int $me): array
    {
        $run = $this->run($runId);
        if (!in_array($run['status'], ['Approved', 'Posted', 'Paid'], true)) {
            throw new RuntimeException('Payslips can be emailed once the run is approved.');
        }
        $base = rtrim((string) (config('server_path') ?: config('abs_url', '')), '/');
        $org = office_display_name();
        $counts = ['sent' => 0, 'skipped' => 0, 'failed' => 0];
        foreach ($this->db->select(
            'SELECT l.*, u.`email` FROM `tbl_payroll_run_lines` l JOIN `tbl_users_login` u ON u.`id` = l.`staff_id`
             WHERE l.`run_id` = ? AND l.`excluded` = 0 ORDER BY l.`fullname`',
            [$runId]
        ) as $l) {
            $email = trim((string) $l['email']);
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $counts['skipped']++;
                continue;
            }
            $mail = payroll_payslip_email($l + ['period_label' => $run['period_label']],
                $base . '/admin/show_page.php?module=my_office&page=payslips&line=' . (int) $l['id'], $org);
            $res = CommunicationService::sendEmail($email, $mail['subject'], $mail['html']);
            $counts[!empty($res['ok']) ? 'sent' : 'failed']++;
        }
        auditLog('payroll', 'payslips_emailed', 'payroll_run', $runId, null, $counts, 'Payslip notices for ' . $run['period_label']);

        return $counts;
    }

    /**
     * Year-to-date figures from earlier approved runs in the same tax year.
     *
     * @return array{assessable: float, contributions: float, tds: float}
     */
    private function ytdFor(int $staffId, ?int $taxYearId, string $before): array
    {
        if (!$taxYearId) {
            return ['assessable' => 0.0, 'contributions' => 0.0, 'tds' => 0.0];
        }
        $r = $this->db->selectOne(
            "SELECT COALESCE(SUM(l.`assessable`), 0) AS a, COALESCE(SUM(l.`deductible_funds`), 0) AS c, COALESCE(SUM(l.`tds`), 0) AS t
             FROM `tbl_payroll_run_lines` l
             JOIN `tbl_payroll_runs` r ON r.`id` = l.`run_id`
             WHERE l.`staff_id` = ? AND l.`excluded` = 0 AND r.`tax_year_id` = ?
               AND r.`status` IN ('Approved','Posted','Paid') AND r.`period_start` < ?",
            [$staffId, $taxYearId, $before]
        ) ?: [];

        return ['assessable' => (float) ($r['a'] ?? 0), 'contributions' => (float) ($r['c'] ?? 0), 'tds' => (float) ($r['t'] ?? 0)];
    }

    private function refreshTotals(int $runId): void
    {
        $t = $this->db->selectOne(
            'SELECT COUNT(*) AS n, COALESCE(SUM(`gross`), 0) AS g, COALESCE(SUM(`employee_funds`), 0) AS ef, COALESCE(SUM(`employer_funds`), 0) AS rf,
                    COALESCE(SUM(`tds`), 0) AS t, COALESCE(SUM(`other_deductions`), 0) AS d, COALESCE(SUM(`net`), 0) AS net
             FROM `tbl_payroll_run_lines` WHERE `run_id` = ? AND `excluded` = 0',
            [$runId]
        ) ?: [];
        $this->db->update('tbl_payroll_runs', [
            'headcount'            => (int) ($t['n'] ?? 0),
            'total_gross'          => (float) ($t['g'] ?? 0),
            'total_employee_funds' => (float) ($t['ef'] ?? 0),
            'total_employer_funds' => (float) ($t['rf'] ?? 0),
            'total_tds'            => (float) ($t['t'] ?? 0),
            'total_deductions'     => (float) ($t['d'] ?? 0),
            'total_net'            => (float) ($t['net'] ?? 0),
        ], '`id` = ?', [$runId]);
    }
}
