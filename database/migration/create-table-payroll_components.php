<?php
// Earnings and deductions catalogue used by salary structures.
$query = [
    "CREATE TABLE `tbl_payroll_components` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(30) NOT NULL,
    `name` VARCHAR(191) NOT NULL,
    `kind` ENUM('earning','deduction') NOT NULL DEFAULT 'earning',
    `calc` ENUM('fixed','percent_of_basic') NOT NULL DEFAULT 'fixed',
    `taxable` TINYINT(1) NOT NULL DEFAULT 1,
    `prorate` TINYINT(1) NOT NULL DEFAULT 1,
    `position` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_payroll_component_code` (`code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    "INSERT INTO `tbl_payroll_components` (`code`, `name`, `kind`, `calc`, `taxable`, `prorate`, `position`) VALUES
    ('DA', 'Dearness Allowance', 'earning', 'fixed', 1, 1, 1),
    ('TRANSPORT', 'Transport Allowance', 'earning', 'fixed', 1, 1, 2),
    ('COMMUNICATION', 'Communication Allowance', 'earning', 'fixed', 1, 1, 3),
    ('MEDICAL', 'Medical Allowance', 'earning', 'fixed', 1, 1, 4),
    ('FESTIVAL', 'Festival Allowance (Dashain)', 'earning', 'fixed', 1, 0, 5),
    ('ADVANCE', 'Advance Recovery', 'deduction', 'fixed', 0, 0, 10);",
];
$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_payroll_components`;',
];
