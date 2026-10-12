<?php

/**
 * Migration: App-listing fields on tbl_client_projects for GET /api/v1/schools.
 *
 * A row is published to the school app once it has an `app_slug` (short,
 * unique, never changed after it is set). `city`, `accent` (#RRGGBB) and
 * `sort_order` are display data for the app's school picker.
 */
$query = [
    "ALTER TABLE `tbl_client_projects`
        ADD COLUMN IF NOT EXISTS `app_slug` VARCHAR(32) DEFAULT NULL COMMENT 'Public app id; set = listed in /api/v1/schools' AFTER `url`,
        ADD COLUMN IF NOT EXISTS `city` VARCHAR(100) DEFAULT NULL AFTER `app_slug`,
        ADD COLUMN IF NOT EXISTS `accent` CHAR(7) DEFAULT NULL COMMENT '#RRGGBB' AFTER `city`,
        ADD COLUMN IF NOT EXISTS `sort_order` INT NOT NULL DEFAULT 0 AFTER `accent`",
    "ALTER TABLE `tbl_client_projects` ADD UNIQUE INDEX `uq_project_app_slug` (`app_slug`)",
];

// The runner executes $rollbackQuery in reverse: index first, then columns.
$rollbackQuery = [
    "ALTER TABLE `tbl_client_projects`
        DROP COLUMN IF EXISTS `sort_order`,
        DROP COLUMN IF EXISTS `accent`,
        DROP COLUMN IF EXISTS `city`,
        DROP COLUMN IF EXISTS `app_slug`",
    "ALTER TABLE `tbl_client_projects` DROP INDEX IF EXISTS `uq_project_app_slug`",
];
