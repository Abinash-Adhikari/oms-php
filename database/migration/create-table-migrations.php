<?php

$query = [
    "CREATE TABLE `tbl_migrations` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `filename` TEXT,
    `executed_on` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_migrations`;',
];
