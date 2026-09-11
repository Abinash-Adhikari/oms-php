<?php

/**
 * SB-Tech — tbl_leads: link the pipeline to a business source.
 *
 * client_id / won_client_id become business_source_id / won_business_source_id
 * (both → tbl_business_sources). The pipeline hub now formally combines a
 * Business Source with a Project (tbl_leads.project_id → tbl_projects).
 */

$query = [
    "ALTER TABLE `tbl_leads` DROP FOREIGN KEY `fk_lead_client`;",
    "ALTER TABLE `tbl_leads` DROP FOREIGN KEY `fk_lead_won`;",

    "ALTER TABLE `tbl_leads` DROP INDEX `idx_lead_client`;",
    "ALTER TABLE `tbl_leads` DROP INDEX `idx_lead_won`;",

    "ALTER TABLE `tbl_leads`
     CHANGE COLUMN `client_id` `business_source_id` INT DEFAULT NULL
     COMMENT 'Business source being pursued';",

    "ALTER TABLE `tbl_leads`
     CHANGE COLUMN `won_client_id` `won_business_source_id` INT DEFAULT NULL
     COMMENT 'Business source that won the deal';",

    "ALTER TABLE `tbl_leads`
     ADD INDEX `idx_lead_business_source` (`business_source_id`);",
    "ALTER TABLE `tbl_leads`
     ADD INDEX `idx_lead_won_business_source` (`won_business_source_id`);",

    "ALTER TABLE `tbl_leads`
     ADD CONSTRAINT `fk_lead_business_source`
     FOREIGN KEY (`business_source_id`) REFERENCES `tbl_business_sources` (`id`)
     ON DELETE SET NULL ON UPDATE CASCADE;",

    "ALTER TABLE `tbl_leads`
     ADD CONSTRAINT `fk_lead_won_business_source`
     FOREIGN KEY (`won_business_source_id`) REFERENCES `tbl_business_sources` (`id`)
     ON DELETE SET NULL ON UPDATE CASCADE;",
];

$rollbackQuery = [
    "ALTER TABLE `tbl_leads` DROP FOREIGN KEY `fk_lead_business_source`;",
    "ALTER TABLE `tbl_leads` DROP FOREIGN KEY `fk_lead_won_business_source`;",
    "ALTER TABLE `tbl_leads` DROP INDEX `idx_lead_business_source`;",
    "ALTER TABLE `tbl_leads` DROP INDEX `idx_lead_won_business_source`;",
    "ALTER TABLE `tbl_leads`
     CHANGE COLUMN `business_source_id` `client_id` INT DEFAULT NULL;",
    "ALTER TABLE `tbl_leads`
     CHANGE COLUMN `won_business_source_id` `won_client_id` INT DEFAULT NULL;",
    "ALTER TABLE `tbl_leads` ADD INDEX `idx_lead_client` (`client_id`);",
    "ALTER TABLE `tbl_leads` ADD INDEX `idx_lead_won` (`won_client_id`);",
    "ALTER TABLE `tbl_leads`
     ADD CONSTRAINT `fk_lead_client`
     FOREIGN KEY (`client_id`) REFERENCES `tbl_business_sources` (`id`)
     ON DELETE SET NULL ON UPDATE CASCADE;",
    "ALTER TABLE `tbl_leads`
     ADD CONSTRAINT `fk_lead_won`
     FOREIGN KEY (`won_client_id`) REFERENCES `tbl_business_sources` (`id`)
     ON DELETE SET NULL ON UPDATE CASCADE;",
];
