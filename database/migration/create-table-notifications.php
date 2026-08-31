<?php

$query = [
    "CREATE TABLE `tbl_notifications` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `details` TEXT,
    `ref_id` VARCHAR(255) DEFAULT NULL,
    `receiver` INT DEFAULT NULL,
    `type` VARCHAR(100) DEFAULT NULL,
    `viewed` INT NOT NULL DEFAULT 0,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `added_by` INT DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_notif_receiver` (`receiver`, `viewed`),
    CONSTRAINT `fk_notif_receiver`
        FOREIGN KEY (`receiver`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_notifications`;',
];
