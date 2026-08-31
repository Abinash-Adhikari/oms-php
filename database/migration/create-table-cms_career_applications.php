<?php

$query = [
    "CREATE TABLE `tbl_cms_career_applications` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `career_id` INT NOT NULL,
    `applicant_name` VARCHAR(191) NOT NULL,
    `email` VARCHAR(191) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `cover_letter` TEXT,
    `resume_name` VARCHAR(255) DEFAULT NULL,
    `resume_location` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('New','Shortlisted','Interview','Offer','Rejected') NOT NULL DEFAULT 'New',
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_careerapp_career` (`career_id`),
    KEY `idx_careerapp_status` (`status`),
    CONSTRAINT `fk_careerapp_career`
        FOREIGN KEY (`career_id`) REFERENCES `tbl_cms_careers` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_cms_career_applications`;',
];
