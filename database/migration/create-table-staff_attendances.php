<?php

$query = [
    "CREATE TABLE `tbl_staff_attendances` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `date` DATE NOT NULL,
    `checkin` TIME DEFAULT NULL,
    `checkout` TIME DEFAULT NULL,
    `checkin_delay` INT DEFAULT NULL,
    `checkout_early` INT DEFAULT NULL,
    `reason_checkin` TEXT,
    `reason_checkout` TEXT,
    `config_checkin` TIME DEFAULT NULL,
    `config_checkout` TIME DEFAULT NULL,
    `status` ENUM('present','absent','leave','holiday') NOT NULL DEFAULT 'present',
    `late_checkin` TINYINT(1) NOT NULL DEFAULT 0,
    `early_checkout` TINYINT(1) NOT NULL DEFAULT 0,
    `late_checkin_minutes` INT NOT NULL DEFAULT 0,
    `working_hours` FLOAT DEFAULT NULL,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_attendance_user_date` (`user_id`, `date`),
    KEY `idx_attendance_date` (`date`),
    KEY `idx_attendance_status` (`status`),
    CONSTRAINT `fk_attendance_user`
        FOREIGN KEY (`user_id`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_staff_attendances`;',
];
