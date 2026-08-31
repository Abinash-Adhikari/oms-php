<?php

$query = [
    "CREATE TABLE `tbl_office_meeting_hall_setup` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `hall_name` VARCHAR(191) NOT NULL,
    `occupancy` INT DEFAULT NULL,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_office_meeting_hall_setup`;',
];
