<?php

$query = [
    "INSERT INTO `tbl_account_sub_groups` (`group_id`, `title`, `position`) VALUES
    (1, 'Current Assets', 1),
    (1, 'Fixed Assets', 2),
    (2, 'Current Liabilities', 1),
    (2, 'Long Term Liabilities', 2),
    (3, 'Service Income', 1),
    (3, 'Other Income', 2),
    (4, 'Operating Expenses', 1),
    (4, 'Administrative Expenses', 2),
    (5, 'Owner Capital', 1)
    ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);",
];

$rollbackQuery = [
    "DELETE FROM `tbl_account_sub_groups`;",
];
