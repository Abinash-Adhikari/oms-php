<?php

/**
 * Migration: Add `logo` and `favicon` to tbl_cms_setup.
 *
 * The public header/footer (website/includes/header.php, footer.php) already
 * render $setup['logo'] / $setup['favicon'], but the columns never existed,
 * so no logo could be set. Both hold a path relative to user_uploads/.
 */
$query = [
    "ALTER TABLE `tbl_cms_setup`
        ADD COLUMN IF NOT EXISTS `logo` VARCHAR(255) DEFAULT NULL COMMENT 'path under user_uploads/' AFTER `tagline`,
        ADD COLUMN IF NOT EXISTS `favicon` VARCHAR(255) DEFAULT NULL COMMENT 'path under user_uploads/' AFTER `logo`",
];

$rollbackQuery = [
    "ALTER TABLE `tbl_cms_setup` DROP COLUMN IF EXISTS `favicon`, DROP COLUMN IF EXISTS `logo`",
];
