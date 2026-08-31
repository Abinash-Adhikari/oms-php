<?php

$query = [
    "CREATE TABLE `tbl_inv_purchase_requisition_items` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `pr_id` INT NOT NULL,
    `item_id` INT DEFAULT NULL,
    `item_name` VARCHAR(191) NOT NULL,
    `description` VARCHAR(500) DEFAULT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `unit` VARCHAR(30) NOT NULL DEFAULT 'pcs',
    `estimated_unit_cost` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `total_cost` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    `received_qty` INT NOT NULL DEFAULT 0,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_invpritem_pr` (`pr_id`),
    KEY `idx_invpritem_item` (`item_id`),
    CONSTRAINT `fk_invpritem_pr` FOREIGN KEY (`pr_id`) REFERENCES `tbl_inv_purchase_requisitions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_invpritem_item` FOREIGN KEY (`item_id`) REFERENCES `tbl_inv_items` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_inv_purchase_requisition_items`;',
];
