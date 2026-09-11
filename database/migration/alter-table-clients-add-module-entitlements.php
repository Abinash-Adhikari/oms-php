<?php

/**
 * SB-Tech — Client module & database entitlements.
 *
 * Mirrors the way staff module permissions are stored on the user row
 * (JSON columns edited via the Module Permission screen): a client carries
 * its own permitted-module matrix so "which product modules this client may
 * use, and against which database" is decided exactly like a permission
 * grant — not hard-coded.
 *
 *   permitted_modules    JSON array of granted module keys
 *   permitted_submodules JSON map  { module: [submodule keys] }
 *   db_name              primary deployment database for the client
 *   module_databases     JSON map  { module: db_name } per-module database
 *
 * All columns nullable so existing clients keep working untouched.
 */

$query = [
    "ALTER TABLE `tbl_clients`
     ADD COLUMN `permitted_modules` TEXT DEFAULT NULL
     AFTER `notes`;",

    "ALTER TABLE `tbl_clients`
     ADD COLUMN `permitted_submodules` TEXT DEFAULT NULL
     AFTER `permitted_modules`;",

    "ALTER TABLE `tbl_clients`
     ADD COLUMN `db_name` VARCHAR(191) DEFAULT NULL
     COMMENT 'Primary deployment database for the client'
     AFTER `permitted_submodules`;",

    "ALTER TABLE `tbl_clients`
     ADD COLUMN `module_databases` TEXT DEFAULT NULL
     AFTER `db_name`;",
];

$rollbackQuery = [
    "ALTER TABLE `tbl_clients` DROP COLUMN `module_databases`;",
    "ALTER TABLE `tbl_clients` DROP COLUMN `db_name`;",
    "ALTER TABLE `tbl_clients` DROP COLUMN `permitted_submodules`;",
    "ALTER TABLE `tbl_clients` DROP COLUMN `permitted_modules`;",
];
