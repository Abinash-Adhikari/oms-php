<?php

$query = [
    "CREATE TABLE `tbl_office_grievance_files` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `ref_id` INT NOT NULL,
    `type` ENUM('grievance','Update') DEFAULT 'grievance',
    `file_location` VARCHAR(255) DEFAULT NULL,
    `filename` VARCHAR(255) DEFAULT NULL,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_grievancefile_ref` (`ref_id`),
    CONSTRAINT `fk_grievancefile_ref`
        FOREIGN KEY (`ref_id`) REFERENCES `tbl_office_grievances` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_office_grievance_files`;',
];
