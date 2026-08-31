<?php

$query = [
    "CREATE TABLE `tbl_communication_settings` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `smtp_host` VARCHAR(191) DEFAULT NULL,
    `smtp_port` INT DEFAULT NULL,
    `smtp_username` VARCHAR(191) DEFAULT NULL,
    `smtp_password_enc` TEXT,
    `smtp_from_name` VARCHAR(191) DEFAULT NULL,
    `smtp_from_email` VARCHAR(191) DEFAULT NULL,
    `sms_provider` VARCHAR(50) DEFAULT NULL,
    `sms_api_key_enc` TEXT,
    `sms_sender_id` VARCHAR(50) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_communication_settings`;',
];
