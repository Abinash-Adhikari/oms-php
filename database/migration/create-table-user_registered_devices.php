<?php

$query = [
    "CREATE TABLE `tbl_user_registered_devices` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `device_name` VARCHAR(191) DEFAULT NULL,
    `device_token` VARCHAR(255) DEFAULT NULL,
    `ip_address` VARCHAR(50) DEFAULT NULL,
    `user_agent` TEXT,
    `status` ENUM('Active','Block') DEFAULT 'Active',
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_device_user` (`user_id`),
    CONSTRAINT `fk_device_user`
        FOREIGN KEY (`user_id`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_user_registered_devices`;',
];
