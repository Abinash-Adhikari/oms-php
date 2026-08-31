<?php

$query = [
    "CREATE TABLE `tbl_quotations` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `quotation_number` VARCHAR(50) NOT NULL,
    `client_id` INT DEFAULT NULL,
    `client_name` VARCHAR(191) NOT NULL,
    `client_email` VARCHAR(191) DEFAULT NULL,
    `client_phone` VARCHAR(50) DEFAULT NULL,
    `client_address` TEXT,
    `subject` VARCHAR(255) NOT NULL,
    `quotation_date` DATE NOT NULL,
    `valid_until` DATE DEFAULT NULL,
    `subtotal` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `discount_type` ENUM('percentage','fixed') DEFAULT NULL,
    `discount_value` DECIMAL(18,4) DEFAULT NULL,
    `tax_type` ENUM('percentage','fixed') DEFAULT NULL,
    `tax_value` DECIMAL(18,4) DEFAULT NULL,
    `total` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `notes` TEXT,
    `terms` TEXT,
    `status` ENUM('Draft','Sent','Accepted','Rejected','Expired') NOT NULL DEFAULT 'Draft',
    `lead_id` INT DEFAULT NULL,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_quotation_number` (`quotation_number`),
    KEY `idx_client_id` (`client_id`),
    KEY `idx_status` (`status`),
    KEY `idx_added_on` (`added_on`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE `tbl_quotation_items` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `quotation_id` INT NOT NULL,
    `item_name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    `unit` VARCHAR(50) DEFAULT NULL,
    `unit_price` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `amount` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `sort_order` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_quotation_id` (`quotation_id`),
    CONSTRAINT `fk_quotation_items_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `tbl_quotations` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE `tbl_quotation_files` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `quotation_id` INT NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_location` VARCHAR(255) NOT NULL,
    `file_extension` VARCHAR(10) DEFAULT NULL,
    `file_size` INT DEFAULT NULL,
    `added_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_quotation_files_quotation` (`quotation_id`),
    CONSTRAINT `fk_quotation_files_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `tbl_quotations` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_quotation_files`;',
    'DROP TABLE IF EXISTS `tbl_quotation_items`;',
    'DROP TABLE IF EXISTS `tbl_quotations`;',
];
