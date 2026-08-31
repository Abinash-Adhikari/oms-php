<?php

$query = [
    "CREATE TABLE `tbl_bank_reconciliation` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `fiscal_year_id` INT UNSIGNED NOT NULL,
    `account_terminal_id` INT NOT NULL COMMENT 'Bank account terminal from chart of accounts',
    `statement_ref` VARCHAR(128) DEFAULT NULL,
    `statement_date` DATE DEFAULT NULL,
    `opening_balance` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `total_statement_amount` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `total_matched_amount` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `status` ENUM('Open','Matched','Closed') NOT NULL DEFAULT 'Open',
    `remarks` VARCHAR(2048) DEFAULT NULL,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_bankrec_fy` (`fiscal_year_id`),
    KEY `idx_bankrec_terminal` (`account_terminal_id`),
    KEY `idx_bankrec_status` (`status`),
    CONSTRAINT `fk_bankrec_fy`
        FOREIGN KEY (`fiscal_year_id`) REFERENCES `tbl_fiscal_years` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_bankrec_terminal`
        FOREIGN KEY (`account_terminal_id`) REFERENCES `tbl_account_terminals` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_bank_reconciliation`;',
];
