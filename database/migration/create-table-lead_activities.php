<?php

$query = [
    "CREATE TABLE `tbl_lead_activities` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `lead_id` INT NOT NULL,
    `type` ENUM('Call','Email','Note','Meeting','Status Change','Task') NOT NULL DEFAULT 'Note',
    `note` TEXT,
    `added_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_leadact_lead` (`lead_id`),
    KEY `idx_leadact_added` (`added_on`),
    CONSTRAINT `fk_leadact_lead`
        FOREIGN KEY (`lead_id`) REFERENCES `tbl_leads` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_leadact_actor`
        FOREIGN KEY (`added_by`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_lead_activities`;',
];
