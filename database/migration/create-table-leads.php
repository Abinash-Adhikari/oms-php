<?php

$query = [
    "CREATE TABLE `tbl_leads` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `source` ENUM('Website','Phone','Email','Walk-in','Referral','Social','Other') NOT NULL DEFAULT 'Website',
    `company` VARCHAR(191) DEFAULT NULL,
    `contact_name` VARCHAR(191) DEFAULT NULL,
    `email` VARCHAR(191) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `service_interest` VARCHAR(191) DEFAULT NULL,
    `message` TEXT,
    `priority` ENUM('Hot','Warm','Cold') NOT NULL DEFAULT 'Warm',
    `estimated_value` DECIMAL(18,4) DEFAULT NULL,
    `stage` ENUM('New','Contacted','Qualified','Proposal','Won','Lost') NOT NULL DEFAULT 'New',
    `assigned_to` INT DEFAULT NULL,
    `won_client_id` INT DEFAULT NULL,
    `lost_reason` VARCHAR(255) DEFAULT NULL,
    `last_activity_on` DATETIME DEFAULT NULL,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lead_stage` (`stage`),
    KEY `idx_lead_assigned` (`assigned_to`),
    KEY `idx_lead_priority` (`priority`),
    KEY `idx_lead_email` (`email`),
    KEY `idx_lead_phone` (`phone`),
    KEY `idx_lead_won` (`won_client_id`),
    CONSTRAINT `fk_lead_assigned`
        FOREIGN KEY (`assigned_to`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_lead_won`
        FOREIGN KEY (`won_client_id`) REFERENCES `tbl_clients` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_leads`;',
];
