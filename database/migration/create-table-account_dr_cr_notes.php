<?php

$query = [
    "CREATE TABLE `tbl_account_dr_cr_notes` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `note_type` ENUM('Debit Note','Credit Note') NOT NULL,
    `fiscal_year_id` INT UNSIGNED NOT NULL,
    `note_no` VARCHAR(32) NOT NULL,
    `note_date` DATE NOT NULL,
    `party_name` VARCHAR(191) DEFAULT NULL,
    `amount` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `tax_amount` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `total_amount` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `narration` VARCHAR(2048) DEFAULT NULL,
    `status` ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `approved_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_fy_note_no` (`fiscal_year_id`, `note_type`, `note_no`),
    CONSTRAINT `fk_drcr_fy`
        FOREIGN KEY (`fiscal_year_id`) REFERENCES `tbl_fiscal_years` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_account_dr_cr_notes`;',
];
