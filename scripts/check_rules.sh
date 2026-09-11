#!/usr/bin/env bash
#
# SB-Tech — mechanical enforcement of docs/RULES.md.
#
# Blocking checks mirror the rules that the codebase already satisfies.
# Warning-only checks surface legacy debt without blocking (tracked for
# cleanup separately). Run via `composer lint` or `make lint`.
#
# Usage: scripts/check_rules.sh [--warn-only]

set -uo pipefail
cd "$(dirname "$0")/.."

FAILURES=0
ALLOW_DIE="scripts/allowlists/die.txt"
ALLOW_CAMEL="scripts/allowlists/camelcase-functions.txt"

grep_php() {
    # grep the repo for a pattern, excluding vendor and this script.
    grep -rnE "$1" --include='*.php' . 2>/dev/null \
        | grep -v '^\./vendor/' \
        | grep -v '^\./scripts/check_rules.sh'
}

fail() { echo "  ✗ FAIL: $1"; FAILURES=$((FAILURES + 1)); }
pass() { echo "  ✓ $1"; }
warn() { echo "  ⚠ WARN: $1"; }

echo "== SB-Tech rules check (docs/RULES.md) =="

# ---- Rule 1.1: One Database Access Point ---------------------------------
echo "-- Rule 1.1: Database access only via classes/Database.php"

hits=$(grep_php 'new\s+(mysqli|PDO)\s*\(' | grep -vE '^\./(classes/Database\.php|scripts/(run_migrations_test\.php|check_schema_docs\.php))')
if [ -n "$hits" ]; then
    fail "raw mysqli/PDO construction outside Database.php:"
    echo "$hits" | head -5 | sed 's/^/      /'
else
    pass "no raw mysqli/PDO outside the allowed connection point"
fi

hits=$(grep_php '\bmysqli_(query|real_escape_string|prepare|fetch_|store_result|num_rows|error|insert_id)' \
        | grep -vE '^\./(classes/Database\.php)')
if [ -n "$hits" ]; then
    fail "mysqli_* called outside Database.php:"
    echo "$hits" | head -5 | sed 's/^/      /'
else
    pass "no mysqli_* calls outside Database.php"
fi

hits=$(grep_php '\bmysql_(connect|query|fetch_|select_db|error|real_escape_string|num_rows)')
if [ -n "$hits" ]; then
    fail "legacy mysql_* extension usage:"
    echo "$hits" | head -5 | sed 's/^/      /'
else
    pass "no legacy mysql_* calls"
fi

# ---- Rule 1.1: No die() in library code -----------------------------------
echo "-- Rule 1.1: no die() in classes/ or functions/ (exceptions allowlisted)"

hits=$(grep_php '\bdie\s*\(' \
        | grep -E '^\./(classes|functions)/' \
        | grep -vE '^[^:]+:[0-9]+:[[:space:]]*//' \
        | awk -F: '{print $1}' \
        | sort -u \
        | grep -vF -f "$ALLOW_DIE" 2>/dev/null)
if [ -n "$hits" ]; then
    fail "die() in library code — throw RuntimeException instead:"
    echo "$hits" | head -5 | sed 's/^/      /'
    echo "      (legacy sites deliberately allowlisted in $ALLOW_DIE —"
    echo "       do not add new entries casually)"
else
    pass "no non-allowlisted die() in library code"
fi

# ---- Rule 2.3: No blind include of superglobals ---------------------------
echo "-- Rule ROUTE-01: no include of request superglobals"

hits=$(grep_php '(include|require)(_once)?\s*\(?\s*\$_(GET|POST|REQUEST|COOKIE)')
if [ -n "$hits" ]; then
    fail "dynamic include/require of superglobals:"
    echo "$hits" | head -5 | sed 's/^/      /'
else
    pass "no dynamic includes from superglobals"
fi

# ---- Rule 3: snake_case for functions in functions/ -----------------------
echo "-- Rule 3: new functions in functions/ must be snake_case"

found=$(grep -rhoE '^\s*function\s+[a-zA-Z0-9_]+' functions/*.php 2>/dev/null | awk '{print $2}' | grep -E '^[a-z]+[A-Z]' | sort -u)
if [ -z "$found" ]; then
    pass "no camelCase functions in functions/"
else
    new_hits=$(comm -23 <(echo "$found" | grep -v '^$') <(grep -v '^#' "$ALLOW_CAMEL" | grep -v '^$' | sort -u))
    if [ -n "$new_hits" ]; then
        fail "camelCase functions not in the legacy allowlist — name new functions snake_case:"
        echo "$new_hits" | head -5 | sed 's/^/      /'
    else
        pass "camelCase functions all belong to the documented legacy set"
    fi
fi

# ---- Warning-only: legacy output-escaping debt -----------------------------
echo "-- XSS-01 (warning-only, legacy debt tracked separately)"

unescaped=$(grep -rnE '<\?=\s*\$' --include='*.php' admin website *.php 2>/dev/null \
    | grep -v '^\./vendor/' | wc -l)
warn "$unescaped short-echo tags may bypass e() — new code must use <?= e(\$x) ?>. \
Legacy count should trend DOWN; see RULES.md §2.1."

echo
if [ "$FAILURES" -gt 0 ]; then
    echo "rules check: ${FAILURES} failing check(s)" >&2
    exit 1
fi
echo "rules check: all blocking checks passed"
exit 0
