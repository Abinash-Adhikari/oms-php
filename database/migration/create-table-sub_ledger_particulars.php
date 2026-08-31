<?php

$query = [
    "CREATE TABLE `tbl_sub_ledger_particulars` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `ledger_particular_id` BIGINT NOT NULL,
    `voucher_type` VARCHAR(64) DEFAULT NULL,
    `voucher_type_id` INT DEFAULT NULL,
    `particulars_date` DATE DEFAULT NULL,
    `account_terminal_id` INT DEFAULT NULL,
    `sub_ledger_name` VARCHAR(191) DEFAULT NULL,
    `debit` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `credit` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `remarks` VARCHAR(2048) DEFAULT NULL,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_slp_particular` (`ledger_particular_id`),
    KEY `idx_slp_terminal` (`account_terminal_id`),
    CONSTRAINT `fk_slp_particular`
        FOREIGN KEY (`ledger_particular_id`) REFERENCES `tbl_ledger_particulars` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_sub_ledger_particulars`;',
];
