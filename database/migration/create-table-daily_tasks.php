<?php

$query = [
    "CREATE TABLE `tbl_daily_tasks` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `staff_id` INT DEFAULT NULL,
    `fullname` VARCHAR(191) DEFAULT NULL,
    `date` DATE DEFAULT NULL,
    `tasks` TEXT,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_dailytask_staff_date` (`staff_id`, `date`),
    CONSTRAINT `fk_dailytask_staff`
        FOREIGN KEY (`staff_id`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_daily_tasks`;',
];
