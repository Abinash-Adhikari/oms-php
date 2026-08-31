<?php

$query = [
    "CREATE TABLE `tbl_office_document_files` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(191) DEFAULT NULL,
    `document_id` INT NOT NULL,
    `file_location` VARCHAR(255) DEFAULT NULL,
    `file_name` VARCHAR(255) DEFAULT NULL,
    `file_extension` VARCHAR(50) DEFAULT NULL,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_docfile_document` (`document_id`),
    CONSTRAINT `fk_docfile_document`
        FOREIGN KEY (`document_id`) REFERENCES `tbl_office_documents` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_office_document_files`;',
];
