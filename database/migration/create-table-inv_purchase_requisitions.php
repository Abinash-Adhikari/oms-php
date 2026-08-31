<?php

$query = [
    "CREATE TABLE `tbl_inv_purchase_requisitions` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `pr_no` VARCHAR(30) NOT NULL,
    `requested_by` INT NOT NULL,
    `department_id` INT DEFAULT NULL,
    `priority` ENUM('Low','Normal','Urgent') NOT NULL DEFAULT 'Normal',
    `status` ENUM('Draft','Submitted','Approved','Rejected','Ordered','Received','Cancelled') NOT NULL DEFAULT 'Draft',
    `total_estimated` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `justification` TEXT,
    `approved_by` INT DEFAULT NULL,
    `approved_on` DATETIME DEFAULT NULL,
    `reject_reason` TEXT,
    `supplier_id` INT DEFAULT NULL,
    `po_no` VARCHAR(50) DEFAULT NULL,
    `received_on` DATE DEFAULT NULL,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_invpr_no` (`pr_no`),
    KEY `idx_invpr_status` (`status`),
    KEY `idx_invpr_requestedby` (`requested_by`),
    CONSTRAINT `fk_invpr_requestedby` FOREIGN KEY (`requested_by`) REFERENCES `tbl_users_login` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_invpr_dept` FOREIGN KEY (`department_id`) REFERENCES `tbl_office_departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_invpr_approvedby` FOREIGN KEY (`approved_by`) REFERENCES `tbl_users_login` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_invpr_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `tbl_inv_suppliers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_invpr_addedby` FOREIGN KEY (`added_by`) REFERENCES `tbl_users_login` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_inv_purchase_requisitions`;',
];
