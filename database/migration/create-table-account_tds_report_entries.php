<?php

$query = [
    "CREATE TABLE `tbl_account_tds_report_entries` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `tds_type_id` INT DEFAULT NULL,
    `voucher_type` VARCHAR(64) DEFAULT NULL,
    `voucher_type_id` INT DEFAULT NULL,
    `particulars_date` DATE DEFAULT NULL,
    `fiscal_year_id` INT UNSIGNED DEFAULT NULL,
    `party_name` VARCHAR(191) DEFAULT NULL,
    `pan_number` VARCHAR(50) DEFAULT NULL,
    `gross_amount` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `tds_amount` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `remarks` VARCHAR(2048) DEFAULT NULL,
    `added_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tds_type` (`tds_type_id`),
    KEY `idx_tds_fy` (`fiscal_year_id`),
    CONSTRAINT `fk_tds_type`
        FOREIGN KEY (`tds_type_id`) REFERENCES `tbl_account_tds_types` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_tds_fy`
        FOREIGN KEY (`fiscal_year_id`) REFERENCES `tbl_fiscal_years` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_account_tds_report_entries`;',
];
