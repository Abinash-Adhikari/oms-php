<?php

/**
 * SB-Tech — Client project provisioning.
 *
 * tbl_client_projects is the record created when a pipeline deal reaches Won.
 * It provisions three settings per won project:
 *   1. Client Database (db_name)  — optional dedicated database identifier
 *   2. Permitted Modules          — high-level modules the client may access
 *   3. Permitted Submodules       — granular permissions nested per module
 *
 * This migration also:
 *   - renames client_id → business_source_id (FK now → tbl_business_sources)
 *   - adds lead_id (the winning deal) and project_id (the catalog project won)
 *   - carries over legacy per-source grants (previously stored on the client
 *     row) onto that source's won projects
 *   - drops the now-obsolete entitlement columns from tbl_business_sources
 */

$query = [
    "ALTER TABLE `tbl_client_projects` DROP FOREIGN KEY `fk_project_client`;",
    "ALTER TABLE `tbl_client_projects` DROP INDEX `idx_project_client`;",
    "ALTER TABLE `tbl_client_projects`
     CHANGE COLUMN `client_id` `business_source_id` INT DEFAULT NULL
     COMMENT 'The business source this won project provisions';",
    "ALTER TABLE `tbl_client_projects`
     ADD INDEX `idx_project_business_source` (`business_source_id`);",
    "ALTER TABLE `tbl_client_projects`
     ADD CONSTRAINT `fk_project_business_source`
     FOREIGN KEY (`business_source_id`) REFERENCES `tbl_business_sources` (`id`)
     ON DELETE SET NULL ON UPDATE CASCADE;",

    "ALTER TABLE `tbl_client_projects`
     ADD COLUMN `lead_id` INT DEFAULT NULL
     AFTER `business_source_id`;",
    "ALTER TABLE `tbl_client_projects`
     ADD INDEX `idx_project_lead` (`lead_id`);",
    "ALTER TABLE `tbl_client_projects`
     ADD CONSTRAINT `fk_project_lead`
     FOREIGN KEY (`lead_id`) REFERENCES `tbl_leads` (`id`)
     ON DELETE SET NULL ON UPDATE CASCADE;",

    "ALTER TABLE `tbl_client_projects`
     ADD COLUMN `project_id` INT DEFAULT NULL
     AFTER `lead_id`;",
    "ALTER TABLE `tbl_client_projects`
     ADD INDEX `idx_project_catalog` (`project_id`);",
    "ALTER TABLE `tbl_client_projects`
     ADD CONSTRAINT `fk_project_catalog`
     FOREIGN KEY (`project_id`) REFERENCES `tbl_projects` (`id`)
     ON DELETE SET NULL ON UPDATE CASCADE;",

    "ALTER TABLE `tbl_client_projects`
     ADD COLUMN `permitted_modules` TEXT DEFAULT NULL
     AFTER `db_name`;",
    "ALTER TABLE `tbl_client_projects`
     ADD COLUMN `permitted_submodules` TEXT DEFAULT NULL
     AFTER `permitted_modules`;",

    // Carry over legacy per-source grants onto that source's won projects.
    "UPDATE `tbl_client_projects` cp
     LEFT JOIN `tbl_business_sources` bs ON bs.`id` = cp.`business_source_id`
     SET cp.`permitted_modules`    = IF(cp.`permitted_modules` IS NULL,    bs.`permitted_modules`,    cp.`permitted_modules`),
         cp.`permitted_submodules` = IF(cp.`permitted_submodules` IS NULL, bs.`permitted_submodules`, cp.`permitted_submodules`),
         cp.`db_name`              = IF(cp.`db_name` IS NULL,              bs.`db_name`,              cp.`db_name`)
     WHERE bs.`id` IS NOT NULL;",

    // The source row no longer carries grants — provisioning lives on the won
    // project record (one deployment can have its own database + module set).
    "ALTER TABLE `tbl_business_sources` DROP COLUMN `module_databases`;",
    "ALTER TABLE `tbl_business_sources` DROP COLUMN `permitted_modules`;",
    "ALTER TABLE `tbl_business_sources` DROP COLUMN `permitted_submodules`;",
    "ALTER TABLE `tbl_business_sources` DROP COLUMN `db_name`;",
];

$rollbackQuery = [
    "ALTER TABLE `tbl_business_sources`
     ADD COLUMN `permitted_modules` TEXT DEFAULT NULL AFTER `notes`;",
    "ALTER TABLE `tbl_business_sources`
     ADD COLUMN `permitted_submodules` TEXT DEFAULT NULL AFTER `permitted_modules`;",
    "ALTER TABLE `tbl_business_sources`
     ADD COLUMN `db_name` VARCHAR(191) DEFAULT NULL AFTER `permitted_submodules`;",
    "ALTER TABLE `tbl_business_sources`
     ADD COLUMN `module_databases` TEXT DEFAULT NULL AFTER `db_name`;",
    "ALTER TABLE `tbl_client_projects` DROP COLUMN `permitted_submodules`;",
    "ALTER TABLE `tbl_client_projects` DROP COLUMN `permitted_modules`;",
    "ALTER TABLE `tbl_client_projects` DROP FOREIGN KEY `fk_project_catalog`;",
    "ALTER TABLE `tbl_client_projects` DROP INDEX `idx_project_catalog`;",
    "ALTER TABLE `tbl_client_projects` DROP COLUMN `project_id`;",
    "ALTER TABLE `tbl_client_projects` DROP FOREIGN KEY `fk_project_lead`;",
    "ALTER TABLE `tbl_client_projects` DROP INDEX `idx_project_lead`;",
    "ALTER TABLE `tbl_client_projects` DROP COLUMN `lead_id`;",
    "ALTER TABLE `tbl_client_projects` DROP FOREIGN KEY `fk_project_business_source`;",
    "ALTER TABLE `tbl_client_projects` DROP INDEX `idx_project_business_source`;",
    "ALTER TABLE `tbl_client_projects`
     CHANGE COLUMN `business_source_id` `client_id` INT DEFAULT NULL;",
    "ALTER TABLE `tbl_client_projects` ADD INDEX `idx_project_client` (`client_id`);",
    "ALTER TABLE `tbl_client_projects`
     ADD CONSTRAINT `fk_project_client`
     FOREIGN KEY (`client_id`) REFERENCES `tbl_business_sources` (`id`)
     ON DELETE SET NULL ON UPDATE CASCADE;",
];
