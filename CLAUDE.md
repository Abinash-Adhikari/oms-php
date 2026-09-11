# AGENTS.md — Instructions for AI Coding Agents

Working in this repo? Read `docs/RULES.md` first — it is **mandatory** and every
change is measured against it. This file is only the short version.

## Project in one paragraph

PHP 8.1+ (developed & tested on 8.4) + MySQL **modular monolith**: a public marketing site (`*.php` at repo
root, rendered via `website/includes/`) plus a staff-only Office Management
System (`admin/`, AdminLTE 3). No framework. Routing is `.htaccess` +
`admin/includes/route.php`. DB access exclusively through `classes/Database.php`
(mysqli, prepared statements). Migrations via `php artisan migrate` (registry:
`database/migration/migrations.php`).

## Hard invariants (violations = rejected PR)

1. **One DB access point.** Only `Database::instance()` touches mysqli. Every
   query is `$db->select($sql, $params)` / `insert()` / `update()` with bound
   params. No string-concatenated SQL. No `die()` in library code — throw.
2. **Escape all output.** `<?= e($value) ?>` for every user-derived value.
3. **PRG on every mutation.** POST → `verifyCsrf()` → `$db->transaction()` →
   `setFlash()` → `redirect(pageUrl(...))` → `exit`. Forms include
   `<?= csrfField() ?>`.
4. **Pure functions in `functions/`** (no globals, no `$_SESSION`, no DB) so
   they are unit-testable. DB-dependent helpers guard with try/catch.
5. **New functions are `snake_case`** (legacy camelCase exists — use it, don't
   add to it). Classes `PascalCase`, constants `UPPER_SNAKE`, tables
   `tbl_snake_case`.
6. **Every schema change is a migration**: create
   `database/migration/{create-table|alter-table|add-index|seed-table}-{...}.php`
   with `$query` AND `$rollbackQuery`, then **append** the filename to
   `database/migration/migrations.php`. Never hand-run DDL.
7. **Module template**: `admin/modules/<module>/` needs `home.php`, page views,
   and `operation/<page>.php` write handlers (see RULES.md §4.1).
8. **Money** is `DECIMAL(18,4)`, compared with tolerance ≤ 0.01, ledger writes
   inside transactions. **Timezone** is `Asia/Kathmandu` (config).
9. **Secrets** live in `config/setup.php` (git-ignored). Never commit them.

## Commands

```bash
composer check      # tests + static analysis + rules linter  (run before every commit)
composer test       # PHPUnit (pure-function tests run without a DB)
composer stan       # PHPStan (baseline covers legacy code — don't loosen it)
composer lint       # mechanical check of RULES.md forbidden patterns
```

Docker-free local run needs MariaDB/MySQL + the credentials in
`config/setup.php` (copy from `config/setup.sample.php`).

## Verification expectations

- After **any** PHP edit: run `composer check` (or at minimum `composer stan`).
- New business-logic functions ship with ≥ 2 PHPUnit tests (happy + edge) —
  TEST-05.
- DB-dependent tests must `markTestSkipped()` when no DB is available — never
  fatal (TEST-03).
- If you add/remove a table or column, update `docs/Schema.md`;
  `scripts/check_schema_docs.php` verifies it stays in sync.

## Where to look

| Need | File |
|---|---|
| Rules & conventions (mandatory) | `docs/RULES.md` |
| Architecture & why | `docs/ARCHITECTURE.md` |
| DB schema reference | `docs/Schema.md` |
| Design system | `docs/DESIGN.md` |
| Requirements / acceptance criteria | `docs/PRD.md` |
| Reusable helpers | `functions/*.php` |
| Service classes | `classes/*.php` |
