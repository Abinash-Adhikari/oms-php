<?php

$query = [
    "CREATE TABLE `tbl_cms_contacts_us` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(191) DEFAULT NULL,
    `email` VARCHAR(191) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `subject` VARCHAR(255) DEFAULT NULL,
    `message` TEXT,
    `service_interest` VARCHAR(191) DEFAULT NULL,
    `source_type` VARCHAR(50) DEFAULT 'Website',
    `status` ENUM('New','Read','Converted') NOT NULL DEFAULT 'New',
    `lead_id` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_contact_status` (`status`),
    KEY `idx_contact_lead` (`lead_id`),
    CONSTRAINT `fk_contact_lead`
        FOREIGN KEY (`lead_id`) REFERENCES `tbl_leads` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_cms_contacts_us`;',
];
