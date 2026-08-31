<?php

$query = [
    "CREATE TABLE `tbl_voucher_logs` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `voucher_type` VARCHAR(64) DEFAULT NULL,
    `voucher_type_id` INT DEFAULT NULL,
    `voucher_no` VARCHAR(32) DEFAULT NULL,
    `action` ENUM('Create','Update','Approve','Reject','Unapprove','Void','Delete') DEFAULT NULL,
    `old_data` JSON DEFAULT NULL,
    `new_data` JSON DEFAULT NULL,
    `remarks` VARCHAR(2048) DEFAULT NULL,
    `added_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_vlog_voucher` (`voucher_type`, `voucher_type_id`),
    KEY `idx_vlog_actor` (`added_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_voucher_logs`;',
];
