<?php

/**
 * SB-Tech — Add is_active indexes to CMS tables for faster public site queries.
 *
 * Most siteRows() calls filter WHERE is_active = 1. Without an index,
 * MySQL does a full table scan on every page load.
 */
$query = [
    "ALTER TABLE `tbl_cms_hero`
     ADD KEY `idx_cms_hero_active` (`is_active`);",
    "ALTER TABLE `tbl_cms_services`
     ADD KEY `idx_cms_services_active` (`is_active`);",
    "ALTER TABLE `tbl_cms_abouts`
     ADD KEY `idx_cms_abouts_active` (`is_active`);",
    "ALTER TABLE `tbl_cms_testimonials`
     ADD KEY `idx_cms_testimonials_active` (`is_active`);",
    "ALTER TABLE `tbl_cms_news`
     ADD KEY `idx_cms_news_active` (`is_active`);",
    "ALTER TABLE `tbl_cms_notices`
     ADD KEY `idx_cms_notices_active` (`is_active`);",
];

$rollbackQuery = [
    "ALTER TABLE `tbl_cms_hero`     DROP KEY `idx_cms_hero_active`;",
    "ALTER TABLE `tbl_cms_services` DROP KEY `idx_cms_services_active`;",
    "ALTER TABLE `tbl_cms_abouts`   DROP KEY `idx_cms_abouts_active`;",
    "ALTER TABLE `tbl_cms_testimonials` DROP KEY `idx_cms_testimonials_active`;",
    "ALTER TABLE `tbl_cms_news`     DROP KEY `idx_cms_news_active`;",
    "ALTER TABLE `tbl_cms_notices`  DROP KEY `idx_cms_notices_active`;",
];
