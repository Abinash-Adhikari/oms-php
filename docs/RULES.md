# SB-Tech — Engineering Rules & Conventions

> **Author:** Principal Engineer (20-yr full-stack veteran)
> **Status:** Mandatory. Every pull request is measured against these.
> **Scope:** all PHP, CSS, and JS in the repo.

These are not suggestions. They encode the lessons learned from the
smart-school fork and from 20 years of shipping software. **Violate a rule,
and PR review will reject.**

---

## 1. The Three Sacred Rules

### 1.1 Rule: One Database Access Point

```
┌─ FORBIDDEN ───────────────────────────────────────┐
│  $mysqli = new mysqli(...);                        │
│  $pdo    = new PDO(...);                           │
│  mysqli_query($objQuery, $sql . $userInput);       │
│  $db->select("SELECT * WHERE id = $id");          │
└───────────────────────────────────────────────────┘
┌─ MANDATORY ───────────────────────────────────────┐
│  $db = Database::instance();                       │
│  $db->select($sql, [params]);                      │
│  $db->insert('tbl_x', $assoc);                     │
│  $db->transaction(fn($db) => ...);                 │
└───────────────────────────────────────────────────┘
```

- `Database.php` is the **only** class that touches mysqli. Period.
- No PDO, no `mysql_*` legacy, no raw `mysqli_*` in page code.
- Every query goes through `prepare()` which **always** `bind_param`s.
- Table names in `Database::insert/update` are backtick-quoted automatically.
- Never `die()` in a library function. Throw `RuntimeException`. Only
  `database connection failed` (in bootstrap) may `die()`.

**Why:** SQL injection, the #1 cause of rewrites in the last 20 years, is
eliminated at the boundary. If a string-concatenated query ships, it *will*
be exploited.

### 1.2 Rule: Pure Functions for Business Logic

A function in `functions/*.php` is **pure** if:
1. Its output depends **only** on its arguments (no global state, no `$_SESSION`,
   no `date()` without a passed timezone, no `Database::instance()`).
2. It has **no side effects** (no `echo`, no `header()`, no `session_write`).

| Do | Don't |
|---|---|
| `function computeAttendanceMetrics(?string $in, ?string $out, ?string $cfgIn, ?string $cfgOut): array` | `function computeAttendanceMetrics(int $userId, string $date)` |
| Tests call it with literal strings | Tests need a DB fixture |

**Exception:** Functions explicitly marked `/** db-dependent */` in their
docblock (e.g. `countLeaveDays` which needs holiday data) must guard their
DB calls in `try/catch` so a missing table never produces a 500.

**Why:** Pure logic is the only logic we can afford to test without a
database. Untested business logic is a liability.

### 1.3 Rule: PRG on Every Mutation

```php
// operation handler
$db->transaction(function ($db) use ($post) {
    $db->insert('tbl_x', $clean);
    auditLog('module', 'create', 'x', $id, null, $clean);
});
setFlash('success', 'Created.');
redirect(pageUrl('module', 'page'));
exit;
```

- **POST → validate → transaction → redirect → GET renders.**
- `redirect()` is the **last** statement; `exit` follows it.
- Never render HTML after a `header('Location:')` call — use the
  `redirect()` helper which falls back to `<meta refresh>` if headers
  were already sent.

## 2. Security Rules (XSS-01 through AUTH-09)

### 2.1 Output Escaping (XSS-01)
- **Every** user-derived value is wrapped in `e()` at the call site:
  `<?= e($row['title']) ?>`, never `<?= $row['title'] ?>`.
- The `e()` function wraps `htmlspecialchars($v, ENT_QUOTES|UTF-8)`.
- Exception: rich HTML columns from the CMS are passed through
  `sanitizeEmbed()` (iframe whitelist) — never raw.

### 2.2 CSRF Protection (CSRF-01)
- Every `<form method="POST">` **must** include `<?= csrfField() ?>`.
- Every POST handler **must** call `verifyCsrf()` as its **first** statement
  after `Auth::check()`.
- API endpoints accept the token via `X-CSRF-TOKEN` header **or**
  `$_POST['csrf_token']`.
- CSRF tokens are per-session, 32 bytes, never rotated per-request
  (so multi-tab forms don't break).

### 2.3 Route Hardening (ROUTE-01)
- Module/page names are reduced via `preg_replace('/[^a-zA-Z0-9_-]/', '', …)`
  in `route.php`. No path, no `..`, no encoded bytes can survive.
- Includes always check `file_exists()` before `include` — never a blind
  `include($_GET['page'])`.

### 2.4 Upload Safety (UPLOAD-01)
- `validateUpload()`: extension whitelist, 10 MB cap from config.
- `storeUpload()`: sanitizes module path, random `date('Ymd_His').'_'.bin2hex(6)`
  filename, `move_uploaded_file()` (never `copy()`).
- Allowed types: `jpg, jpeg, png, pdf, doc, docx, xls, xlsx, pptx, txt` —
  no `php`, no `js`, no `html`/`svg` (XSS vectors).

### 2.5 Auth Hardening (AUTH-01…AUTH-09)
- AUTH-01: bcrypt for all new passwords (cost 12); legacy sha512 auto-upgraded
  on next login.
- AUTH-02: `session_regenerate_id(true)` on login success.
- AUTH-03: IP allow-list from `tbl_office_profiles.allow_ips`; localhost always
  allowed; `*` = allow-all.
- AUTH-04: Lockout enforced by `RateLimiter` (file-based, 5 failures → 60s).
- AUTH-05: Failed login returns generic "Invalid UserId or Password" (no
  oracle — attacker can't distinguish user-exists from wrong-password).

## 3. Naming Conventions

| Artifact | Convention | Example |
|---|---|---|
| PHP functions | `snake_case` | `computeAttendanceMetrics` ❌ → `compute_attendance_metrics` ✅ |
| PHP classes | `PascalCase` | `Database`, `Auth`, `RateLimiter` |
| PHP constants | `UPPER_SNAKE` | `PLAN`, `ORGANIZATION_NAME` |
| Config keys | `snake_case` | `db_host`, `session_lifetime_seconds` |
| DB tables | `tbl_snake_case` | `tbl_users_login`, `tbl_staff_attendances` |
| DB columns | `snake_case` | `fullname`, `created_at`, `is_active` |
| Admin module folder | `snake_case` | `staff_management/`, `office_setup/` |
| Migration files | `create-table-foo` / `seed-table-foo` / `add-index-foo` | matches `migrations.php` registry |

> Real note: the codebase **already** has `camelCase` functions in places
> (`computeAttendanceMetrics`, `bsMonthName`, `formatMoney`). These pre-date
> the rules doc. New code **must** use `snake_case`. Do not refactor the old
> names — it breaks callers — but never introduce new camelCase functions.

## 4. File & Folder Conventions

### 4.1 Admin Module Structure (MANDATORY TEMPLATE)
```
admin/modules/<module>/
├── home.php              # default landing (when ?page omitted)
├── <page>.php            # display view (forms, tables, lists)
├── operation/
│   ├── <page>.php        # write handler (validate→transaction→redirect)
│   └── _partials.php     # shared form fragments (optional)
├── includes/
│   └── <partial>.php     # view-only partials
└── api/                  # module-specific JSON (rare)
```

### 4.2 Public Page Structure
- **One file per page** at the repo root: `index.php`, `about.php`, etc.
- Each starts with `require __DIR__ . '/website/includes/site.php';`.
- HTML goes in `website/includes/header.php` (top) → body → `footer.php` (bottom).

### 4.3 Function Files
- `functions/` is for **pure or db-guarded helpers only**.
- A function belongs here if two+ modules call it, or if a unit test targets it.
- Each file = one domain: `hr.php`, `accounting.php`, `inventory.php`,
  `office.php`, `documents.php`, `helpers.php` (cross-cutting).

## 5. Money Rules (ACCOUNTS-01…05)

- ACCOUNTS-01: Store as `DECIMAL(18,4)`. Never `FLOAT`, never `DOUBLE`.
- ACCOUNTS-02: Compare with tolerance `≤ 0.01` (see `testTinyImbalanceWithinTolerance`).
- ACCOUNTS-03: Debit/credit must balance — enforced by
  `accountingParseVoucherLines()` which returns `['ok' => false, 'error' => …]`.
- ACCOUNTS-04: Every ledger mutation is wrapped in `Database::transaction()`.
- ACCOUNTS-05: Negative amounts are rejected at the validator
  (`testNegativeAmountBlocked`).

## 6. Date & Time Rules

- DT-01: Global timezone set to `Asia/Kathmandu` (configurable) in bootstrap.
- DT-02: Store all DB timestamps as `Y-m-d H:i:s` in the server timezone.
- DT-03: The BS (Vikram Samvat) calendar is **display-only** — `adToBs()`
  returns `null` if `tbl_calendar` is empty; never silently fall back to AD.
- DT-04: For durations/minutes, pass `int` minutes (never `DateTime` spans) —
  see `formatMinutes(150)` → "2 hr 30 min".

## 7. PRG & Redirect Rules

- PRG-01: All form POSTs end with `redirect()` + `exit`.
- PRG-02: After redirect, query strings are **re-parsed** via
  `pageUrl('module','sub')` — no hand-built URLs.
- PRG-03: `redirect()` accepts only same-origin URLs — enforce
  `parse_url($url, PHP_URL_HOST)` is empty or matches `config('abs_url')`.

## 8. Testing Rules

- TEST-01: Pure functions in `functions/` **must** have unit tests.
- TEST-02: Tests live in `tests/` and extend `PHPUnit\Framework\TestCase`.
- TEST-03: DB-dependent test branches use `$this->markTestSkipped()` when
  `Database::instance()` fails — never fatal.
- TEST-04: Run `composer test` before every merge. Target: 0 failures.
- TEST-05: New business-logic functions ship with ≥2 tests (happy + edge).

## 9. Git Hygiene

- Every commit references an ID from `docs/PRD.md` ("Implement AC-AUTH-03.1:
  IP allow-list").
- No secrets in source. `config/setup.php` is git-ignored. `app_encryption_key`
  rotates via env var injection in CI (not checked in).
- Migrations **append only** to `migrations.php` — never insert in the middle.

## 10. Deployment Rules

- DEPLOY-01: `php artisan migrate` runs **before** the code is swapped in
  (schema ahead of code).
- DEPLOY-02: `.htaccess` is the router — no `mod_rewrite` beyond what's
  committed. Test clean URLs in `scripts/smoke_test.sh` after deploy.
- DEPLOY-03: Assets load from CDNs (Bootstrap, Font Awesome, Google Fonts) —
  offline fallback: `assets/css/` and `assets/js/` contain local mirrors for
  air-gapped installs.
- DEPLOY-04: `php artisan status` before, `php artisan rollback` available if
  migration fails, `php artisan migrate` confirmed.

## 11. Database Changes (MANDATORY)

**Every database schema change MUST go through the migration system.**
No direct `ALTER TABLE` in production. No skipping the registry.

### Migration Pattern

| Step | Action |
|------|--------|
| 1 | Create `database/migration/{type}-{table}-{description}.php` |
| 2 | Define `$query` array (forward SQL) and `$rollbackQuery` array (reverse SQL) |
| 3 | Append filename to `database/migration/migrations.php` (never insert in middle) |
| 4 | Run `php artisan migrate` to apply |

### File Naming Convention

```
create-table-{table_name}.php          — new table
alter-table-{table}-{description}.php   — add/modify column
add-index-{description}.php             — add index
seed-table-{table_name}.php             — seed data
```

### Example (alter-table)

```php
<?php
// database/migration/alter-table-cms_services-add-slug.php
$query = [
    "ALTER TABLE `tbl_cms_services`
     ADD COLUMN `slug` VARCHAR(255) NULL AFTER `title`;",
    "ALTER TABLE `tbl_cms_services`
     ADD UNIQUE KEY `idx_services_slug` (`slug`);",
];

$rollbackQuery = [
    "ALTER TABLE `tbl_cms_services` DROP KEY `idx_services_slug`;",
    "ALTER TABLE `tbl_cms_services` DROP COLUMN `slug`;",
];
```

### Rules

- DB-01: Always define `$rollbackQuery` — every migration must be reversible.
- DB-02: Append to `migrations.php` — never insert in the middle.
- DB-03: Test rollback: `php artisan rollback` must succeed.
- DB-04: Column adds use `AFTER` to maintain logical order.
- DB-05: New columns with defaults: backfill existing rows in the same migration.
- DB-06: FK order: parent table migration must come before child table.

## 12. The "When in Doubt" Matrix

| If you're writing… | Do this |

| If you're writing… | Do this |
|---|---|
| A new DB query | `$db->select($sql, [...])` — never string-concat |
| A math/validation function | Put it in `functions/`, make it pure, add a test |
| A page that shows a form | Include `csrfField()`, use `POST`, route action via `operation/` |
| A page that writes data | Wrap in `$db->transaction()`, `auditLog()`, `setFlash()`, `redirect()` |
| A redirect target | Use `pageUrl('module', 'page')` |
| Output of any user value | `<?= e($val) ?>` |
| A new color | Add it as a CSS `--token` in `theme-variables.css`, not a hex inline |
| A new table | Add a migration; register in `migrations.php`; never hand-run DDL |
| A new module | Copy the template structure; add to `varriables.php` nav; stub `home.php` |

---

*These rules exist because each one was written in blood — a production
outage, a security incident, a 5 AM pager. Respect them, and the system will
outlive us all.*