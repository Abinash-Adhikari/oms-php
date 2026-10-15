<?php
// Retirement / social-security funds (SSF, PF, CIT, …). Rates are percentages
// and editable; staff opt in per fund via tbl_staff_fund_enrollments.
$query = [
    "CREATE TABLE `tbl_payroll_funds` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(20) NOT NULL,
    `name` VARCHAR(191) NOT NULL,
    `employee_rate` DECIMAL(7,4) NOT NULL DEFAULT 0,
    `employer_rate` DECIMAL(7,4) NOT NULL DEFAULT 0,
    `contribution_base` ENUM('basic','gross') NOT NULL DEFAULT 'basic',
    `max_base` DECIMAL(18,4) DEFAULT NULL,
    `tax_deductible` TINYINT(1) NOT NULL DEFAULT 1,
    `waives_social_security_tax` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `notes` TEXT,
    `added_by` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_payroll_fund_code` (`code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    "INSERT INTO `tbl_payroll_funds` (`code`, `name`, `employee_rate`, `employer_rate`, `contribution_base`, `tax_deductible`, `waives_social_security_tax`, `notes`) VALUES
    ('SSF', 'Social Security Fund', 11, 20, 'basic', 1, 1, 'Contribution-based SSF: 11% employee + 20% employer on basic. Contributors are exempt from the 1% social security tax band. Verify rates with SSF before use.'),
    ('PF', 'Employees Provident Fund', 10, 10, 'basic', 1, 0, '10% + 10% of basic. Verify with your accountant.'),
    ('CIT', 'Citizen Investment Trust', 0, 0, 'basic', 1, 0, 'Voluntary; set the employee amount per staff enrollment.');",
];
$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_payroll_funds`;',
];
