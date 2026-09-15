<?php

/**
 * Office spaces were created with a `status` ENUM but the form, handler and
 * view all read/write `is_active` (TINYINT 1/0) — adding `is_active` (backfilled
 * from `status`) and dropping the never-used `status` column so saves work.
 */

$query = [
    "ALTER TABLE `tbl_office_spaces`
     ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `description`;",
    "UPDATE `tbl_office_spaces`
     SET `is_active` = IF(`status` = 'Inactive', 0, 1);",
    "ALTER TABLE `tbl_office_spaces` DROP COLUMN `status`;",
];

$rollbackQuery = [
    "ALTER TABLE `tbl_office_spaces` DROP COLUMN `is_active`;",
    "UPDATE `tbl_office_spaces`
     SET `status` = IF(`is_active` = 0, 'Inactive', 'Active');",
    "ALTER TABLE `tbl_office_spaces`
     ADD COLUMN `status` ENUM('Active','Inactive') DEFAULT 'Active' AFTER `description`;",
];