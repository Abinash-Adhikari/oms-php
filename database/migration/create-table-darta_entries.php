<?php

$query = [
    "CREATE TABLE `tbl_darta_entries` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `fiscal_year_id` INT UNSIGNED NOT NULL,
    `darta_no` INT NOT NULL,
    `sender_ref_no` VARCHAR(100) DEFAULT NULL,
    `sender_chalani_no` VARCHAR(100) DEFAULT NULL,
    `letter_date` DATE NOT NULL,
    `received_on` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `sender_type` ENUM('Office','Person','Other') NOT NULL DEFAULT 'Person',
    `sender_name` VARCHAR(255) NOT NULL,
    `sender_address` VARCHAR(255) DEFAULT NULL,
    `received_via` ENUM('Hand','Post','Email','Courier','Other') NOT NULL DEFAULT 'Hand',
    `subject` VARCHAR(500) NOT NULL,
    `summary` TEXT DEFAULT NULL,
    `priority` ENUM('Normal','Urgent') NOT NULL DEFAULT 'Normal',
    `assigned_to` INT DEFAULT NULL,
    `status` ENUM('Pending','In Progress','Disposed','Filed','Cancelled') NOT NULL DEFAULT 'Pending',
    `disposal_note` TEXT DEFAULT NULL,
    `disposed_on` DATETIME DEFAULT NULL,
    `disposed_by` INT DEFAULT NULL,
    `added_by` INT DEFAULT NULL,
    `added_on` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_by` INT DEFAULT NULL,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_fy_darta_no` (`fiscal_year_id`, `darta_no`),
    KEY `idx_darta_letter_date` (`letter_date`),
    KEY `idx_darta_status` (`status`),
    KEY `idx_darta_sender_ref` (`sender_ref_no`),
    CONSTRAINT `fk_darta_fy`
        FOREIGN KEY (`fiscal_year_id`) REFERENCES `tbl_fiscal_years` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_darta_assigned`
        FOREIGN KEY (`assigned_to`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_darta_disposed_by`
        FOREIGN KEY (`disposed_by`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_darta_added`
        FOREIGN KEY (`added_by`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_darta_updated`
        FOREIGN KEY (`updated_by`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_darta_entries`;',
];
