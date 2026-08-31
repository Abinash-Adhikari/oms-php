<?php

$query = [
    "CREATE TABLE `tbl_communication_logs` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `campaign_id` INT DEFAULT NULL,
    `type` ENUM('Email','SMS') NOT NULL,
    `recipient` VARCHAR(191) DEFAULT NULL,
    `subject` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('Queued','Sent','Failed') NOT NULL DEFAULT 'Queued',
    `error_message` TEXT,
    `sent_on` DATETIME DEFAULT NULL,
    `added_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_commlog_campaign` (`campaign_id`),
    KEY `idx_commlog_status` (`status`),
    CONSTRAINT `fk_commlog_campaign`
        FOREIGN KEY (`campaign_id`) REFERENCES `tbl_communication_campaigns` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_communication_logs`;',
];
