<?php
/**
 * Payroll accounts, created next to the existing ones they belong with
 * (skipped when the anchor account or the title is missing / already there):
 *   SSF / PF / CIT Payable   → subgroup of "Staff Payable"   (current liabilities)
 *   Employer Fund Contributions → subgroup of "Staff Salaries" (expenses)
 *   Staff Advances           → subgroup of "Prepaid Expenses" (current assets)
 * Then pre-fills tbl_payroll_settings and each fund's payable account.
 */
$query = [
    "INSERT INTO `tbl_account_terminals` (`account_subgroup_id`, `title`, `position`)
     SELECT a.`account_subgroup_id`, x.title, 0
     FROM (SELECT 'SSF Payable' AS title, 'Staff Payable' AS anchor
           UNION ALL SELECT 'PF Payable', 'Staff Payable'
           UNION ALL SELECT 'CIT Payable', 'Staff Payable'
           UNION ALL SELECT 'Employer Fund Contributions', 'Staff Salaries'
           UNION ALL SELECT 'Staff Advances', 'Prepaid Expenses') x
     JOIN (SELECT `title`, MIN(`account_subgroup_id`) AS account_subgroup_id FROM `tbl_account_terminals` GROUP BY `title`) a ON a.`title` = x.anchor
     WHERE NOT EXISTS (SELECT 1 FROM (SELECT `title` FROM `tbl_account_terminals`) z WHERE z.`title` = x.title);",
    "UPDATE `tbl_payroll_settings` s SET
        s.`salary_expense_terminal_id`        = COALESCE(s.`salary_expense_terminal_id`, (SELECT `id` FROM `tbl_account_terminals` WHERE `title` = 'Staff Salaries' ORDER BY `id` LIMIT 1)),
        s.`employer_contribution_terminal_id` = COALESCE(s.`employer_contribution_terminal_id`, (SELECT `id` FROM `tbl_account_terminals` WHERE `title` = 'Employer Fund Contributions' ORDER BY `id` LIMIT 1)),
        s.`staff_payable_terminal_id`         = COALESCE(s.`staff_payable_terminal_id`, (SELECT `id` FROM `tbl_account_terminals` WHERE `title` = 'Staff Payable' ORDER BY `id` LIMIT 1)),
        s.`tds_payable_terminal_id`           = COALESCE(s.`tds_payable_terminal_id`, (SELECT `id` FROM `tbl_account_terminals` WHERE `title` = 'TDS Payable' ORDER BY `id` LIMIT 1)),
        s.`other_deductions_terminal_id`      = COALESCE(s.`other_deductions_terminal_id`, (SELECT `id` FROM `tbl_account_terminals` WHERE `title` = 'Staff Advances' ORDER BY `id` LIMIT 1)),
        s.`payment_terminal_id`               = COALESCE(s.`payment_terminal_id`, (SELECT `id` FROM `tbl_account_terminals` WHERE `title` = 'Bank Accounts' ORDER BY `id` LIMIT 1))
     WHERE s.`id` = 1;",
    "UPDATE `tbl_payroll_funds` f SET f.`payable_terminal_id` =
        (SELECT `id` FROM `tbl_account_terminals` WHERE `title` = CONCAT(f.`code`, ' Payable') ORDER BY `id` LIMIT 1)
     WHERE f.`payable_terminal_id` IS NULL;",
];
$rollbackQuery = [
    "UPDATE `tbl_payroll_funds` SET `payable_terminal_id` = NULL;",
    "DELETE t FROM `tbl_account_terminals` t
     WHERE t.`title` IN ('SSF Payable', 'PF Payable', 'CIT Payable', 'Employer Fund Contributions', 'Staff Advances')
       AND NOT EXISTS (SELECT 1 FROM `tbl_ledger_particulars` lp WHERE lp.`account_terminal_id` = t.`id`);",
];
