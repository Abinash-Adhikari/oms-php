<?php

/**
 * Migration: portfolio clients now link to website clients (tbl_cms_clients)
 * instead of Sales clients (tbl_clients).
 *
 * Every Sales client already linked to a portfolio item is copied into
 * tbl_cms_clients (by name) and the link is repointed to it; free-text names
 * (no client id) stay free text. Then the Sales FK/column is dropped.
 *
 * Rollback is lossy by design: it restores the `client_id` column/FK to
 * tbl_clients (empty — names are kept in client_name) and drops cms_client_id.
 * The copied tbl_cms_clients rows stay until that table's own rollback.
 */
$query = [
    "ALTER TABLE `tbl_cms_project_clients`
        ADD COLUMN IF NOT EXISTS `cms_client_id` INT DEFAULT NULL COMMENT 'tbl_cms_clients.id; NULL = free-text name' AFTER `project_id`,
        ADD KEY `idx_cpc_cms_client` (`cms_client_id`)",

    "INSERT INTO `tbl_cms_clients` (`name`, `position`, `is_active`)
     SELECT DISTINCT TRIM(c.`name`), 0, 1
     FROM `tbl_cms_project_clients` pc
     JOIN `tbl_clients` c ON c.`id` = pc.`client_id`
     WHERE NOT EXISTS (SELECT 1 FROM `tbl_cms_clients` cc WHERE cc.`name` = TRIM(c.`name`))",

    "UPDATE `tbl_cms_project_clients` pc
     JOIN `tbl_clients` c ON c.`id` = pc.`client_id`
     SET pc.`cms_client_id` = (SELECT MIN(cc.`id`) FROM `tbl_cms_clients` cc WHERE cc.`name` = TRIM(c.`name`))",

    'ALTER TABLE `tbl_cms_project_clients` DROP FOREIGN KEY `fk_cpc_client`',
    'ALTER TABLE `tbl_cms_project_clients` DROP KEY `idx_cpc_client`, DROP COLUMN `client_id`',

    "ALTER TABLE `tbl_cms_project_clients`
        ADD CONSTRAINT `fk_cpc_cms_client` FOREIGN KEY (`cms_client_id`) REFERENCES `tbl_cms_clients` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE",
];

// Runs in reverse (bottom first).
$rollbackQuery = [
    'ALTER TABLE `tbl_cms_project_clients` DROP KEY IF EXISTS `idx_cpc_cms_client`, DROP COLUMN IF EXISTS `cms_client_id`',
    "ALTER TABLE `tbl_cms_project_clients`
        ADD CONSTRAINT `fk_cpc_client` FOREIGN KEY (`client_id`) REFERENCES `tbl_clients` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE",
    "ALTER TABLE `tbl_cms_project_clients`
        ADD COLUMN IF NOT EXISTS `client_id` INT DEFAULT NULL AFTER `project_id`,
        ADD KEY `idx_cpc_client` (`client_id`)",
    'ALTER TABLE `tbl_cms_project_clients` DROP FOREIGN KEY IF EXISTS `fk_cpc_cms_client`',
];
