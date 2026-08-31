<?php

$query = [
    "CREATE TABLE `tbl_office_task_assignees` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `task_id` INT NOT NULL,
    `staff_id` INT NOT NULL,
    `status` ENUM('Pending','In Progress','Done','Rejected') DEFAULT 'Pending',
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_task_assignee` (`task_id`, `staff_id`),
    KEY `idx_taskassign_staff` (`staff_id`),
    CONSTRAINT `fk_taskassign_task`
        FOREIGN KEY (`task_id`) REFERENCES `tbl_office_tasks` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_taskassign_staff`
        FOREIGN KEY (`staff_id`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];

$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_office_task_assignees`;',
];
