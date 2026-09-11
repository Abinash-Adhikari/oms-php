# Docker Development Stack (Reference)

> **Status: NOT REQUIRED right now.** The app runs directly on shared cloud
> hosting. This document is the playbook for when you outgrow that — local
> parity, CI parity, or moving to a VPS/container platform. Nothing here needs
> to be done today.

Docker gives you a reproducible local environment that matches what the
tooling already expects: PHP + Apache with the exact extensions the app needs
(`mysqli`, `gd`, `zip`, `intl`), and a disposable MariaDB built purely from
migrations — no tribal knowledge.

## What ships in the repo (already committed)

| File | Purpose |
|------|---------|
| `Dockerfile` | `php:8.2-apache` + mysqli/gd/zip/intl, `.htaccess` AllowOverride enabled |
| `docker-compose.yml` | `app` (:8080) + `db` (mariadb:10.6, host port 3307) |
| `docker/setup.php` | Container-only config, mounted read-only over `config/setup.php` — host credentials never enter the image |
| `docker/apache.conf` | ServerName + sendfile off (dev niceties) |

## Quick start

```bash
make up            # build + start both services
# first boot only:
docker compose exec app composer install
docker compose exec app php artisan migrate    # schema + seeds (admin login)
```

App: http://localhost:8080/admin/login.php

## Daily use

```bash
docker compose exec app composer check   # full gate (test+stan+lint+schema)
make fresh-db                            # drop/rebuild sb_tech_test from migrations
docker compose exec app bash             # shell in
make down                                # stop
```

## Why migrations, not a schema dump

`docker-compose.yml` deliberately does **not** import `database/schema.sql`:
the dump has no seed rows (no admin login → app unusable), and `php artisan
migrate` replays all 108+ migrations in registry order, producing a complete,
seeded DB. `schema.sql` stays in the repo as a read-only reference artifact.

## When you eventually deploy with Docker

This stack is dev-shaped (bind mount, debug on, dev encryption key). Before
containerized production:

1. Bake vendor/ into the image (remove the bind mount, add `composer install`
   in the Dockerfile), pin an image tag, drop debug mode
2. Real `app_encryption_key`, real DB credentials via secrets/env
3. HTTPS termination in front (Caddy/Traefik/NGINX or your platform's LB)
4. Revisit `docker/setup.php` — it is a dev convenience, not a prod config
