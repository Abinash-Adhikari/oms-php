<?php

$query = [
    "INSERT INTO `tbl_fiscal_years` (`id`, `title`, `starting_date`, `ending_date`, `closing`, `added_on`)
    VALUES (1, '2026/27', '2026-07-16', '2027-07-15', 'Open', NOW())
    ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);",
];

$rollbackQuery = [
    "DELETE FROM `tbl_fiscal_years` WHERE `id` = 1;",
];
