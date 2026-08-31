<?php

$query = [
    "CREATE TABLE `tbl_lead_files` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `lead_id` INT NOT NULL,
    `file_location` VARCHAR(255) DEFAULT NULL,
    `file_name` VARCHAR(255) DEFAULT NULL,
    `file_extension` VARCHAR(50) DEFAULT NULL,
    `file_size` INT DEFAULT NULL,
    `added_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_leadfile_lead` (`lead_id`),
    CONSTRAINT `fk_leadfile_lead`
        FOREIGN KEY (`lead_id`) REFERENCES `tbl_leads` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_lead_files`;',
];
