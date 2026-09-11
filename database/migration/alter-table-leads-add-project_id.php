<?php

/**
 * SB-Tech — Link leads to client projects.
 *
 * A sales lead can reference the project (product/engagement) it is selling or
 * managing. This lets a Won lead surface "manage this project" and keeps the
 * conversion story client -> project explicit. Deleting a project only clears
 * the link (SET NULL); lead history survives.
 */

$query = [
    "ALTER TABLE `tbl_leads`
     ADD COLUMN `project_id` INT DEFAULT NULL
     AFTER `client_id`;",

    "ALTER TABLE `tbl_leads`
     ADD INDEX `idx_lead_project` (`project_id`);",

    "ALTER TABLE `tbl_leads`
     ADD CONSTRAINT `fk_lead_project`
     FOREIGN KEY (`project_id`) REFERENCES `tbl_client_projects` (`id`)
     ON DELETE SET NULL ON UPDATE CASCADE;",
];

$rollbackQuery = [
    "ALTER TABLE `tbl_leads` DROP FOREIGN KEY `fk_lead_project`;",
    "ALTER TABLE `tbl_leads` DROP INDEX `idx_lead_project`;",
    "ALTER TABLE `tbl_leads` DROP COLUMN `project_id`;",
];
