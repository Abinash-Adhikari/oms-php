<?php

$query = [
    "CREATE TABLE `tbl_ledger_closings` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `fiscal_year_id` INT UNSIGNED NOT NULL,
    `ledger_id` INT DEFAULT NULL COMMENT 'Account terminal carried forward',
    `start_date` DATE DEFAULT NULL,
    `end_date` DATE DEFAULT NULL,
    `closing_debit` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `closing_credit` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `remarks` VARCHAR(2048) DEFAULT NULL,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ledgerclose_fy` (`fiscal_year_id`),
    KEY `idx_ledgerclose_ledger` (`ledger_id`),
    CONSTRAINT `fk_ledgerclose_fy`
        FOREIGN KEY (`fiscal_year_id`) REFERENCES `tbl_fiscal_years` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_ledgerclose_ledger`
        FOREIGN KEY (`ledger_id`) REFERENCES `tbl_account_terminals` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_ledger_closings`;',
];
