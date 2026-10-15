# Payroll Module — Plan

> **Status:** P1–P4 built (2026-10-13 → 2026-10-15) — see §10.
> **Changes scope:** `docs/PRD.md` §2.2 and §7 list payroll as a v1 non-goal.
> Approving this plan moves it in scope; update the PRD when we start.

## 1. Goal

Run monthly salary inside the OMS: calculate pay from salary structures,
attendance, and leave; produce payslips; and post the result to the ledger.
Nothing should be retyped into Accounts, and every number should trace back
to its source.

**v1 is done when** an admin can pick a BS month and generate a draft payroll
for all active staff. A second person then approves it, payslips go out as
PDFs, and one journal voucher plus one payment voucher land in Accounts,
balanced.

## 2. Decisions

Decided 2026-10-13 (D1, D2, D4, D5) — the rest use the proposed default
until someone says otherwise. Every statutory figure is a **setting, never
code**; confirm them with your accountant.

| # | Question | Decision |
|---|---|---|
| D1 | Funds (SSF, PF, …) | **Per-staff opt-in to any fund.** Funds are a CRUD list (SSF, PF, CIT seeded; add others); each enrollment can override the employee/employer rate or set a fixed employee amount. |
| D2 | Income tax (TDS) | **Calculated by the system and editable** — a per-line manual override in the payroll run (P2). |
| D3 | Pay period | BS month (default). |
| D4 | Unpaid days | **Unpaid by default:** days marked absent, approved leave of an unpaid type (`is_paid = 0`, “Unpaid Leave” seeded), and leave applied for but not approved (pending, verified or rejected). A day worked cancels a full-day leave; half-days count as 0.5. Switchable per person (“Reduce pay for unpaid days”). |
| D5 | Interns | **Choose per person:** pay type Salary or fixed Stipend, tax on/off, and any fund enrollments (or none). |
| D6 | CIT / PF | Covered by D1. |
| D7 | Dashain allowance | Earning component “Festival Allowance” (not cut for unpaid days). |
| D8 | Prepare / approve | `manage_payroll` prepares, `approve_payroll` approves (different users). |
| D9 | Payment | Bank transfer, one payment voucher per run (P3). |

**Tax slabs:** CRUD per tax year, seeded with FY 2083/84 under the Finance
Act 2083 — a single schedule (the couple table was removed): ≤ 10L 1% (social
security tax, waived for SSF / pension contributors) · 10–15L 10% · 15–25L
20% · 25–40L 27% · > 40L 29%. To verify: the top band (one source shows
27% + 2% surcharge), the retirement-deduction cap (seeded NPR 5L or one-third
of assessable income) and the 10% rebate for women.

## 3. What already exists and gets reused

| Need | Existing piece |
|---|---|
| Staff identity, bank account, PAN, SSF/CIT numbers | `tbl_users_login` (already stored on the Add Staff form) |
| Present, absent, leave and holiday days per month | `tbl_staff_attendances` |
| Approved leave and leave types | `tbl_staff_leave_applications`, `tbl_office_leave_configs` |
| Fiscal years, voucher numbering, balanced posting | `functions/accounting.php` (`accountingNextVoucherNo`, `accountingReplaceLines`, `accountingLogVoucher`) |
| Template for auto-created vouchers | `accountingPaymentVoucherForClaim()`, which does exactly this for expense claims |
| Accounts | **Staff Salaries** (expense), **Staff Payable**, **TDS Payable**, **Cash in Hand**, **Bank Accounts** |
| BS month names and AD↔BS conversion | `bsMonthName()`, `adToBs()`, `bsToAd()` in `functions/helpers.php` |
| PDF output | `makePDF()` in `functions/pdf_helpers.php` |
| Notifications and email | `notifyUser()`, the Communication module |
| Permission keys | `$specialPermissions` in `admin/includes/varriables.php` |
| Report and dashboard surfaces | `stat_tile()`, `chart_attr()` (`functions/analytics.php`) |

**Missing accounts**, to be added by a seed migration: *SSF Payable*,
*CIT Payable*, *SSF Contribution — Employer* (expense).

## 4. Data model

All money columns are `DECIMAL(18,4)`. Every table gets a
`create-table-*.php` migration with `$query` and `$rollbackQuery`,
registered in `migrations.php` and documented in `docs/Schema.md`.

### 4.1 Changes to existing tables (built in P1)

- `tbl_users_login`: `employment_type` ENUM('Permanent','Contract','Probation','Intern'), `contract_end_date` DATE NULL, `tax_filing_status` ENUM('Single','Couple')
- `tbl_office_leave_configs`: `is_paid` TINYINT(1) DEFAULT 1, plus a seeded **Unpaid Leave** type with `is_paid = 0`

### 4.2 New tables

| Table | Phase | Purpose | Key columns |
|---|---|---|---|
| `tbl_payroll_funds` | P1 ✅ | Fund catalogue (SSF, PF, CIT, custom) | `code` UNIQUE, `employee_rate`, `employer_rate` (%), `contribution_base` basic/gross, `max_base`, `tax_deductible`, `waives_social_security_tax`, `is_active` |
| `tbl_staff_fund_enrollments` | P1 ✅ | Per-staff opt-in | UNIQUE (`staff_id`, `fund_id`), `member_no`, `employee_rate` / `employee_fixed` / `employer_rate` overrides, `start_date` / `end_date` |
| `tbl_payroll_components` | P1 ✅ | Earnings and deductions catalogue | `code` UNIQUE, `kind`, `calc` fixed / % of basic, `taxable`, `prorate`, `is_active` |
| `tbl_staff_salary_structures` | P1 ✅ | Pay from a date onward; history kept | UNIQUE (`staff_id`, `effective_from`), `pay_type` Salary/Stipend, `basic`, `tax_applicable`, `deduct_unpaid_days` |
| `tbl_staff_salary_structure_items` | P1 ✅ | Component values on a structure | `structure_id`, `component_id`, `value` |
| `tbl_payroll_tax_years` | P1 ✅ | Tax year settings | `title` UNIQUE, AD `start_date` / `end_date` (no overlap), `retirement_cap`, `retirement_fraction`, `female_rebate_percent`, `notes` |
| `tbl_payroll_tax_slabs` | P1 ✅ | Bands per year | `filing_status` All/Single/Couple, `lower_limit`, `upper_limit` NULL = open, `rate` %, `is_social_security_tax` |
| `tbl_payroll_runs` | P2 ✅ | One run per period | `period_key` (BS `2083-06`) UNIQUE, AD period bounds, `status` Draft → Approved → Posted → Paid / Cancelled, totals, `prepared_by`, `approved_by`, voucher ids |
| `tbl_payroll_run_lines` | P2 ✅ | One row per staff per run, **frozen at approval** | days (period / unpaid / payable), `gross`, `taxable`, fund totals, `tds`, `tds_overridden`, `net`; snapshot of name, designation, bank, PAN |
| `tbl_payroll_run_line_items` | P2 ✅ | Itemised payslip lines | `component_code`, `name`, `kind`, `amount`, `is_adjustment` |

**Why snapshot:** a payslip must show what was paid, even after the
staff member's bank details, designation or salary change later.

## 5. Calculation rules

All maths goes in `functions/payroll.php` as pure functions (RULES §1.2),
with at least two tests each in `tests/PayrollTest.php`. Pages and operations
only fetch the inputs and save the outputs.

| Function | Rule |
|---|---|
| `payroll_payable_days(int $periodDays, float $unpaidDays): float` | `max(0, period − unpaid)` |
| `payroll_prorate(float $amount, float $payable, int $period): float` | `amount × payable ÷ period`, rounded to 2 dp |
| `payroll_component_amount(array $component, float $basic): float` | Fixed amount, or `basic × percent` |
| `payroll_ssf(float $basic, float $empRate, float $erRate): array` | Employee and employer shares on (prorated) basic |
| `payroll_annual_tax(float $taxableAnnual, array $slabs, bool $sstWaived): float` | Progressive slabs; the first-slab social-security tax is waived when SSF-enrolled and the setting says so |
| `payroll_retirement_deduction(float $contrib, float $income, float $fraction, float $cap): float` | Allowed deduction = min(contribution, income × fraction, cap) |
| `payroll_monthly_tds(float $annualTax, float $taxDeductedYtd, int $monthsRemaining): float` | Projection method: `(annual tax − already deducted) ÷ months left including this one`, floored at 0 |
| `payroll_net(array $line): float` | `gross − ssf_employee − cit − tds − other deductions`; must not go negative (flag the line instead) |
| `payroll_period_bounds(string $bsPeriodKey): array` | BS month → AD start and end dates, plus the day count |

**Annual projection for TDS.** Projected annual taxable income = YTD taxable
income in this FY + (this month's taxable × months remaining). Subtract
allowed retirement deductions, compute the annual tax, then spread what is
still owed. This self-corrects after raises, unpaid months and bonuses.

**Unpaid days (D4).** For the period, count `absent` attendance rows plus
approved leave days whose type has `is_paid = 0`. Days with no attendance row
are **not** assumed absent. The run screen lists them as a warning, so a
missing punch never silently docks pay.

**Rounding.** Every line item rounds to 2 dp at creation. Totals are sums of
rounded items, so the payslip and the voucher agree to the paisa.

## 6. Module and workflow

New module `admin/modules/payroll/`, following the module template (RULES §4.1):

| Page | Who | What |
|---|---|---|
| `runs` | manage/approve | List of runs by period, with status, headcount, gross and net |
| `run` (detail) | manage/approve | Line review, adjustments, warnings, approve, post, pay |
| `structures` | manage | Salary structure per staff, with history |
| `components` | manage | Earnings and deductions catalogue |
| `settings` | manage | Rates and tax slabs per fiscal year |
| `my_office/payslips` | every staff | Own payslips only, as PDF |

**Permissions:** new special keys `manage_payroll` and `approve_payroll`.
Salary data never appears in other modules' exports.

**Run lifecycle** (every step is POST → `verifyCsrf()` → transaction →
`auditLog()` → flash → redirect):

```
Create (pick BS month)
  → Draft: lines computed from structures + attendance + leave
      ↺ Adjust (one-off item, TDS override, exclude staff) → recalculate
  → Approve   (approve_payroll; approver ≠ preparer; lines frozen)
  → Post      journal voucher (accrual), status Posted
  → Pay       payment voucher (disbursement), status Paid → payslips emailed
Cancel is allowed only from Draft or Approved. A Posted run is reversed by a
reversing journal, never deleted.
```

A run is unique per period. Staff who joined or left mid-period are prorated
from `join_date` or `termination_date`.

## 7. Accounting entries

Posted through the existing helpers, inside one transaction, and asserted
balanced within 0.01 (Hard invariant 8).

**On Post (journal voucher, accrual):**

| Account | Debit | Credit |
|---|---|---|
| Staff Salaries | gross | |
| SSF Contribution — Employer | ssf_employer | |
| Staff Payable | | net |
| TDS Payable | | tds |
| SSF Payable | | ssf_employee + ssf_employer |
| CIT Payable | | cit |

**On Pay (payment voucher):** Dr Staff Payable / Cr Bank Accounts, for total net pay.

**Later, when remitted:** Dr TDS Payable or SSF Payable / Cr Bank. These are
ordinary payment vouchers, and the remittance report (§8) gives the amounts.

## 8. Outputs

- **Payslip PDF:** earnings, deductions, employer contributions, payable
  days, YTD gross and tax. Uses `makePDF()` and is emailed on Pay.
- **Bank transfer sheet (CSV):** name, bank, account number, net.
- **Payroll register:** every line of a run (CSV and print).
- **Remittance summary:** TDS and SSF due per period, for filing.
- **Reports → Payroll:** monthly cost trend (gross, employer cost, net),
  cost by department, and headcount paid. Visible to payroll roles only.
- **Dashboard tile:** "Payroll — Ashwin: Draft / Approved / Paid", for
  payroll roles only.

## 9. Security and privacy

Salary is the most sensitive data in the system.

- Visible only to `manage_payroll` and `approve_payroll`, plus a staff member's own payslips.
- Every create, adjust, approve, post and pay action writes `auditLog()` with old and new values.
- Payslip files are served through a permission check, never as a public `user_uploads` path.
- No payroll figures appear in general CSV exports, the staff report, or notifications beyond "your payslip is ready".

## 10. Phases

| Phase | Delivers | Depends on |
|---|---|---|
| **P1 — Foundation** ✅ | Migrations (§4), Funds / Pay Components / Tax Slabs CRUD, Salary Setup with payslip estimate, employment type on staff, paid/unpaid leave types, `functions/payroll.php` + `PayrollService` with tests | — |
| **P2 — Payroll run** ✅ | BS-month runs; unpaid days from attendance/leave (holidays & off-days skipped, join/leave mid-month prorated); per-line unpaid-days / TDS overrides, exclusion, one-off adjustments; YTD-aware TDS (one-off tax withheld in the month paid); four-eyes approval with staff notifications; payslip print/PDF (one or all); My Office → My Payslips | — |
| **P3 — Accounting** ✅ | Payroll accounts seeded + Accounting Setup mapping; post → balanced Auto/Pending journal, record payment → Pending payment voucher (accountant approves in Accounts → Posting); undo while still Pending, locked once approved; bank sheet + payroll register CSV; Remittance report (TDS + fund shares per month) + CSV | — |
| **P4 — Polish** ✅ | Reports → Payroll (cost split, by department, YTD per person); dashboard chips (runs awaiting approval, contracts ending / overdue); contracts list + employment-type mix on Reports → Staff; staff advances with automatic instalment recovery (full balance when leaving, capped at net) + payout voucher; “add to everyone” one-offs (Dashain = months of basic / fixed / % of basic); payslip-ready email notices with a sign-in link (no attachment). Not built: leave encashment & gratuity in final settlement, PDF attachments (needs PHPMailer) | — |

Each phase ships on its own and passes `composer check`.

## 11. Acceptance checks

- A staff member with known basic, allowances, unpaid days and SSF status
  produces a payslip matching a hand-worked spreadsheet to the paisa. Use
  three reference cases agreed with the accountant: SSF-enrolled, not
  enrolled, and intern.
- The TDS total over a full fiscal year matches the annual tax computed
  once on the year's income (the projection self-corrects).
- The posted journal balances, and the Staff Payable balance returns to 0 after Pay.
- An approved run cannot be edited. Re-running a period is refused.
- A staff member without `manage_payroll` cannot see another person's salary through any URL.

## 12. Not in this plan

Attendance-based overtime, multi-currency pay, shift differentials,
government e-filing integration, and loans with interest schedules.
Revisit these after P4.

## 13. Review fixes (2026-10-15)

A code review of P1–P4 found ten issues; all are fixed and tested:

1. **Approve recalculates first** and refuses (asking for a fresh review) if any figure changed — no stale advances, double-recovered instalments or out-of-date year-to-date tax get frozen.
2. **Cancelling an approved run reopens advances** its approval had closed.
3. **One-off pay and gross-based funds:** the employer share on a one-off is no longer projected over the remaining months.
4. **Every filing status must be covered** by tax bands (an “Everyone” schedule, or both Single and Couple); runs and estimates warn if a person has no bands.
5. **Advance recoveries and other deductions post to separate accounts** (Staff Advances vs. a new “Other deductions” setting, seeded to Miscellaneous Income).
6. **Concurrency:** one active run per month is a database rule (`active_period_key` UNIQUE); every state change locks the run row, so double-clicks can't duplicate runs or vouchers.
7. **A fund's code is locked** once enrolments or payroll use it.
8. **A tax year used by runs can't be deleted** (plain-language refusal).
9. **One holiday rule** (`holiday_applies_to()` in `functions/hr.php`) shared by leave counting and payroll.
10. **Payslip email** has edge-case tests (and greets “Hello,” when there's no name).
