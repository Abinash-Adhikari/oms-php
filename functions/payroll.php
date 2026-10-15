<?php

/**
 * SB-Tech — Payroll calculation (docs/PAYROLL_PLAN.md §5).
 *
 * Pure functions only (RULES §1.2): rates, slabs, enrollments and attendance
 * facts all arrive as arguments, so every rule is unit-tested in
 * tests/PayrollTest.php. Money is rounded to 2 dp per line item; totals are
 * sums of rounded items so a payslip and its voucher agree to the paisa.
 *
 * Percent fields (fund rates, slab rates, component % of basic) are stored
 * as percentages (11 = 11%); fractions (retirement_fraction) as 0..1.
 */

/**
 * The slab schedule for a filing status: that status's own rows when the
 * year defines them, otherwise the shared 'All' schedule. Sorted by lower limit.
 *
 * @param list<array<string, mixed>> $slabs
 * @return list<array<string, mixed>>
 */
function payroll_slabs_for(array $slabs, string $filingStatus): array
{
    $own = array_values(array_filter($slabs, static fn ($s) => ($s['filing_status'] ?? 'All') === $filingStatus));
    $rows = $own ?: array_values(array_filter($slabs, static fn ($s) => ($s['filing_status'] ?? 'All') === 'All'));
    usort($rows, static fn ($a, $b) => (float) $a['lower_limit'] <=> (float) $b['lower_limit']);

    return $rows;
}

/**
 * Sanity-check one slab schedule (sorted by lower limit). Returns readable
 * problems; an empty list means the schedule is contiguous and complete.
 *
 * @param list<array<string, mixed>> $slabs
 * @return list<string>
 */
function payroll_tax_slab_problems(array $slabs): array
{
    if (!$slabs) {
        return ['No tax bands defined.'];
    }
    $problems = [];
    $last = count($slabs) - 1;
    if (abs((float) $slabs[0]['lower_limit']) > 0.005) {
        $problems[] = 'The first band must start at 0.';
    }
    foreach ($slabs as $i => $s) {
        $n = $i + 1;
        $lower = (float) $s['lower_limit'];
        $upper = $s['upper_limit'] === null || $s['upper_limit'] === '' ? null : (float) $s['upper_limit'];
        $rate = (float) $s['rate'];
        if ($rate < 0 || $rate > 100) {
            $problems[] = "Band {$n}: rate must be between 0 and 100%.";
        }
        if ($upper !== null && $upper <= $lower) {
            $problems[] = "Band {$n}: upper limit must be above the lower limit.";
        }
        if ($upper === null && $i !== $last) {
            $problems[] = "Band {$n}: only the last band can have no upper limit.";
        }
        if ($i > 0) {
            $prevUpper = $slabs[$i - 1]['upper_limit'];
            if ($prevUpper !== null && $prevUpper !== '' && abs((float) $prevUpper - $lower) > 0.005) {
                $problems[] = "Band {$n} starts at " . number_format($lower, 2) . ' but band ' . $i . ' ends at ' . number_format((float) $prevUpper, 2) . '.';
            }
        }
    }
    $lastUpper = $slabs[$last]['upper_limit'];
    if ($lastUpper !== null && $lastUpper !== '') {
        $problems[] = 'The last band has an upper limit, so income above ' . number_format((float) $lastUpper, 2) . ' would be untaxed.';
    }

    return $problems;
}

/**
 * Progressive annual tax with a per-band breakdown. When $waiveSocialSecurityTax
 * is true (SSF / pension-fund contributor) bands flagged is_social_security_tax
 * are charged at 0.
 *
 * @param list<array<string, mixed>> $slabs one schedule, sorted by lower limit
 * @return array{tax: float, bands: list<array{lower: float, upper: ?float, rate: float, amount: float, tax: float, waived: bool}>}
 */
function payroll_annual_tax(float $taxableIncome, array $slabs, bool $waiveSocialSecurityTax = false): array
{
    $bands = [];
    $total = 0.0;
    foreach ($slabs as $s) {
        $lower = (float) $s['lower_limit'];
        $upper = $s['upper_limit'] === null || $s['upper_limit'] === '' ? null : (float) $s['upper_limit'];
        $inBand = max(0.0, min($taxableIncome, $upper ?? INF) - $lower);
        $waived = $waiveSocialSecurityTax && !empty($s['is_social_security_tax']);
        $tax = $waived ? 0.0 : round($inBand * (float) $s['rate'] / 100, 2);
        $total += $tax;
        $bands[] = ['lower' => $lower, 'upper' => $upper, 'rate' => (float) $s['rate'], 'amount' => round($inBand, 2), 'tax' => $tax, 'waived' => $waived];
    }

    return ['tax' => round($total, 2), 'bands' => $bands];
}

/**
 * Deduction allowed for retirement-fund contributions: the lowest of the
 * contributions, $fraction of assessable income, and $cap. A fraction or cap
 * of 0 (or less) means that limit is not applied.
 */
function payroll_retirement_deduction(float $contributions, float $assessableIncome, float $fraction, float $cap): float
{
    $limits = [max(0.0, $contributions)];
    if ($fraction > 0) {
        $limits[] = max(0.0, $assessableIncome * $fraction);
    }
    if ($cap > 0) {
        $limits[] = $cap;
    }

    return round(min($limits), 2);
}

/**
 * One fund's monthly contributions. The enrollment's overrides win over the
 * fund defaults; a fixed employee amount (e.g. a CIT pledge) beats any rate.
 *
 * @param array<string, mixed> $fund       row of tbl_payroll_funds
 * @param array<string, mixed> $enrollment row of tbl_staff_fund_enrollments
 * @return array{base: float, employee: float, employer: float}
 */
function payroll_fund_contribution(array $fund, array $enrollment, float $basic, float $gross): array
{
    $base = ($fund['contribution_base'] ?? 'basic') === 'gross' ? $gross : $basic;
    $maxBase = (float) ($fund['max_base'] ?? 0);
    if ($maxBase > 0) {
        $base = min($base, $maxBase);
    }
    $employeeRate = isset($enrollment['employee_rate']) && $enrollment['employee_rate'] !== '' ? (float) $enrollment['employee_rate'] : (float) ($fund['employee_rate'] ?? 0);
    $employerRate = isset($enrollment['employer_rate']) && $enrollment['employer_rate'] !== '' ? (float) $enrollment['employer_rate'] : (float) ($fund['employer_rate'] ?? 0);
    $employee = isset($enrollment['employee_fixed']) && $enrollment['employee_fixed'] !== ''
        ? (float) $enrollment['employee_fixed']
        : $base * $employeeRate / 100;

    return [
        'base'     => round($base, 2),
        'employee' => round($employee, 2),
        'employer' => round($base * $employerRate / 100, 2),
    ];
}

/**
 * Unpaid days in a period (PAYROLL_PLAN D4). Each map is date => fraction of
 * the day (1 or 0.5):
 *   $absent       attendance marked absent
 *   $unpaidLeave  approved leave of an unpaid type
 *   $unapproved   leave applied for but not approved (pending/verified/rejected)
 *   $present      attendance marked present
 * A day counts once (largest fraction). On a day the person was present, only
 * part-day leave still counts (a half-day unpaid leave plus a half day worked).
 *
 * @param array<string, float|int> $absent
 * @param array<string, float|int> $unpaidLeave
 * @param array<string, float|int> $unapproved
 * @param array<string, float|int> $present
 */
function payroll_unpaid_days(array $absent, array $unpaidLeave, array $unapproved, array $present = []): float
{
    $total = 0.0;
    foreach (array_keys($absent + $unpaidLeave + $unapproved) as $date) {
        $leave = max((float) ($unpaidLeave[$date] ?? 0), (float) ($unapproved[$date] ?? 0));
        if (isset($present[$date])) {
            $day = $leave < 1 ? $leave : 0.0;
        } else {
            $day = max($leave, (float) ($absent[$date] ?? 0));
        }
        $total += min(1.0, $day);
    }

    return $total;
}

/** Days paid in a period: never negative, never more than the period. */
function payroll_payable_days(int $periodDays, float $unpaidDays): float
{
    return max(0.0, min((float) $periodDays, $periodDays - $unpaidDays));
}

/** Scale a monthly amount by days paid. */
function payroll_prorate(float $amount, float $payableDays, int $periodDays): float
{
    if ($periodDays <= 0) {
        return 0.0;
    }

    return round($amount * min($payableDays, $periodDays) / $periodDays, 2);
}

/**
 * This month's TDS under the projection method: tax still owed for the year
 * spread over the months left (including this one). Never negative.
 */
function payroll_monthly_tds(float $annualTax, float $tdsDeductedYtd, int $monthsRemaining): float
{
    $months = max(1, $monthsRemaining);

    return round(max(0.0, ($annualTax - $tdsDeductedYtd) / $months), 2);
}

/**
 * Compute one staff member's monthly pay line.
 *
 * Input keys:
 *   pay_type ('Salary'|'Stipend'), basic, tax_applicable (bool), deduct_unpaid_days (bool),
 *   items: list of {code, name, kind (earning|deduction), calc (fixed|percent_of_basic), taxable, prorate, value,
 *          one_off? (bool — a this-month-only amount such as a bonus or arrears)},
 *   funds: list of {fund: tbl_payroll_funds row, enrollment: tbl_staff_fund_enrollments row},
 *   period_days, unpaid_days,
 *   tax: {slabs, retirement_cap, retirement_fraction, female_rebate_percent, is_female,
 *         months_remaining (incl. this month), ytd_assessable, ytd_contributions, ytd_tds},
 *   tds_override (?float — a manual TDS replaces the computed one).
 *
 * Assessable income includes the employer's fund contributions; employee +
 * employer contributions to tax-deductible funds are then deducted within the
 * year's cap. Annual tax is projected from YTD + this month's recurring pay ×
 * months left and spread over those months; one-off amounts are added once
 * (never multiplied) and the extra tax they cause is withheld this month.
 *
 * @param array<string, mixed> $in
 * @return array<string, mixed>
 */
function payroll_compute_line(array $in): array
{
    $period = max(1, (int) ($in['period_days'] ?? 30));
    $unpaid = max(0.0, (float) ($in['unpaid_days'] ?? 0));
    $deductUnpaid = (bool) ($in['deduct_unpaid_days'] ?? true);
    $payable = $deductUnpaid ? payroll_payable_days($period, $unpaid) : (float) $period;
    $basicFull = (float) ($in['basic'] ?? 0);
    $basic = payroll_prorate($basicFull, $payable, $period);
    $warnings = [];
    if ($basicFull <= 0) {
        $warnings[] = 'No basic salary or stipend is set.';
    }

    $earnings = [[
        'code' => 'BASIC', 'name' => ($in['pay_type'] ?? 'Salary') === 'Stipend' ? 'Stipend' : 'Basic salary',
        'amount' => $basic, 'taxable' => true,
    ]];
    $deductions = [];
    foreach ($in['items'] ?? [] as $it) {
        $raw = ($it['calc'] ?? 'fixed') === 'percent_of_basic' ? $basicFull * (float) $it['value'] / 100 : (float) $it['value'];
        $amount = !empty($it['prorate']) ? payroll_prorate($raw, $payable, $period) : round($raw, 2);
        if ($amount == 0.0) {
            continue;
        }
        $row = ['code' => (string) $it['code'], 'name' => (string) $it['name'], 'amount' => $amount,
            'taxable' => !empty($it['taxable']), 'one_off' => !empty($it['one_off'])];
        if (($it['kind'] ?? 'earning') === 'deduction') {
            $deductions[] = $row;
        } else {
            $earnings[] = $row;
        }
    }
    $gross = round(array_sum(array_column($earnings, 'amount')), 2);
    $taxableEarnings = round(array_sum(array_map(static fn ($e) => $e['taxable'] ? $e['amount'] : 0.0, $earnings)), 2);
    $oneOffTaxable = round(array_sum(array_map(static fn ($e) => $e['taxable'] && !empty($e['one_off']) ? $e['amount'] : 0.0, $earnings)), 2);

    $funds = [];
    $employeeTotal = 0.0;
    $employerTotal = 0.0;
    $deductibleTotal = 0.0;
    $waiveSst = false;
    foreach ($in['funds'] ?? [] as $f) {
        $c = payroll_fund_contribution($f['fund'], $f['enrollment'] ?? [], $basic, $gross);
        $funds[] = ['code' => (string) $f['fund']['code'], 'name' => (string) $f['fund']['name']] + $c;
        $employeeTotal += $c['employee'];
        $employerTotal += $c['employer'];
        if (!empty($f['fund']['tax_deductible'])) {
            $deductibleTotal += $c['employee'] + $c['employer'];
        }
        if (!empty($f['fund']['waives_social_security_tax'])) {
            $waiveSst = true;
        }
    }

    // The same contributions on recurring pay only (gross without one-offs):
    // only these may be projected over the remaining months.
    $grossRecurring = round($gross - array_sum(array_map(static fn ($e) => !empty($e['one_off']) ? $e['amount'] : 0.0, $earnings)), 2);
    $employerRecurring = 0.0;
    $deductibleRecurring = 0.0;
    foreach ($in['funds'] ?? [] as $f) {
        $c = payroll_fund_contribution($f['fund'], $f['enrollment'] ?? [], $basic, $grossRecurring);
        $employerRecurring += $c['employer'];
        if (!empty($f['fund']['tax_deductible'])) {
            $deductibleRecurring += $c['employee'] + $c['employer'];
        }
    }

    $annual = ['assessable' => 0.0, 'retirement_deduction' => 0.0, 'taxable' => 0.0, 'tax' => 0.0];
    $tdsComputed = 0.0;
    if (!empty($in['tax_applicable'])) {
        $t = $in['tax'] ?? [];
        $months = max(1, (int) ($t['months_remaining'] ?? 12));
        if (empty($t['slabs'])) {
            $warnings[] = 'No tax bands apply to this person’s filing status — TDS is 0. Check Payroll → Tax Slabs.';
        }
        // Annual tax for an assessable income and its deductible fund contributions.
        $annualTaxOn = static function (float $assessable, float $contributions) use ($t, $waiveSst): array {
            $deduction = payroll_retirement_deduction(
                $contributions,
                $assessable,
                (float) ($t['retirement_fraction'] ?? 0),
                (float) ($t['retirement_cap'] ?? 0)
            );
            $taxable = max(0.0, $assessable - $deduction);
            $tax = payroll_annual_tax($taxable, $t['slabs'] ?? [], $waiveSst)['tax'];
            if (!empty($t['is_female']) && (float) ($t['female_rebate_percent'] ?? 0) > 0) {
                $tax = round($tax * (1 - (float) $t['female_rebate_percent'] / 100), 2);
            }

            return ['assessable' => round($assessable, 2), 'retirement_deduction' => $deduction, 'taxable' => round($taxable, 2), 'tax' => $tax];
        };
        $recurringAssessable = (float) ($t['ytd_assessable'] ?? 0) + ($taxableEarnings - $oneOffTaxable + $employerRecurring) * $months;
        $recurringContrib = (float) ($t['ytd_contributions'] ?? 0) + $deductibleRecurring * $months;
        $oneOffAssessable = $oneOffTaxable + ($employerTotal - $employerRecurring);
        $oneOffContrib = $deductibleTotal - $deductibleRecurring;

        $withoutOneOffs = $annualTaxOn($recurringAssessable, $recurringContrib);
        $annual = $annualTaxOn($recurringAssessable + $oneOffAssessable, $recurringContrib + $oneOffContrib);
        // Recurring tax is spread over the months left; the extra tax one-off
        // amounts cause is withheld in full in the month they are paid.
        $tdsComputed = payroll_monthly_tds($withoutOneOffs['tax'], (float) ($t['ytd_tds'] ?? 0), $months);
        if ($oneOffAssessable > 0) {
            $tdsComputed = round($tdsComputed + max(0.0, $annual['tax'] - $withoutOneOffs['tax']), 2);
        }
    }
    $override = $in['tds_override'] ?? null;
    $tds = $override !== null && $override !== '' ? round(max(0.0, (float) $override), 2) : $tdsComputed;

    $otherDeductions = round(array_sum(array_column($deductions, 'amount')), 2);
    $net = round($gross - $employeeTotal - $tds - $otherDeductions, 2);
    if ($net < 0) {
        $warnings[] = 'Deductions exceed earnings — net pay is negative.';
    }

    return [
        'payable_days'           => $payable,
        'unpaid_days'            => $deductUnpaid ? min($unpaid, (float) $period) : 0.0,
        'basic'                  => $basic,
        'earnings'               => $earnings,
        'gross'                  => $gross,
        'taxable_earnings'       => $taxableEarnings,
        'assessable_month'       => round($taxableEarnings + $employerTotal, 2),
        'deductible_contributions' => round($deductibleTotal, 2),
        'funds'                  => $funds,
        'employee_contributions' => round($employeeTotal, 2),
        'employer_contributions' => round($employerTotal, 2),
        'deductions'             => $deductions,
        'other_deductions'       => $otherDeductions,
        'annual'                 => $annual,
        'tds_computed'           => $tdsComputed,
        'tds'                    => $tds,
        'tds_overridden'         => $override !== null && $override !== '',
        'net'                    => $net,
        'employer_cost'          => round($gross + $employerTotal, 2),
        'warnings'               => $warnings,
    ];
}

/**
 * Working dates in [$start, $end]: every date that is not a weekly off-day
 * (English weekday names, e.g. ['Saturday']) and not in $holidays.
 *
 * @param list<string> $offWeekdays
 * @param list<string> $holidays Y-m-d
 * @return list<string>
 */
function payroll_working_dates(string $start, string $end, array $offWeekdays, array $holidays): array
{
    $off = array_map('strtolower', array_map('trim', $offWeekdays));
    $holidaySet = array_flip($holidays);
    $dates = [];
    for ($d = strtotime($start), $last = strtotime($end); $d !== false && $last !== false && $d <= $last; $d = strtotime('+1 day', $d)) {
        $ymd = date('Y-m-d', $d);
        if (!isset($holidaySet[$ymd]) && !in_array(strtolower(date('l', $d)), $off, true)) {
            $dates[] = $ymd;
        }
    }

    return $dates;
}

/**
 * A leave application's days that fall on working dates, as date => fraction
 * (0.5 for a half-day application, else 1).
 *
 * @param list<string> $workingDates
 * @return array<string, float>
 */
function payroll_leave_dates(string $from, string $to, bool $halfDay, array $workingDates): array
{
    $out = [];
    foreach ($workingDates as $d) {
        if ($d >= $from && $d <= $to) {
            $out[$d] = $halfDay ? 0.5 : 1.0;
        }
    }

    return $out;
}

/**
 * Calendar days of [$start, $end] outside employment: before $joinDate or
 * after $leaveDate (termination). Those days are never paid.
 */
function payroll_outside_employment_days(string $start, string $end, ?string $joinDate, ?string $leaveDate): int
{
    $days = 0;
    for ($d = strtotime($start), $last = strtotime($end); $d !== false && $last !== false && $d <= $last; $d = strtotime('+1 day', $d)) {
        $ymd = date('Y-m-d', $d);
        if (($joinDate && $ymd < $joinDate) || ($leaveDate && $ymd > $leaveDate)) {
            $days++;
        }
    }

    return $days;
}

/**
 * Journal lines for posting an approved payroll run (PAYROLL_PLAN §7):
 *   Dr salary expense           gross
 *   Dr employer contributions   employer fund share
 *      Cr staff payable         net pay
 *      Cr TDS payable           tds
 *      Cr <fund> payable        employee + employer share, per fund
 *      Cr staff advances        advance recoveries
 *      Cr other deductions      every other deduction (canteen, fines, …)
 * Zero lines are dropped. Throws when an account is unmapped or the
 * voucher would not balance within 0.01 (Hard invariant 8).
 *
 * @param array{gross: float, employer_funds: float, net: float, tds: float, other_deductions: float, advance_recoveries?: float} $totals
 *        other_deductions = every non-fund, non-tax deduction (incl. advance recoveries);
 *        advance_recoveries = the part of it that repays staff advances
 * @param array<string, float> $fundTotals fund code => employee + employer contributions
 * @param array{salary_expense: ?int, employer_contribution: ?int, staff_payable: ?int, tds_payable: ?int, advances: ?int, other_deductions: ?int, funds: array<string, ?int>} $accounts terminal ids
 * @return list<array{account_terminal_id: int, debit: float, credit: float, remarks: string}>
 */
function payroll_journal_lines(array $totals, array $fundTotals, array $accounts, string $label): array
{
    $plan = [
        ['salary_expense', round((float) $totals['gross'], 2), 0.0, 'Gross salaries'],
        ['employer_contribution', round((float) $totals['employer_funds'], 2), 0.0, 'Employer fund contributions'],
        ['staff_payable', 0.0, round((float) $totals['net'], 2), 'Net pay due to staff'],
        ['tds_payable', 0.0, round((float) $totals['tds'], 2), 'Salary TDS withheld'],
        ['advances', 0.0, round((float) ($totals['advance_recoveries'] ?? 0), 2), 'Staff advance recoveries'],
        ['other_deductions', 0.0, round((float) $totals['other_deductions'] - (float) ($totals['advance_recoveries'] ?? 0), 2), 'Other salary deductions'],
    ];
    $lines = [];
    $missing = [];
    foreach ($plan as [$key, $debit, $credit, $remark]) {
        if ($debit == 0.0 && $credit == 0.0) {
            continue;
        }
        if (empty($accounts[$key])) {
            $missing[] = str_replace('_', ' ', $key);
            continue;
        }
        $lines[] = ['account_terminal_id' => (int) $accounts[$key], 'debit' => $debit, 'credit' => $credit, 'remarks' => $remark . ' — ' . $label];
    }
    foreach ($fundTotals as $code => $amount) {
        $amount = round((float) $amount, 2);
        if ($amount == 0.0) {
            continue;
        }
        if (empty($accounts['funds'][$code])) {
            $missing[] = $code . ' payable';
            continue;
        }
        $lines[] = ['account_terminal_id' => (int) $accounts['funds'][$code], 'debit' => 0.0, 'credit' => $amount, 'remarks' => $code . ' contributions — ' . $label];
    }
    if ($missing) {
        throw new RuntimeException('Map these accounts in Payroll → Accounting Setup first: ' . implode(', ', $missing) . '.');
    }
    $debits = array_sum(array_column($lines, 'debit'));
    $credits = array_sum(array_column($lines, 'credit'));
    if (abs($debits - $credits) > 0.01) {
        throw new RuntimeException(sprintf('Payroll journal does not balance (debit %.2f, credit %.2f).', $debits, $credits));
    }

    return $lines;
}

/**
 * This month's recovery of a staff advance: the monthly instalment, or the
 * whole outstanding balance when the person leaves this month — never more
 * than is outstanding, and never more than $available (net pay left before
 * the recovery), so a recovery cannot push net pay below zero.
 */
function payroll_advance_recovery(float $outstanding, float $installment, bool $leavingThisMonth, float $available): float
{
    $want = $leavingThisMonth ? $outstanding : min($installment, $outstanding);

    return round(max(0.0, min($want, $available, $outstanding)), 2);
}

/**
 * Amount for an "add to everyone" one-off (e.g. Dashain allowance):
 *   fixed            → $value NPR
 *   basic_months     → $value × monthly basic (1 = one month's basic)
 *   percent_of_basic → $value % of monthly basic
 */
function payroll_bulk_amount(string $mode, float $value, float $basicFull): float
{
    switch ($mode) {
        case 'basic_months':
            return round($basicFull * $value, 2);
        case 'percent_of_basic':
            return round($basicFull * $value / 100, 2);
        default:
            return round($value, 2);
    }
}

/**
 * Payslip-ready email: a notice with the headline figures and a link to the
 * full payslip behind login (no PDF attachment — salary documents stay in
 * the app). All dynamic values are HTML-escaped.
 *
 * @param array<string, mixed> $line tbl_payroll_run_lines row + period_label
 * @return array{subject: string, html: string}
 */
function payroll_payslip_email(array $line, string $link, string $orgName): array
{
    $m = static fn ($v): string => 'NPR ' . number_format((float) $v, 2);
    $esc = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $deductions = (float) $line['employee_funds'] + (float) $line['tds'] + (float) $line['other_deductions'];
    $subject = 'Your payslip for ' . $line['period_label'] . ' is ready';
    $first = explode(' ', trim((string) $line['fullname']))[0];
    $html = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#0f172a;max-width:520px">'
        . '<p>' . ($first !== '' ? 'Dear ' . $esc($first) : 'Hello') . ',</p>'
        . '<p>Your payslip for <strong>' . $esc($line['period_label']) . '</strong> is ready.</p>'
        . '<table style="border-collapse:collapse;width:100%;margin:12px 0">'
        . '<tr><td style="padding:6px 0;color:#64748b">Gross pay</td><td style="padding:6px 0;text-align:right">' . $esc($m($line['gross'])) . '</td></tr>'
        . '<tr><td style="padding:6px 0;color:#64748b">Deductions</td><td style="padding:6px 0;text-align:right">' . $esc($m($deductions)) . '</td></tr>'
        . '<tr><td style="padding:8px 0;border-top:1px solid #0f172a"><strong>Net pay</strong></td><td style="padding:8px 0;border-top:1px solid #0f172a;text-align:right"><strong>' . $esc($m($line['net'])) . '</strong></td></tr>'
        . '</table>'
        . '<p><a href="' . $esc($link) . '" style="color:#2563eb">View or download your payslip</a> (sign-in required).</p>'
        . '<p style="color:#64748b;font-size:12px">' . $esc($orgName) . ' · This is an automated message.</p>'
        . '</div>';

    return ['subject' => $subject, 'html' => $html];
}
