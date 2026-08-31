<?php

$query = [
    "CREATE TABLE `tbl_cms_galleries` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `gallery_category_id` INT DEFAULT NULL,
    `title` VARCHAR(255) DEFAULT NULL,
    `image_name` VARCHAR(255) DEFAULT NULL,
    `image_location` VARCHAR(255) DEFAULT NULL,
    `position` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `seo_meta_keywords` TEXT,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_gallery_category` (`gallery_category_id`),
    CONSTRAINT `fk_gallery_category`
        FOREIGN KEY (`gallery_category_id`) REFERENCES `tbl_cms_gallery_categories` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_cms_galleries`;',
];
