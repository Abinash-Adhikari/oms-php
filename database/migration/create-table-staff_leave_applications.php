<?php

$query = [
    "CREATE TABLE `tbl_staff_leave_applications` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `staff_id` INT NOT NULL,
    `leave_type_id` INT NOT NULL,
    `absence_filler` INT DEFAULT NULL,
    `half_day` TINYINT(1) NOT NULL DEFAULT 0,
    `first_half` TINYINT(1) NOT NULL DEFAULT 0,
    `from_date` DATE NOT NULL,
    `to_date` DATE NOT NULL,
    `leave_days` DECIMAL(5,1) NOT NULL,
    `reason` TEXT,
    `status` ENUM('Pending','Verified','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    `reject_reason` TEXT,
    `verified_by` INT DEFAULT NULL,
    `approved_by` INT DEFAULT NULL,
    `year` VARCHAR(20) DEFAULT NULL,
    `leave_year` VARCHAR(50) DEFAULT NULL,
    `leave_year_id` INT DEFAULT NULL,
    `added_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_by` INT DEFAULT NULL,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_leaveapp_staff` (`staff_id`),
    KEY `idx_leaveapp_type` (`leave_type_id`),
    KEY `idx_leaveapp_status` (`status`),
    KEY `idx_leaveapp_dates` (`from_date`, `to_date`),
    CONSTRAINT `fk_leaveapp_staff`
        FOREIGN KEY (`staff_id`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_leaveapp_type`
        FOREIGN KEY (`leave_type_id`) REFERENCES `tbl_office_leave_configs` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_leaveapp_filler`
        FOREIGN KEY (`absence_filler`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_leaveapp_verified`
        FOREIGN KEY (`verified_by`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_leaveapp_approved`
        FOREIGN KEY (`approved_by`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_staff_leave_applications`;',
];
