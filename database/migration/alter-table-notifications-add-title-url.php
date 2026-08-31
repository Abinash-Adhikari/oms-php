<?php

/**
 * SB-Tech — Add title and url columns to tbl_notifications for
 * the SSE-powered real-time notification dropdown.
 */
$query = [
    "ALTER TABLE `tbl_notifications`
     ADD COLUMN `title` VARCHAR(150) NOT NULL DEFAULT '' AFTER `type`,
     ADD COLUMN `url` VARCHAR(255) NULL AFTER `title`;",
];

$rollbackQuery = [
    "ALTER TABLE `tbl_notifications`
     DROP COLUMN `title`,
     DROP COLUMN `url`;",
];
