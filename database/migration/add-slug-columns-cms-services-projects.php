<?php

/**
 * SB-Tech — Add slug columns to CMS services and projects tables.
 *
 * Enables SEO-friendly URLs like /services/web-development instead of
 * /services/19. Slugs are URL-safe, lowercase, hyphen-separated strings
 * derived from the title.
 *
 * Pattern: alter-table-{table}-{description}.php
 * See: docs/Schema.md §6 "Reading This Document"
 */

$query = [
    // --- Services ---
    "ALTER TABLE `tbl_cms_services`
     ADD COLUMN `slug` VARCHAR(255) NULL AFTER `title`;",
    "ALTER TABLE `tbl_cms_services`
     ADD UNIQUE KEY `idx_services_slug` (`slug`);",

    // --- Projects ---
    "ALTER TABLE `tbl_cms_projects`
     ADD COLUMN `slug` VARCHAR(255) NULL AFTER `title`;",
    "ALTER TABLE `tbl_cms_projects`
     ADD UNIQUE KEY `idx_projects_slug` (`slug`);",
];

$rollbackQuery = [
    "ALTER TABLE `tbl_cms_services` DROP KEY `idx_services_slug`;",
    "ALTER TABLE `tbl_cms_services` DROP COLUMN `slug`;",

    "ALTER TABLE `tbl_cms_projects` DROP KEY `idx_projects_slug`;",
    "ALTER TABLE `tbl_cms_projects` DROP COLUMN `slug`;",
];
