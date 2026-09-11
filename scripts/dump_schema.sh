#!/usr/bin/env bash
#
# Dump the current schema (no data) from the dev database into
# database/schema.sql. This file is a schema *reference* artifact:
# consumed by scripts/check_schema_docs.php (docs/Schema.md parity) and as
# context for humans and AI agents. Test databases are NOT built from it —
# scripts/fresh_test_db.sh replays migrations instead (always consistent).
#
# Refresh deliberately when the schema changes.

set -euo pipefail
cd "$(dirname "$0")/.."

DB_NAME="${DB_NAME:-sb_tech}"
DB_USER="${DB_USER:-admin}"
DB_PASS="${DB_PASS:-admin}"

echo "Dumping schema of '${DB_NAME}' (no data) -> database/schema.sql"

# --no-data: structure only. --routines/--triggers included for completeness.
# --no-tablespaces: works without PROCESS privilege on MySQL 8 hosts.
mysqldump --no-data --routines --triggers --no-tablespaces \
    --user="${DB_USER}" ${DB_PASS:+--password="${DB_PASS}"} \
    "${DB_NAME}" > database/schema.sql

echo "Wrote $(grep -c 'CREATE TABLE' database/schema.sql) CREATE TABLE statements to database/schema.sql"
