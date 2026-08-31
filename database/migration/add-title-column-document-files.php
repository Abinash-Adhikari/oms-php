<?php
/**
 * Migration: Add title column to tbl_document_files.
 */
$query = [
    "ALTER TABLE `tbl_document_files`
        ADD COLUMN `title` VARCHAR(255) NULL DEFAULT NULL COMMENT 'User-provided title/description for the file' AFTER `file_name`",
];

$rollbackQuery = [
    "ALTER TABLE `tbl_document_files` DROP COLUMN `title`",
];
