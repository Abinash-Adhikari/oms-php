<?php

$query = [
    "CREATE TABLE `tbl_inv_stock_movements` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `item_id` INT NOT NULL,
    `movement_type` ENUM('Purchase','Issue','Return','Transfer','Adjustment','Write-off','Opening') NOT NULL,
    `quantity` INT NOT NULL,
    `direction` ENUM('In','Out') NOT NULL,
    `reference_no` VARCHAR(50) DEFAULT NULL,
    `from_location` VARCHAR(100) DEFAULT NULL,
    `to_location` VARCHAR(100) DEFAULT NULL,
    `unit_cost` DECIMAL(18,4) DEFAULT NULL,
    `total_cost` DECIMAL(18,4) DEFAULT NULL,
    `supplier_id` INT DEFAULT NULL,
    `issued_to` INT DEFAULT NULL,
    `remarks` TEXT,
    `date` DATE NOT NULL,
    `added_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_invmove_item` (`item_id`),
    KEY `idx_invmove_type` (`movement_type`),
    KEY `idx_invmove_date` (`date`),
    KEY `idx_invmove_supplier` (`supplier_id`),
    KEY `idx_invmove_issuedto` (`issued_to`),
    CONSTRAINT `fk_invmove_item` FOREIGN KEY (`item_id`) REFERENCES `tbl_inv_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_invmove_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `tbl_inv_suppliers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_invmove_issuedto` FOREIGN KEY (`issued_to`) REFERENCES `tbl_users_login` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_invmove_addedby` FOREIGN KEY (`added_by`) REFERENCES `tbl_users_login` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_inv_stock_movements`;',
];
