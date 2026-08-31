<?php

$query = [
    "CREATE TABLE `tbl_office_holidays` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `from_date` DATE NOT NULL,
    `to_date` DATE NOT NULL,
    `remarks` VARCHAR(255) DEFAULT NULL,
    `department_id` INT DEFAULT NULL,
    `gender_to` ENUM('Male','Female','Both') NOT NULL DEFAULT 'Both',
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_holiday_dates` (`from_date`, `to_date`),
    KEY `idx_holiday_department` (`department_id`),
    CONSTRAINT `fk_holiday_department`
        FOREIGN KEY (`department_id`) REFERENCES `tbl_office_departments` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_office_holidays`;',
];
