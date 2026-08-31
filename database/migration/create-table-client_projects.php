<?php

$query = [
    "CREATE TABLE `tbl_client_projects` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `client_id` INT NOT NULL,
    `title` VARCHAR(191) NOT NULL,
    `description` TEXT,
    `value` DECIMAL(18,4) DEFAULT NULL,
    `start_date` DATE DEFAULT NULL,
    `end_date` DATE DEFAULT NULL,
    `status` ENUM('Active','Completed','On Hold','Cancelled') NOT NULL DEFAULT 'Active',
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_project_client` (`client_id`),
    CONSTRAINT `fk_project_client`
        FOREIGN KEY (`client_id`) REFERENCES `tbl_clients` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_client_projects`;',
];
