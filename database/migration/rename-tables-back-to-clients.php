<?php

/**
 * SB-Tech — Rename business_sources tables and columns back to clients.
 *
 * Reverses the earlier "business_sources" naming: tbl_business_sources →
 * tbl_clients, tbl_business_source_contacts → tbl_client_contacts, and the
 * columns business_source_id / won_business_source_id → client_id / won_client_id
 * in tbl_leads, tbl_client_projects and tbl_business_source_contacts.
 * Also rewrites per-user grants ('business_sources' → 'clients') so the
 * renamed module keeps resolving for existing staff.
 */

$query = [
    // ── tbl_leads: drop old FKs + indexes ──
    "ALTER TABLE `tbl_leads` DROP FOREIGN KEY `fk_lead_business_source`;",
    "ALTER TABLE `tbl_leads` DROP FOREIGN KEY `fk_lead_won_business_source`;",
    "ALTER TABLE `tbl_leads` DROP INDEX `idx_lead_business_source`;",
    "ALTER TABLE `tbl_leads` DROP INDEX `idx_lead_won_business_source`;",

    // ── tbl_client_projects: drop old FK + index ──
    "ALTER TABLE `tbl_client_projects` DROP FOREIGN KEY `fk_project_business_source`;",
    "ALTER TABLE `tbl_client_projects` DROP INDEX `idx_project_business_source`;",

    // ── tbl_business_source_contacts: drop old FK + index (before table rename) ──
    "ALTER TABLE `tbl_business_source_contacts` DROP FOREIGN KEY `fk_bs_contacts_source`;",
    "ALTER TABLE `tbl_business_source_contacts` DROP INDEX `idx_bs_contacts_source`;",

    // ── Rename tables (also drops/rebuilds any auto-FK pointers to tbl_business_sources) ──
    "RENAME TABLE `tbl_business_sources` TO `tbl_clients`;",
    "RENAME TABLE `tbl_business_source_contacts` TO `tbl_client_contacts`;",

    // ── tbl_leads: rename columns ──
    "ALTER TABLE `tbl_leads`
     CHANGE COLUMN `business_source_id` `client_id` INT DEFAULT NULL
     COMMENT 'Client being pursued';",

    "ALTER TABLE `tbl_leads`
     CHANGE COLUMN `won_business_source_id` `won_client_id` INT DEFAULT NULL
     COMMENT 'Client that won the deal';",

    "ALTER TABLE `tbl_leads` ADD INDEX `idx_lead_client` (`client_id`);",
    "ALTER TABLE `tbl_leads` ADD INDEX `idx_lead_won` (`won_client_id`);",

    "ALTER TABLE `tbl_leads`
     ADD CONSTRAINT `fk_lead_client`
     FOREIGN KEY (`client_id`) REFERENCES `tbl_clients` (`id`)
     ON DELETE SET NULL ON UPDATE CASCADE;",
    "ALTER TABLE `tbl_leads`
     ADD CONSTRAINT `fk_lead_won`
     FOREIGN KEY (`won_client_id`) REFERENCES `tbl_clients` (`id`)
     ON DELETE SET NULL ON UPDATE CASCADE;",

    // ── tbl_client_projects: rename column ──
    "ALTER TABLE `tbl_client_projects`
     CHANGE COLUMN `business_source_id` `client_id` INT DEFAULT NULL
     COMMENT 'The client this won project provisions';",

    "ALTER TABLE `tbl_client_projects` ADD INDEX `idx_project_client` (`client_id`);",

    "ALTER TABLE `tbl_client_projects`
     ADD CONSTRAINT `fk_project_client`
     FOREIGN KEY (`client_id`) REFERENCES `tbl_clients` (`id`)
     ON DELETE SET NULL ON UPDATE CASCADE;",

    // ── tbl_client_contacts: rename column ──
    "ALTER TABLE `tbl_client_contacts`
     CHANGE COLUMN `business_source_id` `client_id` INT NOT NULL
     COMMENT 'The client this contact belongs to';",

    // ── Recreate tbl_client_contacts indexes + FK (now pointing to tbl_clients) ──
    "ALTER TABLE `tbl_client_contacts` ADD INDEX `idx_client_contacts_source` (`client_id`);",
    "ALTER TABLE `tbl_client_contacts`
     ADD CONSTRAINT `fk_client_contacts_source`
     FOREIGN KEY (`client_id`) REFERENCES `tbl_clients` (`id`)
     ON DELETE CASCADE ON UPDATE CASCADE;",

    // ── Rewrite user grants so the renamed module keeps resolving ──
    "UPDATE `tbl_users_login`
     SET `permitted_modules` = REPLACE(`permitted_modules`, '\"business_sources\"', '\"clients\"')",
    "UPDATE `tbl_users_login`
     SET `permitted_submodules` = REPLACE(`permitted_submodules`, '\"business_sources\"', '\"clients\"')",
];

$rollbackQuery = [
    // ── Drop tbl_client_contacts FK + index ──
    "ALTER TABLE `tbl_client_contacts` DROP FOREIGN KEY `fk_client_contacts_source`;",
    "ALTER TABLE `tbl_client_contacts` DROP INDEX `idx_client_contacts_source`;",

    // ── tbl_client_contacts: rename column back ──
    "ALTER TABLE `tbl_client_contacts`
     CHANGE COLUMN `client_id` `business_source_id` INT NOT NULL
     COMMENT 'The business source this contact belongs to';",

    // ── Drop tbl_leads new FKs + indexes ──
    "ALTER TABLE `tbl_leads` DROP FOREIGN KEY `fk_lead_client`;",
    "ALTER TABLE `tbl_leads` DROP FOREIGN KEY `fk_lead_won`;",
    "ALTER TABLE `tbl_leads` DROP INDEX `idx_lead_client`;",
    "ALTER TABLE `tbl_leads` DROP INDEX `idx_lead_won`;",

    // ── Drop tbl_client_projects new FK + index ──
    "ALTER TABLE `tbl_client_projects` DROP FOREIGN KEY `fk_project_client`;",
    "ALTER TABLE `tbl_client_projects` DROP INDEX `idx_project_client`;",

    // ── Rename tables back ──
    "RENAME TABLE `tbl_clients` TO `tbl_business_sources`;",
    "RENAME TABLE `tbl_client_contacts` TO `tbl_business_source_contacts`;",

    // ── tbl_leads: rename columns back ──
    "ALTER TABLE `tbl_leads`
     CHANGE COLUMN `client_id` `business_source_id` INT DEFAULT NULL
     COMMENT 'Business source being pursued';",

    "ALTER TABLE `tbl_leads`
     CHANGE COLUMN `won_client_id` `won_business_source_id` INT DEFAULT NULL
     COMMENT 'Business source that won the deal';",

    "ALTER TABLE `tbl_leads` ADD INDEX `idx_lead_business_source` (`business_source_id`);",
    "ALTER TABLE `tbl_leads` ADD INDEX `idx_lead_won_business_source` (`won_business_source_id`);",

    "ALTER TABLE `tbl_leads`
     ADD CONSTRAINT `fk_lead_business_source`
     FOREIGN KEY (`business_source_id`) REFERENCES `tbl_business_sources` (`id`)
     ON DELETE SET NULL ON UPDATE CASCADE;",
    "ALTER TABLE `tbl_leads`
     ADD CONSTRAINT `fk_lead_won_business_source`
     FOREIGN KEY (`won_business_source_id`) REFERENCES `tbl_business_sources` (`id`)
     ON DELETE SET NULL ON UPDATE CASCADE;",

    // ── tbl_client_projects: rename column back ──
    "ALTER TABLE `tbl_client_projects`
     CHANGE COLUMN `client_id` `business_source_id` INT DEFAULT NULL
     COMMENT 'The business source this won project provisions';",

    "ALTER TABLE `tbl_client_projects` ADD INDEX `idx_project_business_source` (`business_source_id`);",
    "ALTER TABLE `tbl_client_projects`
     ADD CONSTRAINT `fk_project_business_source`
     FOREIGN KEY (`business_source_id`) REFERENCES `tbl_business_sources` (`id`)
     ON DELETE SET NULL ON UPDATE CASCADE;",

    // ── Recreate old tbl_business_source_contacts indexes + FK ──
    "ALTER TABLE `tbl_business_source_contacts` ADD INDEX `idx_bs_contacts_source` (`business_source_id`);",
    "ALTER TABLE `tbl_business_source_contacts`
     ADD CONSTRAINT `fk_bs_contacts_source`
     FOREIGN KEY (`business_source_id`) REFERENCES `tbl_business_sources` (`id`)
     ON DELETE CASCADE ON UPDATE CASCADE;",

    // ── Rewrite user grants back to the old module key ──
    "UPDATE `tbl_users_login`
     SET `permitted_modules` = REPLACE(`permitted_modules`, '\"clients\"', '\"business_sources\"')",
    "UPDATE `tbl_users_login`
     SET `permitted_submodules` = REPLACE(`permitted_submodules`, '\"clients\"', '\"business_sources\"')",
];