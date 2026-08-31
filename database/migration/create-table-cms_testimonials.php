<?php

$query = [
    "CREATE TABLE `tbl_cms_testimonials` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `client_name` VARCHAR(191) NOT NULL,
    `client_position` VARCHAR(191) DEFAULT NULL,
    `client_company` VARCHAR(191) DEFAULT NULL,
    `testimonial` TEXT,
    `rating` TINYINT NOT NULL DEFAULT 5,
    `image_name` VARCHAR(255) DEFAULT NULL,
    `image_location` VARCHAR(255) DEFAULT NULL,
    `position` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `seo_meta_keywords` TEXT,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_cms_testimonials`;',
];
