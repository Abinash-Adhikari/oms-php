<?php

/**
 * A lead can have many contact persons (the people at the prospect
 * organization), mirroring `tbl_client_contacts`: one row marked primary
 * whenever any contact exists. The legacy single `contact_name`/`email`/
 * `phone` columns stay on the lead as a read-fast mirror of the primary row
 * (same convention as `tbl_clients.contact_person`). Referral leads were
 * already moved to `referrer_*` by the preceding migration, so only genuine
 * client-side contacts are carried over here.
 */

$query = [
    "CREATE TABLE `tbl_lead_contacts` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `lead_id` INT NOT NULL,
    `name` VARCHAR(191) NOT NULL,
    `email` VARCHAR(191) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lead_contacts_lead` (`lead_id`),
    CONSTRAINT `fk_lead_contacts_lead`
        FOREIGN KEY (`lead_id`) REFERENCES `tbl_leads` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "INSERT INTO `tbl_lead_contacts` (`lead_id`, `name`, `email`, `phone`, `is_primary`, `added_by`, `added_on`)
     SELECT `id`, `contact_name`, `email`, `phone`, 1, `added_by`, `added_on`
     FROM `tbl_leads`
     WHERE `contact_name` IS NOT NULL AND TRIM(`contact_name`) <> '';",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_lead_contacts`;',
];
