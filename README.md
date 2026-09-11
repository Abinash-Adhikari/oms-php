# SB-Tech Office Management System

> A single-deployable PHP+MySQL modular monolith with two faces: a **public marketing website** and a **staff-only Office Management System (OMS)**, sharing one database and one login domain.

**Stack:** PHP 8.1+ (developed & tested on 8.4) · mysqli (prepared statements) · MySQL · AdminLTE 3 / Bootstrap 4 · vanilla JS

---

## Quick Start

```bash
composer install
cp config/setup.sample.php config/setup.php   # set DB credentials
php artisan migrate
```

- **Admin:** `/admin/login.php` (seeded Super Admin)
- **Tests:** `composer test`

---

## Development Workflow

One command verifies everything (tests + PHPStan + rules linter + schema docs):

```bash
composer check
```

| Command | What it does |
|--------|--------------|
| `composer test` | PHPUnit (uses `DB_HOST/DB_PORT/DB_USER/DB_PASS/DB_NAME` env overrides if set) |
| `composer stan` | PHPStan (level 5, baseline for legacy code — new code must be clean) |
| `composer lint` | Mechanical enforcement of docs/RULES.md (blocks: raw mysqli, `die()` in library code, dynamic includes, camelCase in new code; warns on legacy XSS debt) |
| `composer schema:check` | Fails if `docs/Schema.md` drifts from the live DB (98 tables) |
| `composer fix` | PHP-CS-Fixer (curated rules — zero churn in legacy files) |

### Docker

```bash
make up        # app on http://localhost:8080 + MariaDB, DB built by migrations
make fresh-db  # rebuild disposable test DB (sb_tech_test) from migrations — all 32 tests run, 0 skipped
make test      # run tests against it
```

### Git hooks (recommended)

```bash
make hooks     # pre-commit runs php -l + rules linter + PHPStan on staged files (read-only)
```

### CI

GitHub Actions (`.github/workflows/ci.yml`) runs `composer check` on every push/PR against a MariaDB service, with all tests enabled.

### AI/agent notes

`AGENTS.md` (root) indexes the hard invariants; `.agents/skills/new-module/` and `.agents/skills/new-migration/` scaffold the two most common change types per docs/RULES.md §4.1 and §11.

---

## Documentation

All project documentation lives in [`docs/`](docs/).

| Doc | What it covers |
|-----|---------------|
| [docs/](docs/) | Full documentation index — start here |
| [docs/OVERVIEW.md](docs/OVERVIEW.md) | Codebase overview, architecture, components |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Architecture decisions & design rationale |
| [docs/RULES.md](docs/RULES.md) | Engineering rules & conventions (mandatory) |
| [docs/DESIGN.md](docs/DESIGN.md) | Design system & UX specification |
| [docs/Schema.md](docs/Schema.md) | Database schema reference (98 tables — kept fresh by `composer schema:check`) |
| [docs/DOCKER.md](docs/DOCKER.md) | Docker stack reference (future use — not needed on shared hosting) |
| [docs/PHP-UPGRADE.md](docs/PHP-UPGRADE.md) | PHP 7.4 → 8.4 upgrade runbook |
| [docs/PRD.md](docs/PRD.md) | Product Requirements Document |
| [docs/PED.md](docs/PED.md) | Pedigree, lineage & evolution history |

---

## License

Proprietary — SB-Tech. All rights reserved.
