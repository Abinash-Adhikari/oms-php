# PHP Upgrade Runbook — 7.4 → 8.4

> **Status: executed locally on 2026-09-10.** All repo pins bumped, lock
> re-resolved, `composer check` green, 108/108 migrations replay, 32/32 tests
> pass on PHP 8.4. PHPUnit was also widened 9.6 → 11.5 the same day (zero
> test-code breakage; `phpunit.xml.dist` migrated to the 11.x schema, and
> classes/Database.php now converts the PHP 8.1+ always-throwing mysqli
> connect failure into a RuntimeException instead of die()).
> The only remaining step is **production**: switch the host
> to 8.4 (or host max) via cPanel MultiPHP Manager and smoke test.
> If the host tops out below 8.4, re-pin `config.platform.php` to that version
> and re-run `composer update`.
>
> **Why 8.4:** it is already installed on the dev machine (Herd Lite,
> PHP 8.4.1, all required extensions present), the app is verified working on
> it (32/32 tests green), and it receives security fixes until ~2028.
> Latest stable upstream is 8.5 — revisit after dompdf/phpword declare support.
>
> **Why leave 7.4:** EOL since Nov 2022 — zero security fixes.
>
> **Hosting note:** this project runs on shared cloud hosting today. The
> upgrade is only "done" when production runs 8.4 too — set it in cPanel
> ("MultiPHP Manager" → select domain → 8.4, or the highest the host offers).
> If the host tops out at 8.2/8.3, target that instead; the steps are identical.

## Where PHP versions live in this repo

| Location | Current | Target |
|----------|---------|--------|
| `composer.json` → `require.php` | `>=7.4` | `>=8.1` (raise the floor so 7.4 code can't sneak back) |
| `composer.json` → `config.platform.php` | `7.4.33` | `8.4.1` (must match **production**, not just dev) |
| `Dockerfile` | `php:8.2-apache` | `php:8.4-apache` |
| `.github/workflows/ci.yml` | `php-version: '8.3'` | `'8.4'` |

## Step-by-step

### 0. Pre-flight (already verified ✅)

```bash
php -v          # 8.4.1 on this machine
php -m          # mysqli, gd, zip, intl all present
composer test   # was 32/32 pass on 8.4 (8 skips are DB-credential skips, not PHP)
composer stan   # [OK] No errors on 8.4
```

### 1. Check what production can run

cPanel → MultiPHP Manager (or ask the host). Whatever the highest offered
version is, that is your real target. Set the domain to it **first** and smoke
test the app — shared hosts let you switch back instantly, so this is safe.

### 2. Re-resolve dependencies against the new platform

```bash
# composer.json: platform.php -> 8.4.1 (or the host's max, e.g. 8.2.x)
composer update
git add composer.json composer.lock
composer check
```

What changes in the lock: packages previously resolved under the 7.4 pin may
bump to newer minors. Constraint `^9.5` keeps PHPUnit on 9.x — moving to
PHPUnit 10/11 is an **optional, separate** commit (widen the constraint,
fix any test API changes). Don't mix it into the version switch.
> **Update 2026-09-10:** this optional step was done — see the status note above.

### 3. Sweep 8.x deprecations (PHPStan already flags most of these)

Quick greps for the classes of issues PHP 8.1–8.2 deprecated:

```bash
grep -rn 'utf8_encode\|utf8_decode' classes/ functions/ --include='*.php'  # deprecated 8.2, removed 9
grep -rn '\${[a-zA-Z_]' classes/ functions/ --include='*.php'              # string interpolation deprecated 8.2
grep -rnE '\$[a-zA-Z_]+->[a-zA-Z_]+ = ' classes/ | grep -v constructor     # dynamic properties (8.2 notice) — classes here are declared, low risk
```

One **behavioral** change to check in `classes/Database.php` (the single
connection point — this is why RULES §1.1 pays off): since PHP 8.1, mysqli
throws exceptions by default instead of returning `false`/silencing errors.
Any `if (!$result)` style error handling after queries needs a look.

### 4. Update the remaining version pins

```bash
# Dockerfile: FROM php:8.4-apache
# .github/workflows/ci.yml: php-version: '8.4'
git commit -m "Target PHP 8.4 across tooling"
```

### 5. Verify everywhere

```bash
composer check                 # full local gate
bash scripts/fresh_test_db.sh  # migrations replay on 8.4
make up && docker compose exec app composer check   # container parity (optional)
```

Then on production (shared hosting): smoke test login → leads → quotations →
PDF/document generation (dompdf + phpword are the most PHP-sensitive paths).

### 6. Rollback (if anything breaks in prod)

cPanel MultiPHP Manager → back to the previous version (instant), then
`git revert` the pin commit. Because `config.platform.php` follows production,
keep pin and prod in lockstep in the same commit.

## Done-when checklist

- [ ] Host PHP set to 8.4 (or host max) and app smoke-tested there
- [ ] `config.platform.php` = production version
- [ ] `require.php` floor raised (`>=8.1`)
- [ ] `composer update` clean, `composer check` green
- [ ] Dockerfile + CI updated (even though Docker is not used yet)
- [ ] mysqli error-handling reviewed in `classes/Database.php`
