<?php
// One row per staff per run. Identity and bank details are snapshotted so a
// payslip shows what was paid even after the staff record changes.
$query = [
    "CREATE TABLE `tbl_payroll_run_lines` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `run_id` INT NOT NULL,
    `staff_id` INT NOT NULL,
    `fullname` VARCHAR(191) NOT NULL,
    `designation` VARCHAR(191) DEFAULT NULL,
    `department` VARCHAR(191) DEFAULT NULL,
    `employment_type` VARCHAR(20) DEFAULT NULL,
    `pan_num` VARCHAR(50) DEFAULT NULL,
    `bank` VARCHAR(191) DEFAULT NULL,
    `bank_account_num` VARCHAR(191) DEFAULT NULL,
    `bank_account_name` VARCHAR(191) DEFAULT NULL,
    `pay_type` VARCHAR(10) NOT NULL DEFAULT 'Salary',
    `basic_full` DECIMAL(18,4) NOT NULL DEFAULT 0,
    `tax_applicable` TINYINT(1) NOT NULL DEFAULT 1,
    `period_days` INT NOT NULL,
    `outside_days` INT NOT NULL DEFAULT 0,
    `unpaid_days` DECIMAL(6,2) NOT NULL DEFAULT 0,
    `unpaid_override` DECIMAL(6,2) DEFAULT NULL,
    `payable_days` DECIMAL(6,2) NOT NULL DEFAULT 0,
    `missing_attendance_days` INT NOT NULL DEFAULT 0,
    `gross` DECIMAL(18,4) NOT NULL DEFAULT 0,
    `taxable_earnings` DECIMAL(18,4) NOT NULL DEFAULT 0,
    `assessable` DECIMAL(18,4) NOT NULL DEFAULT 0,
    `employee_funds` DECIMAL(18,4) NOT NULL DEFAULT 0,
    `employer_funds` DECIMAL(18,4) NOT NULL DEFAULT 0,
    `deductible_funds` DECIMAL(18,4) NOT NULL DEFAULT 0,
    `other_deductions` DECIMAL(18,4) NOT NULL DEFAULT 0,
    `annual_tax` DECIMAL(18,4) NOT NULL DEFAULT 0,
    `tds_computed` DECIMAL(18,4) NOT NULL DEFAULT 0,
    `tds_override` DECIMAL(18,4) DEFAULT NULL,
    `tds` DECIMAL(18,4) NOT NULL DEFAULT 0,
    `net` DECIMAL(18,4) NOT NULL DEFAULT 0,
    `employer_cost` DECIMAL(18,4) NOT NULL DEFAULT 0,
    `warnings` TEXT,
    `excluded` TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_payroll_line_staff` (`run_id`, `staff_id`),
    KEY `idx_payroll_line_staff` (`staff_id`),
    CONSTRAINT `fk_payroll_line_run`
        FOREIGN KEY (`run_id`) REFERENCES `tbl_payroll_runs` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_payroll_line_staff`
        FOREIGN KEY (`staff_id`) REFERENCES `tbl_users_login` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];
$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_payroll_run_lines`;',
];
