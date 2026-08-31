<?php

/**
 * SB-Tech — Add letterhead layout style to document settings.
 *
 * Lets the admin choose how the logo and header-text details are arranged
 * on every generated PDF/Word document header. Stored on the singleton
 * tbl_document_settings row (id = 1).
 */

$styles = [
    'logo_left_details_right',
    'logo_left_details_left',
    'details_right_logo_right',
    'details_left_logo_right',
    'centered',
    'logo_left_details_center',
    'details_center_logo_right',
    'logo_top_details_bottom',
];

$query = [
    "ALTER TABLE `tbl_document_settings`
     ADD COLUMN `letterhead_style` ENUM("
    . implode(', ', array_map(function ($s) { return "'$s'"; }, $styles))
    . ") NOT NULL DEFAULT 'logo_left_details_right'
     AFTER `header_mode`;",
    // Backfill existing row with the default (idempotent).
    "UPDATE `tbl_document_settings` SET `letterhead_style` = 'logo_left_details_right'
     WHERE `letterhead_style` IS NULL OR `letterhead_style` = '';",
];

$rollbackQuery = [
    "ALTER TABLE `tbl_document_settings` DROP COLUMN `letterhead_style`;",
];
