<?php

$query = [
    "CREATE TABLE `tbl_communication_campaigns` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(191) NOT NULL,
    `type` ENUM('Email','SMS') NOT NULL,
    `template_id` INT DEFAULT NULL,
    `recipients` TEXT,
    `scheduled_at` DATETIME DEFAULT NULL,
    `sent_at` DATETIME DEFAULT NULL,
    `status` ENUM('Draft','Scheduled','Sending','Sent','Failed') NOT NULL DEFAULT 'Draft',
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_commcampaign_status` (`status`),
    CONSTRAINT `fk_commcampaign_template`
        FOREIGN KEY (`template_id`) REFERENCES `tbl_communication_templates` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_communication_campaigns`;',
];
