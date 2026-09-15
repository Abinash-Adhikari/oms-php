<?php

/**
 * SB-Tech — Personal to-do items shown on the office calendar.
 *
 * A todo is a lightweight personal checklist item (creator + Super Admin
 * can see/manage it).  It is separate from tbl_office_events because an
 * event carries schedules/venue/attendees while a todo only needs a due
 * date, an optional time and a completed flag.
 */

$query = [
    "CREATE TABLE `tbl_office_todos` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(191) NOT NULL,
    `todo_date` DATE NOT NULL,
    `todo_time` TIME DEFAULT NULL,
    `remarks` VARCHAR(1024) DEFAULT NULL,
    `completed` TINYINT(1) NOT NULL DEFAULT 0,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_todo_staff_date` (`added_by`, `todo_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_office_todos`;',
];