<?php
// Employment category + contract end (interns, contracts, probation) and
// income-tax filing status used by payroll (docs/PAYROLL_PLAN.md §4.1).
$query = [
    "ALTER TABLE `tbl_users_login`
        ADD COLUMN `employment_type` ENUM('Permanent','Contract','Probation','Intern') NOT NULL DEFAULT 'Permanent' AFTER `staff_type`,
        ADD COLUMN `contract_end_date` DATE DEFAULT NULL AFTER `join_date`,
        ADD COLUMN `tax_filing_status` ENUM('Single','Couple') NOT NULL DEFAULT 'Single' AFTER `pan_num`;",
];
$rollbackQuery = [
    'ALTER TABLE `tbl_users_login` DROP COLUMN `employment_type`, DROP COLUMN `contract_end_date`, DROP COLUMN `tax_filing_status`;',
];
