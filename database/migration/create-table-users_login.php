<?php

$query = [
    "CREATE TABLE `tbl_users_login` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(191) NOT NULL,
    `email` VARCHAR(191) DEFAULT NULL,
    `password` CHAR(128) NOT NULL,
    `salt` CHAR(128) NOT NULL,
    `fullname` VARCHAR(191) NOT NULL,
    `permitted_modules` MEDIUMTEXT,
    `permitted_submodules` MEDIUMTEXT,
    `special_permission` MEDIUMTEXT,
    `role` VARCHAR(100) DEFAULT NULL,
    `staff_type` ENUM('Admin','Service') NOT NULL DEFAULT 'Admin',
    `phone1` VARCHAR(50) DEFAULT NULL,
    `phone2` VARCHAR(50) DEFAULT NULL,
    `phone3` VARCHAR(50) DEFAULT NULL,
    `address` VARCHAR(255) DEFAULT NULL,
    `gender` ENUM('Male','Female','Other') DEFAULT NULL,
    `physically_challenged` ENUM('Yes','No') NOT NULL DEFAULT 'No',
    `marital_status` ENUM('Married','Unmarried','Divorced','') NOT NULL DEFAULT '',
    `citizenship` VARCHAR(191) DEFAULT NULL,
    `education` MEDIUMTEXT,
    `dob` DATE DEFAULT NULL,
    `department_id` INT DEFAULT NULL,
    `designation_id` INT DEFAULT NULL,
    `join_date` DATE DEFAULT NULL,
    `termination_date` DATE DEFAULT NULL,
    `pan_num` VARCHAR(50) DEFAULT NULL,
    `bank` VARCHAR(191) DEFAULT NULL,
    `bank_account_num` VARCHAR(191) DEFAULT NULL,
    `bank_account_name` VARCHAR(191) DEFAULT NULL,
    `ssf_number` VARCHAR(100) DEFAULT NULL,
    `pf_number` VARCHAR(100) DEFAULT NULL,
    `cit_number` VARCHAR(100) DEFAULT NULL,
    `checkin` TIME DEFAULT NULL,
    `checkout` TIME DEFAULT NULL,
    `daily_working_hour` INT DEFAULT NULL,
    `allow_checkin_by_other` ENUM('Yes','No') DEFAULT 'No',
    `off_day` VARCHAR(100) DEFAULT NULL,
    `status` ENUM('Active','Block','Terminated') NOT NULL DEFAULT 'Active',
    `image_extension` VARCHAR(50) DEFAULT NULL,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_username` (`username`),
    KEY `idx_department` (`department_id`),
    KEY `idx_designation` (`designation_id`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_users_department`
        FOREIGN KEY (`department_id`) REFERENCES `tbl_office_departments` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_users_designation`
        FOREIGN KEY (`designation_id`) REFERENCES `tbl_office_designation` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_users_added_by`
        FOREIGN KEY (`added_by`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_users_updated_by`
        FOREIGN KEY (`updated_by`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_users_login`;',
];
