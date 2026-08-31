<?php

$query = [
    "CREATE TABLE `tbl_cms_careers` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(191) NOT NULL,
    `slug` VARCHAR(255) DEFAULT NULL,
    `department_id` INT DEFAULT NULL,
    `designation` VARCHAR(191) DEFAULT NULL,
    `location` VARCHAR(191) DEFAULT NULL,
    `job_type` ENUM('Full-time','Part-time','Contract','Internship') DEFAULT 'Full-time',
    `salary` VARCHAR(100) DEFAULT NULL,
    `description` LONGTEXT,
    `requirements` TEXT,
    `deadline` DATE DEFAULT NULL,
    `status` ENUM('Open','Closed') NOT NULL DEFAULT 'Open',
    `seo_meta_keywords` TEXT,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_career_slug` (`slug`),
    KEY `idx_career_status` (`status`),
    CONSTRAINT `fk_career_department`
        FOREIGN KEY (`department_id`) REFERENCES `tbl_office_departments` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_cms_careers`;',
];
