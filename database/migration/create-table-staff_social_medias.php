<?php

$query = [
    "CREATE TABLE `tbl_staff_social_medias` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `staff_id` INT DEFAULT NULL,
    `title` VARCHAR(191) DEFAULT NULL,
    `media_link` VARCHAR(255) DEFAULT NULL,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_staffsocial_staff` (`staff_id`),
    CONSTRAINT `fk_staffsocial_staff`
        FOREIGN KEY (`staff_id`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_staff_social_medias`;',
];
