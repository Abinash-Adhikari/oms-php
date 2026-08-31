<?php

$query = [
    "CREATE TABLE `tbl_login_attempts` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(191) DEFAULT NULL,
    `date` DATE DEFAULT NULL,
    `time` TIME DEFAULT NULL,
    `user_agent` TEXT,
    `executed_on` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_username_date` (`username`, `date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_login_attempts`;',
];
