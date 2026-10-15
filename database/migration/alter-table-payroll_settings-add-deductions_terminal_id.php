<?php
// Advance recoveries keep crediting Staff Advances (other_deductions_terminal_id);
// every other payroll deduction (canteen, fines, …) credits this account.
$query = [
    'ALTER TABLE `tbl_payroll_settings`
        ADD COLUMN `deductions_terminal_id` INT DEFAULT NULL AFTER `other_deductions_terminal_id`,
        ADD CONSTRAINT `fk_payroll_set_deductions` FOREIGN KEY (`deductions_terminal_id`) REFERENCES `tbl_account_terminals` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;',
    "UPDATE `tbl_payroll_settings` SET `deductions_terminal_id` =
        (SELECT `id` FROM `tbl_account_terminals` WHERE `title` = 'Miscellaneous Income' ORDER BY `id` LIMIT 1)
     WHERE `id` = 1 AND `deductions_terminal_id` IS NULL;",
];
$rollbackQuery = [
    'ALTER TABLE `tbl_payroll_settings` DROP FOREIGN KEY `fk_payroll_set_deductions`, DROP COLUMN `deductions_terminal_id`;',
];
