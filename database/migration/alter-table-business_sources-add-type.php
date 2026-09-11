<?php

/**
 * SB-Tech — Business source classification.
 *
 * Every business source is either an Individual or a Company. Legacy rows
 * (created from won leads) default to 'Company' because they carried a company
 * name; anything else can be re-classified from the Business Sources screen.
 */

$query = [
    "ALTER TABLE `tbl_business_sources`
     ADD COLUMN `type` ENUM('Individual','Company') NOT NULL DEFAULT 'Company'
     COMMENT 'Classification of the business source'
     AFTER `id`;",
];

$rollbackQuery = [
    "ALTER TABLE `tbl_business_sources` DROP COLUMN `type`;",
];
