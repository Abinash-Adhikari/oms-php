<?php
// One non-cancelled run per BS month, enforced by the database: a generated
// column that is NULL for cancelled runs (NULLs don't collide in a UNIQUE
// index), so a double-click on "Start payroll" can't create two runs.
$query = [
    "ALTER TABLE `tbl_payroll_runs`
        ADD COLUMN `active_period_key` VARCHAR(7) AS (IF(`status` = 'Cancelled', NULL, `period_key`)) PERSISTENT AFTER `period_key`,
        ADD UNIQUE KEY `uniq_payroll_run_active_period` (`active_period_key`);",
];
$rollbackQuery = [
    'ALTER TABLE `tbl_payroll_runs` DROP KEY `uniq_payroll_run_active_period`, DROP COLUMN `active_period_key`;',
];
