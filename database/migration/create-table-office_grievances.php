<?php

$query = [
    "CREATE TABLE `tbl_office_grievances` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `assignment_id` VARCHAR(64) DEFAULT NULL,
    `title` VARCHAR(191) NOT NULL,
    `description` MEDIUMTEXT,
    `author` INT DEFAULT NULL,
    `assigned` INT DEFAULT NULL,
    `deadline` DATETIME DEFAULT NULL,
    `status` ENUM('Pending','In Progress','Done','Rejected','Acknowledged') DEFAULT 'Pending',
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_grievance_author` (`author`),
    KEY `idx_grievance_assigned` (`assigned`),
    KEY `idx_grievance_status` (`status`),
    CONSTRAINT `fk_grievance_author`
        FOREIGN KEY (`author`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_grievance_assigned`
        FOREIGN KEY (`assigned`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_office_grievances`;',
];
