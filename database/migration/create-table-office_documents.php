<?php

$query = [
    "CREATE TABLE `tbl_office_documents` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `filename` VARCHAR(255) DEFAULT NULL,
    `type` VARCHAR(100) DEFAULT NULL,
    `size` INT DEFAULT NULL,
    `renew_date` DATE DEFAULT NULL,
    `access_type` ENUM('Public','Private') NOT NULL DEFAULT 'Public',
    `category_id` INT DEFAULT NULL,
    `category` VARCHAR(191) DEFAULT NULL,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_doc_category` (`category_id`),
    KEY `idx_doc_access` (`access_type`),
    KEY `idx_doc_renew` (`renew_date`),
    CONSTRAINT `fk_doc_category`
        FOREIGN KEY (`category_id`) REFERENCES `tbl_office_document_category` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_office_documents`;',
];
