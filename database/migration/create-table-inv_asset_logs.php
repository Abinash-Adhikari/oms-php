<?php

$query = [
    "CREATE TABLE `tbl_inv_asset_logs` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `asset_id` INT NOT NULL,
    `action` ENUM('Assigned','Returned','Maintenance','Condition Change','Status Change','Note') NOT NULL,
    `old_value` VARCHAR(500) DEFAULT NULL,
    `new_value` VARCHAR(500) DEFAULT NULL,
    `remarks` TEXT,
    `performed_by` INT DEFAULT NULL,
    `performed_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_invassetlog_asset` (`asset_id`),
    CONSTRAINT `fk_invassetlog_asset` FOREIGN KEY (`asset_id`) REFERENCES `tbl_inv_assets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_invassetlog_actor` FOREIGN KEY (`performed_by`) REFERENCES `tbl_users_login` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_inv_asset_logs`;',
];
