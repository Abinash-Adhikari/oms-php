<?php
// Each fund's liability account (SSF Payable, …) for payroll posting.
$query = [
    'ALTER TABLE `tbl_payroll_funds`
        ADD COLUMN `payable_terminal_id` INT DEFAULT NULL AFTER `waives_social_security_tax`,
        ADD CONSTRAINT `fk_payroll_fund_payable` FOREIGN KEY (`payable_terminal_id`) REFERENCES `tbl_account_terminals` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;',
];
$rollbackQuery = [
    'ALTER TABLE `tbl_payroll_funds` DROP FOREIGN KEY `fk_payroll_fund_payable`, DROP COLUMN `payable_terminal_id`;',
];
