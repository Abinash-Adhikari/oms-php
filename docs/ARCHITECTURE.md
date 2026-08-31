# SB-Tech — Architecture Decision Record

> **Author:** Principal Engineer (20-yr full-stack veteran)
> **Status:** Reference Architecture
> **Context version:** snapshot 2026-08-26

---

## 1. Vision

One deployable artifact. Two faces — a public marketing website and a staff-only
Office Management System (OMS) — sharing a single MySQL database and a single
login domain. No framework, no external services, no vendor lock-in beyond MySQL.
The constraint: must run on a basic LAMP host (`$7/mo shared hosting acceptable),
with every non-core dependency (Bootstrap, Font Awesome, AdminLTE) served from
a CDN and swappable to local at deployment time.

## 2. Architectural Style — "Thin Framework Monolith"

We deliberately rejected a micro-services split. The bounded contexts (HR, Finance,
Inventory, Leads) are coupled by shared staff/user data and atomic transactions
(e.g. an expense claim → ledger posting). A monolith gives us:

- **Relational atomicity** (MySQL transactions across modules) for free.
- **Single deploy** (no network between modules, no OAuth dance between contexts).
- **Operational simplicity** on a $7 host.

What we *did* extract, hard, are the **layers**:

```
┌──────────────────────────────────────────────────────────────────┐
│  Presentation Layer                                              │
│    admin/show_page.php  ─── route.php ── Auth::can() ── module  │
│    website (root *.php) ─── site.php ─── siteRows()             │
├──────────────────────────────────────────────────────────────────┤
│  Application Layer (thin)                                        │
│    admin/operation/*.php  — form handlers, PRG pattern            │
│    admin/api/*.php        — JSON endpoints (notifications, SSE)  │
├──┬───────────────────────────────────────────────────────────────┤
│  Business Logic Layer  (PURE — fully unit testable)              │
│    functions/{hr,accounting,inventory,office,documents}.php      │
│    ┌─ no I/O, no singletons, deterministic inputs → outputs     │
├──┼───────────────────────────────────────────────────────────────┤
│  Domain Layer                                                     │
│    classes/{Auth,CommunicationService,RateLimiter}.php           │
│    ┌─ Auth: cached request-scoped user, bcrypt w/ auto-upgrade    │
│    ┌─ CommSvc: SMTP/SMS/campaign engine, AES-encrypted settings  │
│    ┌─ RateLimiter: token-bucket w/ file store                    │
├──┼───────────────────────────────────────────────────────────────┤
│  Data Access Layer                                                │
│    classes/Database.php  — ONLY mysqli, ALL prepared statements   │
└──────────────────────────────────────────────────────────────────┘
```

### Decision: Single DB Access Point (Database.java-class singleton)

> *"Never let a developer reach for mysqli directly in page code."*

**Rationale:** The reference fork (`smart-school`) had dual mysqli *and* PDO code
paths — a known source of SQLi drift. Here there is **one** class.
`Database::instance()` is instantiated once per request in bootstrap, stored as `$objQuery`.
Every public method (`select`, `selectOne`, `insert`, `update`, `delete`, `transaction`)
uses `mysqli_stmt::bind_param` with inferred types. No string-concatenated
user input ever reaches a query. This is the single strongest security guarantee
in the system.

**Trade-off:** No ORM means hand-written SQL everywhere — but SQL is the team's
lingua franca, and query intent is far clearer than `User::with('roles')->get()`.

### Decision: Pure Functions for Business Logic

Everything in `functions/*.php` is written to be **free of side effects**:
no `$_SESSION`, no `Database::instance()`, no `date()` on the global clock
(timezones are passed in or derived from config constants). Why?

1. **Testability** — `BusinessLogicTest.php` (21 tests) covers attendance math,
   leave day counting, voucher balancing without a DB connection.
2. **Reasoning** — `computeAttendanceMetrics('09:15:00','17:00:00','09:00:00','17:00:00')`
   returns the same array every time. No "depends on current session" surprise.
3. **Future porting** — these functions could be dropped into a Laravel job or a
   Go microservice with zero change.

### Decision: Custom Migration Runner (`artisan`)

A Laravel app would use `php artisan migrate`. We don't have Laravel. We have a
**60-line CLI script** that reads an explicit ordered array (`$files` in
`migrations.php`), compares against `tbl_migrations`, and upserts. The registry is
**explicitly ordered** (foundation → office → staff → HR → … → docs), not
auto-discovered by filename sort. This prevents the classic "alphabetical
migration breaks FK order" bug.

**Why not migrate in on autoload?** Because `config/setup.php` may point at a
DB that doesn't exist yet. `APP_BOOTSTRAP_SKIP_DB` is defined for the CLI path
so bootstrap doesn't try to connect before the DB is created.

## 3. Request Lifecycle (3 faces)

### 3.1 Public website request (`index.php`)

```
index.php                          ─┐
  └── require website/includes/site.php
                                        ├── require ../../config/setup.php
                                        │       ├── bootstrap.php (session, DB, helpers)
                                        │       └── auto-constants (UPPER_SNAKE)
                                        ├── $db = Database::instance()
                                        ├── siteSetup()          ← cached CMS row
                                        ├── $sitePage = basename($_SERVER['PHP_SELF'])
                                        └── exit
  └── $heroes = siteRows('tbl_cms_hero', …, cols)  ← whitelisted SQL
  └── include header.php (HTML shell)               ─┐
                                                      └── render → browser
  └── ... content ...
  └── include footer.php (footer.php = the one above) ───┘
```

**Security:** `siteRows()` validates `table`/`columns`/`orderBy` against a strict
regex whitelist — all call-sites use hardcoded literals, so there is literally
no path for user input to reach this function. This is defense-in-depth: even
if a junior dev later refactors to accept a param, the guard fires.

### 3.2 Admin page request (`show_page.php`)

```
admin/show_page.php
  └── include ../../config/setup.php  (full bootstrap + DB)
  └── if (!Auth::check()) → redirect('login.php')        [AUTH gate 1]
  └── include includes/route.php
  │       ├── $routeModule = preg_replace('/[^a-zA-Z0-9_-]/','', $_GET['module'])
  │       ├── $page        = preg_replace('/[^a-zA-Z0-9_-]/','', $_GET['page'])
  │       └── $permissionModule = strtolower($routeModule === 'home' ? 'dashboard' : …)
  └── include includes/varriables.php  (nav map: $navBars, $subNavBars, $icons)
  └── include includes/head.php        (AdminLTE + theme boot + CSRF meta)
  └── include includes/topNavBar.php / mainSideBar.php
  └── if (!Auth::can($permissionModule, $page)) → " Access denied"  [RBAC gate 2]
  └── if (file_exists modules/<moduleFs>/<page>.php)) → include
  └── else → "Page not installed yet" (graceful stub)
  └── include includes/footer.php
```

Two-stage defense: **AUTH gate** (session valid) → **RBAC gate** (module+submodule).
Notice the stub fallback: a module listed in `varriables.php` but not yet
implemented renders a polite "Page not installed" rather than a 404 or fatal.
This is a **product decision** — the nav shows the module so users know it's
coming, and the dev isn't blocked.

### 3.3 Admin form POST / API (`operation.php`, `api/*.php`)

```
admin/operation.php (or api/foo.php)
  └── require ../../config/setup.php
  └── POST only  → verifyCsrf()    [CSRF gate]
  └── Auth::check() + Auth::can()   [auth gate]
  └── $_POST inputs validated (types, non-empty, whitelisted enums)
  └── $db->transaction(fn($db) => [ insert … ; auditLog … ])
  └── setFlash('success|error', msg) → redirect(pageUrl(...))   [PRG]
```

**All mutations are wrapped in `$db->transaction()`** — not for the happy path,
but so a failure mid-way rolls back the audit log, the insert, *and* the side
effect (e.g. ledger entries). Rollback is automatic via `try/catch → rollback`.

**SSE notifications:** `admin/api/sse-notifications.php` streams real-time
events on a chunked response. It is deliberately the only long-lived endpoint.

## 4. Cross-Cutting Concerns

### 4.1 Authorization — RBAC 3-Level

| Level | Method | Check |
|---|---|---|
| Module | `Auth::hasModule($mod)` | user's `permitted_modules` JSON list |
| Submodule | `Auth::hasSubmodule($mod, $sub)` | `permitted_submodules[module]` list |
| Action | `Auth::hasSpecial($key)` | `special_permission` list |

Super Admin: `permitted_modules = 'All'` OR role `Super Admin`. The check is
**live** (DB read every request via `Auth::user()`, cached only within the
request) — permission changes take effect on the *next request*, no cache-bust
needed. `clearUserCache()` exists for when you mutate permissions mid-request.

### 4.2 Money & Precision

- Storage: `DECIMAL(18,4)` everywhere (X-07 in PRD).
- Application: floats internally (`(float)` cast in `accountingParseVoucherLines`).
- Comparison tolerance: **`abs($diff) <= 0.01`** (see
  `testTinyImbalanceWithinTolerance`). This prevents "penny rounding" from
  blocking a valid voucher.
- Display: `formatMoney()` → `number_format($amt, 2)`.

**Hard rule:** never store money as `FLOAT`. Never compare floats with `==`.
These two rules cover 95% of accounting bugs.

### 4.3 Date/Time & the BS Calendar

- Global: `date_default_timezone_set('Asia/Kathmandu')` (configurable).
- BS calendar lives in `tbl_calendar` (eng-date ↔ nepali-year mapping).
- `bsCalendarAvailable()` caches a `SELECT 1` once per request.
- `adToBs($adDate)` returns `null` if the calendar isn't seeded — all callers
  must null-check (no silent fallback to AD).

This is a **Nepal-specific** decision baked into the domain — the calendar
conversion is treated like localization, not a feature flag.

### 4.4 Error Handling Philosophy

| Context | Policy |
|---|---|
| DB connection failure | `die('Database connection failed')` (debug mode shows error) — hard fail, no retry storm |
| Query failure | `throw new RuntimeException` — never silently return `[]` |
| Audit log failure | caught, silently ignored — logging must never break the workflow |
| Rate limiter | file-based, degrades gracefully (no DB lock risk) |
| Login failed | records IP + username to `tbl_login_attempts`, never reveals which part failed ("Invalid UserId or Password") |

## 5. Why Not …

### 5.1 Why not Laravel?
Budget/hosting constraint. This runs on a $7 LAMP host. A 60-line `artisan`
script replaces 80KB of `Illuminate\Database`. Every byte of framework is a
byte that needs a security patch.

### 5.2 Why not PDO?
mysqli's prepared-statement API is simpler and the host ships with it always
enabled. The **single-API rule** (mysqli only) is an explicit anti-drift
decision documented at the top of `Database.php`.

### 5.3 Why not JWT / stateless auth?
Role changes must propagate instantly. Stateless JWT invalidates only on expiry
(or a revocation list we'd have to build). Stateful sessions give us
immediate propagation + simpler CSRF. The cost — no horizontal scaling without
sticky sessions — is irrelevant at this scale.

### 5.4 Why not a rich JS frontend?
The public site uses vanilla JS (theme switcher, scroll-reveal). The admin
panel is server-rendered HTML. Reason: one deploy, zero build step, works on
IE11 if it has to. A React SPA is a v2 ambition, not a v1 requirement.

## 6. Evolution History

| Version | What changed | Why |
|---|---|---|
| v0 (reference `smart-school`) | Dual mysqli+PDO, string-concatenated SQL | — (fork source) |
| v1 | Single `Database.php`, prepared statements only | Kill SQLi drift |
| v1 | Extract pure functions out of page scripts | Testability |
| v1 | bcrypt + auto-upgrade | Move off sha512+salt |
| v1 | `siteRows()` whitelist | Kill injection vector |

## 7. Quality Attributes

| Attribute | Target | How measured |
|---|---|---|
| Security | SAQ-A equivalent | CSRF on all POST, RBAC gates, no string SQL |
| Observability | Minimal | `auditLog` table + `tbl_login_attempts` |
| Testability | 90% of business-logic functions covered | `phpunit tests/` — 21 tests |
| Deployability | One `tar`/unzip | `.htaccess` handles routing, no build |
| Modifiability | Junior dev adds a module in 2h | `show_page.php` stub pattern + `route.php` |
| Performance | First paint < 800ms | Server-rendered, CDN assets, no JS bundle |

---

*This document is the contract. When in doubt, make the change that keeps the
monolith thin, the business logic pure, and the single DB access point sacred.