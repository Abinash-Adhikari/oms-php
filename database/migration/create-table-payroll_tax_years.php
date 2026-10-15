<?php
// Income-tax year settings (Nepal FY, Shrawan–Asar). Slabs hang off a year.
$query = [
    "CREATE TABLE `tbl_payroll_tax_years` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(20) NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `retirement_cap` DECIMAL(18,4) NOT NULL DEFAULT 0,
    `retirement_fraction` DECIMAL(10,8) NOT NULL DEFAULT 0,
    `female_rebate_percent` DECIMAL(7,4) NOT NULL DEFAULT 0,
    `notes` TEXT,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_tax_year_title` (`title`),
    KEY `idx_tax_year_dates` (`start_date`, `end_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];
$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_payroll_tax_years`;',
];
