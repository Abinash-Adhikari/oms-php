<?php

$query = [
    "CREATE TABLE `tbl_cms_setup` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `site_title` VARCHAR(255) DEFAULT NULL,
    `tagline` VARCHAR(255) DEFAULT NULL,
    `template` VARCHAR(50) DEFAULT 'classic',
    `primary_color` VARCHAR(20) DEFAULT NULL,
    `secondary_color` VARCHAR(20) DEFAULT NULL,
    `maps_embed` TEXT,
    `contact_email` VARCHAR(191) DEFAULT NULL,
    `contact_phone` VARCHAR(50) DEFAULT NULL,
    `facebook` VARCHAR(255) DEFAULT NULL,
    `instagram` VARCHAR(255) DEFAULT NULL,
    `linkedin` VARCHAR(255) DEFAULT NULL,
    `twitter` VARCHAR(255) DEFAULT NULL,
    `seo_meta_keywords` TEXT,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_cms_setup`;',
];
