<?php

/**
 * SB-Tech — schema docs parity check.
 *
 * Compares the live database (or CI database) against docs/Schema.md:
 *   1. Every real table must be mentioned in docs/Schema.md.
 *   2. The doc's declared table count must match reality.
 *
 * LLMs and new engineers rely on docs/Schema.md; if it rots, generated
 * code silently references tables/columns that don't exist. This keeps
 * the doc honest the same way tests keep code honest.
 *
 * Usage:
 *   composer schema:check            (uses config/setup.php / env overrides)
 *   php scripts/check_schema_docs.php --dump   also print missing tables
 *
 * DB target resolution mirrors tests/bootstrap.php: config/setup.php first,
 * then DB_HOST/DB_PORT/DB_USER/DB_PASS/DB_NAME env overrides.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(2);
}

error_reporting(E_ALL & ~E_DEPRECATED);
$appRoot = dirname(__DIR__);

// --- Resolve DB config (config/setup.php + env overrides) ------------------
$GLOBALS['APP_CONFIG'] = [];
$setupFile = $appRoot . '/config/setup.php';
if (file_exists($setupFile)) {
    // Swallow deprecation noise from legacy vendor files compiled during the
    // app bootstrap (thecodingmachine/safe on PHP 8.4); non-deprecations pass
    // through to PHP's normal handling.
    set_error_handler(static function (int $no, string $msg, string $file = '', int $line = 0): bool {
        return ($no === E_DEPRECATED || $no === E_USER_DEPRECATED);
    });
    require $setupFile;   // defines $APP_CONFIG then requires bootstrap.php
    restore_error_handler();
    // setup.php pulls in the full app bootstrap; neutralize globals it sets.
} else {
    // Minimal stand-in when no local config exists (CI containers).
    $GLOBALS['APP_CONFIG'] = [
        'db_host'     => getenv('DB_HOST') ?: '127.0.0.1',
        'db_port'     => getenv('DB_PORT') ?: '3306',
        'db_username' => getenv('DB_USER') ?: 'admin',
        'db_password' => getenv('DB_PASS') ?: 'admin',
        'db_name'     => getenv('DB_NAME') ?: 'sb_tech_test',
        'timezone'    => 'Asia/Kathmandu',
        'debug'       => false,
        'session_lifetime_seconds' => 28800,
        'upload_max_bytes' => 10485760,
        'pagination'  => 50,
        'server_path' => '',
        'organization_name' => 'SB-Tech',
    ];
}

foreach ([
    'DB_HOST' => 'db_host',
    'DB_PORT' => 'db_port',
    'DB_USER' => 'db_username',
    'DB_PASS' => 'db_password',
    'DB_NAME' => 'db_name',
] as $envKey => $cfgKey) {
    if (getenv($envKey) !== false) {
        $GLOBALS['APP_CONFIG'][$cfgKey] = getenv($envKey);
    }
}

$host = (string) ($GLOBALS['APP_CONFIG']['db_host'] ?? '127.0.0.1');
$port = (int) ($GLOBALS['APP_CONFIG']['db_port'] ?? 3306);
$user = (string) ($GLOBALS['APP_CONFIG']['db_username'] ?? 'admin');
$pass = (string) ($GLOBALS['APP_CONFIG']['db_password'] ?? '');
$name = (string) ($GLOBALS['APP_CONFIG']['db_name'] ?? 'sb_tech');

// Skip app bootstrap side effects (sessions, headers) if setup.php loaded it.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
if (isset($objQuery) && $objQuery instanceof Database) {
    $mysqli = $objQuery->mysqli();
} else {
    mysqli_report(MYSQLI_REPORT_OFF);
    $mysqli = @new mysqli($host, $user, $pass, $name, $port);
    if ($mysqli->connect_errno) {
        fwrite(STDERR, "schema:check — cannot reach DB '{$name}' on {$host}:{$port}: {$mysqli->connect_error}\n");
        fwrite(STDERR, "Fix: point DB_* env vars at a database built by scripts/fresh_test_db.sh\n");
        exit(2);
    }
}
$mysqli->set_charset('utf8mb4');

// --- Collect real tables ----------------------------------------------------
$tables = [];
$res = $mysqli->query('SHOW TABLES');
if (!$res) {
    fwrite(STDERR, "schema:check — SHOW TABLES failed: {$mysqli->error}\n");
    exit(2);
}
while ($row = $res->fetch_array()) {
    $tables[] = $row[0];
}
sort($tables);

// --- Parse docs/Schema.md ---------------------------------------------------
$docFile = $appRoot . '/docs/Schema.md';
if (!is_readable($docFile)) {
    fwrite(STDERR, "schema:check — docs/Schema.md not found.\n");
    exit(2);
}
$doc = (string) file_get_contents($docFile);

$missing = [];
foreach ($tables as $t) {
    if (strpos($doc, $t) === false) {
        $missing[] = $t;
    }
}

// Declared count line: "**98 tables** total"
$declaredCount = null;
if (preg_match('/\*\*(\d+)\s+tables?\*\*/', $doc, $m)) {
    $declaredCount = (int) $m[1];
}

// --- Report ----------------------------------------------------------------
$exit = 0;
echo "== Schema docs parity (docs/Schema.md vs '{$name}') ==\n";
echo 'Live tables: ' . count($tables) . "\n";
if ($declaredCount !== null) {
    echo "Doc declares: {$declaredCount}\n";
    if ($declaredCount !== count($tables)) {
        echo "✗ Doc table count is stale (declares {$declaredCount}, actual " . count($tables) . ").\n";
        $exit = 1;
    }
}

if ($missing) {
    echo '✗ ' . count($missing) . " table(s) missing from docs/Schema.md:\n";
    foreach (array_slice($missing, 0, 20) as $t) {
        echo "   - {$t}\n";
    }
    if (count($missing) > 20) {
        echo '   ... and ' . (count($missing) - 20) . " more\n";
    }
    $exit = 1;
} else {
    echo "✓ Every live table is documented in docs/Schema.md\n";
}

if ($exit !== 0) {
    echo "\nFix: update docs/Schema.md (count line + domain section) in the same PR as the schema change.\n";
}
exit($exit);
