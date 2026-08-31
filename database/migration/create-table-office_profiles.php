<?php

$query = [
    "CREATE TABLE `tbl_office_profiles` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) DEFAULT NULL,
    `accronym` VARCHAR(100) DEFAULT NULL,
    `address1` VARCHAR(255) DEFAULT NULL,
    `address2` VARCHAR(255) DEFAULT NULL,
    `email` VARCHAR(191) DEFAULT NULL,
    `phone1` VARCHAR(50) DEFAULT NULL,
    `phone2` VARCHAR(50) DEFAULT NULL,
    `vat_no` VARCHAR(100) DEFAULT NULL,
    `website` VARCHAR(255) DEFAULT NULL,
    `logo` VARCHAR(255) DEFAULT NULL,
    `logo_extension` VARCHAR(50) DEFAULT NULL,
    `allow_ips` VARCHAR(255) DEFAULT 'All',
    `use_date` ENUM('AD','BS') DEFAULT 'AD',
    `leave_year_mode` ENUM('AD','BS') DEFAULT 'AD',
    `plan_name` VARCHAR(100) DEFAULT NULL,
    `slogan` VARCHAR(255) DEFAULT NULL,
    `estd` VARCHAR(20) DEFAULT NULL,
    `certificate_regd_numbers` TEXT,
    `payment_qr_code` VARCHAR(255) DEFAULT NULL,
    `backup_email` VARCHAR(191) DEFAULT NULL,
    `otp_email` VARCHAR(191) DEFAULT NULL,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_office_profiles`;',
];
