<?php
// Temporary: run all sb-tech migrations against a scratch DB to validate SQL + FK ordering.
// Usage: php scripts/run_migrations_test.php <db_name>
$dbName = $argv[1] ?? 'sb_tech_migration_test';

$mysqli = new mysqli('localhost', 'admin', 'admin', $dbName, 0, '/var/run/mysqld/mysqld.sock');
if ($mysqli->connect_errno) {
    die("Connect failed: " . $mysqli->connect_error . "\n");
}
$mysqli->set_charset('utf8mb4');

require __DIR__ . '/../database/migration/migrations.php';
$dir = __DIR__ . '/../database/migration/';

$fail = 0;
foreach ($files as $file) {
    require $dir . $file . '.php';
    $queries = $query ?? [];
    foreach ($queries as $q) {
        if (!$mysqli->query($q)) {
            echo "FAIL [$file]: " . $mysqli->error . "\n  SQL: " . substr(preg_replace('/\s+/', ' ', $q), 0, 140) . "...\n";
            $fail++;
        }
    }
    $stmt = $mysqli->prepare("INSERT INTO `tbl_migrations` (`filename`, `executed_on`) VALUES (?, NOW())");
    $stmt->bind_param('s', $file);
    $stmt->execute();
    $stmt->close();
    unset($query, $rollbackQuery);
}

$res = $mysqli->query("SHOW TABLES");
$tables = [];
while ($row = $res->fetch_array()) { $tables[] = $row[0]; }

echo ($fail === 0 ? "ALL MIGRATIONS EXECUTED OK" : "$fail query failures") . " — " . count($tables) . " tables created in `$dbName`\n";

// FK integrity summary
$fk = $mysqli->query("SELECT COUNT(*) c FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA = '$dbName' AND REFERENCED_TABLE_NAME IS NOT NULL");
$fkRow = $fk->fetch_assoc();
echo "Foreign keys created: {$fkRow['c']}\n";

// verify seed rows
foreach (['tbl_users_login', 'tbl_office_profiles', 'tbl_fiscal_years', 'tbl_account_groups'] as $t) {
    if (in_array($t, $tables, true)) {
        $r = $mysqli->query("SELECT COUNT(*) c FROM `$t`");
        $row = $r->fetch_assoc();
        echo "  $t: {$row['c']} rows\n";
    }
}
