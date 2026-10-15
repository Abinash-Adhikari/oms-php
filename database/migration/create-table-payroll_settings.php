<?php
// Single-row payroll accounting map (id = 1): which chart-of-account
// terminals a payroll run posts to. Edited in Payroll → Accounting Setup.
$query = [
    "CREATE TABLE `tbl_payroll_settings` (
    `id` INT NOT NULL,
    `salary_expense_terminal_id` INT DEFAULT NULL,
    `employer_contribution_terminal_id` INT DEFAULT NULL,
    `staff_payable_terminal_id` INT DEFAULT NULL,
    `tds_payable_terminal_id` INT DEFAULT NULL,
    `other_deductions_terminal_id` INT DEFAULT NULL,
    `payment_terminal_id` INT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `updated_on` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_payroll_set_salary` FOREIGN KEY (`salary_expense_terminal_id`) REFERENCES `tbl_account_terminals` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_payroll_set_employer` FOREIGN KEY (`employer_contribution_terminal_id`) REFERENCES `tbl_account_terminals` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_payroll_set_staff_payable` FOREIGN KEY (`staff_payable_terminal_id`) REFERENCES `tbl_account_terminals` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_payroll_set_tds` FOREIGN KEY (`tds_payable_terminal_id`) REFERENCES `tbl_account_terminals` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_payroll_set_other` FOREIGN KEY (`other_deductions_terminal_id`) REFERENCES `tbl_account_terminals` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_payroll_set_payment` FOREIGN KEY (`payment_terminal_id`) REFERENCES `tbl_account_terminals` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    'INSERT INTO `tbl_payroll_settings` (`id`) VALUES (1);',
];
$rollbackQuery = [
    'DROP TABLE IF EXISTS `tbl_payroll_settings`;',
];
