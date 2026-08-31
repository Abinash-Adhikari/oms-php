<?php

/**
 * SB-Tech — Add client_id to tbl_leads for one-to-many client-lead relationship.
 *
 * This migration adds a client_id column to tbl_leads, allowing leads to reference
 * an existing client. This supports the scenario where one client can have multiple
 * leads (e.g., same company inquiring about different services over time).
 *
 * Backfills existing data: for leads that have won_client_id set, we also set client_id
 * to maintain consistency. The won_client_id remains for backward compatibility.
 */

$query = [
    // Add client_id column after won_client_id
    "ALTER TABLE `tbl_leads`
     ADD COLUMN `client_id` INT DEFAULT NULL
     AFTER `won_client_id`;",

    // Add index for client_id
    "ALTER TABLE `tbl_leads`
     ADD INDEX `idx_lead_client` (`client_id`);",

    // Add foreign key constraint
    "ALTER TABLE `tbl_leads`
     ADD CONSTRAINT `fk_lead_client`
     FOREIGN KEY (`client_id`) REFERENCES `tbl_clients` (`id`)
     ON DELETE SET NULL ON UPDATE CASCADE;",

    // Backfill existing data: set client_id = won_client_id for converted leads
    "UPDATE `tbl_leads`
     SET `client_id` = `won_client_id`
     WHERE `won_client_id` IS NOT NULL AND `client_id` IS NULL;",
];

$rollbackQuery = [
    "ALTER TABLE `tbl_leads` DROP FOREIGN KEY `fk_lead_client`;",
    "ALTER TABLE `tbl_leads` DROP INDEX `idx_lead_client`;",
    "ALTER TABLE `tbl_leads` DROP COLUMN `client_id`;",
];
