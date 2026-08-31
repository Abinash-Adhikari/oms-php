<?php

$query = [
    "CREATE TABLE `tbl_staff_history` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `staff_id` INT NOT NULL,
    `event_type` VARCHAR(50) NOT NULL,
    `details` TEXT,
    `event_date` DATE DEFAULT NULL,
    `actor_id` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_staffhist_staff` (`staff_id`),
    KEY `idx_staffhist_actor` (`actor_id`),
    CONSTRAINT `fk_staffhist_staff`
        FOREIGN KEY (`staff_id`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_staffhist_actor`
        FOREIGN KEY (`actor_id`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_staff_history`;',
];
