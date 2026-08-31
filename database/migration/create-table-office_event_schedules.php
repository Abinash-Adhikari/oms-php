<?php

$query = [
    "CREATE TABLE `tbl_office_event_schedules` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `event_id` INT NOT NULL,
    `date` DATE DEFAULT NULL,
    `from_time` TIME DEFAULT NULL,
    `to_time` TIME DEFAULT NULL,
    `this_event` VARCHAR(191) DEFAULT NULL,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_schedule_event` (`event_id`),
    KEY `idx_schedule_date` (`date`),
    CONSTRAINT `fk_schedule_event`
        FOREIGN KEY (`event_id`) REFERENCES `tbl_office_events` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_office_event_schedules`;',
];
