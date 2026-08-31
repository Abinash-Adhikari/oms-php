<?php

$query = [
    "CREATE TABLE `tbl_inv_items` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `sku` VARCHAR(50) NOT NULL,
    `name` VARCHAR(191) NOT NULL,
    `description` TEXT,
    `category_id` INT DEFAULT NULL,
    `unit` VARCHAR(30) NOT NULL DEFAULT 'pcs',
    `cost_price` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `sell_price` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `min_stock` INT NOT NULL DEFAULT 0,
    `max_stock` INT NOT NULL DEFAULT 0,
    `reorder_point` INT NOT NULL DEFAULT 0,
    `is_serialized` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `photo` VARCHAR(255) DEFAULT NULL,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_invitem_sku` (`sku`),
    KEY `idx_invitem_category` (`category_id`),
    CONSTRAINT `fk_invitem_category` FOREIGN KEY (`category_id`) REFERENCES `tbl_inv_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_invitem_addedby` FOREIGN KEY (`added_by`) REFERENCES `tbl_users_login` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_inv_items`;',
];
