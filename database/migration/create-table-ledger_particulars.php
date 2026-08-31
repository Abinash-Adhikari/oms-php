<?php

$query = [
    "CREATE TABLE `tbl_ledger_particulars` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `voucher_type_id` INT NOT NULL,
    `voucher_type` VARCHAR(64) NOT NULL COMMENT 'Journal, Receipt, Payment, Contra, Purchase, Sales, Opening Balance',
    `voucher_status` ENUM('Approved','Pending') NOT NULL DEFAULT 'Pending',
    `particulars_date` DATE NOT NULL,
    `fiscal_year_id` INT UNSIGNED DEFAULT NULL,
    `account_group_id` INT DEFAULT NULL,
    `account_subgroup_id` INT DEFAULT NULL,
    `account_terminal_id` INT DEFAULT NULL,
    `account_group_title` VARCHAR(191) DEFAULT NULL,
    `account_subgroup_title` VARCHAR(191) DEFAULT NULL,
    `account_terminal_title` VARCHAR(255) DEFAULT NULL,
    `account_sub_terminal_id` INT DEFAULT NULL,
    `debit` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `credit` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `currency_code` CHAR(3) NOT NULL DEFAULT 'NPR',
    `fx_rate` DECIMAL(18,8) NOT NULL DEFAULT 1.00000000,
    `remarks` VARCHAR(2048) DEFAULT NULL,
    `reconcile_ref` VARCHAR(128) DEFAULT NULL,
    `reconciled_on` DATETIME DEFAULT NULL,
    `reconciled_by` INT DEFAULT NULL,
    `contra_terminal_id` INT DEFAULT NULL,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lp_voucher` (`voucher_type`, `voucher_type_id`),
    KEY `idx_lp_date` (`particulars_date`),
    KEY `idx_lp_fy_terminal` (`fiscal_year_id`, `account_terminal_id`),
    KEY `idx_lp_terminal` (`account_terminal_id`),
    KEY `idx_lp_status` (`voucher_status`),
    CONSTRAINT `fk_lp_fy`
        FOREIGN KEY (`fiscal_year_id`) REFERENCES `tbl_fiscal_years` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_lp_terminal`
        FOREIGN KEY (`account_terminal_id`) REFERENCES `tbl_account_terminals` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_lp_subterminal`
        FOREIGN KEY (`account_sub_terminal_id`) REFERENCES `tbl_account_sub_terminals` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_lp_reconciled_by`
        FOREIGN KEY (`reconciled_by`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_ledger_particulars`;',
];
