<?php
// Payroll: leave of an unpaid type reduces payable days (PAYROLL_PLAN D4).
$query = [
    'ALTER TABLE `tbl_office_leave_configs` ADD COLUMN `is_paid` TINYINT(1) NOT NULL DEFAULT 1 AFTER `is_active`;',
];
$rollbackQuery = [
    'ALTER TABLE `tbl_office_leave_configs` DROP COLUMN `is_paid`;',
];
