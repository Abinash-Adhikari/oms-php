<?php

$query = [
    "CREATE TABLE `tbl_communication_signatures` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(191) DEFAULT NULL,
    `html` MEDIUMTEXT,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_communication_signatures`;',
];
