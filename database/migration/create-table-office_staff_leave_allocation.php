<?php

$query = [
    "CREATE TABLE `tbl_office_staff_leave_allocation` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `year` INT NOT NULL,
    `leave_id` INT NOT NULL,
    `staff_id` INT NOT NULL,
    `allocated_days` DECIMAL(5,1) NOT NULL DEFAULT 0.0,
    `used_days` DECIMAL(5,1) NOT NULL DEFAULT 0.0,
    `carry_forward_days` DECIMAL(5,1) NOT NULL DEFAULT 0.0,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_leave_allocation` (`year`, `leave_id`, `staff_id`),
    KEY `idx_leavealloc_staff` (`staff_id`),
    KEY `idx_leavealloc_leave` (`leave_id`),
    CONSTRAINT `fk_leavealloc_staff`
        FOREIGN KEY (`staff_id`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_leavealloc_leave`
        FOREIGN KEY (`leave_id`) REFERENCES `tbl_office_leave_configs` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_office_staff_leave_allocation`;',
];
