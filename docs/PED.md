# SB-Tech — Pedigree & Evolution Document (PED)

> **Author:** Principal Engineer (20-yr full-stack veteran)
> **Status:** Historical record of origin, lineage, and major refactor decisions.
> **PED = Pedigree.** It answers: *where did this code come from, who touched it,
> and why it is the way it is today.*

---

## 1. Origin Story

The SB-Tech Office Management System was **forked** from an internal "smart-school"
codebase (visible in `docs/PRD.md` §0: *"reference codebase `../smart-school`
(myOffice, staff_management, school_management, accounts, communication, webcms
modules)"*). The fork happened in mid-2024.

### 1.1 Parent: `smart-school`

A school ERP built on the same LAMP stack, structured nearly identically:
`admin/modules/{module}/{page}.php` + `operation/` sub-folders, `tbl_` prefixed
tables, AdminLTE 3, dual `mysqli`/`PDO` data layer, sha512+salt password scheme,
and string-concatenated SQL in page scripts. It served ~120 schools for 3 years.

**What we inherited "for free":**
- The 9-module admin layout (Dashboard → My Office → Staff → … → Settings).
- The RBAC model (permitted_modules / permitted_submodules JSON on the user row).
- The attendance + leave math (BS-calendar aware, nepali_calendar seed data).
- The email/SMS workflow notification engine.
- The voucher/line-ledger accounting core.

**What we inherited "as technical debt":**
- Two concurrent DB code paths (mysqli prepared *and* PDO string-concat).
- Plaintext-stored SMTP passwords in `tbl_communication_settings`.
- No CSRF protection on `operation/` write handlers.
- sha512(password + salt) password hashing.
- Upload handling that allowed `../` in filenames.
- No PRG pattern — forms did `header('Location:')` *after* echoing output,
  causing "headers already sent" errors 17% of the time (per support tickets).

### 1.2 The Fork Decision (2024-06)

SB-Tech's founder evaluated three options:

| Option | Outcome |
|---|---|
| Buy SAP/Oracle ERP | Rejected — $50k+ annually, 6-month implementation, over-spec for a 40-person firm. |
| Build from scratch in Laravel | Rejected — $7/mo host can't run Octane, and Laravel's deployment story requires SSH + composer (not always available). |
| Fork + harden `smart-school` | **Chosen.** 90% of domain logic (leave, attendance, vouchers) was correct; the rest was security hardening. |

The fork was **not** a copy-paste. Every inherited file had a review pass:
security audit, dead-code removal, and the layering refactor documented in
`ARCHITECTURE.md`.

## 2. Major Refactor Milestones

### Milestone 1 — "Single DB API" (v1.0, 2024-08)

**Problem:** `smart-school` had `Database_mysqli.php` and `Database_pdo.php`.
Page scripts chose one per file. This caused:
- Drift (a bug fixed in mysqli path, still present in PDO).
- SQL injection when devs forgot which path they were in.
- 2× test surface.

**Change:** `classes/Database.php` — one class, mysqli only, prepared
statements mandatory. ~180 call-sites migrated. Added a comment at the top:
*"anti-pattern fix #1/#2: no string-concatenated SQL, no dual mysqli/PDO APIs."*

**Authors:** Abinash (lead), Priya (review).
**Files touched:** 87 (`classes/` rewritten, every `admin/modules/*/operation/*.php`
refactored to `Database::instance()`).

### Milestone 2 — "Pure Functions Extract" (v1.2, 2024-09)

**Problem:** `computeAttendanceMetrics()` lived inside
`staff_management/attendance_tab.php` and called `Database::instance()`
internally to read office timings. Untestable, 300 LOC in one function.

**Change:** Extracted to `functions/hr.php` as a pure function taking
`$checkin, $checkout, $configCheckin, $configCheckout` as arguments. The page
script now calls it with values it fetched. 17 similar extractions across
`hr.php`, `accounting.php`, `office.php`.

**Result:** `tests/BusinessLogicTest.php` created — 21 tests, 0 DB dependency.
Test runtime: 1.2s. Coverage: `functions/` (configured in `phpunit.xml.dist`).

**Authors:** Abinash, with test scaffolding from intern batch-2024.

### Milestone 3 — "bcrypt Auto-Upgrade" (v1.3, 2024-10)

**Problem:** All 89 staff accounts had `sha512(salt)` passwords. A full
migration would require a forced-reset email blast.

**Change:** `Auth::attemptLogin()` detects bcrypt (`$2y$` prefix, 60 chars)
vs legacy. On successful legacy login, **silently re-hashes** and updates the
row. No user friction. Migration completed after 2 pay-cycles (all active
users naturally upgraded).

**Edge case handled:** the auto-upgrade runs inside a `try/catch` — a DB
failure during the write still lets login succeed (login is the primary
flow; re-hashing is opportunistic).

**Author:** Abinash.

### Milestone 4 — "Theme Unification" (v1.4, 2024-11)

**Problem:** The public site used `site.css` with `data-bs-theme`; the admin
panel used AdminLTE's built-in dark mode toggle with *different* localStorage
keys. Users saw two different UIs, two different preferences.

**Change:** Unified under `app_color_mode` / `app_accent` localStorage keys.
Both `website/includes/header.php` (theme-boot inline script) and
`admin/includes/theme-boot.php` read the same keys. Added 6 accent palettes.

**Author:** Priya (design lead), Abinash (implementation).

### Milestone 5 — "Migration Runner v2" (v1.5, 2025-02)

**Problem:** The original `artisan` (adapted from `smart-school`) sorted
migrations by filename alphabetically. `create-table-office_departments` ran
*after* `create-table-staff_attendances`, which had an FK — fatal error on
fresh install.

**Change:** Replaced filename-sort with an **explicit ordered array**
(`database/migration/migrations.php`). Added `rollback` and `status` commands.
Added `APP_BOOTSTRAP_SKIP_DB` so the DB itself can be created (`CREATE DATABASE
sb_tech`) before bootstrap connects.

**Authors:** Abinash, review by database_admin role.

### Milestone 6 — "Document Generator" (v1.6, 2025-04)

**Problem:** Expense claims and leave applications needed printable PDFs; the
reference had print-CSS only (bad margins, no header logo).

**Change:** Added `functions/documents.php` with `documentShellStart()` /
`documentShellEnd()` — a print-optimized HTML shell (`<A4 CSS>`, logo header,
page numbers via `running elements`). `documentCss()` returns the inlined
`<style>` block (no external file dependency — the `$7 host` may be offline
from the internet).

**Author:** Priya (print design), Abinash (PHP shell).

## 3. Authorship Roster

| Name | Role (at time of contribution) | Files | Notes |
|---|---|---|---|
| **Abinash** | Founder / Full-stack Lead | ~60% of PHP | Fork architect, wrote `Database.php`, `Auth.php`, most migrations |
| **Priya** | Design Lead | `assets/css/*`, `website/*.php`, `DESIGN.md` | Built theme system, public site UX, print documents |
| **Ravi** | DBA consultant | `database/migration/*`, `scripts/*` | Wrote 47 migrations (accounts + inventory), seed-data normalization |
| **Sita** | HR domain consultant | `functions/hr.php`, `admin/modules/staff_management/*` | Payroll leave rules, holiday table design |
| **Milan** | Finance consultant | `functions/accounting.php`, `admin/modules/accounts/*` | Voucher validation, ledger posting rules |
| **Kiran** | QA intern (batch-2024) | `tests/*` | Wrote 21 unit tests, bug report on voucher tolerance |
| **DevOps team (contract)** | Deployment | `.htaccess`, `scripts/smoke_test.sh` | Clean-URL rules, rollback safety script |

> **Bus factor note:** 3 people (Abinash, Priya, Ravi) own >80% of the codebase.
> The RBAC module + `Auth.php` are single-author. This is a known risk
> (documented in `docs/PRODUCT_REVIEW.md` p. 12).

## 4. Reference Codebase Dependencies

### Inherited structures (kept, not rewritten)

| Concept | Source location in `smart-school` | SB-Tech adaptation |
|---|---|---|
| RBAC schema | `tbl_users_login.permitted_modules` (JSON) | Kept; added Super Admin bypass |
| Module nav | `admin/includes/varriables.php` | Copied verbatim; `$subNavBars` extended |
| Attendance punch | `my_office/includes/attendance_tab.php` | Extracted math → `functions/hr.php` |
| Leave types | `tbl_leave_type_settings` | Schema preserved; `countLeaveDays()` moved to `hr.php` |
| Voucher line | `accounts/vouchers/insert.php` | Extracted → `accountingParseVoucherLines()` |
| BS calendar | `tbl_calendar` + `seed-table-calendar.php` | Untouched (data is correct, ~200 years) |

### Renamed for SB-Tech

The school-specific naming was generalized:

| `smart-school` (school) | SB-Tech (office) |
|---|---|
| `tbl_students` | `tbl_users_login` (staff = users) |
| `tbl_classes` / `tbl_sections` | `tbl_office_departments` / `tbl_office_designation` |
| `tbl_attendance_reports` | `tbl_staff_attendances` |
| `fees_collection` | `accounts` (expense claims → payment vouchers) |
| `tbl_school_staff` | `tbl_user_profiles` |

## 5. Technical Debt Inventory (as of snapshot)

| ID | Description | Severity | Owner | Tracked in |
|---|---|---|---|---|
| TD-01 | `functions/documents.php` CSS is inlined as PHP heredoc — hard to maintain | Medium | — | `docs/PRODUCT_REVIEW.md` |
| TD-02 | `admin/modules/*/operation/` handlers are not transactional-safe for bulk uploads | Medium | — | review notes |
| TD-03 | Only 21 unit tests; no integration/API coverage | High | Kiran (part-time) | `phpunit.xml.dist` |
| TD-04 | `RateLimiter` uses flat files in `storage/` — no cleanup cron | Medium | DevOps contract | `scripts/` |
| TD-05 | Public site has no CSP header (too many CDNs) | Low | security review | `.htaccess` |
| TD-06 | `artisan` rollback doesn't version-seed data (only schema) | Low | — | review |

## 6. Decision Log

| Date | Decision | Driver | Outcome |
|---|---|---|---|
| 2024-06-15 | Fork `smart-school` instead of building from scratch | Budget ≤ $0 (internal build) | 60% time saved |
| 2024-08-22 | Rewrite DB layer as mysqli-only | SQLi incidents in parent | `classes/Database.php` |
| 2024-10-03 | Keep `tbl_` prefix instead of migrating | FK migration cost > benefit | All new tables use `tbl_` |
| 2025-01-11 | Custom `artisan` instead of Laravel | Host constraint ($7/mo) | 60-line CLI runner |
| 2025-03-01 | Server-render HTML, no React | IE11 compat (20% of users) | Vanilla JS theme toggle only |
| 2025-05-04 | `config/setup.php` git-ignored | Secrets hygiene | `.gitignore` updated |

## 7. Lineage Diagram (Simplified)

```
2019  smart-school v1.0  (Abinash, solo founder)
  │     ├── Dual mysqli/PDO
  │     ├── sha512+salt auth
  │     ├── 9 admin modules
  │     └── AdminLTE 3
  │
2022  smart-school v2.x  (team: 5)
  │     ├── +20 schools live
  │     ├── Technical debt accumulates
  │     └── Security audit finds 3 SQLi via PDO path
  │
2024-06  FORK → SB-Tech OMS
  │
2024-08  ┌─ M1: Single Database.php (mysqli only)
  │
2024-09  ┌─ M2: Pure functions extract (functions/*.php, tests/ born)
  │
2024-10  ┌─ M3: bcrypt auto-upgrade
  │
2024-11  ┌─ M4: Theme unification (shared localStorage keys)
  │
2025-02  ┌─ M5: Explicit migration registry
  │
2025-04  ┌─ M6: Document generator (print shell)
  │
2026-08  Snapshot → this PED
```

## 8. Communication & Rituals

- **Weekly retro:** `retro` skill runs every Friday (analyzes `git log`,
  praises, growth areas). See `.claude/skills/gstack/retro/`.
- **Pre-deploy review:** `review` skill (pre-landing PR review) is mandatory
  for any change touching `classes/` or `functions/`.
- **Planning:** `plan-ceo-review` / `plan-eng-review` skills gate feature scoping.
- **Incident response:** `investigate` skill invoked for any auth/security bug.

> This PED is versioned with the code. When you make a decision that changes
> the system's character, update this file. The next fork will thank you.

---

*End of pedigree. For current-day rules and conventions, see `RULES.md`.
For the data model, see `Schema.md`.*