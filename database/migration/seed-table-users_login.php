<?php

$query = [
    "INSERT INTO `tbl_users_login`
        (`id`, `username`, `email`, `password`, `salt`, `fullname`, `permitted_modules`, `permitted_submodules`, `special_permission`, `role`, `staff_type`, `status`)
    VALUES
        (1, 'admin', 'admin@sbtech.local',
         'a511f0c8b058dc265269c982c2f2541e40248e2288e1c9e7a0fd255ab11a38d799a89e5f06e50d6636e570d75e8df95af38ff91d39209becc3e776388642f405',
         'sbtech-admin-salt-2026x',
         'Super Admin', 'All', 'All', 'All', 'Super Admin', 'Admin', 'Active')
    ON DUPLICATE KEY UPDATE `fullname` = VALUES(`fullname`);",
];

$rollbackQuery = [
    "DELETE FROM `tbl_users_login` WHERE `id` = 1;",
];
