<?php

$query = [
    "CREATE TABLE `tbl_office_notices` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(191) NOT NULL,
    `description` TEXT,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `added_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_by` INT DEFAULT NULL,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_officenotice_active` (`is_active`),
    CONSTRAINT `fk_officenotice_added`
        FOREIGN KEY (`added_by`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_office_notices`;',
];