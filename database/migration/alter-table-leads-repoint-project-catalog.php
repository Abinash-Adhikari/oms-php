<?php

/**
 * SB-Tech — tbl_leads: repoint project_id to the catalog.
 *
 * tbl_leads.project_id previously referenced a won deployment
 * (tbl_client_projects). In the pipeline model a lead pursues a *catalog*
 * project (tbl_projects). Legacy deployment references become unconvertible
 * and are captured onto tbl_client_projects.lead_id where possible, then all
 * project_id values are rebuilt from each lead's service interest (seeding the
 * catalog with the distinct services the team has been pursuing).
 */

$query = [
    // Capture the legacy won-deployment link (leads.project_id → client project)
    // onto tbl_client_projects.lead_id so the Won → provisioning story survives.
    "UPDATE `tbl_client_projects` cp
     LEFT JOIN `tbl_leads` l ON l.`project_id` = cp.`id`
     SET cp.`lead_id` = l.`id`
     WHERE l.`id` IS NOT NULL;",

    "ALTER TABLE `tbl_leads` DROP FOREIGN KEY `fk_lead_project`;",
    "ALTER TABLE `tbl_leads` DROP INDEX `idx_lead_project`;",

    // Clear stale deployment references entirely, then rebuild from service
    // interest against the freshly-seeded catalog.
    "UPDATE `tbl_leads` SET `project_id` = NULL;",

    "INSERT INTO `tbl_projects` (`name`, `category`, `status`, `added_on`)
     SELECT DISTINCT TRIM(l.`service_interest`), 'Lead generated', 'Active', NOW()
     FROM `tbl_leads` l
     WHERE l.`service_interest` IS NOT NULL AND TRIM(l.`service_interest`) <> '';",

    "UPDATE `tbl_leads` l
     JOIN `tbl_projects` p ON p.`name` = TRIM(l.`service_interest`)
     SET l.`project_id` = p.`id`
     WHERE l.`service_interest` IS NOT NULL AND TRIM(l.`service_interest`) <> '';",

    "ALTER TABLE `tbl_leads`
     ADD INDEX `idx_lead_project` (`project_id`);",

    "ALTER TABLE `tbl_leads`
     ADD CONSTRAINT `fk_lead_project_catalog`
     FOREIGN KEY (`project_id`) REFERENCES `tbl_projects` (`id`)
     ON DELETE SET NULL ON UPDATE CASCADE;",
];

$rollbackQuery = [
    "ALTER TABLE `tbl_leads` DROP FOREIGN KEY `fk_lead_project_catalog`;",
    "ALTER TABLE `tbl_leads` DROP INDEX `idx_lead_project`;",
    "UPDATE `tbl_leads` SET `project_id` = NULL;",
    "ALTER TABLE `tbl_leads` ADD INDEX `idx_lead_project` (`project_id`);",
    "ALTER TABLE `tbl_leads`
     ADD CONSTRAINT `fk_lead_project`
     FOREIGN KEY (`project_id`) REFERENCES `tbl_client_projects` (`id`)
     ON DELETE SET NULL ON UPDATE CASCADE;",
];
