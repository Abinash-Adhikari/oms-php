<?php

$query = [
    "CREATE TABLE `tbl_office_leave_configs` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(191) NOT NULL,
    `max_allowed` INT NOT NULL DEFAULT 0,
    `leave_year` VARCHAR(20) DEFAULT NULL,
    `description` TEXT,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `carry_forward` TINYINT(1) NOT NULL DEFAULT 0,
    `max_carry_forward` INT NOT NULL DEFAULT 0,
    `requires_approval` TINYINT(1) NOT NULL DEFAULT 1,
    `gender_specific` ENUM('Male','Female','Both') DEFAULT 'Both',
    `documentation_required` TINYINT(1) NOT NULL DEFAULT 0,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_office_leave_configs`;',
];
