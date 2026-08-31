<?php

$query = [
    "CREATE TABLE `tbl_calendar` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `nepali_year` INT NOT NULL DEFAULT 0,
    `month_code` INT NOT NULL DEFAULT 0,
    `eng_start_date` DATE DEFAULT NULL,
    `eng_end_date` DATE DEFAULT NULL,
    `no_days` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_cal_year_month` (`nepali_year`, `month_code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_calendar`;',
];
