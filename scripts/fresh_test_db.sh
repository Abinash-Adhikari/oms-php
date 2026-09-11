#!/usr/bin/env bash
#
# Build a disposable test database by replaying ALL migrations from an empty
# DB (same strategy as scripts/run_migrations_test.php). This is self-contained
# and always consistent: migrations run in registry order, so seed migrations
# find their parent rows (docs/RULES.md DB-06).
#
#   make fresh-db
#
# database/schema.sql is NOT used here — it is a schema *reference* (docs
# parity check, LLM context). Rebuild test DBs from migrations, not the dump.
#
# Env overrides: DB_HOST, DB_PORT, DB_USER, DB_PASS, DB_NAME (default
# sb_tech_test).

set -euo pipefail
cd "$(dirname "$0")/.."

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USER:-admin}"
DB_PASS="${DB_PASS:-admin}"
DB_NAME="${DB_NAME:-sb_tech_test}"

echo "Rebuilding test DB '${DB_NAME}' on ${DB_HOST}:${DB_PORT} from migrations"

mysql --host="${DB_HOST}" --port="${DB_PORT}" \
    --user="${DB_USER}" ${DB_PASS:+--password="${DB_PASS}"} <<SQL
DROP DATABASE IF EXISTS \`${DB_NAME}\`;
CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
SQL

# Point artisan at the disposable DB, not the one in config/setup.php.
ARTISAN_DB_HOST="${DB_HOST}" ARTISAN_DB_NAME="${DB_NAME}" \
ARTISAN_DB_USER="${DB_USER}" ARTISAN_DB_PASS="${DB_PASS}" \
    php artisan migrate

echo "Done: ${DB_NAME} is ready."
