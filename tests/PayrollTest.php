<?php
/**
 * SB-Tech — PHPUnit tests for functions/payroll.php (pure, no DB).
 * Figures are hand-worked against the FY 2083/84 schedule seeded by
 * seed-table-payroll_tax_years-2083-84.
 *
 * Run: vendor/bin/phpunit tests/PayrollTest.php
 */
use PHPUnit\Framework\TestCase;

class PayrollTest extends TestCase
{
    /** FY 2083/84: ≤10L 1% (SST) · 10–15L 10% · 15–25L 20% · 25–40L 27% · >40L 29%. */
    private function slabs(): array
    {
        return [
            ['filing_status' => 'All', 'lower_limit' => 0, 'upper_limit' => 1000000, 'rate' => 1, 'is_social_security_tax' => 1],
            ['filing_status' => 'All', 'lower_limit' => 1000000, 'upper_limit' => 1500000, 'rate' => 10, 'is_social_security_tax' => 0],
            ['filing_status' => 'All', 'lower_limit' => 1500000, 'upper_limit' => 2500000, 'rate' => 20, 'is_social_security_tax' => 0],
            ['filing_status' => 'All', 'lower_limit' => 2500000, 'upper_limit' => 4000000, 'rate' => 27, 'is_social_security_tax' => 0],
            ['filing_status' => 'All', 'lower_limit' => 4000000, 'upper_limit' => null, 'rate' => 29, 'is_social_security_tax' => 0],
        ];
    }

    private function ssf(): array
    {
        return ['code' => 'SSF', 'name' => 'Social Security Fund', 'employee_rate' => 11, 'employer_rate' => 20,
            'contribution_base' => 'basic', 'max_base' => null, 'tax_deductible' => 1, 'waives_social_security_tax' => 1];
    }

    private function taxContext(array $extra = []): array
    {
        return $extra + ['slabs' => $this->slabs(), 'retirement_cap' => 500000, 'retirement_fraction' => 0.33333333,
            'female_rebate_percent' => 10, 'is_female' => false, 'months_remaining' => 12,
            'ytd_assessable' => 0, 'ytd_contributions' => 0, 'ytd_tds' => 0];
    }

    // ── Slabs ───────────────────────────────────────────────────────────

    public function testAnnualTaxAcrossAllBands()
    {
        // 10,000 + 50,000 + 200,000 + 405,000 + 145,000
        $this->assertSame(810000.0, payroll_annual_tax(4500000, $this->slabs())['tax']);
    }

    public function testAnnualTaxWaivesSocialSecurityBandForContributors()
    {
        $r = payroll_annual_tax(4500000, $this->slabs(), true);
        $this->assertSame(800000.0, $r['tax']);
        $this->assertTrue($r['bands'][0]['waived']);
        $this->assertSame(0.0, payroll_annual_tax(0, $this->slabs())['tax']);
    }

    public function testSlabsForFallsBackToSharedSchedule()
    {
        $slabs = array_reverse($this->slabs());
        $this->assertCount(5, payroll_slabs_for($slabs, 'Couple'));
        $this->assertSame(0, payroll_slabs_for($slabs, 'Single')[0]['lower_limit']);
    }

    public function testSlabsForPrefersOwnStatusRows()
    {
        $slabs = array_merge($this->slabs(), [['filing_status' => 'Couple', 'lower_limit' => 0, 'upper_limit' => null, 'rate' => 5]]);
        $this->assertSame([5], array_column(payroll_slabs_for($slabs, 'Couple'), 'rate'));
    }

    public function testSlabProblemsOnValidSchedule()
    {
        $this->assertSame([], payroll_tax_slab_problems($this->slabs()));
    }

    public function testSlabProblemsCatchesGapsOpenBandsAndBadRates()
    {
        $bad = [
            ['lower_limit' => 100, 'upper_limit' => null, 'rate' => 1],
            ['lower_limit' => 500, 'upper_limit' => 400, 'rate' => 150],
        ];
        $p = payroll_tax_slab_problems($bad);
        $this->assertContains('The first band must start at 0.', $p);
        $this->assertContains('Band 1: only the last band can have no upper limit.', $p);
        $this->assertContains('Band 2: rate must be between 0 and 100%.', $p);
        $this->assertContains('Band 2: upper limit must be above the lower limit.', $p);
        $this->assertSame(['No tax bands defined.'], payroll_tax_slab_problems([]));
    }

    // ── Building blocks ─────────────────────────────────────────────────

    public function testRetirementDeductionTakesLowestLimit()
    {
        $this->assertSame(186000.0, payroll_retirement_deduction(186000, 840000, 0.3333, 500000));
        $this->assertSame(500000.0, payroll_retirement_deduction(900000, 3000000, 0.3333, 500000));
        $this->assertSame(100000.0, payroll_retirement_deduction(150000, 300000, 0.33333333, 500000));
    }

    public function testRetirementDeductionZeroLimitsAreIgnored()
    {
        $this->assertSame(150000.0, payroll_retirement_deduction(150000, 300000, 0, 0));
        $this->assertSame(0.0, payroll_retirement_deduction(-5, 300000, 0.3333, 500000));
    }

    public function testFundContributionDefaultsAndCeiling()
    {
        $this->assertSame(['base' => 50000.0, 'employee' => 5500.0, 'employer' => 10000.0], payroll_fund_contribution($this->ssf(), [], 50000, 60000));
        $capped = payroll_fund_contribution(['max_base' => 40000, 'contribution_base' => 'gross'] + $this->ssf(), [], 50000, 60000);
        $this->assertSame(40000.0, $capped['base']);
    }

    public function testFundContributionOverridesAndFixedAmount()
    {
        $cit = ['code' => 'CIT', 'employee_rate' => 0, 'employer_rate' => 0, 'contribution_base' => 'basic'];
        $this->assertSame(3000.0, payroll_fund_contribution($cit, ['employee_fixed' => 3000], 50000, 50000)['employee']);
        $r = payroll_fund_contribution($this->ssf(), ['employee_rate' => 5, 'employer_rate' => null], 10000, 10000);
        $this->assertSame([500.0, 2000.0], [$r['employee'], $r['employer']]);
    }

    public function testUnpaidDaysUnionAbsenceUnpaidAndUnapprovedLeave()
    {
        $days = payroll_unpaid_days(
            ['2026-10-01' => 1, '2026-10-02' => 1],
            ['2026-10-02' => 1, '2026-10-05' => 0.5],
            ['2026-10-07' => 1]
        );
        $this->assertSame(3.5, $days); // 1st + 2nd (counted once) + half of 5th + 7th
    }

    public function testUnpaidDaysPresenceCancelsFullDayLeaveButNotHalfDay()
    {
        $this->assertSame(0.5, payroll_unpaid_days([], ['2026-10-05' => 0.5], ['2026-10-07' => 1], ['2026-10-05' => 1, '2026-10-07' => 1]));
        $this->assertSame(0.0, payroll_unpaid_days([], [], []));
    }

    public function testPayableDaysAndProrate()
    {
        $this->assertSame(27.0, payroll_payable_days(30, 3));
        $this->assertSame(0.0, payroll_payable_days(30, 40));
        $this->assertSame(27000.0, payroll_prorate(30000, 27, 30));
        $this->assertSame(0.0, payroll_prorate(30000, 27, 0));
    }

    public function testMonthlyTdsSpreadsRemainingTax()
    {
        $this->assertSame(2500.0, payroll_monthly_tds(30000, 0, 12));
        $this->assertSame(3000.0, payroll_monthly_tds(30000, 12000, 6));
        $this->assertSame(0.0, payroll_monthly_tds(30000, 40000, 3)); // over-deducted: never negative
    }

    // ── Full lines ──────────────────────────────────────────────────────

    public function testSsfEnrolledSalaryLine()
    {
        $line = payroll_compute_line([
            'pay_type' => 'Salary', 'basic' => 50000, 'tax_applicable' => true, 'deduct_unpaid_days' => true,
            'items' => [['code' => 'DA', 'name' => 'Dearness Allowance', 'kind' => 'earning', 'calc' => 'fixed', 'taxable' => 1, 'prorate' => 1, 'value' => 10000]],
            'funds' => [['fund' => $this->ssf(), 'enrollment' => []]],
            'period_days' => 30, 'unpaid_days' => 0, 'tax' => $this->taxContext(),
        ]);
        $this->assertSame(60000.0, $line['gross']);
        $this->assertSame(5500.0, $line['employee_contributions']);
        $this->assertSame(10000.0, $line['employer_contributions']);
        // assessable (60,000 + 10,000) × 12 = 840,000; deduction 186,000; 654,000 falls in the waived 1% band.
        $this->assertSame(840000.0, $line['annual']['assessable']);
        $this->assertSame(186000.0, $line['annual']['retirement_deduction']);
        $this->assertSame(0.0, $line['tds']);
        $this->assertSame(54500.0, $line['net']);
        $this->assertSame(70000.0, $line['employer_cost']);
    }

    public function testUnenrolledSalaryPaysTaxIncludingSocialSecurityBand()
    {
        $line = payroll_compute_line([
            'basic' => 100000, 'tax_applicable' => true, 'period_days' => 30, 'tax' => $this->taxContext(),
        ]);
        // 1,200,000: 10,000 + 20,000 = 30,000 a year → 2,500 a month.
        $this->assertSame(30000.0, $line['annual']['tax']);
        $this->assertSame(2500.0, $line['tds']);
        $this->assertSame(97500.0, $line['net']);
    }

    public function testFemaleRebateReducesTax()
    {
        $line = payroll_compute_line(['basic' => 100000, 'tax_applicable' => true, 'period_days' => 30, 'tax' => $this->taxContext(['is_female' => true])]);
        $this->assertSame(27000.0, $line['annual']['tax']);
        $this->assertSame(2250.0, $line['tds']);
    }

    public function testInternStipendWithoutTaxOrFunds()
    {
        $line = payroll_compute_line(['pay_type' => 'Stipend', 'basic' => 15000, 'tax_applicable' => false, 'period_days' => 30, 'tax' => $this->taxContext()]);
        $this->assertSame('Stipend', $line['earnings'][0]['name']);
        $this->assertSame(0.0, $line['tds']);
        $this->assertSame(15000.0, $line['net']);
    }

    public function testStipendCanStillBeTaxedAndSsfEnrolled()
    {
        $line = payroll_compute_line([
            'pay_type' => 'Stipend', 'basic' => 20000, 'tax_applicable' => true, 'period_days' => 30,
            'funds' => [['fund' => $this->ssf(), 'enrollment' => []]], 'tax' => $this->taxContext(),
        ]);
        $this->assertSame(2200.0, $line['employee_contributions']);
        $this->assertSame(17800.0, $line['net']); // SST waived for the SSF contributor → no TDS
    }

    public function testUnpaidDaysProrateBasicAndAllowancesButNotFixedOneOffs()
    {
        $line = payroll_compute_line([
            'basic' => 30000, 'period_days' => 30, 'unpaid_days' => 3, 'deduct_unpaid_days' => true,
            'items' => [
                ['code' => 'TRANSPORT', 'name' => 'Transport', 'kind' => 'earning', 'calc' => 'fixed', 'taxable' => 1, 'prorate' => 1, 'value' => 3000],
                ['code' => 'FESTIVAL', 'name' => 'Festival', 'kind' => 'earning', 'calc' => 'fixed', 'taxable' => 1, 'prorate' => 0, 'value' => 30000],
                ['code' => 'ADVANCE', 'name' => 'Advance', 'kind' => 'deduction', 'calc' => 'fixed', 'taxable' => 0, 'prorate' => 0, 'value' => 5000],
            ],
        ]);
        $this->assertSame(27.0, $line['payable_days']);
        $this->assertSame(27000.0, $line['basic']);
        $this->assertSame(59700.0, $line['gross']); // 27,000 + 2,700 + 30,000
        $this->assertSame(54700.0, $line['net']);
    }

    public function testDeductUnpaidDaysOffPaysFullPeriod()
    {
        $line = payroll_compute_line(['basic' => 30000, 'period_days' => 30, 'unpaid_days' => 3, 'deduct_unpaid_days' => false]);
        $this->assertSame(30000.0, $line['basic']);
        $this->assertSame(0.0, $line['unpaid_days']);
    }

    public function testTdsOverrideReplacesComputedTax()
    {
        $line = payroll_compute_line(['basic' => 100000, 'tax_applicable' => true, 'period_days' => 30, 'tax' => $this->taxContext(), 'tds_override' => '1000']);
        $this->assertSame(2500.0, $line['tds_computed']);
        $this->assertSame(1000.0, $line['tds']);
        $this->assertTrue($line['tds_overridden']);
        $this->assertSame(99000.0, $line['net']);
    }

    public function testWarningsForMissingBasicAndNegativeNet()
    {
        $line = payroll_compute_line(['basic' => 0, 'period_days' => 30,
            'items' => [['code' => 'ADVANCE', 'name' => 'Advance', 'kind' => 'deduction', 'calc' => 'fixed', 'taxable' => 0, 'prorate' => 0, 'value' => 500]]]);
        $this->assertContains('No basic salary or stipend is set.', $line['warnings']);
        $this->assertContains('Deductions exceed earnings — net pay is negative.', $line['warnings']);
    }

    // ── P2: one-off amounts, YTD, working dates ─────────────────────────

    public function testOneOffBonusIsTaxedOnceNotProjected()
    {
        // 100,000/month unenrolled + a 200,000 bonus this month, 12 months left:
        // assessable = 1,200,000 + 200,000 = 1,400,000 → 10,000 + 40,000 = 50,000.
        $line = payroll_compute_line([
            'basic' => 100000, 'tax_applicable' => true, 'period_days' => 30, 'tax' => $this->taxContext(),
            'items' => [['code' => 'ADJ', 'name' => 'Bonus', 'kind' => 'earning', 'calc' => 'fixed', 'taxable' => 1, 'prorate' => 0, 'value' => 200000, 'one_off' => true]],
        ]);
        $this->assertSame(1400000.0, $line['annual']['assessable']);
        $this->assertSame(50000.0, $line['annual']['tax']);
        $this->assertSame(300000.0, $line['assessable_month']);
        // Regular 2,500 a month + the bonus's whole extra tax (50,000 − 30,000) now.
        $this->assertSame(22500.0, $line['tds']);
    }

    public function testYtdFiguresCarryIntoProjection()
    {
        // 6 months already paid at 100,000 with 15,000 TDS; 6 months left at the same pay.
        $line = payroll_compute_line(['basic' => 100000, 'tax_applicable' => true, 'period_days' => 30,
            'tax' => $this->taxContext(['months_remaining' => 6, 'ytd_assessable' => 600000, 'ytd_tds' => 15000])]);
        $this->assertSame(30000.0, $line['annual']['tax']);
        $this->assertSame(2500.0, $line['tds']); // (30,000 − 15,000) ÷ 6
    }

    public function testWorkingDatesSkipOffDaysAndHolidays()
    {
        // 2026-10-10 is a Saturday; 2026-10-12 a holiday.
        $this->assertSame(['2026-10-09', '2026-10-11', '2026-10-13'],
            payroll_working_dates('2026-10-09', '2026-10-13', ['Saturday'], ['2026-10-12']));
        $this->assertSame([], payroll_working_dates('2026-10-13', '2026-10-12', [], []));
    }

    public function testLeaveDatesOnlyCountWorkingDays()
    {
        $working = ['2026-10-09', '2026-10-11', '2026-10-13'];
        $this->assertSame(['2026-10-09' => 1.0, '2026-10-11' => 1.0], payroll_leave_dates('2026-10-08', '2026-10-11', false, $working));
        $this->assertSame(['2026-10-13' => 0.5], payroll_leave_dates('2026-10-13', '2026-10-13', true, $working));
    }

    public function testOutsideEmploymentDays()
    {
        $this->assertSame(9, payroll_outside_employment_days('2026-09-17', '2026-10-16', '2026-09-26', null));
        $this->assertSame(6, payroll_outside_employment_days('2026-09-17', '2026-10-16', null, '2026-10-10'));
        $this->assertSame(0, payroll_outside_employment_days('2026-09-17', '2026-10-16', '2025-01-01', null));
    }

    // ── P3: journal posting ─────────────────────────────────────────────

    private function accounts(): array
    {
        return ['salary_expense' => 19, 'employer_contribution' => 31, 'staff_payable' => 11, 'tds_payable' => 9,
            'other_deductions' => 33, 'funds' => ['SSF' => 32]];
    }

    public function testJournalLinesBalanceForATypicalRun()
    {
        // gross 300,000; SSF 5,500 employee + 10,000 employer; TDS 15,500; advance 2,000.
        $net = 300000 - 5500 - 15500 - 2000;
        $lines = payroll_journal_lines(
            ['gross' => 300000, 'employer_funds' => 10000, 'net' => $net, 'tds' => 15500, 'other_deductions' => 2000],
            ['SSF' => 15500], $this->accounts(), 'Kartik 2083'
        );
        $this->assertSame(310000.0, array_sum(array_column($lines, 'debit')));
        $this->assertSame(310000.0, array_sum(array_column($lines, 'credit')));
        $this->assertCount(6, $lines);
        $this->assertSame(['account_terminal_id' => 32, 'debit' => 0.0, 'credit' => 15500.0, 'remarks' => 'SSF contributions — Kartik 2083'], $lines[5]);
    }

    public function testJournalLinesDropZeroLines()
    {
        $lines = payroll_journal_lines(['gross' => 15000, 'employer_funds' => 0, 'net' => 15000, 'tds' => 0, 'other_deductions' => 0], [], $this->accounts(), 'x');
        $this->assertSame([19, 11], array_column($lines, 'account_terminal_id'));
    }

    public function testJournalLinesRefuseUnmappedAccounts()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PF payable');
        payroll_journal_lines(['gross' => 100, 'employer_funds' => 10, 'net' => 80, 'tds' => 0, 'other_deductions' => 0], ['PF' => 30], $this->accounts(), 'x');
    }

    public function testJournalLinesRefuseImbalance()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not balance');
        payroll_journal_lines(['gross' => 100, 'employer_funds' => 0, 'net' => 90, 'tds' => 0, 'other_deductions' => 0], [], $this->accounts(), 'x');
    }

    // ── P4: advances, bulk one-offs, payslip email ──────────────────────

    public function testAdvanceRecoveryTakesInstalmentWithinBalance()
    {
        $this->assertSame(5000.0, payroll_advance_recovery(20000, 5000, false, 50000));
        $this->assertSame(3000.0, payroll_advance_recovery(3000, 5000, false, 50000)); // last instalment
    }

    public function testAdvanceRecoveryOnLeavingAndCapByNetPay()
    {
        $this->assertSame(20000.0, payroll_advance_recovery(20000, 5000, true, 50000)); // leaving: recover all
        $this->assertSame(12000.0, payroll_advance_recovery(20000, 5000, true, 12000)); // but not below zero net
        $this->assertSame(0.0, payroll_advance_recovery(0, 5000, false, 50000));
        $this->assertSame(0.0, payroll_advance_recovery(20000, 5000, false, -100));
    }

    public function testBulkAmountModes()
    {
        $this->assertSame(10000.0, payroll_bulk_amount('fixed', 10000, 50000));
        $this->assertSame(50000.0, payroll_bulk_amount('basic_months', 1, 50000));
        $this->assertSame(25000.0, payroll_bulk_amount('basic_months', 0.5, 50000));
        $this->assertSame(5000.0, payroll_bulk_amount('percent_of_basic', 10, 50000));
    }

    public function testPayslipEmailEscapesAndSummarises()
    {
        $mail = payroll_payslip_email(
            ['fullname' => 'Ram <b>Thapa</b>', 'period_label' => 'Ashwin 2083', 'gross' => 60000, 'employee_funds' => 5500, 'tds' => 0, 'other_deductions' => 500, 'net' => 54000],
            'http://x/admin/show_page.php?module=my_office&page=payslips&line=7', 'Codegenexis'
        );
        $this->assertSame('Your payslip for Ashwin 2083 is ready', $mail['subject']);
        $this->assertStringContainsString('Dear Ram,', $mail['html']);
        $this->assertStringContainsString('NPR 6,000.00', $mail['html']); // deductions
        $this->assertStringContainsString('NPR 54,000.00', $mail['html']);
        $this->assertStringContainsString('page=payslips&amp;line=7', $mail['html']);
        $this->assertStringNotContainsString('<b>', $mail['html']);
    }

    // ── Review fixes ────────────────────────────────────────────────────

    public function testGrossBasedFundShareOnOneOffIsNotProjected()
    {
        // Employer 20% on gross; 100,000 basic + a 50,000 one-off; 9 months left.
        // Recurring: (100,000 + 20,000 employer) × 9 = 1,080,000; one-off: 50,000 + 10,000 = 60,000.
        $fund = ['code' => 'GF', 'name' => 'Gross fund', 'employee_rate' => 0, 'employer_rate' => 20,
            'contribution_base' => 'gross', 'max_base' => null, 'tax_deductible' => 0, 'waives_social_security_tax' => 0];
        $line = payroll_compute_line([
            'basic' => 100000, 'tax_applicable' => true, 'period_days' => 30,
            'items' => [['code' => 'ADJ', 'name' => 'Dashain', 'kind' => 'earning', 'calc' => 'fixed', 'taxable' => 1, 'prorate' => 0, 'value' => 50000, 'one_off' => true]],
            'funds' => [['fund' => $fund, 'enrollment' => []]],
            'tax' => $this->taxContext(['months_remaining' => 9]),
        ]);
        $this->assertSame(30000.0, $line['employer_contributions']); // actual this month: 20% of 150,000
        $this->assertSame(1140000.0, $line['annual']['assessable']);
        // 18,000 recurring tax ÷ 9 = 2,000 + the one-off's extra 6,000 (24,000 − 18,000).
        $this->assertSame(8000.0, $line['tds']);
    }

    public function testMissingTaxBandsWarnInsteadOfSilentZero()
    {
        $line = payroll_compute_line(['basic' => 100000, 'tax_applicable' => true, 'period_days' => 30, 'tax' => $this->taxContext(['slabs' => []])]);
        $this->assertSame(0.0, $line['tds']);
        $this->assertStringContainsString('No tax bands apply', implode(' ', $line['warnings']));
        $quiet = payroll_compute_line(['basic' => 100000, 'tax_applicable' => false, 'period_days' => 30, 'tax' => $this->taxContext(['slabs' => []])]);
        $this->assertSame([], $quiet['warnings']); // tax switched off: nothing to warn about
    }

    public function testJournalSplitsAdvanceRecoveriesFromOtherDeductions()
    {
        $accounts = $this->accounts() + ['advances' => 56];
        $lines = payroll_journal_lines(
            ['gross' => 100000, 'employer_funds' => 0, 'net' => 92000, 'tds' => 0, 'other_deductions' => 8000, 'advance_recoveries' => 5000],
            [], $accounts, 'x'
        );
        $credits = array_column(array_filter($lines, static fn ($l) => $l['credit'] > 0), 'credit', 'account_terminal_id');
        $this->assertSame([11 => 92000.0, 56 => 5000.0, 33 => 3000.0], $credits);
    }

    public function testJournalRequiresAdvancesAccountOnlyWhenRecovering()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('advances');
        payroll_journal_lines(['gross' => 100, 'employer_funds' => 0, 'net' => 90, 'tds' => 0, 'other_deductions' => 10, 'advance_recoveries' => 10], [], $this->accounts(), 'x');
    }

    public function testHolidayAppliesByDepartmentAndGender()
    {
        $this->assertTrue(holiday_applies_to(['department_id' => null, 'gender_to' => 'Both'], ['department_id' => 5, 'gender' => 'Male']));
        $this->assertFalse(holiday_applies_to(['department_id' => 7, 'gender_to' => 'Both'], ['department_id' => 5, 'gender' => 'Male']));
        $this->assertTrue(holiday_applies_to(['department_id' => 5, 'gender_to' => 'Female'], ['department_id' => 5, 'gender' => 'female']));
        $this->assertFalse(holiday_applies_to(['department_id' => null, 'gender_to' => 'Female'], ['department_id' => 5, 'gender' => 'Male']));
    }

    public function testHolidayAppliesToEveryoneWithoutAPerson()
    {
        $this->assertTrue(holiday_applies_to(['department_id' => 7, 'gender_to' => 'Female'], null));
        $this->assertTrue(holiday_applies_to(['gender_to' => null], ['department_id' => 1, 'gender' => 'Male'])); // NULL gender scope = Both
    }

    public function testPayslipEmailEdgeCases()
    {
        $mail = payroll_payslip_email(
            ['fullname' => '', 'period_label' => 'Ashwin <2083>', 'gross' => 0, 'employee_funds' => 0, 'tds' => 0, 'other_deductions' => 0, 'net' => 0],
            'http://x/?a=1&b="2"', 'A & B'
        );
        $this->assertStringContainsString('<p>Hello,</p>', $mail['html']);
        $this->assertStringContainsString('Ashwin &lt;2083&gt;', $mail['html']);
        $this->assertStringContainsString('href="http://x/?a=1&amp;b=&quot;2&quot;"', $mail['html']);
        $this->assertStringContainsString('A &amp; B', $mail['html']);
        $this->assertSame('Your payslip for Ashwin <2083> is ready', $mail['subject']); // plain-text subject, not HTML
    }
}
