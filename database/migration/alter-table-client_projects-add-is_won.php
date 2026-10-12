<?php

/**
 * Migration: Add `is_won` flag to tbl_client_projects.
 *
 * Demo/trial deployments for leads that are not yet won are now recorded as
 * client projects too (is_won = 0); reaching Won flips the same row to 1.
 * Every pre-existing row was provisioned from a won deal, so it is
 * backfilled to 1.
 */
$query = [
    "ALTER TABLE `tbl_client_projects`
        ADD COLUMN IF NOT EXISTS `is_won` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = won deal, 0 = demo/trial' AFTER `project_id`",
    "UPDATE `tbl_client_projects` SET `is_won` = 1",
    "ALTER TABLE `tbl_client_projects` ADD INDEX `idx_project_is_won` (`is_won`)",
];

// Runs in reverse: the index is dropped before the column.
$rollbackQuery = [
    "ALTER TABLE `tbl_client_projects` DROP COLUMN IF EXISTS `is_won`",
    "ALTER TABLE `tbl_client_projects` DROP INDEX IF EXISTS `idx_project_is_won`",
];
