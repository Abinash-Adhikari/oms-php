<?php

$query = [
    "CREATE TABLE `tbl_account_confirmation_letters` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `fiscal_year_id` INT UNSIGNED NOT NULL,
    `letter_no` VARCHAR(32) NOT NULL,
    `letter_date` DATE NOT NULL,
    `party_name` VARCHAR(191) DEFAULT NULL,
    `party_address` VARCHAR(255) DEFAULT NULL,
    `balance_amount` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `narration` VARCHAR(2048) DEFAULT NULL,
    `status` ENUM('Draft','Sent','Acknowledged') NOT NULL DEFAULT 'Draft',
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_conf_fy` (`fiscal_year_id`),
    CONSTRAINT `fk_conf_fy`
        FOREIGN KEY (`fiscal_year_id`) REFERENCES `tbl_fiscal_years` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_account_confirmation_letters`;',
];
