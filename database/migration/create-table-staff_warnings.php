<?php

$query = [
    "CREATE TABLE `tbl_staff_warnings` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `staff_id` INT NOT NULL,
    `warning_type` ENUM('Verbal','Written','Final') NOT NULL DEFAULT 'Written',
    `title` VARCHAR(191) NOT NULL,
    `description` TEXT,
    `issued_on` DATE DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `added_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_by` INT DEFAULT NULL,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_staffwarning_staff` (`staff_id`),
    KEY `idx_staffwarning_type` (`warning_type`),
    KEY `idx_staffwarning_active` (`is_active`),
    CONSTRAINT `fk_staffwarning_staff`
        FOREIGN KEY (`staff_id`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_staffwarning_added`
        FOREIGN KEY (`added_by`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_staff_warnings`;',
];