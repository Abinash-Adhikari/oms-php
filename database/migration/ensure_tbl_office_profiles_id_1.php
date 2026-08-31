<?php

$orgName = config('organization_name', 'SB-Tech');
$orgShort = config('organization_short_name', 'SB');
$query = [
    "INSERT INTO `tbl_office_profiles` (`id`, `name`, `accronym`, `use_date`, `leave_year_mode`, `added_on`)
    VALUES (1, '" . $orgName . "', '" . $orgShort . "', 'AD', 'AD', NOW())
    ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);",
];

$rollbackQuery = [
    "DELETE FROM `tbl_office_profiles` WHERE `id` = 1;",
];
