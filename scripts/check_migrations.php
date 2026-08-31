<?php
// Temporary integrity check for sb-tech migrations. Run: php scripts/check_migrations.php
require __DIR__ . '/../database/migration/migrations.php';
$dir = __DIR__ . '/../database/migration/';

$created = [];   // table => file (first creator)
$errors = [];

foreach ($files as $file) {
    $code = file_get_contents($dir . $file . '.php');
    // tables created by this file
    if (preg_match_all('/CREATE TABLE `([^`]+)`/', $code, $m)) {
        foreach ($m[1] as $t) {
            if (isset($created[$t])) {
                $errors[] = "DUPLICATE CREATE: `$t` in $file (already in {$created[$t]})";
            }
            $created[$t] = $file;
        }
    }
    // tables created by this file (for same-file self-FK detection)
    $tablesThisFile = $m[1] ?? [];
    // FK references
    if (preg_match_all('/FOREIGN KEY[^)]*\)\s*REFERENCES `([^`]+)`/', $code, $m)) {
        foreach ($m[1] as $ref) {
            if (!isset($created[$ref])) {
                $errors[] = "FK to missing table `$ref` in $file";
            } elseif ($created[$ref] === $file && !in_array($ref, $tablesThisFile, true)) {
                $errors[] = "SELF-ORDER FK: `$ref` referenced within same file $file (circular?)";
            }
        }
    }
}

// Ordering: referencing file must come after the referenced table's creator
$order = array_flip($files);
foreach ($files as $file) {
    $code = file_get_contents($dir . $file . '.php');
    if (preg_match_all('/FOREIGN KEY[^)]*\)\s*REFERENCES `([^`]+)`/', $code, $m)) {
        foreach ($m[1] as $ref) {
            if (isset($created[$ref]) && $order[$created[$ref]] > $order[$file]) {
                $errors[] = "ORDER: $file references `$ref` but {$created[$ref]} runs later";
            }
        }
    }
}

echo count($files) . " migrations listed, " . count($created) . " tables created.\n";
if ($errors) {
    foreach ($errors as $e) echo "  ! $e\n";
    exit(1);
}
echo "OK: no duplicate tables, no missing FK targets, no ordering violations.\n";
