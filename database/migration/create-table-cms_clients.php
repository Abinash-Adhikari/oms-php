<?php

/**
 * Migration: tbl_cms_clients — website showcase clients.
 *
 * Public-facing client profiles (name, company, logo, description, website)
 * managed from Website CMS → Clients, shown in the home-page logo carousel and
 * on the public /clients page, and linked to portfolio items through
 * tbl_cms_project_clients. Separate from Sales → Clients (tbl_clients) on
 * purpose: office/prospect data never leaks to the public site.
 */
$query = [
    "CREATE TABLE IF NOT EXISTS `tbl_cms_clients` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(191) NOT NULL,
    `company_name` VARCHAR(191) DEFAULT NULL,
    `logo_name` VARCHAR(255) DEFAULT NULL,
    `logo_location` VARCHAR(255) DEFAULT NULL,
    `description` TEXT,
    `website_url` VARCHAR(255) DEFAULT NULL,
    `position` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cms_clients_active` (`is_active`, `position`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_cms_clients`;',
];
