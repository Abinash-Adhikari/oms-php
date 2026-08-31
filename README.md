# SB-Tech Office Management System

> A single-deployable PHP+MySQL modular monolith with two faces: a **public marketing website** and a **staff-only Office Management System (OMS)**, sharing one database and one login domain.

**Stack:** PHP 7.4+ · mysqli (prepared statements) · MySQL · AdminLTE 3 / Bootstrap 4 · vanilla JS

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

## Documentation

All project documentation lives in [`docs/`](docs/).

| Doc | What it covers |
|-----|---------------|
| [docs/](docs/) | Full documentation index — start here |
| [docs/OVERVIEW.md](docs/OVERVIEW.md) | Codebase overview, architecture, components |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Architecture decisions & design rationale |
| [docs/RULES.md](docs/RULES.md) | Engineering rules & conventions (mandatory) |
| [docs/DESIGN.md](docs/DESIGN.md) | Design system & UX specification |
| [docs/Schema.md](docs/Schema.md) | Database schema reference (84 tables) |
| [docs/PRD.md](docs/PRD.md) | Product Requirements Document |
| [docs/PED.md](docs/PED.md) | Pedigree, lineage & evolution history |

---

## License

Proprietary — SB-Tech. All rights reserved.
