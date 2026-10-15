# Porting Payroll to smart-school — task guide for an AI coding agent

**Read this whole file before doing anything.** Then do **one task per
session**, in order. Each task says exactly what to read, what to change, and
how to prove it works. If a check fails, **stop and report** — do not guess,
do not continue to the next task.

- **Source (read only):** `/var/www/html/codegenexis` — never edit anything here.
- **Target:** `/var/www/html/smart-school` — all changes go here.
- **Database (target):** the one in `smart-school/config/setup.php`
  (`db_name`). Back it up before Task 3.

---

## 0. How to run this with the agent (for the person operating it)

1. In smart-school: `git checkout -b feature/payroll`, and take a DB backup.
2. Start a **fresh session per task** and paste this prompt:

   > Read `/var/www/html/codegenexis/docs/PAYROLL_PORT_SMART_SCHOOL.md`
   > sections 1–3 and 8, then do **Task N only**. Follow its steps exactly,
   > run its checks, and show me the check output. Do not edit
   > `/var/www/html/codegenexis`. If a check fails, stop and tell me why.

3. Read the check output yourself. If it passes, commit in smart-school
   (`git add -A && git commit -m "payroll: task N"`) before the next task.
4. Tasks 1–6 are mechanical and suit a small model. **Task 7 (accounting)
   is the hard one** — consider having a stronger model do or review it.

---

## 1. What is being ported

A Nepal payroll module (four phases, all tested in codegenexis):

| Phase | What it does |
|---|---|
| P1 | Funds (SSF/PF/CIT/custom), pay components, income-tax years + slabs (FY 2083/84 seeded), per-staff salary setup with payslip estimate |
| P2 | Monthly payroll runs per BS month: unpaid days from attendance/leave, overrides, one-off adjustments, year-to-date TDS, four-eyes approval, payslips (print/PDF), My Payslips |
| P3 | Posting runs to Accounts (journal + payment voucher), bank sheet, payroll register, remittance report |
| P4 | Staff advances with automatic recovery, "add to everyone" (Dashain), Reports → Payroll, dashboard alerts, payslip email notices |

Design reference: `codegenexis/docs/PAYROLL_PLAN.md` (read §2, §5, §6, §13).

---

## 2. The two codebases are different — the facts that matter

The tables have the same names (shared ancestry) but **many details differ**.
Never assume a column exists: check with `SHOW CREATE TABLE tbl_x` first.

| Topic | codegenexis (source) | smart-school (target) |
|---|---|---|
| DB access | `Database::instance()->select($sql, $params)` — prepared statements | `$objQuery->querySelect("…'$escaped'…")` (string SQL) **and** a PDO connection via `getPdoConnection()` (`database/db_helpers.php`, loaded by `config/bootstrap.php`) |
| Port strategy | — | Use the **PayrollDb** adapter (§8.1) on PDO, so payroll SQL stays prepared. Do **not** rewrite payroll queries into string SQL. |
| Input cleaning | values stored raw, escaped on output with `e()` | `escape_data()` stores HTML-escaped text. **Payroll stores raw values** (prepared statements) and escapes on output with `e()` (shim §8.2). Never pass payroll input through `escape_data()`. |
| CSRF | `csrfField()` + `verifyCsrf()` | `app_csrf_field()`; `admin/operation.php` already checks every POST (`app_require_csrf()`) |
| Flash message | `setFlash('success'|'error'|'warning', $msg)` | `setErrorMsg($mode, $html)` (`functions/smart_functions.php`) |
| Permissions | `Auth::hasSpecial('manage_payroll')`, `Auth::id()`, `Auth::isSuperAdmin()` | `checkSpecialPermission($key)`, `$_SESSION['userId']`; catalogue `$specialPermissions` + `$specialPermissionModule` in `admin/includes/varriables.php` |
| Navigation | `admin/includes/varriables.php` (`$modules`, `$navBars`, `$subNavBars`, `$pages`) | `admin/includes/nav_definition.php` (+ `varriables.php`) — mimic how `myOffice` / `staff_management` are registered |
| My Office module key | `my_office` | **`myOffice`** (mixed case) |
| Operation handlers | `admin/modules/<m>/operation/<page>.php` | same — `operation/` **or** `operations/` both work |
| Migrations | `database/migration/<name>.php` with `$query` + `$rollbackQuery`, listed in `migrations.php` (`$files` array in smart-school), run with `php artisan migrate` | same idea — append to the `$files` array **at the end** |
| Tests | PHPUnit (`tests/PayrollTest.php`) | **no PHPUnit**; plain scripts `tests/*_check.php` run with `php tests/x_check.php` (copy the style of `tests/activity_log_check.php`) |
| Audit log | `auditLog(...)` → `tbl_audit_log` | `app_activity_log(array $log)` → `tbl_activity_logs` (shim §8.2) |
| Notifications | `notifyUser(..., $url)`; `tbl_notifications` has `title`, `url` | `tbl_notifications` has **no `title`, no `url`** (shim §8.2); links come from `notificationTargetRoute()` in `admin/functions/smart_school_function.php` — add a `payroll` type there |
| PDF | `PdfGenerator` + `documentShellStart()/End()` letterhead | `makePDF($html, $fileName)` (`admin/functions/smart_school_function.php`); no `documentShell*` — use smart-school's own print header or a plain header |
| Email | `CommunicationService::sendEmail($to, $subject, $html)` | `sendEmail($to, $subject, $content)` (`functions/smart_functions.php`) — check its return value before relying on it |
| Absolute URL | `config('server_path')` | `config('abs_url')` (`server_path` is empty) |
| Weekly off-days (office) | `tbl_office_profiles.weekly_off_days` = JSON `["Sunday","Saturday"]` | **numbers** `"1,7"` (1 = Sunday … 7 = Saturday) — convert (Task 5) |
| Staff off-day | `tbl_users_login.off_day` = weekday names | same (names, e.g. `Saturday`) |
| Holiday scope | `tbl_office_holidays.department_id` (NULL = everyone) | `department` **varchar**, `"0"` = everyone |
| Staff ids in other tables | INT | often **varchar** (`department_id`, `added_by`, …) — joins still work; cast when comparing in PHP |
| Leave types | `gender_specific` `Both`; payroll added `is_paid` + seeded "Unpaid Leave" | `gender_specific` uses **`None`**; an **"Unpaid Leave" type already exists** (created by `leave_config_operation.php`) — mark it unpaid instead of inserting a new one |
| Accounting tables | `tbl_journal_vouchers`, `tbl_payment_vouchers`, `tbl_ledger_particulars`, `tbl_voucher_logs` (codegenexis columns) | **different columns** (e.g. `payment_no`, `debit_terminal_id`, `date`, `fiscal_year`, status `Deleted`; `tbl_voucher_logs` = `voucher_table, voucher_id, action, snapshot_json`). **P3 posting code cannot be copied** — see Task 7 |
| BS calendar | `tbl_calendar` (`nepali_year`, `month_code`, `eng_start_date`, `eng_end_date`) | same structure ✓, **but the data differs**: Ashwin 2083 is 30 days in codegenexis, 31 in smart-school. Payroll always reads the target's own calendar, so never hard-code month lengths or copy codegenexis dates into checks. Fiscal-year bounds agree (Shrawan 1 2083 = 2026-07-17 … 2027-07-16), so the seeded tax year is valid. |
| Attendance / leave applications | `tbl_staff_attendances`, `tbl_staff_leave_applications` | same columns ✓ (ids typed differently, harmless) |

---

## 3. Ground rules

1. **Copy, then adapt** — don't redesign. The maths in `functions/payroll.php`
   is tested; change it only where this guide says.
2. **One task per session.** Don't start the next task early.
3. **Every schema change is a migration** with `$query` and `$rollbackQuery`,
   appended to the end of `database/migration/migrations.php`. Never run DDL
   by hand. After adding one: `php artisan migrate`, then
   `php artisan rollback <n>` and `php artisan migrate` again — all three must pass.
4. **Never assume a column** — run `SHOW CREATE TABLE` and match the real one.
5. **No string-built SQL with user input** in payroll code — always `PayrollDb`
   with `?` placeholders.
6. **Escape every output** with `e()`.
7. **Don't modify unrelated smart-school code** except the specific
   registration points named in a task.
8. After every PHP edit: `php -l <file>`. Before finishing a task, run its checks
   and paste the output.
9. If something in smart-school contradicts this guide, **stop and report** it.

---

## 4. File map (source → target)

| codegenexis (source) | smart-school (target) | Task | Notes |
|---|---|---|---|
| `functions/payroll.php` | `functions/payroll.php` | 1 | copy as-is + `holiday_applies_to()` (see Task 1) |
| `functions/analytics.php` | `functions/payroll_analytics.php` | 1 | copy as-is (no name collisions — checked) |
| — | `classes/PayrollDb.php` | 2 | code in §8.1 |
| — | `functions/payroll_compat.php` | 2 | code in §8.2 |
| `database/migration/*payroll*`, `*staff_salary*`, `*staff_fund*`, `*staff_advances*`, `alter-table-users_login-add-employment-fields`, `alter-table-office_leave_configs-add-is_paid` | same folder | 3 | adapt as listed in Task 3 |
| `classes/PayrollService.php` | `classes/PayrollService.php` | 4 | mechanical replacements §8.3 |
| `classes/PayrollRunService.php` | `classes/PayrollRunService.php` | 4 | §8.3 + Task 4 adaptations |
| `admin/modules/payroll/{home,salary_setup,funds,components,tax_slabs}.php` + their `operation/` files | `admin/modules/payroll/…` | 5 | §8.3 |
| `admin/modules/payroll/{runs,run,payslip}.php`, `includes/payslip_render.php`, `operation/runs.php` | `admin/modules/payroll/…` | 6 | §8.3 + PDF/letterhead changes |
| `admin/modules/my_office/payslips.php` | `admin/modules/myOffice/payslips.php` | 6 | module key `myOffice` |
| `classes/PayrollPostingService.php`, `admin/modules/payroll/{accounting,remittance}.php` + ops | same | 7 | **rewrite voucher writes** for smart-school tables |
| `admin/modules/payroll/advances.php` + op, `admin/modules/reports/payroll.php` | same | 8 | |
| CSS block `ana-*` in `assets/css/admin.css` (from `/* Analytics surfaces (ana-*)` to the end) + `--viz-*` tokens in `assets/css/theme-variables.css` | a new `admin/assets/css/payroll.css` included on payroll pages | 5 | add the `:root` token fallbacks in §8.4 |
| `assets/js/analytics-charts.js` | `admin/assets/js/analytics-charts.js` | 6 | load with Chart.js 4.4.1 on payroll + reports pages |

---

## 5. Tasks

### Task 1 — Pure calculation code (no DB, no UI)

**Read:** `codegenexis/functions/payroll.php`, `codegenexis/functions/analytics.php`,
`codegenexis/functions/hr.php` (only the function `holiday_applies_to`).

**Do:**
1. Copy `functions/payroll.php` → `smart-school/functions/payroll.php` unchanged.
2. Append `holiday_applies_to()` (copy from `codegenexis/functions/hr.php`) to the end of the new file.
3. Copy `functions/analytics.php` → `smart-school/functions/payroll_analytics.php` unchanged.
4. In `smart-school/config/bootstrap.php`, after the other `require_once` lines
   for `functions/*.php`, add:
   ```php
   require_once $appRoot . '/functions/payroll_analytics.php'; // or the path variable bootstrap uses
   require_once $appRoot . '/functions/payroll.php';
   ```
   (use whatever root variable that file already uses for its includes).
5. Create `smart-school/tests/payroll_check.php` in the style of
   `tests/activity_log_check.php` with these exact checks (hand-worked, FY 2083/84 slabs):
   - `payroll_annual_tax(4500000, $slabs)['tax'] === 810000.0`
   - `payroll_annual_tax(4500000, $slabs, true)['tax'] === 800000.0`
   - `payroll_monthly_tds(30000, 0, 12) === 2500.0`
   - `payroll_unpaid_days(['2026-10-01'=>1,'2026-10-02'=>1], ['2026-10-02'=>1,'2026-10-05'=>0.5], ['2026-10-07'=>1]) === 3.5`
   - SSF line: basic 50,000 + DA 10,000, SSF 11/20 on basic, 12 months → `net === 54500.0`, `tds === 0.0`
   - One-off: basic 100,000 + 200,000 taxable one-off, 12 months, no funds → `tds === 22500.0`
   - `holiday_applies_to(['department_id'=>7,'gender_to'=>'Both'], ['department_id'=>5,'gender'=>'Male']) === false`

   Build `$slabs` as in `codegenexis/tests/PayrollTest.php::slabs()` and the
   line inputs as in its `testSsfEnrolledSalaryLine` / `testOneOffBonusIsTaxedOnceNotProjected`.

**Check:** `php -l` on both files; `php tests/payroll_check.php` → all `ok`, `0 failed`.

---

### Task 2 — Adapter + compatibility layer

**Do:**
1. Create `smart-school/classes/PayrollDb.php` with the code in §8.1, exactly.
2. Create `smart-school/functions/payroll_compat.php` with the code in §8.2, exactly.
3. Load both from `config/bootstrap.php` **before** `functions/payroll.php`
   (classes: follow how bootstrap loads other classes, or `require_once` it).

**Check:**
```bash
php -r 'require "config/setup.php"; $db=PayrollDb::instance(); var_dump($db->selectOne("SELECT COUNT(*) c FROM tbl_users_login")); var_dump(e("<b>"), pageUrl("payroll","runs"), PayrollAuth::id());'
```
Expect a count, `&lt;b&gt;`, `./show_page.php?module=payroll&page=runs`, `NULL` (no session in CLI).
Also add to `tests/payroll_check.php`: a `PayrollDb` transaction that inserts into a
temporary table and rolls back.

---

### Task 3 — Migrations

Copy these from `codegenexis/database/migration/` **in this order**, append each
name to the end of smart-school's `migrations.php`, and adapt as noted:

1. `alter-table-users_login-add-employment-fields` — check `staff_type`, `join_date`, `pan_num` exist (they do); keep.
2. `alter-table-office_leave_configs-add-is_paid` — keep.
3. **Do not copy** `seed-table-office_leave_configs-unpaid-leave`. Write a new
   `seed-table-office_leave_configs-mark-unpaid`:
   `$query = ["UPDATE tbl_office_leave_configs SET is_paid = 0 WHERE title = 'Unpaid Leave'"]`,
   `$rollbackQuery = ["UPDATE tbl_office_leave_configs SET is_paid = 1 WHERE title = 'Unpaid Leave'"]`.
4. `create-table-payroll_funds`
5. `create-table-staff_fund_enrollments`
6. `create-table-payroll_components`
7. `create-table-staff_salary_structures`
8. `create-table-staff_salary_structure_items`
9. `create-table-payroll_tax_years`
10. `create-table-payroll_tax_slabs`
11. `seed-table-payroll_tax_years-2083-84`
12. `create-table-payroll_runs`
13. `create-table-payroll_run_lines`
14. `create-table-payroll_run_line_items`
15. `alter-table-payroll_runs-add-posting-fields`
16. `alter-table-payroll_runs-add-active_period_key`
17. `create-table-staff_advances`
18. `alter-table-payroll_run_line_items-add-advance_id`
19. My Payslips grant: copy `seed-table-users_login-grant-my_office-payslips` but change
    every `$.my_office` to **`$.myOffice`** — first check how smart-school stores
    `permitted_submodules` (`SELECT permitted_submodules FROM tbl_users_login LIMIT 3`);
    if it is not JSON keyed by module, stop and report.

Leave the P3 accounting migrations (`create-table-payroll_settings`,
`alter-table-payroll_funds-add-payable_terminal_id`, `seed-table-account_terminals-payroll`,
`alter-table-payroll_settings-add-deductions_terminal_id`) for Task 7.

Foreign keys reference `tbl_users_login(id)` (INT — matches) and
`tbl_office_leave_configs` is not referenced. If any FK fails, run
`SHOW CREATE TABLE` on the parent and match the id type exactly.

**Check:** `php artisan migrate` ✓ → `php artisan rollback 19` ✓ → `php artisan migrate` ✓, then
`SELECT lower_limit, upper_limit, rate FROM tbl_payroll_tax_slabs` returns the 5 FY 2083/84 bands
(0–10L 1%, 10–15L 10%, 15–25L 20%, 25–40L 27%, >40L 29%).

---

### Task 4 — Service classes

Copy `classes/PayrollService.php` and `classes/PayrollRunService.php`, then:

1. Apply every replacement in §8.3.
2. In `PayrollRunService::recalculate()`, the office off-days block: smart-school stores
   numbers. Replace the JSON decode with:
   ```php
   $map = [1 => 'Sunday', 2 => 'Monday', 3 => 'Tuesday', 4 => 'Wednesday', 5 => 'Thursday', 6 => 'Friday', 7 => 'Saturday'];
   $officeOff = [];
   foreach (explode(',', (string) ($profile['weekly_off_days'] ?? '')) as $n) {
       if (isset($map[(int) $n])) { $officeOff[] = $map[(int) $n]; }
   }
   if (($profile['weekly_off_days'] ?? null) === null) { $officeOff = ['Sunday', 'Saturday']; } // smart-school's default
   ```
3. Holidays: select `` `department` `` instead of `` `department_id` ``, and before
   calling `holiday_applies_to()` convert each row:
   `$h['department_id'] = ((string) $h['department'] === '0' || $h['department'] === '' || $h['department'] === null) ? null : (int) $h['department'];`
4. In `createRun()`, replace `catch (QueryException $e)` with
   `catch (PDOException $e)` and test `(string) $e->getCode() === '23000'` instead of
   `str_contains($e->getMessage(), 'Duplicate')`.
5. `approve()` calls `notifyUser(... url)`: keep (the shim ignores the URL; Task 6 adds the route).
6. `emailPayslips()`: use `config('abs_url')` instead of `config('server_path')`, and call
   smart-school `sendEmail($to, $subject, $html)`; treat a truthy / `['ok'=>true]` return as sent
   (read `sendEmail()` to see what it returns).

**Check:** `php -l` both; then in `tests/payroll_check.php`:
`(new PayrollRunService())->period('2083-06')` returns the same start/end as
`SELECT eng_start_date, eng_end_date FROM tbl_calendar WHERE nepali_year = 2083 AND month_code = 6`
(in smart-school today: `2026-09-17` → `2026-10-17`, **31 days** — do not compare with codegenexis, whose
calendar says 30; see §2).

---

### Task 5 — P1 pages: Salary Setup, Funds, Pay Components, Tax Slabs

1. Copy the pages and their `operation/` handlers (file map §4); apply §8.3.
2. Register the module like `staff_management` in `nav_definition.php` / `varriables.php`:
   module key `payroll`, section next to People/HR, submodules
   `runs, salary_setup, funds, components, tax_slabs, advances, remittance, accounting`
   (pages that don't exist yet are fine — they appear as they are built).
3. Add special permissions `manage_payroll` ("Manage payroll (salaries, funds, tax)") and
   `approve_payroll` ("Approve payroll runs") to `$specialPermissions`, owned by module
   `payroll` in `$specialPermissionModule`.
4. CSS: create `admin/assets/css/payroll.css` = the §8.4 token block + the codegenexis
   `ana-*` block; include it on payroll pages (follow how other pages add a stylesheet,
   or link it at the top of each payroll page).
5. Staff form (employment type, contract end, tax filing status): find smart-school's
   add/edit staff page + its operation (`admin/modules/staff_management/…`), add the three
   fields the same way codegenexis `add_staff.php` / `operation/add_staff.php` do.
6. Leave type form: add a "Paid leave" checkbox writing `is_paid` (codegenexis
   `includes/setup_leave_tab.php` + `operation/leave_setup.php` show how).

**Check (in a browser, logged in as admin):** Payroll appears in the sidebar; Tax Slabs shows
FY 2083/84; the calculator gives **120,000** tax on 18,00,000; saving bands with a gap is refused;
Salary Setup → set a staff member to basic 50,000 + DA 10,000 + SSF → estimate shows net **54,500**.

---

### Task 6 — P2: runs, payslips, My Payslips

1. Copy runs/run/payslip pages, `includes/payslip_render.php`, `operation/runs.php`; apply §8.3.
2. `payslip_render.php`: replace `documentShellStart()/documentShellEnd()` with a simple header
   (school name from `tbl_office_profiles.name`, logo if smart-school has a helper for it) and
   replace `PdfGenerator` with `makePDF($slipHtml, $filename)` (check its signature first).
3. Copy `my_office/payslips.php` → `admin/modules/myOffice/payslips.php`; change
   `pageUrl('my_office', …)` → `pageUrl('myOffice', …)` everywhere in payroll code; register the
   `payslips` submodule under `myOffice`.
4. Notifications: in `notificationTargetRoute()` add type `payroll` → `myOffice/payslips&line=<ref_id>`.
5. Load Chart.js 4.4.1 + `analytics-charts.js` on payroll and reports pages.

**Check:** with two test staff set up, create a run for a past BS month → lines appear; mark one
day absent for a staff member → **Recalculate** → 1 unpaid day; Approve as a different user than the
preparer (preparer gets refused); payslip PDF downloads; the staff member sees it in My Payslips and
**cannot** open another person's payslip by changing `line=` in the URL. Start the same month twice
quickly → only one run exists.

---

### Task 7 — P3: accounting (hard — stronger model recommended)

The voucher tables differ, so **do not copy the voucher-writing code**. Keep the
pure `payroll_journal_lines()` (it decides *what* to post); rewrite *how* to post.

1. Read smart-school `admin/modules/accounts/operations/journal_voucher_operation.php` and
   `payment_voucher_operation.php` end to end, and `functions/accounting_helpers.php`
   (`accounting_resolve_fiscal_year_by_date`, `accounting_next_voucher_number`,
   `accounting_append_voucher_log`).
2. Write `PayrollPostingService::post()/pay()/unpost()/unpay()/advancePayment()` so they create
   vouchers **exactly the way those handlers do** (same columns in `tbl_journal_vouchers` /
   `tbl_payment_vouchers`, same rows in `tbl_ledger_particulars` incl. `account_group`,
   `account_subgroup`, `account_terminal`, `date`, `fiscal_year`, and `tbl_sub_ledger_particulars`
   if those handlers write it), status **Pending**, plus a voucher log via
   `accounting_append_voucher_log()`.
3. Migrations: copy `create-table-payroll_settings`,
   `alter-table-payroll_funds-add-payable_terminal_id`,
   `alter-table-payroll_settings-add-deductions_terminal_id`. **Do not copy**
   `seed-table-account_terminals-payroll` (terminal columns differ): instead the user creates
   SSF/PF/CIT Payable, Employer Fund Contributions and Staff Advances in smart-school's Chart of
   Accounts, then maps them on Payroll → Accounting Setup.
4. Copy `accounting.php`, `remittance.php` + ops; replace `accountingTerminalOptions()` with a
   query over smart-school's `tbl_account_terminals` grouped by group/subgroup.

**Check:** post an approved run → one Pending journal appears in smart-school's Journal Vouchers
list, debits = credits, and it can be approved there like a manual voucher; trial balance
still balances after approving; Undo posting works only while Pending.

---

### Task 8 — P4: advances, reports, dashboard

Copy `advances.php` + op, `reports/payroll.php` (register under smart-school's reports module),
the dashboard chips (payroll drafts, contracts ending) into smart-school's dashboard following its
structure, and the contracts list. Apply §8.3.

**Check:** advance 20,000 at 10,000/month → two approved runs recover it and it closes; cancelling
the second run reopens it with 10,000 outstanding.

---

### Task 9 — Final verification

Run `php tests/payroll_check.php` and the browser checks of Tasks 5–8 once more, then hand the
four seeded tax figures to the accountant to confirm (top band 29% vs 27% + 2% surcharge,
retirement cap NPR 5 L or one-third, 10% rebate for women, SSF 11% + 20%).

---

## 8. Code to copy verbatim

### 8.1 `classes/PayrollDb.php`

```php
<?php
/**
 * Payroll DB adapter for smart-school.
 *
 * Gives the payroll code (ported from codegenexis) the same prepared-statement
 * API it was written against — select / selectOne / insert / update / delete /
 * execute / count / transaction — on smart-school's PDO connection
 * (getPdoConnection(), database/db_helpers.php). Payroll never concatenates
 * user input into SQL.
 */
class PayrollDb
{
    private static ?PayrollDb $instance = null;

    private PDO $pdo;

    public static function instance(): self
    {
        return self::$instance ??= new self(getPdoConnection());
    }

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** @return list<array<string, mixed>> */
    public function select(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($params));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed>|null */
    public function selectOne(string $sql, array $params = []): ?array
    {
        $rows = $this->select($sql, $params);

        return $rows[0] ?? null;
    }

    /** Run a write statement; returns affected rows. */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($params));

        return $stmt->rowCount();
    }

    /** Insert one row; returns the new id. Column names come from code, never from users. */
    public function insert(string $table, array $data): int
    {
        $cols = implode(', ', array_map(static fn ($c) => '`' . $c . '`', array_keys($data)));
        $marks = implode(', ', array_fill(0, count($data), '?'));
        $this->execute('INSERT INTO `' . $table . '` (' . $cols . ') VALUES (' . $marks . ')', array_values($data));

        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $whereSql, array $whereParams = []): int
    {
        $set = implode(', ', array_map(static fn ($c) => '`' . $c . '` = ?', array_keys($data)));

        return $this->execute('UPDATE `' . $table . '` SET ' . $set . ' WHERE ' . $whereSql, array_merge(array_values($data), array_values($whereParams)));
    }

    public function delete(string $table, string $whereSql, array $whereParams = []): int
    {
        return $this->execute('DELETE FROM `' . $table . '` WHERE ' . $whereSql, $whereParams);
    }

    public function count(string $table, string $whereSql = '1=1', array $params = []): int
    {
        return (int) ($this->selectOne('SELECT COUNT(*) AS c FROM `' . $table . '` WHERE ' . $whereSql, $params)['c'] ?? 0);
    }

    public function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }

    /**
     * Run $fn inside a transaction and return its result. PDO cannot nest
     * transactions, so an inner call joins the outer one and the outermost
     * caller commits or rolls back.
     */
    public function transaction(callable $fn)
    {
        if ($this->pdo->inTransaction()) {
            return $fn($this);
        }
        $this->pdo->beginTransaction();
        try {
            $result = $fn($this);
            $this->pdo->commit();

            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
```

### 8.2 `functions/payroll_compat.php`

```php
<?php
/**
 * Payroll compatibility layer for smart-school: the codegenexis helpers and
 * Auth calls the ported payroll code uses, implemented on smart-school's own
 * functions. Each helper is defined only if smart-school doesn't have it.
 */

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('pageUrl')) {
    function pageUrl(string $module, string $page = ''): string
    {
        return './show_page.php?module=' . rawurlencode($module) . ($page !== '' ? '&page=' . rawurlencode($page) : '');
    }
}

if (!function_exists('csrfField')) {
    function csrfField(): string
    {
        return app_csrf_field();
    }
}

if (!function_exists('setFlash')) {
    /** $type: success | error | warning | info */
    function setFlash(string $type, string $message): void
    {
        setErrorMsg($type, htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url): void
    {
        if (!headers_sent()) {
            header('Location: ' . $url);
        } else {
            echo '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">';
        }
    }
}

if (!function_exists('auditLog')) {
    function auditLog(string $module, string $action, ?string $entityType = null, ?int $entityId = null, $oldData = null, $newData = null, ?string $description = null): void
    {
        if (function_exists('app_activity_log')) {
            app_activity_log([
                'module'    => $module,
                'action'    => $action,
                'entity'    => $entityType,
                'entity_id' => $entityId,
                'summary'   => $description ?? ($module . ': ' . $action),
                'details'   => ['old' => $oldData, 'new' => $newData],
            ]);
        }
    }
}

if (!function_exists('notifyUser')) {
    /** smart-school's tbl_notifications has no title/url; the link comes from notificationTargetRoute() by type. */
    function notifyUser(?int $receiver, string $details, string $type = 'general', $refId = null, ?int $actor = null, ?string $url = null): void
    {
        if (!$receiver) {
            return;
        }
        try {
            PayrollDb::instance()->insert('tbl_notifications', [
                'details'  => $details,
                'ref_id'   => $refId === null ? null : (string) $refId,
                'receiver' => $receiver,
                'type'     => $type,
                'viewed'   => 0,
                'added_on' => date('Y-m-d H:i:s'),
                'added_by' => $actor === null ? null : (string) $actor,
            ]);
        } catch (Throwable $e) {
            // A notification failure must never break payroll.
        }
    }
}

if (!function_exists('formatMoney')) {
    function formatMoney($amount): string
    {
        return number_format((float) $amount, 2);
    }
}

if (!function_exists('formatDateView')) {
    /** Replace with smart-school's own date display helper if it has one. */
    function formatDateView($date): string
    {
        return $date ? substr((string) $date, 0, 10) : '';
    }
}

if (!function_exists('bsMonthName')) {
    function bsMonthName(int $month): string
    {
        $names = [1 => 'Baisakh', 2 => 'Jeth', 3 => 'Asar', 4 => 'Shrawan', 5 => 'Bhadra', 6 => 'Ashwin',
            7 => 'Kartik', 8 => 'Mangsir', 9 => 'Poush', 10 => 'Magh', 11 => 'Falgun', 12 => 'Chaitra'];

        return $names[$month] ?? (string) $month;
    }
}

if (!function_exists('office_display_name')) {
    function office_display_name(): string
    {
        try {
            return (string) (PayrollDb::instance()->selectOne('SELECT `name` FROM `tbl_office_profiles` ORDER BY `id` LIMIT 1')['name'] ?? '');
        } catch (Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('old')) {
    function old(string $key, $default = '')
    {
        return $default;
    }
}

if (!function_exists('old_checkbox')) {
    function old_checkbox(string $scope, string $key, bool $storedChecked): bool
    {
        return $storedChecked;
    }
}

if (!class_exists('PayrollAuth')) {
    /** The three Auth calls payroll uses, on smart-school's session + permission helpers. */
    final class PayrollAuth
    {
        public static function id(): ?int
        {
            return isset($_SESSION['userId']) ? (int) $_SESSION['userId'] : null;
        }

        public static function hasSpecial(string $key): bool
        {
            return function_exists('checkSpecialPermission') && (bool) checkSpecialPermission($key);
        }

        /** Super admins may approve a run they prepared themselves (four-eyes exemption). */
        public static function isSuperAdmin(): bool
        {
            $id = self::id();
            if (!$id) {
                return false;
            }
            $u = PayrollDb::instance()->selectOne('SELECT `permitted_modules`, `role` FROM `tbl_users_login` WHERE `id` = ?', [$id]);

            return $u && ($u['permitted_modules'] === 'All' || strcasecmp((string) $u['role'], 'Superadmin') === 0);
        }
    }
}
```

### 8.3 Mechanical replacements (apply to every copied payroll PHP file)

| Find | Replace with |
|---|---|
| `Database::instance()` | `PayrollDb::instance()` |
| `Database $db` (type hints, incl. `?Database $db`, `fn (Database $db)`) | `PayrollDb $db` |
| `Auth::hasSpecial(` | `PayrollAuth::hasSpecial(` |
| `Auth::id()` | `PayrollAuth::id()` |
| `Auth::isSuperAdmin()` | `PayrollAuth::isSuperAdmin()` |
| `pageUrl('my_office', 'payslips')` | `pageUrl('myOffice', 'payslips')` |
| `catch (QueryException $e)` | `catch (PDOException $e)` (+ Task 4 step 4) |
| `config('server_path')` | `config('abs_url')` |

After replacing, search each file for `Database`, `Auth::`, `QueryException`, `PdfGenerator`,
`documentShell`, `accounting` and `CommunicationService` — every remaining hit must be handled by the
current task's notes or reported.

### 8.4 CSS token fallbacks (top of `payroll.css`)

The copied `ana-*` styles use these variables. Define them in case smart-school's theme doesn't:

```css
:root {
    --bg-card: #ffffff; --bg-body: #f8fafc; --text-primary: #0f172a; --text-secondary: #475569;
    --text-muted: #64748b; --border-color: #e2e8f0; --border-light: #f1f5f9;
    --accent: #2563eb; --accent-rgb: 37, 99, 235;
    --radius-md: .5rem; --radius-lg: .75rem; --radius-full: 9999px;
    --shadow-sm: 0 1px 3px rgba(15,23,42,.04), 0 1px 2px rgba(15,23,42,.06);
    --shadow-md: 0 4px 6px -1px rgba(15,23,42,.07), 0 2px 4px -2px rgba(15,23,42,.05);
    --transition-fast: 150ms cubic-bezier(.4,0,.2,1);
    --font-display: 'Poppins', sans-serif;
    --viz-1: #2a78d6; --viz-2: #eb6834; --viz-3: #1baf7a; --viz-4: #eda100; --viz-5: #e87ba4; --viz-6: #008300;
    --viz-good: #0ca30c; --viz-warning: #fab219; --viz-serious: #ec835a; --viz-critical: #d03b3b;
    --viz-neutral: #94a3b8; --viz-grid: #eef2f6; --viz-delta-good: #006300; --viz-delta-bad: #b42318;
}
```
