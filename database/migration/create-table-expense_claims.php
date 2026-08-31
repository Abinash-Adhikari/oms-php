<?php

$query = [
    "CREATE TABLE `tbl_expense_claims` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `staff_id` INT NOT NULL,
    `claim_no` VARCHAR(32) NOT NULL,
    `category` VARCHAR(191) DEFAULT NULL,
    `expense_date` DATE DEFAULT NULL,
    `description` TEXT,
    `amount` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `currency_code` CHAR(3) NOT NULL DEFAULT 'NPR',
    `project_id` INT DEFAULT NULL,
    `client_id` INT DEFAULT NULL,
    `status` ENUM('Draft','Submitted','Approved','Rejected','Paid') NOT NULL DEFAULT 'Draft',
    `reject_reason` TEXT,
    `approved_by` INT DEFAULT NULL,
    `approved_on` DATETIME DEFAULT NULL,
    `payment_voucher_id` INT DEFAULT NULL,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_claim_no` (`claim_no`),
    KEY `idx_claim_staff` (`staff_id`),
    KEY `idx_claim_status` (`status`),
    KEY `idx_claim_payment` (`payment_voucher_id`),
    CONSTRAINT `fk_claim_staff`
        FOREIGN KEY (`staff_id`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_claim_approved_by`
        FOREIGN KEY (`approved_by`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_claim_payment`
        FOREIGN KEY (`payment_voucher_id`) REFERENCES `tbl_payment_vouchers` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_expense_claims`;',
];
