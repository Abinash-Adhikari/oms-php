<?php

/**
 * SB-Tech — Add package + deployment database columns to client projects.
 *
 * A project can represent a delivered product/engagement (e.g. a Smart School
 * deployment). `package` records what was sold (plan/edition) and `db_name`
 * records the customer's own database for that deployment — the handle used by
 * the module-entitlements console later. Both are optional and nullable so
 * existing rows keep working untouched.
 */

$query = [
    "ALTER TABLE `tbl_client_projects`
     ADD COLUMN `package` VARCHAR(191) DEFAULT NULL
     AFTER `title`;",

    "ALTER TABLE `tbl_client_projects`
     ADD COLUMN `db_name` VARCHAR(191) DEFAULT NULL
     AFTER `package`;",
];

$rollbackQuery = [
    "ALTER TABLE `tbl_client_projects` DROP COLUMN `db_name`;",
    "ALTER TABLE `tbl_client_projects` DROP COLUMN `package`;",
];
