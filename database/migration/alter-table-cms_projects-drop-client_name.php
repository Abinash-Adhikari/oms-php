<?php

/**
 * Migration: drop tbl_cms_projects.client_name.
 *
 * Replaced by tbl_cms_project_clients (zero/one/many clients per portfolio
 * item), which create-table-cms_project_clients backfilled from this column.
 * Rollback re-adds the column and fills it with the item's client names
 * joined by ", ".
 */
$query = [
    'ALTER TABLE `tbl_cms_projects` DROP COLUMN IF EXISTS `client_name`',
];

// Runs in reverse: the column is re-added before it is repopulated.
$rollbackQuery = [
    "UPDATE `tbl_cms_projects` p
     SET p.`client_name` = (
        SELECT LEFT(GROUP_CONCAT(COALESCE(c.`name`, pc.`client_name`) ORDER BY pc.`position`, pc.`id` SEPARATOR ', '), 191)
        FROM `tbl_cms_project_clients` pc
        LEFT JOIN `tbl_clients` c ON c.`id` = pc.`client_id`
        WHERE pc.`project_id` = p.`id`
     )",
    'ALTER TABLE `tbl_cms_projects` ADD COLUMN IF NOT EXISTS `client_name` VARCHAR(191) DEFAULT NULL AFTER `slug`',
];
