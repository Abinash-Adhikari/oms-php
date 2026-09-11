<?php

/**
 * SB-Tech — Nested contact persons for business sources.
 *
 * A business source can have many contact persons (the "+ Contact Person"
 * sub-entity). Legacy single `contact_person` values are carried over as the
 * primary contact row; the column is kept on the source as a read-fast mirror
 * of the primary contact for existing financial/copying code.
 */

$query = [
    "CREATE TABLE `tbl_business_source_contacts` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `business_source_id` INT NOT NULL,
    `name` VARCHAR(191) NOT NULL,
    `designation` VARCHAR(191) DEFAULT NULL,
    `email` VARCHAR(191) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
    `notes` TEXT,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_bs_contacts_source` (`business_source_id`),
    CONSTRAINT `fk_bs_contacts_source`
        FOREIGN KEY (`business_source_id`) REFERENCES `tbl_business_sources` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Carry over the legacy single contact_person as the primary contact.
    "INSERT INTO `tbl_business_source_contacts` (`business_source_id`, `name`, `is_primary`, `added_on`)
     SELECT `id`, `contact_person`, 1, `added_on`
     FROM `tbl_business_sources`
     WHERE `contact_person` IS NOT NULL AND TRIM(`contact_person`) <> '';",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_business_source_contacts`;',
];
