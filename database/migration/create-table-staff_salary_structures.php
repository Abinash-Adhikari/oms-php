<?php
// A staff member's pay from `effective_from` onward; earlier rows are history.
// pay_type Stipend = fixed amount (interns); funds still follow enrollments.
$query = [
    "CREATE TABLE `tbl_staff_salary_structures` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `staff_id` INT NOT NULL,
    `effective_from` DATE NOT NULL,
    `pay_type` ENUM('Salary','Stipend') NOT NULL DEFAULT 'Salary',
    `basic` DECIMAL(18,4) NOT NULL DEFAULT 0,
    `tax_applicable` TINYINT(1) NOT NULL DEFAULT 1,
    `deduct_unpaid_days` TINYINT(1) NOT NULL DEFAULT 1,
    `notes` VARCHAR(500) DEFAULT NULL,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_salary_structure_staff_date` (`staff_id`, `effective_from`),
    CONSTRAINT `fk_salary_structure_staff`
        FOREIGN KEY (`staff_id`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];
$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_staff_salary_structures`;',
];
