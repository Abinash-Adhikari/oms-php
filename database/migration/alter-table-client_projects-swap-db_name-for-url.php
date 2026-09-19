<?php

/**
 * SB-Tech — Replace the client-project `db_name` with a public `url`.
 *
 * Client projects no longer track a dedicated deployment database (the
 * module-access console was removed), so `db_name` has no meaning. It is
 * dropped and a `url` column is added in its place to record the delivered
 * project's public address. Existing `db_name` values are intentionally not
 * carried over — they are database identifiers, not URLs.
 */

$query = [
    "ALTER TABLE `tbl_client_projects`
     ADD COLUMN `url` VARCHAR(500) DEFAULT NULL
     AFTER `package`;",

    "ALTER TABLE `tbl_client_projects` DROP COLUMN `db_name`;",
];

$rollbackQuery = [
    "ALTER TABLE `tbl_client_projects`
     ADD COLUMN `db_name` VARCHAR(191) DEFAULT NULL
     AFTER `package`;",

    "ALTER TABLE `tbl_client_projects` DROP COLUMN `url`;",
];
