# SB-Tech Office Management System — Codebase Overview

> **Generated:** 2026-08-26 (analysis snapshot)
> **Tech stack:** PHP 7.4+ · mysqli (prepared statements) · MySQL · PHPUnit 9.5 · AdminLTE 3 / Bootstrap 4 · vanilla JS

A single-deployable modular monolith with **two faces sharing one database**: a public marketing website and a role-based staff admin panel ("OMS"). No framework — custom front controllers, routing, auth, and a thin DB layer.

---

## 1. Repository Layout

```
codegenexis/
├── config/              # Bootstrap + environment config (setup.php is git-ignored)
├── classes/             # Domain: Database, Auth, CommunicationService, RateLimiter
├── functions/           # 72 pure PHP business-logic functions across 5 files
├── database/migration/  # 102 ordered migrations + migrations.php registry
├── admin/               # Admin panel (OMS): 11 modules × display+operation pages
├── website/includes/    # Public site bootstrap + theme partials
├── assets/{css,js}/     # AdminLTE overrides + site theme + theme-switcher
├── tests/               # PHPUnit unit tests for pure functions
├── scripts/             # Migration checker + smoke test (bash)
├── docs/                # PRD, system analysis, modules catalog
├── artisan              # Custom CLI migration runner (migrate/status/rollback)
├── index.php, about.php, services.php, projects.php, ...  # Public pages
└── composer.json        # PHP ≥7.4, PHPUnit dev-dep, classmap autoload
```

**Scale:** ~25,700 PHP LOC across 72 source files + 102 migrations. Documented in ~1,335 lines of docs (`docs/`).

---

## 2. Architecture

### 2.1 Layered Model

| Layer | Path | Responsibility |
|---|---|---|
| **Config/Bootstrap** | `config/bootstrap.php` | Loads `config/setup.php` (git-ignored secrets) → wires session, timezone, DB singleton, shared helpers, auto-constants, security headers |
| **DB layer** | `classes/Database.php` | Thin singleton over **mysqli prepared statements** only (anti-pattern fix: no dual mysqli/PDO APIs). Full CRUD: `select/selectOne/execute/insert/update/delete/transaction/count` |
| **Domain classes** | `classes/` | `Auth` (RBAC + bcrypt w/ auto-upgrade from legacy sha512 + IP allow-list + session-fixation guard), `CommunicationService` (SMTP/SMS/email templates, AES for encrypted settings), `RateLimiter` (file-based token-bucket) |
| **Business logic** | `functions/*.php` | Pure, side-effect-free functions: attendance math, leave counting (BS-aware), voucher line validation, inventory, office, documents |
| **Admin UI** | `admin/` | Front controller `show_page.php` → `includes/route.php` (resolves + sanitizes module/page) → `Auth::can()` gate → includes `admin/modules/<module>/<page>.php` |
| **Public site** | `website/includes/site.php` + root `*.php` | `index.php`, `about.php`, etc. bootstrap via `site.php`; `siteRows()` enforces strict identifier-whitelisting for SQL |

### 2.2 Data Layer — Custom Migration Runner

- **`artisan`** (CLI): `php artisan migrate | status | rollback [N] | help`
- Registry: `database/migration/migrations.php` — explicit ordered array of 102 filenames (foundation → office → staff → HR → leave → attendance → accounts → inventory → leads → communication → webcms → perf indexes → settings/docs).
- All tables prefixed `tbl_`, charset `utf8mb4`, money stored `DECIMAL(18,4)` (X-07).
- Applied migrations tracked in `tbl_migrations` table.

**Module map (11 admin modules, sidebar order per `vars.php`):**
`dashboard · my_office · staff_management · leads · accounts · inventory · reports · office_setup · communication · webcms · settings`

Each module follows a **display+operation split** (`pages/` for forms/lists, `operation/` for write handlers) + `includes/` for shared partials. Examples: `admin/modules/office_setup/operation/holidays.php`, `admin/modules/communication/operation/templates.php`.

### 2.3 Routing & Front Controllers

- **Public:** flat route via `.htaccess` rewrite (`^([a-z0-9-]+)/?$ → $1.php`). All public pages `require 'website/includes/site.php'` → `header.php` → `footer.php`.
- **Admin:** `admin/show_page.php?module=X&page=Y` → `route.php` sanitizes `[a-zA-Z0-9_-]` → loads nav map (`varriables.php`) → gates on `Auth::can($module, $page)` → includes module page.
- **Admin API/AJAX:** `admin/ajax.php`, `admin/api/*.php` (notifications, SSE); CSRF-protected for writes.

---

## 3. Security Posture (X-01 … X-10 referenced in code)

### XSS
- `e($val) = htmlspecialchars($val, ENT_QUOTES|UTF-8)` — used everywhere user data is echoed.
- `escape_data()` alias.
- `sanitizeEmbed()`: whitelist-filter iframes; strips scripts, event handlers, `javascript:`/`data:` URIs.

### CSRF
- `csrfToken()` (32-byte `random_bytes`, persisted in `$_SESSION`) + `csrfField()` hidden input on every form.
- `verifyCsrf()` uses `hash_equals()` (constant-time) on every POST.
- API layer accepts token via `$_POST['csrf_token']` or `X-CSRF-TOKEN` header.
- Read actions (GET) are CSRF-safe by method; state changes require POST.

### Input Validation
- **Route params** restricted to `[a-zA-Z0-9_-]` (no path traversal possible in includes).
- **Uploads:** `validateUpload()` — whitelist ext `[jpg|jpeg|png|pdf|doc|docx|xls|xlsx|pptx|txt]`, 10 MB cap (`upload_max_bytes`); `storeUpload()` sanitizes module path (`preg_replace('#[^a-zA-Z0-9_/]#'…` then `..` check).
- **`siteRows()`** enforces regex-ident validation on `table`, `columns`, `orderBy` to prevent injection (all call-sites use hardcoded values).

### Auth & Access Control
- **Password:** bcrypt (`password_hash`, cost 12) w/ **auto-upgrade**: legacy `sha512(salt)` stored hashes are transparently re-hashed to bcrypt on successful login.
- **RBAC:** module + submodule allowlists (JSON on user row, AC-AUTH-01.4); special permissions (granular actions) like `manage_staff_leaves`, `approve_vouchers`, `audit`.
- **Super Admin** bypass: `permitted_modules='All'` OR role `Super Admin`/`Superadmin`.
- **IP allow-list:** read from `tbl_office_profiles.allow_ips`; `127.0.0.1/::1/0.0.0.0` always allowed (never blocks dev/admin).
- **Session fix:** `session_regenerate_id(true)` on login.
- **Brute force:** file-based token-bucket `RateLimiter` + failed-attempt logging to `tbl_login_attempts`.

### PRG Pattern
- `redirect()` helper falls back to `<meta refresh>` if headers already sent (no broken post-back).

### Audit Trail
- `auditLog()`: fire-and-forget (try/catch never throws) to `tbl_audit_log` with actor, IP, old/new JSON data.

### `.htaccess` Hardening
- Denies `config/`, `scripts/`, `classes/`, `tests/`, `docs/`, `database/`, hidden files (`.`-prefixed), `composer.json|lock`, `phpunit.xml.dist`, `.gitignore`, `.htaccess`.
- Blocks `clear_opcache.php` (admin-only).
- **Custom 404** → `/404.php`; `Options -Indexes` (no dir listing).
- Clean-URL rewrites for public + admin paths (`/admin/leads → admin/show_page.php?module=leads`).

---

## 4. Key Components

### 4.1 `classes/Auth.php`
- Session keys: `loginStatus, userId, username, fullname`.
- `Auth::user()` cached per-request (avoids N+1 on permission checks); `clearUserCache()` forces reload.
- `Auth::check() | id() | attemptLogin() | logout()`.
- RBAC: `isSuperAdmin() | hasModule($mod) | hasSubmodule($mod,$sub) | hasSpecial($key) | can($mod, $page='')`.

### 4.2 `classes/CommunicationService.php`
- Static API: `sendEmail(to,subject,body,isHtml)`, `sendSms(to,msg)`, `sendWorkflowNotification(...)`, `sendCampaign($campaignId)`.
- Multi-transport (SMTP/PHPMailer → PHP mail() fallback; SMS via Sparrow/generic).
- AES encryption (`encrypt()/decrypt()`) for SMTP-password storage in `tbl_communication_settings`.
- Templates engine (`renderTemplate()`) with per-event wiring (`isEventWired()`).

### 4.3 `classes/RateLimiter.php`
- Token-bucket per IP (+optional username), config via `tbl_office_profiles.rate_limit_*`.
- `check($ip,$user): ['allowed'=>bool,'retry_after'=>sec]`.

### 4.4 `functions/` — 72 functions, 5 files

| File | Core functions |
|---|---|
| `helpers.php` | `e, redirect, setFlash/flashMessages, csrfToken/csrfField/verifyCsrf, pageUrl, formatDateView, adToBs, bsMonthName, formatMoney, paginationParams, validateUpload, storeUpload, auditLog, renderFlash` |
| `hr.php` | `currentLeaveYear, isOfficeHoliday, countLeaveDays (BS-aware), getStaffLeaveAllocationsWithBalance, computeAttendanceMetrics, autoAttendanceStatus, formatMinutes` |
| `office.php` | `canSeeAllTasks, taskScopeSql, isTaskPastDue, taskBadgeClasses, eventVisibilitySql, bookedStaffIdsAtSlots, scheduleLine` |
| `accounting.php` | `accountingVoucherConfig, accountingFiscalForDate, accountingNextVoucherNo, accountingParseVoucherLines, accountingLedgerLines, accountingOpeningBalance, accountingLogVoucher, accountingPaymentVoucherForClaim` |
| `inventory.php` | `inventoryNextPrNo, inventoryNextAssetTag, inventoryAvailableStock, inventoryRecordMovement, inventoryIsLowStock` |
| `documents.php` | `documentSettings, documentShellStart/End, documentHeaderLogoUrl` |

> Pure-logic functions are **unit tested** (no DB). DB-backed function tests use `markTestSkipped()` when `$DB` unavailable.

---

## 5. Testing

### Setup
- `phpunit.xml.dist` → bootstraps `tests/bootstrap.php` (fakes `config()` if `setup.php` absent → no DB needed).
- Coverage configured to include `functions/` + `classes/`.
- Run: `vendor/bin/phpunit tests/`

### `tests/BusinessLogicTest.php` — 21 tests covering pure functions:
| Group | Tested |
|---|---|
| Attendance (hr.php) | `computeAttendanceMetrics()` — on-time, late checkin, early checkout, both, null checkin/checkout, null config |
| Leave (hr.php) | `countLeaveDays()` — half-day, single day, invalid range, weekend-inclusive (DB skipped if unavailable) |
| Vouchers (accounting.php) | `accountingParseVoucherLines()` — balanced, unbalanced, empty, negative-blocked, multi-line, zero-skipped, tolerance ±0.01 |
| Helpers | `formatMinutes()`, `bsMonthName()` |

### Gaps
- No integration tests for `Auth`, `CommunicationService`, admin page rendering, or API endpoints.
- DB-dependent branches in leave tests rely on manual `dbAvailable()` guard.

---

## 6. Conventions & Config

### Configuration (`config/setup.sample.php` → `config/setup.php` git-ignored)
```php
$APP_CONFIG = [
    'db_host/db_username/db_password/db_name/db_socket',
    'abs_url', 'organization_name', 'organization_short_name',
    'plan' => 'PRO | WEBSITE', 'timezone' => 'Asia/Kathmandu', 'base_currency' => 'NPR',
    'country_code' => '+977', 'debug' => true, 'session_lifetime_seconds' => 28800,
    'upload_max_bytes' => 10485760, 'pagination' => 50, 'server_path', 'app_encryption_key'
];
```
- Any key auto-exposed as constant: `snake_key → UPPER_SNAKE_KEY` (e.g. `organization_short_name → ORGANIZATION_SHORT_NAME`).
- Accessed via `config('key', $default)`.

### Coding Conventions
- PSR-12-ish style (no enforced formatter — manual).
- SQL identifiers backtick-quoted in `Database` methods; table `Database::insert` auto-backticks cols.
- All DB access through `Database::instance()` (`$objQuery` global set in bootstrap).
- PRG (Post-Redirect-Get) via `redirect()` on every form write.
- Audit logging on mutations (`auditLog('module','action',…)`).
- `.htaccess` route-sanitized: clean URLs `/admin/<module>[/<page>]`.

---

## 7. Documentation (docs/)

| Doc | Coverage |
|---|---|
| `PRD.md` (339 lines) | Purpose, roles, permission model, user stories w/ acceptance IDs (X-01…X-10, AC-AUTH-01.x, AC-RPT-01.x) |
| `SYSTEM_MODULES.md` (402 lines) | Exhaustive module catalog + all features (public + OMS) |
| `SB_TECH_SYSTEM_ANALYSIS.md` (477 lines) | Architecture, flows, data model, sequence diagrams |
| `PRODUCT_REVIEW.md` (117 lines) | Feature-by-feature gaps & review |

---

## 8. Onboarding / Running

1. **Install:** `composer install` (PHPUnit), copy `config/setup.sample.php → config/setup.php`, set DB creds, run `php artisan migrate`.
2. **Admin:** `/admin/login.php`; seed admin user via migration seed (`seed-table-users_login`).
3. **Tests:** `composer test` (or `vendor/bin/phpunit tests/`).
4. **Smoke test:** `scripts/smoke_test.sh`.

### Key File References

| Concern | Files |
|---|---|
| Bootstrap | `config/bootstrap.php`, `config/setup.php` |
| DB | `classes/Database.php`, `database/migration/migrations.php` |
| Auth/RBAC | `classes/Auth.php`, `admin/loginOperation.php` |
| Routing | `admin/includes/route.php`, `admin/includes/varriables.php` |
| Front controllers | `index.php`, `admin/show_page.php`, `admin/operation.php`, `admin/ajax.php` |
| Module pages | `admin/modules/*/home.php` (+ `operation/` writes) |
| Public theme | `website/includes/header.php`, `assets/css/site.css`, `assets/js/theme-switcher.js` |
| Admin theme | `admin/includes/head.php`, `assets/css/admin.css` + `adminlte-overrides.css` |
| Business logic | `functions/{hr,accounting,inventory,office,documents}.php` |
| CLI migrations | `artisan` |
| Tests | `tests/BusinessLogicTest.php`, `tests/bootstrap.php` |
