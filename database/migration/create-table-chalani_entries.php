<?php

$query = [
    "CREATE TABLE `tbl_chalani_entries` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `fiscal_year_id` INT UNSIGNED NOT NULL,
    `chalani_no` INT NOT NULL,
    `letter_date` DATE NOT NULL,
    `status` ENUM('Reserved','Dispatched','Cancelled') NOT NULL DEFAULT 'Reserved',
    `dispatched_on` DATETIME DEFAULT NULL,
    `recipient_type` ENUM('Office','Person','Other') NOT NULL DEFAULT 'Office',
    `recipient_name` VARCHAR(255) NOT NULL,
    `recipient_address` VARCHAR(255) DEFAULT NULL,
    `cc_recipients` TEXT DEFAULT NULL,
    `dispatch_mode` ENUM('Hand','Post','Email','Courier','Other') NOT NULL DEFAULT 'Hand',
    `subject` VARCHAR(500) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `sent_by` INT DEFAULT NULL,
    `ref_darta_id` INT DEFAULT NULL,
    `added_by` INT DEFAULT NULL,
    `added_on` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_by` INT DEFAULT NULL,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_fy_chalani_no` (`fiscal_year_id`, `chalani_no`),
    KEY `idx_chalani_letter_date` (`letter_date`),
    KEY `idx_chalani_status` (`status`),
    KEY `idx_chalani_ref_darta` (`ref_darta_id`),
    CONSTRAINT `fk_chalani_fy`
        FOREIGN KEY (`fiscal_year_id`) REFERENCES `tbl_fiscal_years` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_chalani_sent_by`
        FOREIGN KEY (`sent_by`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_chalani_ref_darta`
        FOREIGN KEY (`ref_darta_id`) REFERENCES `tbl_darta_entries` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_chalani_added`
        FOREIGN KEY (`added_by`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_chalani_updated`
        FOREIGN KEY (`updated_by`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_chalani_entries`;',
];
