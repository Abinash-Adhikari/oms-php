<?php

$query = [
    "CREATE TABLE `tbl_inv_categories` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(191) NOT NULL,
    `description` TEXT,
    `parent_id` INT DEFAULT NULL,
    `position` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_invcat_title` (`title`),
    KEY `idx_invcat_parent` (`parent_id`),
    CONSTRAINT `fk_invcat_parent` FOREIGN KEY (`parent_id`) REFERENCES `tbl_inv_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_invcat_addedby` FOREIGN KEY (`added_by`) REFERENCES `tbl_users_login` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_inv_categories`;',
];
