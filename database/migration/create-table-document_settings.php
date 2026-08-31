<?php

$query = [
    "CREATE TABLE `tbl_document_settings` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `paper_size` ENUM('A4','Letter','Legal') NOT NULL DEFAULT 'A4',
    `orientation` ENUM('Portrait','Landscape') NOT NULL DEFAULT 'Portrait',
    `margin_top_mm` DECIMAL(5,2) NOT NULL DEFAULT 15.00,
    `margin_right_mm` DECIMAL(5,2) NOT NULL DEFAULT 15.00,
    `margin_bottom_mm` DECIMAL(5,2) NOT NULL DEFAULT 15.00,
    `margin_left_mm` DECIMAL(5,2) NOT NULL DEFAULT 15.00,
    `font_family` ENUM('helvetica','times','courier','dejavusans') NOT NULL DEFAULT 'helvetica',
    `font_size_pt` TINYINT UNSIGNED NOT NULL DEFAULT 11,
    `header_mode` ENUM('office_logo','custom_logo','text_only','none') NOT NULL DEFAULT 'office_logo',
    `header_logo_location` VARCHAR(255) DEFAULT NULL,
    `header_title` VARCHAR(191) DEFAULT NULL,
    `header_subtitle` VARCHAR(255) DEFAULT NULL,
    `show_header_line` TINYINT(1) NOT NULL DEFAULT 1,
    `footer_text` VARCHAR(255) DEFAULT NULL,
    `show_page_numbers` TINYINT(1) NOT NULL DEFAULT 1,
    `page_number_format` VARCHAR(50) NOT NULL DEFAULT 'Page {PAGE} of {PAGES}',
    `show_generated_stamp` TINYINT(1) NOT NULL DEFAULT 1,
    `watermark_text` VARCHAR(100) DEFAULT NULL,
    `watermark_opacity` DECIMAL(3,2) NOT NULL DEFAULT 0.08,
    `default_terms` TEXT,
    `signature_block` TEXT,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Single-row configuration (like tbl_office_profiles): id 1 always exists.
    "INSERT INTO `tbl_document_settings` (`id`) VALUES (1);",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_document_settings`;',
];
