<?php

$query = [
    "CREATE TABLE `tbl_office_tasks` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `assignment_id` VARCHAR(64) DEFAULT NULL,
    `title` VARCHAR(191) NOT NULL,
    `description` MEDIUMTEXT,
    `author` INT DEFAULT NULL,
    `deadline` DATETIME DEFAULT NULL,
    `status` ENUM('Pending','In Progress','Done','Rejected','Cancelled') DEFAULT 'Pending',
    `department_id` INT DEFAULT NULL,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_task_author` (`author`),
    KEY `idx_task_status` (`status`),
    KEY `idx_task_deadline` (`deadline`),
    KEY `idx_task_department` (`department_id`),
    CONSTRAINT `fk_task_author`
        FOREIGN KEY (`author`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_task_department`
        FOREIGN KEY (`department_id`) REFERENCES `tbl_office_departments` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_office_tasks`;',
];
