<?php

$query = [
    "CREATE TABLE `tbl_fiscal_years` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(20) NOT NULL,
    `starting_date` DATE NOT NULL,
    `ending_date` DATE NOT NULL,
    `closing` ENUM('Open','Closed') NOT NULL DEFAULT 'Open',
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_fy_title` (`title`),
    KEY `idx_fy_dates` (`starting_date`, `ending_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_fiscal_years`;',
];
