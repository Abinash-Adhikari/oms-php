<?php

$query = [
    "CREATE TABLE `tbl_expense_claim_files` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `claim_id` INT NOT NULL,
    `file_location` VARCHAR(255) DEFAULT NULL,
    `file_name` VARCHAR(255) DEFAULT NULL,
    `file_extension` VARCHAR(50) DEFAULT NULL,
    `file_size` INT DEFAULT NULL,
    `added_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_claimfile_claim` (`claim_id`),
    CONSTRAINT `fk_claimfile_claim`
        FOREIGN KEY (`claim_id`) REFERENCES `tbl_expense_claims` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_expense_claim_files`;',
];
