<?php

$query = [
    "CREATE TABLE `tbl_staff_documents` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) DEFAULT NULL,
    `staff_id` INT NOT NULL,
    `document_type` VARCHAR(100) NOT NULL,
    `document_name` VARCHAR(255) NOT NULL,
    `size` VARCHAR(50) DEFAULT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `remarks` TEXT,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_staffdoc_staff` (`staff_id`),
    CONSTRAINT `fk_staffdoc_staff`
        FOREIGN KEY (`staff_id`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_staff_documents`;',
];
