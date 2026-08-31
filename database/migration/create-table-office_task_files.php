<?php

$query = [
    "CREATE TABLE `tbl_office_task_files` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `ref_id` INT NOT NULL,
    `type` ENUM('Task','Update') DEFAULT 'Task',
    `file_location` VARCHAR(255) DEFAULT NULL,
    `filename` VARCHAR(255) DEFAULT NULL,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_taskfile_task` (`ref_id`),
    CONSTRAINT `fk_taskfile_task`
        FOREIGN KEY (`ref_id`) REFERENCES `tbl_office_tasks` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_office_task_files`;',
];
