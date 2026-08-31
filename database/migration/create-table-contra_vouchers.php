<?php

$query = [
    "CREATE TABLE `tbl_contra_vouchers` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `fiscal_year_id` INT UNSIGNED NOT NULL,
    `voucher_no` VARCHAR(32) NOT NULL,
    `voucher_date` DATE NOT NULL,
    `reference_no` VARCHAR(128) DEFAULT NULL,
    `narration` VARCHAR(2048) DEFAULT NULL,
    `description` VARCHAR(2048) DEFAULT NULL,
    `amount` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `discount_amount` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `tax_amount` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `total_amount` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `currency_code` CHAR(3) NOT NULL DEFAULT 'NPR',
    `fx_rate` DECIMAL(18,8) NOT NULL DEFAULT 1.00000000,
    `base_currency_code` CHAR(3) NOT NULL DEFAULT 'NPR',
    `entry_type` ENUM('Manual','Auto') NOT NULL DEFAULT 'Manual',
    `status` ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    `file_name` VARCHAR(512) DEFAULT NULL,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `approved_by` INT DEFAULT NULL,
    `added_on` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_fy_contra_voucher_no` (`fiscal_year_id`, `voucher_no`),
    KEY `idx_cv_date` (`voucher_date`),
    KEY `idx_cv_status` (`status`),
    CONSTRAINT `fk_cv_fy`
        FOREIGN KEY (`fiscal_year_id`) REFERENCES `tbl_fiscal_years` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_cv_approved_by`
        FOREIGN KEY (`approved_by`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_contra_vouchers`;',
];
