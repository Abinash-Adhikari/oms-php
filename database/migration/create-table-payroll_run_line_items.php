<?php
// Itemised payslip lines. is_adjustment = 1 rows are one-off entries added by
// the preparer (bonus, arrears, recovery) and survive recalculation.
$query = [
    "CREATE TABLE `tbl_payroll_run_line_items` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `line_id` INT NOT NULL,
    `kind` ENUM('earning','deduction','fund_employee','fund_employer','tax') NOT NULL,
    `code` VARCHAR(30) NOT NULL,
    `name` VARCHAR(191) NOT NULL,
    `amount` DECIMAL(18,4) NOT NULL DEFAULT 0,
    `taxable` TINYINT(1) NOT NULL DEFAULT 0,
    `is_adjustment` TINYINT(1) NOT NULL DEFAULT 0,
    `position` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_payroll_item_line` (`line_id`, `kind`),
    CONSTRAINT `fk_payroll_item_line`
        FOREIGN KEY (`line_id`) REFERENCES `tbl_payroll_run_lines` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
];
$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_payroll_run_line_items`;',
];
