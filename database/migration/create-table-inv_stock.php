<?php

$query = [
    "CREATE TABLE `tbl_inv_stock` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `item_id` INT NOT NULL,
    `quantity` INT NOT NULL DEFAULT 0,
    `reserved` INT NOT NULL DEFAULT 0,
    `location` VARCHAR(100) DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_invstock_item_loc` (`item_id`, `location`),
    CONSTRAINT `fk_invstock_item` FOREIGN KEY (`item_id`) REFERENCES `tbl_inv_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_invstock_updatedby` FOREIGN KEY (`updated_by`) REFERENCES `tbl_users_login` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_inv_stock`;',
];
