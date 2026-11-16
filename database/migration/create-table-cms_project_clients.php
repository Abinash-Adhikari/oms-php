<?php

/**
 * Migration: tbl_cms_project_clients — the clients of a portfolio item.
 *
 * A portfolio item (tbl_cms_projects) can have zero, one or many clients.
 * Each row is either a Sales client (client_id set; its current name is shown)
 * or a free-text name (client_id NULL). client_name always holds a snapshot so
 * the name survives if the Sales client is later deleted (FK SET NULL).
 *
 * Backfill: every existing tbl_cms_projects.client_name becomes one row,
 * linked to the Sales client whose name matches (ignoring a trailing '.').
 */
$query = [
    "CREATE TABLE IF NOT EXISTS `tbl_cms_project_clients` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `project_id` INT NOT NULL,
    `client_id` INT DEFAULT NULL COMMENT 'tbl_clients.id; NULL = free-text name',
    `client_name` VARCHAR(191) NOT NULL COMMENT 'name snapshot / free-text name',
    `position` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_cpc_project` (`project_id`, `position`),
    KEY `idx_cpc_client` (`client_id`),
    CONSTRAINT `fk_cpc_project` FOREIGN KEY (`project_id`) REFERENCES `tbl_cms_projects` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_cpc_client` FOREIGN KEY (`client_id`) REFERENCES `tbl_clients` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "INSERT INTO `tbl_cms_project_clients` (`project_id`, `client_id`, `client_name`, `position`)
     SELECT p.`id`,
            (SELECT MIN(c.`id`) FROM `tbl_clients` c
              WHERE TRIM(TRAILING '.' FROM TRIM(c.`name`)) = TRIM(TRAILING '.' FROM TRIM(p.`client_name`))),
            TRIM(p.`client_name`),
            0
     FROM `tbl_cms_projects` p
     WHERE TRIM(COALESCE(p.`client_name`, '')) <> ''",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_cms_project_clients`;',
];
