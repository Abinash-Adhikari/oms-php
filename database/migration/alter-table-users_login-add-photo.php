<?php

/**
 * SB-Tech — staff profile photo.
 *
 * `photo` holds the path relative to user_uploads/ (e.g. "staff/2026…_ab12.jpg"),
 * written by store_image_upload() from the Employees form's image editor
 * field. NULL = no photo (UI falls back to initials). The legacy
 * `image_extension` column is unused and left as-is.
 */

$query = [
    "ALTER TABLE `tbl_users_login`
     ADD COLUMN `photo` VARCHAR(255) DEFAULT NULL AFTER `image_extension`;",
];

$rollbackQuery = [
    "ALTER TABLE `tbl_users_login` DROP COLUMN `photo`;",
];
