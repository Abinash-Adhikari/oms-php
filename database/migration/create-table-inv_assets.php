<?php

$query = [
    "CREATE TABLE `tbl_inv_assets` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `asset_tag` VARCHAR(50) NOT NULL,
    `item_id` INT DEFAULT NULL,
    `name` VARCHAR(191) NOT NULL,
    `serial_number` VARCHAR(100) DEFAULT NULL,
    `brand` VARCHAR(100) DEFAULT NULL,
    `model` VARCHAR(100) DEFAULT NULL,
    `purchase_date` DATE DEFAULT NULL,
    `purchase_price` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `warranty_expiry` DATE DEFAULT NULL,
    `warranty_notes` TEXT,
    `condition_status` ENUM('New','Good','Fair','Poor','Damaged','Retired') NOT NULL DEFAULT 'New',
    `current_status` ENUM('In Stock','Assigned','Under Maintenance','Retired','Disposed') NOT NULL DEFAULT 'In Stock',
    `assigned_to` INT DEFAULT NULL,
    `assigned_on` DATE DEFAULT NULL,
    `location` VARCHAR(100) DEFAULT NULL,
    `notes` TEXT,
    `photo` VARCHAR(255) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_invasset_tag` (`asset_tag`),
    KEY `idx_invasset_item` (`item_id`),
    KEY `idx_invasset_status` (`current_status`),
    KEY `idx_invasset_assigned` (`assigned_to`),
    CONSTRAINT `fk_invasset_item` FOREIGN KEY (`item_id`) REFERENCES `tbl_inv_items` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_invasset_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `tbl_users_login` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_invasset_addedby` FOREIGN KEY (`added_by`) REFERENCES `tbl_users_login` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_inv_assets`;',
];
