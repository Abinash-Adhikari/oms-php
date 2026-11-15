<?php

$query = [
    "CREATE TABLE `tbl_correspondence_counters` (
    `register` ENUM('darta','chalani') NOT NULL,
    `fiscal_year_id` INT UNSIGNED NOT NULL,
    `last_no` INT NOT NULL,
    PRIMARY KEY (`register`, `fiscal_year_id`),
    CONSTRAINT `fk_corrcounter_fy`
        FOREIGN KEY (`fiscal_year_id`) REFERENCES `tbl_fiscal_years` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_correspondence_counters`;',
];
