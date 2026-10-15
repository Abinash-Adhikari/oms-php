<?php
// Link an advance-recovery deduction on a payslip to the advance it repays.
$query = [
    'ALTER TABLE `tbl_payroll_run_line_items`
        ADD COLUMN `advance_id` INT DEFAULT NULL AFTER `is_adjustment`,
        ADD KEY `idx_payroll_item_advance` (`advance_id`),
        ADD CONSTRAINT `fk_payroll_item_advance` FOREIGN KEY (`advance_id`) REFERENCES `tbl_staff_advances` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;',
];
$rollbackQuery = [
    'ALTER TABLE `tbl_payroll_run_line_items` DROP FOREIGN KEY `fk_payroll_item_advance`, DROP KEY `idx_payroll_item_advance`, DROP COLUMN `advance_id`;',
];
