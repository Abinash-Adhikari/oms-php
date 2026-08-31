<?php

$query = [
    "CREATE TABLE `tbl_account_terminals` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `account_subgroup_id` INT NOT NULL,
    `title` VARCHAR(191) NOT NULL,
    `alias` VARCHAR(191) DEFAULT NULL,
    `position` INT NOT NULL DEFAULT 0,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_account_terminal` (`account_subgroup_id`, `title`),
    CONSTRAINT `fk_terminal_subgroup`
        FOREIGN KEY (`account_subgroup_id`) REFERENCES `tbl_account_sub_groups` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_account_terminals`;',
];
