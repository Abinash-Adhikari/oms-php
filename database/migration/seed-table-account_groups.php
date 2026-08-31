<?php

$query = [
    "INSERT INTO `tbl_account_groups` (`id`, `title`, `position`) VALUES
    (1, 'Assets', 1),
    (2, 'Liabilities', 2),
    (3, 'Income', 3),
    (4, 'Expenses', 4),
    (5, 'Capital / Owner Equity', 5)
    ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);",
];

$rollbackQuery = [
    "DELETE FROM `tbl_account_groups` WHERE `id` IN (1,2,3,4,5);",
];
