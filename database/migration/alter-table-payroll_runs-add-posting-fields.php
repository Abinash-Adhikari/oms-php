<?php
// Who posted / recorded payment for a run, and when (PAYROLL_PLAN P3).
$query = [
    'ALTER TABLE `tbl_payroll_runs`
        ADD COLUMN `posted_by` INT DEFAULT NULL AFTER `payment_voucher_id`,
        ADD COLUMN `posted_on` DATETIME DEFAULT NULL AFTER `posted_by`,
        ADD COLUMN `paid_by` INT DEFAULT NULL AFTER `posted_on`,
        ADD COLUMN `paid_on` DATETIME DEFAULT NULL AFTER `paid_by`;',
];
$rollbackQuery = [
    'ALTER TABLE `tbl_payroll_runs` DROP COLUMN `posted_by`, DROP COLUMN `posted_on`, DROP COLUMN `paid_by`, DROP COLUMN `paid_on`;',
];
