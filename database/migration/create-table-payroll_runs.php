<?php
// One payroll run per BS month (docs/PAYROLL_PLAN.md §6). Only one
// non-cancelled run may exist per period_key — enforced in PayrollService.
$query = [
    "CREATE TABLE `tbl_payroll_runs` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `tax_year_id` INT DEFAULT NULL,
    `period_key` VARCHAR(7) NOT NULL,
    `period_label` VARCHAR(50) NOT NULL,
    `period_start` DATE NOT NULL,
    `period_end` DATE NOT NULL,
    `period_days` INT NOT NULL,
    `months_remaining` INT NOT NULL DEFAULT 1,
    `status` ENUM('Draft','Approved','Posted','Paid','Cancelled') NOT NULL DEFAULT 'Draft',
    `headcount` INT NOT NULL DEFAULT 0,
    `total_gross` DECIMAL(18,4) NOT NULL DEFAULT 0,
    `total_employee_funds` DECIMAL(18,4) NOT NULL DEFAULT 0,
    `total_employer_funds` DECIMAL(18,4) NOT NULL DEFAULT 0,
    `total_tds` DECIMAL(18,4) NOT NULL DEFAULT 0,
    `total_deductions` DECIMAL(18,4) NOT NULL DEFAULT 0,
    `total_net` DECIMAL(18,4) NOT NULL DEFAULT 0,
    `notes` VARCHAR(500) DEFAULT NULL,
    `prepared_by` INT DEFAULT NULL,
    `calculated_on` DATETIME DEFAULT NULL,
    `approved_by` INT DEFAULT NULL,
    `approved_on` DATETIME DEFAULT NULL,
    `cancelled_by` INT DEFAULT NULL,
    `cancelled_on` DATETIME DEFAULT NULL,
    `cancel_reason` VARCHAR(255) DEFAULT NULL,
    `journal_voucher_id` INT DEFAULT NULL,
    `payment_voucher_id` INT DEFAULT NULL,
    `added_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_payroll_run_period` (`period_key`, `status`),
    KEY `idx_payroll_run_tax_year` (`tax_year_id`),
    CONSTRAINT `fk_payroll_run_tax_year`
        FOREIGN KEY (`tax_year_id`) REFERENCES `tbl_payroll_tax_years` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];
$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_payroll_runs`;',
];
