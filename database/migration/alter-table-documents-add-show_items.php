<?php

/**
 * Migration: Add `show_items` flag to tbl_documents.
 *
 * Present in database/schema.sql but previously never migrated, so any
 * environment built purely from migrations lacked the column and every
 * DocumentEngine::save() failed with "Unknown column 'show_items'".
 */
$query = [
    "ALTER TABLE `tbl_documents`
        ADD COLUMN IF NOT EXISTS `show_items` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Render line items on the printed document' AFTER `reference_id`",
];

$rollbackQuery = [
    "ALTER TABLE `tbl_documents` DROP COLUMN IF EXISTS `show_items`",
];
