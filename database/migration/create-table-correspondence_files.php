<?php

$query = [
    "CREATE TABLE `tbl_correspondence_files` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `register` ENUM('darta','chalani') NOT NULL,
    `entry_id` INT NOT NULL,
    `file_location` VARCHAR(255) DEFAULT NULL,
    `file_name` VARCHAR(255) DEFAULT NULL,
    `file_extension` VARCHAR(50) DEFAULT NULL,
    `file_size` INT DEFAULT NULL,
    `added_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_correspondence_files_entry` (`register`, `entry_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_correspondence_files`;',
];
