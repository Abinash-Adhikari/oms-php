<?php

$query = [
    "CREATE TABLE `tbl_office_events` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(191) NOT NULL,
    `start_date` DATE DEFAULT NULL,
    `end_date` DATE DEFAULT NULL,
    `type` VARCHAR(50) DEFAULT NULL,
    `privacy` ENUM('Public','Private') NOT NULL DEFAULT 'Public',
    `schedules` VARCHAR(255) DEFAULT NULL,
    `venue_type` ENUM('In Office','Out of Office') DEFAULT 'In Office',
    `venue_location` VARCHAR(255) DEFAULT NULL,
    `attendees_staffs` TEXT,
    `attendees_department` INT DEFAULT NULL,
    `other_attendees` VARCHAR(255) DEFAULT NULL,
    `remarks` TEXT,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_event_type` (`type`),
    KEY `idx_event_privacy` (`privacy`),
    KEY `idx_event_added_by` (`added_by`),
    CONSTRAINT `fk_event_author`
        FOREIGN KEY (`added_by`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_office_events`;',
];
