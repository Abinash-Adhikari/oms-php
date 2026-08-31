<?php

$query = [
    "CREATE TABLE `tbl_user_profiles` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `blood_group` VARCHAR(100) DEFAULT NULL,
    `work_experience` VARCHAR(255) DEFAULT NULL,
    `skill` TEXT,
    `hobby_interest` TEXT,
    `award_reward` TEXT,
    `emergency_contact_name` VARCHAR(191) DEFAULT NULL,
    `emergency_contact_mobile` VARCHAR(50) DEFAULT NULL,
    `emergency_contact_relation` VARCHAR(100) DEFAULT NULL,
    `special_training_skill` TEXT,
    `height` DECIMAL(14,4) DEFAULT NULL,
    `weight` DECIMAL(14,4) DEFAULT NULL,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_profile_user` (`user_id`),
    CONSTRAINT `fk_userprofile_user`
        FOREIGN KEY (`user_id`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_user_profiles`;',
];
