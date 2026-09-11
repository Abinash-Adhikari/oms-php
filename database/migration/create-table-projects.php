<?php

/**
 * SB-Tech — Project catalog (independent module).
 *
 * A catalog record is a product/service the business sells (e.g. "Smart School
 * Pro", "Accounting Suite"). It is a first-class, standalone entity with full
 * CRUD and is pursued through the Leads pipeline (tbl_leads.project_id →
 * tbl_projects). Distinct from tbl_client_projects, which records the *won*
 * provisioning for a specific business source.
 */

$query = [
    "CREATE TABLE `tbl_projects` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(191) NOT NULL,
    `code` VARCHAR(50) DEFAULT NULL,
    `category` VARCHAR(191) DEFAULT NULL,
    `description` TEXT,
    `status` ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_project_name` (`name`),
    KEY `idx_project_category` (`category`),
    KEY `idx_project_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_projects`;',
];
